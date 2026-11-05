# Security Policy

## Reporting a vulnerability

Please report security issues privately to **info@intragile.hu** rather than opening a public
issue. Include the affected version and, if possible, steps to reproduce. You will receive a
reply within a few working days.

## Handling of credentials

This package handles NAV technical-user credentials. By design:

- request and response bodies are never logged, because they contain the password hash and the
  exchange token;
- `Credentials` and `ExchangeToken` mask their secrets in debug output;
- the default environment is the NAV test system; production must be selected explicitly.

Never commit real credentials, and use the NAV test environment for automated tests.
