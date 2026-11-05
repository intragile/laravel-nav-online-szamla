<?php

/**
 * Generate the typed invoice data model from the bundled NAV XSDs.
 *
 *   php bin/generate-model.php [--check]
 *
 * Walks every complex and simple type reachable from the InvoiceData root element of
 * invoiceData.xsd (including the types it uses from invoiceBase.xsd and common.xsd) and
 * writes one PHP class per complex type and one backed enum per enumerated simple type to
 * src/Invoice/Model. Re-run it after bin/sync-xsd.php whenever NAV changes the schema.
 * With --check it writes nothing and exits non-zero when the generated code is stale.
 */

declare(strict_types=1);

const XS = 'http://www.w3.org/2001/XMLSchema';
const NS_CONSTANTS = [
    'http://schemas.nav.gov.hu/OSA/3.0/data' => 'Ns::DATA',
    'http://schemas.nav.gov.hu/OSA/3.0/base' => 'Ns::BASE',
    'http://schemas.nav.gov.hu/NTCA/1.0/common' => 'Ns::COMMON',
];
const MODEL_NAMESPACE = 'IntrAgile\\NavOnlineSzamla\\Invoice\\Model';

/**
 * Enumerated simple types that map to enums already used by the API layer. The generator
 * fails if their cases drift from the schema.
 */
const REUSED_ENUMS = [
    '{http://schemas.nav.gov.hu/OSA/3.0/base}PaymentMethodType' => 'IntrAgile\\NavOnlineSzamla\\Enums\\PaymentMethod',
    '{http://schemas.nav.gov.hu/OSA/3.0/base}InvoiceCategoryType' => 'IntrAgile\\NavOnlineSzamla\\Enums\\InvoiceCategory',
    '{http://schemas.nav.gov.hu/OSA/3.0/base}InvoiceAppearanceType' => 'IntrAgile\\NavOnlineSzamla\\Enums\\InvoiceAppearance',
];

const PRIMITIVES = [
    'string' => 'string', 'normalizedString' => 'string', 'token' => 'string',
    'decimal' => 'decimal',
    'int' => 'int', 'integer' => 'int', 'long' => 'int', 'short' => 'int',
    'nonNegativeInteger' => 'int', 'positiveInteger' => 'int',
    'boolean' => 'bool',
    'date' => 'date',
    'dateTime' => 'dateTime',
];

final class Schemas
{
    /** @var array<string, DOMElement> */
    public array $complexTypes = [];

    /** @var array<string, DOMElement> */
    public array $simpleTypes = [];

    /** @var array<string, DOMElement> */
    public array $rootElements = [];

    public function __construct(string $dir, array $files)
    {
        foreach ($files as $file) {
            $document = new DOMDocument;
            if (! $document->load($dir.'/'.$file, LIBXML_NONET)) {
                throw new RuntimeException("Cannot load $file");
            }
            $schema = $document->documentElement;
            $tns = $schema->getAttribute('targetNamespace');
            foreach (children($schema) as $node) {
                $key = '{'.$tns.'}'.$node->getAttribute('name');
                match ($node->localName) {
                    'complexType' => $this->complexTypes[$key] = $node,
                    'simpleType' => $this->simpleTypes[$key] = $node,
                    'element' => $this->rootElements[$key] = $node,
                    default => null,
                };
            }
        }
    }
}

/**
 * @return list<DOMElement>
 */
function children(DOMElement $node, ?string $localName = null): array
{
    $out = [];
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement && $child->namespaceURI === XS && $child->localName !== 'annotation'
            && ($localName === null || $child->localName === $localName)) {
            $out[] = $child;
        }
    }

    return $out;
}

function qname(DOMElement $context, string $value): string
{
    [$prefix, $local] = str_contains($value, ':') ? explode(':', $value, 2) : [null, $value];
    $uri = $context->lookupNamespaceURI($prefix);
    if ($uri === null) {
        throw new RuntimeException("Unresolvable QName $value");
    }

    return '{'.$uri.'}'.$local;
}

function targetNamespace(DOMElement $node): string
{
    return $node->ownerDocument->documentElement->getAttribute('targetNamespace');
}

function localName(string $key): string
{
    return substr($key, strpos($key, '}') + 1);
}

function englishDoc(DOMElement $node): ?string
{
    foreach ($node->childNodes as $annotation) {
        if ($annotation instanceof DOMElement && $annotation->localName === 'annotation') {
            foreach ($annotation->childNodes as $doc) {
                if ($doc instanceof DOMElement && $doc->localName === 'documentation'
                    && $doc->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang') === 'en') {
                    $text = trim((string) preg_replace('/\s+/u', ' ', $doc->textContent));

                    return $text === '' ? null : rtrim($text, '.').'.';
                }
            }
        }
    }

    return null;
}

function className(string $key): string
{
    $name = localName($key);

    return ucfirst(str_ends_with($name, 'Type') ? substr($name, 0, -4) : $name);
}

final class Field
{
    public function __construct(
        public string $name,
        public string $ns,
        public string $type,
        public int $min,
        public ?int $max,
        public ?int $choice,
        public ?string $doc,
    ) {}

    public function isList(): bool
    {
        return $this->max !== 1;
    }

    public function isRequiredParam(): bool
    {
        return $this->choice === null && $this->min >= 1;
    }
}

final class Choice
{
    /** @param list<string> $members */
    public function __construct(public bool $required, public array $members = []) {}
}

final class ComplexType
{
    /** @var list<Field> */
    public array $fields = [];

    /** @var list<Choice> */
    public array $choices = [];

    public function __construct(public string $key, public string $class, public ?string $doc) {}
}

final class SimpleType
{
    /** @param list<array{value: string, doc: ?string}>|null $enum */
    public function __construct(
        public string $key,
        public string $primitive,
        public ?array $enum,
        public ?string $doc,
        public ?string $enumClass = null,
    ) {}
}

final class ModelGenerator
{
    /** @var array<string, ComplexType> */
    public array $complex = [];

    /** @var array<string, SimpleType> */
    public array $simple = [];

    public function __construct(private readonly Schemas $schemas) {}

    public function complex(string $key): ComplexType
    {
        if (isset($this->complex[$key])) {
            return $this->complex[$key];
        }

        $node = $this->schemas->complexTypes[$key] ?? throw new RuntimeException("Unknown complex type $key");
        $type = $this->complex[$key] = new ComplexType($key, className($key), englishDoc($node));
        $this->collect($node, $type);

        return $type;
    }

    public function simple(string $key): SimpleType
    {
        if (isset($this->simple[$key])) {
            return $this->simple[$key];
        }
        if (str_starts_with($key, '{'.XS.'}')) {
            $primitive = PRIMITIVES[localName($key)] ?? throw new RuntimeException("Unsupported primitive $key");

            return new SimpleType($key, $primitive, null, null);
        }

        $node = $this->schemas->simpleTypes[$key] ?? throw new RuntimeException("Unknown simple type $key");
        $restriction = children($node, 'restriction')[0] ?? throw new RuntimeException("Only restrictions are supported: $key");
        $base = $this->simple(qname($restriction, $restriction->getAttribute('base')));

        $enum = null;
        foreach (children($restriction, 'enumeration') as $value) {
            $enum[] = ['value' => $value->getAttribute('value'), 'doc' => englishDoc($value)];
        }
        $enum ??= $base->enum;
        $enumClass = $enum === null ? null : (REUSED_ENUMS[$key] ?? ($base->enum !== null ? $base->enumClass : MODEL_NAMESPACE.'\\'.className($key)));

        return $this->simple[$key] = new SimpleType($key, $base->primitive, $enum, englishDoc($node), $enumClass);
    }

    private function collect(DOMElement $node, ComplexType $type): void
    {
        foreach (children($node) as $child) {
            match ($child->localName) {
                'sequence' => $this->particles($child, $type, null),
                'choice' => $this->choice($child, $type),
                'complexContent' => $this->extension($child, $type),
                default => throw new RuntimeException("Unsupported <{$child->localName}> in {$type->key}"),
            };
        }
    }

    private function extension(DOMElement $content, ComplexType $type): void
    {
        $extension = children($content, 'extension')[0] ?? throw new RuntimeException("Only extensions are supported in {$type->key}");
        $baseKey = qname($extension, $extension->getAttribute('base'));
        $this->collect($this->schemas->complexTypes[$baseKey] ?? throw new RuntimeException("Unknown base $baseKey"), $type);
        $this->collect($extension, $type);
    }

    private function choice(DOMElement $choice, ComplexType $type): void
    {
        if ($choice->getAttribute('maxOccurs') !== '' && $choice->getAttribute('maxOccurs') !== '1') {
            throw new RuntimeException("Repeated choices are not supported in {$type->key}");
        }
        $type->choices[] = new Choice($choice->getAttribute('minOccurs') !== '0');
        $this->particles($choice, $type, array_key_last($type->choices));
    }

    private function particles(DOMElement $group, ComplexType $type, ?int $choice): void
    {
        foreach (children($group) as $particle) {
            match ($particle->localName) {
                'element' => $this->element($particle, $type, $choice),
                'choice' => $choice === null ? $this->choice($particle, $type) : throw new RuntimeException("Nested choice in {$type->key}"),
                'sequence' => $choice === null ? $this->particles($particle, $type, null) : throw new RuntimeException("Sequence inside choice in {$type->key}"),
                default => throw new RuntimeException("Unsupported <{$particle->localName}> in {$type->key}"),
            };
        }
    }

    private function element(DOMElement $element, ComplexType $type, ?int $choice): void
    {
        if ($element->hasAttribute('ref') || ! $element->hasAttribute('type')) {
            throw new RuntimeException("Only typed local elements are supported in {$type->key}");
        }

        $typeKey = qname($element, $element->getAttribute('type'));
        if (isset($this->schemas->complexTypes[$typeKey])) {
            $this->complex($typeKey);
        } else {
            $this->simple($typeKey);
        }

        $max = $element->getAttribute('maxOccurs');
        $field = new Field(
            name: $element->getAttribute('name'),
            ns: targetNamespace($element),
            type: $typeKey,
            min: $element->getAttribute('minOccurs') === '' ? 1 : (int) $element->getAttribute('minOccurs'),
            max: $max === '' ? 1 : ($max === 'unbounded' ? null : (int) $max),
            choice: $choice,
            doc: englishDoc($element),
        );

        foreach ($type->fields as $existing) {
            if ($existing->name === $field->name) {
                throw new RuntimeException("Duplicate element {$field->name} in {$type->key}");
            }
        }
        $type->fields[] = $field;
        if ($choice !== null) {
            $type->choices[$choice]->members[] = $field->name;
        }
    }
}

final class ModelEmitter
{
    /** @var array<string, string> */
    public array $files = [];

    public function __construct(private readonly ModelGenerator $generator) {}

    public function emit(string $rootKey, string $rootElementNs, string $rootElementName): void
    {
        $names = [];
        foreach ($this->generator->complex as $type) {
            $names[$type->class][] = $type->key;
        }
        foreach ($this->generator->simple as $type) {
            if ($type->enum !== null && str_starts_with((string) $type->enumClass, MODEL_NAMESPACE.'\\')) {
                $names[substr((string) $type->enumClass, strlen(MODEL_NAMESPACE) + 1)][] = $type->key;
            }
        }
        foreach ($names as $name => $keys) {
            if (count(array_unique($keys)) > 1) {
                throw new RuntimeException("Class name collision $name: ".implode(', ', array_unique($keys)));
            }
        }

        foreach ($this->generator->complex as $type) {
            $root = $type->key === $rootKey ? [$rootElementNs, $rootElementName] : null;
            $this->files[$type->class.'.php'] = $this->complexClass($type, $root);
        }
        foreach ($this->generator->simple as $type) {
            if ($type->enum === null) {
                continue;
            }
            if (isset(REUSED_ENUMS[$type->key])) {
                $this->assertReusedEnum($type);

                continue;
            }
            $class = substr((string) $type->enumClass, strlen(MODEL_NAMESPACE) + 1);
            if (! isset($this->files[$class.'.php'])) {
                $this->files[$class.'.php'] = $this->enum($type, $class);
            }
        }
        ksort($this->files);
    }

    private function assertReusedEnum(SimpleType $type): void
    {
        /** @var class-string<BackedEnum> $class */
        $class = $type->enumClass;
        $actual = array_map(static fn (BackedEnum $case): string => (string) $case->value, $class::cases());
        $expected = array_column($type->enum ?? [], 'value');
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException("$class no longer matches {$type->key}: ".implode(', ', $expected));
        }
    }

    private function enum(SimpleType $type, string $class): string
    {
        $cases = '';
        $used = [];
        foreach ($type->enum ?? [] as $case) {
            $name = caseName($case['value']);
            if (isset($used[$name])) {
                throw new RuntimeException("Enum case collision $name in {$type->key}");
            }
            $used[$name] = true;
            $cases .= ($case['doc'] !== null ? "    /** {$case['doc']} */\n" : '')
                ."    case $name = ".var_export($case['value'], true).";\n\n";
        }

        return $this->header($type->key)
            ."\n".$this->classDoc($type->doc, $type->key)
            ."enum $class: string\n{\n".rtrim($cases)."\n}\n";
    }

    /**
     * @param  array{0: string, 1: string}|null  $root
     */
    private function complexClass(ComplexType $type, ?array $root): string
    {
        $uses = ['DOMElement' => true, 'IntrAgile\\NavOnlineSzamla\\Invoice\\Xml\\ElementReader' => true,
            'IntrAgile\\NavOnlineSzamla\\Invoice\\Xml\\ElementWriter' => true, 'IntrAgile\\NavOnlineSzamla\\Invoice\\Xml\\ModelElement' => true,
            'IntrAgile\\NavOnlineSzamla\\Xml\\Ns' => true];

        $required = array_filter($type->fields, static fn (Field $f): bool => $f->isRequiredParam());
        $optional = array_filter($type->fields, static fn (Field $f): bool => ! $f->isRequiredParam());

        $params = [];
        $paramDocs = [];
        foreach ([...$required, ...$optional] as $field) {
            [$phpType, $docType] = $this->phpType($field, $uses);
            $default = $field->isRequiredParam() ? '' : ($field->isList() ? ' = []' : ' = null');
            $nullable = (! $field->isRequiredParam() && ! $field->isList()) ? '?' : '';
            $params[] = "        public {$nullable}{$phpType} \${$field->name}{$default},";
            if ($field->isList() || $field->doc !== null) {
                $paramDocs[] = '     * @param  '.($field->isList() ? 'list<'.$docType.'>' : $docType.($nullable !== '' ? '|null' : '')).'  $'.$field->name
                    .($field->doc !== null ? '  '.$field->doc : '');
            }
        }

        $checks = $this->checks($type);
        if ($checks !== '') {
            $uses['IntrAgile\\NavOnlineSzamla\\Exceptions\\InvoiceModelException'] = true;
        }
        $constructor = ($paramDocs !== [] ? "    /**\n".implode("\n", $paramDocs)."\n     */\n" : '')
            ."    public function __construct(\n".implode("\n", $params)."\n    )"
            .($checks === '' ? " {}\n" : " {\n".$checks."    }\n");

        $reads = [];
        $writes = [];
        foreach ($type->fields as $field) {
            $reads[] = '            '.$field->name.': '.$this->read($field, $uses).',';
            $writes[] = '        '.$this->write($field, $uses).';';
        }

        $rootMethods = '';
        if ($root !== null) {
            $uses['IntrAgile\\NavOnlineSzamla\\Invoice\\Xml\\DocumentReader'] = true;
            $nsConst = NS_CONSTANTS[$root[0]];
            $rootMethods = <<<PHP

                /**
                 * Parses a complete {$root[1]} document.
                 */
                public static function fromXmlString(string \$xml): self
                {
                    return self::fromXml(DocumentReader::root(\$xml, {$nsConst}, '{$root[1]}'));
                }

                /**
                 * Serializes this model as a complete {$root[1]} document.
                 */
                public function toXml(): string
                {
                    return ElementWriter::document({$nsConst}, '{$root[1]}', \$this);
                }

            PHP;
        }

        ksort($uses);
        $useBlock = implode('', array_map(static fn (string $u): string => "use $u;\n", array_keys($uses)));

        return $this->header($type->key, $useBlock)
            .$this->classDoc($type->doc, $type->key)
            ."final readonly class {$type->class} implements ModelElement\n{\n"
            .$constructor
            ."\n    public static function fromXml(DOMElement \$element): self\n    {\n"
            ."        \$reader = new ElementReader(\$element);\n"
            ."        \$model = new self(\n".implode("\n", $reads)."\n        );\n"
            ."        \$reader->assertConsumed();\n\n        return \$model;\n    }\n"
            .$rootMethods
            ."\n    public function writeXml(ElementWriter \$writer): void\n    {\n".implode("\n", $writes)."\n    }\n}\n";
    }

    private function checks(ComplexType $type): string
    {
        $out = '';
        $byName = [];
        foreach ($type->fields as $field) {
            $byName[$field->name] = $field;
        }

        foreach ($type->choices as $choice) {
            $terms = array_map(
                static fn (string $name): string => $byName[$name]->isList() ? "(\$this->{$name} !== [] ? 1 : 0)" : "(\$this->{$name} !== null ? 1 : 0)",
                $choice->members,
            );
            $list = implode(', ', $choice->members);
            // A choice may be left empty when any of its members is optional.
            $required = $choice->required && array_filter($choice->members, static fn (string $n): bool => $byName[$n]->min === 0) === [];
            $condition = $required ? '!== 1' : '> 1';
            $message = $required ? "Exactly one of $list must be set." : "At most one of $list may be set.";
            $out .= '        if (('.implode(' + ', $terms).") {$condition}) {\n"
                ."            throw new InvoiceModelException('{$type->class}: {$message}');\n        }\n";
        }

        foreach ($type->fields as $field) {
            if (! $field->isList()) {
                continue;
            }
            $min = $field->choice === null ? $field->min : 0;
            if ($min > 0) {
                $out .= "        if (count(\$this->{$field->name}) < {$min}) {\n"
                    ."            throw new InvoiceModelException('{$type->class}: {$field->name} requires at least {$min} item(s).');\n        }\n";
            }
            if ($field->max !== null) {
                $out .= "        if (count(\$this->{$field->name}) > {$field->max}) {\n"
                    ."            throw new InvoiceModelException('{$type->class}: {$field->name} allows at most {$field->max} item(s).');\n        }\n";
            }
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $uses
     * @return array{0: string, 1: string}
     */
    private function phpType(Field $field, array &$uses): array
    {
        if (isset($this->generator->complex[$field->type])) {
            $class = $this->generator->complex[$field->type]->class;

            return [$field->isList() ? 'array' : $class, $class];
        }

        $simple = $this->generator->simple($field->type);
        if ($simple->enum !== null) {
            $short = $this->import((string) $simple->enumClass, $uses);

            return [$field->isList() ? 'array' : $short, $short];
        }

        $type = match ($simple->primitive) {
            'string' => 'string',
            'int' => 'int',
            'bool' => 'bool',
            'decimal' => $this->import('IntrAgile\\NavOnlineSzamla\\Support\\Decimal', $uses),
            'date', 'dateTime' => $this->import('DateTimeImmutable', $uses),
        };

        return [$field->isList() ? 'array' : $type, $type];
    }

    /**
     * @param  array<string, true>  $uses
     */
    private function read(Field $field, array &$uses): string
    {
        $uses['IntrAgile\\NavOnlineSzamla\\Invoice\\Xml\\Value'] = true;
        $ns = NS_CONSTANTS[$field->ns];
        $method = $field->isList() ? 'many' : ($field->isRequiredParam() ? 'one' : 'optional');

        if (isset($this->generator->complex[$field->type])) {
            $convert = $this->generator->complex[$field->type]->class.'::fromXml(...)';
        } else {
            $simple = $this->generator->simple($field->type);
            if ($simple->enum !== null) {
                $short = $this->import((string) $simple->enumClass, $uses);
                $convert = "static fn (DOMElement \$e): {$short} => {$short}::from(Value::string(\$e))";
            } else {
                $convert = 'Value::'.$simple->primitive.'(...)';
            }
        }

        return "\$reader->{$method}({$ns}, '{$field->name}', {$convert})";
    }

    /**
     * @param  array<string, true>  $uses
     */
    private function write(Field $field, array &$uses): string
    {
        $ns = NS_CONSTANTS[$field->ns];
        $property = '$this->'.$field->name;

        if (isset($this->generator->complex[$field->type])) {
            return '$writer->'.($field->isList() ? 'children' : 'child')."({$ns}, '{$field->name}', {$property})";
        }

        $simple = $this->generator->simple($field->type);
        if ($field->isList()) {
            [, $docType] = $this->phpType($field, $uses);
            $item = $this->lexical($simple, '$v', false);
            $values = $item === '$v' ? $property : "array_map(static fn ({$docType} \$v): string => {$item}, {$property})";

            return "\$writer->texts({$ns}, '{$field->name}', {$values})";
        }

        return "\$writer->text({$ns}, '{$field->name}', ".$this->lexical($simple, $property, ! $field->isRequiredParam()).')';
    }

    /**
     * PHP expression that converts a simple type value to its lexical form.
     */
    private function lexical(SimpleType $simple, string $expr, bool $nullable): string
    {
        $arrow = $nullable ? '?->' : '->';
        if ($simple->enum !== null) {
            return $expr.$arrow.'value';
        }

        return match ($simple->primitive) {
            'string' => $expr,
            'decimal' => $expr.$arrow.'value',
            'int' => $nullable ? "({$expr} === null ? null : (string) {$expr})" : "(string) {$expr}",
            'bool' => "Value::fromBool({$expr})",
            'date' => "Value::fromDate({$expr})",
            'dateTime' => "Value::fromDateTime({$expr})",
        };
    }

    /**
     * @param  array<string, true>  $uses
     */
    private function import(string $fqcn, array &$uses): string
    {
        if (! str_starts_with($fqcn, MODEL_NAMESPACE.'\\')) {
            $uses[$fqcn] = true;
        }

        return substr($fqcn, strrpos($fqcn, '\\') === false ? 0 : strrpos($fqcn, '\\') + 1);
    }

    private function header(string $key, string $uses = ''): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace ".MODEL_NAMESPACE.";\n\n".$uses.($uses !== '' ? "\n" : '');
    }

    private function classDoc(?string $doc, string $key): string
    {
        $source = sprintf('%s (%s)', localName($key), basename(sourceFile($key)));

        return "/**\n".($doc !== null ? " * {$doc}\n *\n" : '')
            ." * Generated from {$source} by bin/generate-model.php. Do not edit.\n */\n";
    }
}

function sourceFile(string $key): string
{
    return match (substr($key, 1, strpos($key, '}') - 1)) {
        'http://schemas.nav.gov.hu/OSA/3.0/data' => 'invoiceData.xsd',
        'http://schemas.nav.gov.hu/OSA/3.0/base' => 'invoiceBase.xsd',
        'http://schemas.nav.gov.hu/NTCA/1.0/common' => 'common.xsd',
    };
}

function caseName(string $value): string
{
    $parts = preg_split('/[^A-Za-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value];
    $name = implode('', array_map(static fn (string $p): string => ucfirst(strtolower($p)), $parts));

    return ctype_digit($name[0]) ? 'Value'.$name : $name;
}

// Entry point.
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$check = in_array('--check', $argv, true);
$schemas = new Schemas($root.'/resources/xsd', ['common.xsd', 'invoiceBase.xsd', 'invoiceData.xsd']);
$generator = new ModelGenerator($schemas);

$rootKey = '{http://schemas.nav.gov.hu/OSA/3.0/data}InvoiceData';
$rootElement = $schemas->rootElements[$rootKey] ?? throw new RuntimeException('InvoiceData root element not found');
$anonymous = children($rootElement, 'complexType')[0] ?? throw new RuntimeException('InvoiceData has no inline type');
$extension = children(children($anonymous, 'complexContent')[0], 'extension')[0];
if (children($extension) !== []) {
    throw new RuntimeException('The InvoiceData root element adds members to its base type; update the generator.');
}
$rootTypeKey = qname($extension, $extension->getAttribute('base'));
$generator->complex($rootTypeKey);

$emitter = new ModelEmitter($generator);
$emitter->emit($rootTypeKey, 'http://schemas.nav.gov.hu/OSA/3.0/data', 'InvoiceData');

$target = $root.'/src/Invoice/Model';
$build = $root.'/build/generated-model';
if (is_dir($build)) {
    array_map('unlink', glob($build.'/*.php') ?: []);
} else {
    mkdir($build, 0777, true);
}
foreach ($emitter->files as $file => $code) {
    file_put_contents($build.'/'.$file, $code);
}

// Format exactly as the repository's Pint configuration would, so --check stays stable.
exec(sprintf('%s --config=%s %s 2>&1', escapeshellarg($root.'/vendor/bin/pint'), escapeshellarg($root.'/pint.json'), escapeshellarg($build)), $output, $status);
if ($status !== 0) {
    fwrite(STDERR, implode("\n", $output)."\n");
    exit(2);
}

$generated = array_map('basename', glob($build.'/*.php') ?: []);
$existing = array_map('basename', glob($target.'/*.php') ?: []);
$stale = false;

foreach ($generated as $file) {
    $code = (string) file_get_contents($build.'/'.$file);
    $path = $target.'/'.$file;
    if (is_file($path) && file_get_contents($path) === $code) {
        continue;
    }
    if ($check) {
        fwrite(STDERR, "Stale: $file\n");
        $stale = true;

        continue;
    }
    file_put_contents($path, $code);
}

foreach (array_diff($existing, $generated) as $orphan) {
    if ($check) {
        fwrite(STDERR, "Orphaned: $orphan\n");
        $stale = true;

        continue;
    }
    unlink($target.'/'.$orphan);
}

array_map('unlink', glob($build.'/*.php') ?: []);
rmdir($build);

if (! $check) {
    printf("Generated %d files in src/Invoice/Model\n", count($generated));
}

exit($stale ? 1 : 0);
