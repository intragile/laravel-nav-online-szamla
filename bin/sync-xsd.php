<?php

/**
 * A hivatalos NAV XSD-k átvétele a nav-gov-hu/Online-Invoice repóból.
 *
 *   php bin/sync-xsd.php /path/to/Online-Invoice [--check]
 *
 * A hivatalos fájlok `xs:import`-jaiból hiányzik a `schemaLocation`, ezért a libxml nem
 * tudja feloldani őket; a script ezt pótolja, mást nem módosít. `--check` módban nem ír,
 * csak nem nulla kóddal kilép, ha a csomag XSD-i eltérnek a hivatalostól (CI-hoz).
 * A common.xsd (NTCA) nincs a hivatalos repóban, azt nem érinti.
 */
$source = $argv[1] ?? null;
$check = in_array('--check', $argv, true);
if ($source === null || ! is_dir($source.'/src/schemas/nav/gov/hu/OSA')) {
    fwrite(STDERR, "Használat: php bin/sync-xsd.php /path/to/Online-Invoice [--check]\n");
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
                throw new RuntimeException("Ismeretlen importált namespace: {$m[1]}");
            }

            return sprintf('<xs:import namespace="%s" schemaLocation="%s"/>', $m[1], $locations[$m[1]]);
        },
        file_get_contents($file),
    );

    $out = $target.'/'.basename($file);
    if ($check) {
        if (! is_file($out) || file_get_contents($out) !== $xsd) {
            fwrite(STDERR, 'ELTÉR: '.basename($file)."\n");
            $drift = true;
        }

        continue;
    }

    file_put_contents($out, $xsd);
    echo 'Frissítve: '.basename($file)."\n";
}

exit($drift ? 1 : 0);
