# XSD-forrás

- `invoiceApi.xsd`, `invoiceBase.xsd`, `invoiceData.xsd`, `invoiceAnnulment.xsd`, `serviceMetrics.xsd`:
  https://github.com/nav-gov-hu/Online-Invoice (commit `cc7a775d6dce361311e409abb9934eb755f2749c`,
  2026-02-13). Egyetlen módosítás: a `schemaLocation` pótlása az `xs:import`-okban
  (`bin/sync-xsd.php`).
- `common.xsd` (NTCA common v1.0, 2020/11/23, szerző: NAV Informatikai Intézet): a hivatalos repó
  nem tartalmazza; a `pzs/nav-online-invoice` csomag v3.0.5-ös példányából átvéve, változatlanul.

Frissítés: `php bin/sync-xsd.php /path/to/Online-Invoice`, ellenőrzés: `--check`.
