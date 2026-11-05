# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Contact address changed to `info@intragile.eu` in the package metadata, the security policy
  and the test fixtures.

## [1.0.1] - 2026-11-05

### Changed

- Re-release of 1.0.0 with identical source code. The repository history was rewritten after
  1.0.0 was published, which moved the `v1.0.0` tag; Packagist keeps stable versions immutable,
  so the rewritten history is published under this version instead.

## [1.0.0] - 2026-11-05

First public release.

### Added

- All ten NAV Online Számla v3.0 operations with typed requests and responses, on Laravel's
  HTTP client, with typed errors and safe retries (queries and token exchange only).
- Request signing, password hashing and exchange-token decoding, verified against the
  specification's examples.
- Official XSDs bundled; every outgoing document is validated. `bin/sync-xsd.php` and a weekly
  CI job detect schema drift.
- Typed invoice data model generated from the XSDs (`bin/generate-model.php`).
- `InvoiceBuilder` for CREATE/MODIFY/STORNO, `stornoOf()`, batch modification, simplified,
  aggregate, foreign-currency and advance invoices, with computed amounts and summaries.
- `InvoiceValidator`: NAV's blocking business rules that the submitted data can decide, with
  per-rule skipping.
- Laravel integration: `Nav` facade, multiple taxpayers via `CredentialsResolver`, queued
  submission with a transaction log, status polling with backoff, reconciliation of lost
  responses instead of blind resubmission, lifecycle events.
- Artisan commands: `nav:software-id`, `nav:test-connection`, `nav:status`, `nav:resume`.
- `Nav::fake()` with an in-memory NAV and assertions.
- Opt-in end-to-end suite against the NAV test environment.

[1.0.1]: https://github.com/intragile/laravel-nav-online-szamla/releases/tag/v1.0.1
[1.0.0]: https://github.com/intragile/laravel-nav-online-szamla/releases/tag/v1.0.0
