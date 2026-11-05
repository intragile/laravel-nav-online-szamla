# Schema sources

- `invoiceApi.xsd`, `invoiceBase.xsd`, `invoiceData.xsd`, `invoiceAnnulment.xsd`, `serviceMetrics.xsd`:
  https://github.com/nav-gov-hu/Online-Invoice at commit `cc7a775d6dce361311e409abb9934eb755f2749c`
  (2026-02-13). The only change is the added `schemaLocation` on `xs:import` (see `bin/sync-xsd.php`).
- `common.xsd` (NTCA common v1.0, 2020-11-23, NAV): not published in the official repository; taken
  unchanged from `pzs/nav-online-invoice` v3.0.5.

Update with `php bin/sync-xsd.php /path/to/Online-Invoice`; verify with `--check`.
