<?php

/**
 * Sync the bundled XSDs from a clone of https://github.com/nav-gov-hu/Online-Invoice.
 *
 *   php bin/sync-xsd.php /path/to/Online-Invoice [--check]
 *
 * The official files omit schemaLocation on xs:import, which libxml cannot resolve; this
 * script adds it and changes nothing else. With --check it writes nothing and exits
 * non-zero when the bundled XSDs differ from upstream. common.xsd is not published
 * upstream and is left untouched.
 */
$source = $argv[1] ?? null;
$check = in_array('--check', $argv, true);
if ($source === null || ! is_dir($source.'/src/schemas/nav/gov/hu/OSA')) {
    fwrite(STDERR, "Usage: php bin/sync-xsd.php /path/to/Online-Invoice [--check]\n");
    exit(2);
}

$locations = [
    'http://schemas.nav.gov.hu/NTCA/1.0/common' => 'common.xsd',
    'http://schemas.nav.gov.hu/OSA/3.0/base' => 'invoiceBase.xsd',
    'http://schemas.nav.gov.hu/OSA/3.0/data' => 'invoiceData.xsd',
    'http://schemas.nav.gov.hu/OSA/3.0/annul' => 'invoiceAnnulment.xsd',
];

$target = __DIR__.'/../resources/xsd';
$drift = false;

foreach (glob($source.'/src/schemas/nav/gov/hu/OSA/*.xsd') as $file) {
    $xsd = preg_replace_callback(
        '#<xs:import namespace="([^"]+)"\s*/>#',
        static function (array $m) use ($locations): string {
            if (! isset($locations[$m[1]])) {
                throw new RuntimeException("Unknown imported namespace: {$m[1]}");
            }

            return sprintf('<xs:import namespace="%s" schemaLocation="%s"/>', $m[1], $locations[$m[1]]);
        },
        file_get_contents($file),
    );

    $out = $target.'/'.basename($file);
    if ($check) {
        if (! is_file($out) || file_get_contents($out) !== $xsd) {
            fwrite(STDERR, 'Differs: '.basename($file)."\n");
            $drift = true;
        }

        continue;
    }

    file_put_contents($out, $xsd);
    echo 'Updated: '.basename($file)."\n";
}

exit($drift ? 1 : 0);
