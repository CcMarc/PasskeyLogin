# Security Policy

Passkey Login is an authentication plugin, so security reports get
priority over every other kind of issue.

## Reporting a vulnerability

Please do NOT open a public GitHub issue for anything security related.

Report privately via **GitHub's private vulnerability reporting** on this
repository (Security tab > Report a vulnerability). Include the plugin
version, your Zen Cart and PHP versions, and reproduction steps if you
have them.

You will get an acknowledgement, and a fix or a considered response
before any public disclosure. Please allow a reasonable disclosure
window; this project is maintained on a best-effort basis.

## Supported versions

| Version | Supported |
| ------- | --------- |
| Latest release | Yes |
| Older releases | Upgrade to the latest release first |

## Scope notes

- The private key never leaves the customer's authenticator; the store
  database holds public keys only.
- Attestation is `none` by design (standard for retail). Reports that
  amount to "attestation is not verified" are working as intended.
- The shared One Page Checkout guest account is blocked from holding
  passkeys at every layer; bypasses of that guard are firmly in scope.
