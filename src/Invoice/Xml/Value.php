<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Xml;

use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use InvalidArgumentException;

/**
 * Conversions between XSD simple type lexical forms and PHP values.
 */
final class Value
{
    public static function string(DOMElement $element): string
    {
        return $element->textContent;
    }

    public static function int(DOMElement $element): int
    {
        $text = trim($element->textContent);
        if (preg_match('/^[+-]?\d+$/', $text) !== 1) {
            throw self::invalid($element, 'an integer');
        }

        return (int) $text;
    }

    public static function bool(DOMElement $element): bool
    {
        return match (trim($element->textContent)) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw self::invalid($element, 'a boolean'),
        };
    }

    public static function decimal(DOMElement $element): Decimal
    {
        try {
            return Decimal::of($element->textContent);
        } catch (InvalidArgumentException) {
            throw self::invalid($element, 'a decimal');
        }
    }

    public static function date(DOMElement $element): DateTimeImmutable
    {
        $text = trim($element->textContent);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $text) {
            throw self::invalid($element, 'a date');
        }

        return $date;
    }

    public static function dateTime(DOMElement $element): DateTimeImmutable
    {
        $text = trim($element->textContent);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,3})?Z$/', $text) !== 1) {
            throw self::invalid($element, 'a UTC timestamp');
        }

        return new DateTimeImmutable($text);
    }

    /**
     * @return ($value is null ? null : string)
     */
    public static function fromBool(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'true' : 'false');
    }

    /**
     * @return ($value is null ? null : string)
     */
    public static function fromDate(?DateTimeImmutable $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    /**
     * @return ($value is null ? null : string)
     */
    public static function fromDateTime(?DateTimeImmutable $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $utc = $value->setTimezone(new DateTimeZone('UTC'));
        $millis = $utc->format('v');

        return $utc->format('Y-m-d\TH:i:s').($millis === '000' ? '' : '.'.$millis).'Z';
    }

    private static function invalid(DOMElement $element, string $expected): InvoiceModelException
    {
        return new InvoiceModelException(sprintf(
            'Element <%s> must contain %s, got "%s".',
            $element->localName,
            $expected,
            $element->textContent,
        ));
    }
}
