# Passkey Login — Changelog

File headers (`@version` / `@updated`) record the release that LAST MODIFIED
each file, not the current release — same convention as Zen Cart core.

## v1.0.1 (08-27-2026)

- FIX: the admin console's Open Settings link used a configuration URL
  pattern core does not support (`action=locate`), which fell through
  to the configuration page while core's `init_templates.php` logged a
  PHP warning about the missing `gID` parameter on PHP 8. The link now
  looks up the plugin's configuration group and links it directly with
  `gID`, the way core navigates.
- I18N: the console's status sentence and the customer lookup id are
  now full sprintf templates (markup and punctuation inside the language
  constant), so languages with different word order or punctuation, such
  as Japanese, can translate them naturally.
- I18N: the admin console previously had its English text hardcoded;
  every string the console displays now comes from language constants in
  the admin extra_definitions language file, so translations can be
  added as additional language files without touching code. Reported
  via the support thread.
- DOCS: the README Compatibility section now covers customer devices
  and browsers: iOS 16+ / Android 9+ requirements, the Bluetooth
  requirement of the QR cross device sign in flow, older phones (such
  as the iPhone 7) that cannot complete it, and Firefox's conditional
  UI limitations.
- Deploying over v1.0.0: upload the `v1.0.1` directory alongside the
  old one and use Plugin Manager's Upgrade (or uninstall and
  reinstall). Passkeys, audit data, and settings behave as documented
  in the README's Data section.

## v1.0.0 (08-23-2026)

Initial public release. Developed and production-tested on Zen Cart 2.2.2
before publishing; see the README compatibility section for details.

- Passkey (WebAuthn / FIDO2) sign in via conditional UI on the login page:
  saved passkeys surface inside the browser's own autofill on the email
  field, with no visible layout change for customers who do not use them.
- Passkeys account page: customers add, rename, and remove passkeys, with
  a drawn-in-place remove confirmation.
- My Account: a Passkeys tile plus a one time, dismissible enrollment
  invitation, shown only when the device supports platform passkeys.
  Injected at footer end with zero template edits.
- Smart default passkey names resolved from the AAGUID community registry
  ("Apple Passwords (Aug 2026)", "Google Password Manager (Aug 2026)"),
  falling back to the reported transports ("Phone or tablet", "Security
  key", "This device").
- After passkey sign in the customer lands where core password login
  would take them: navigation snapshot honored, activation routing and
  combined cart notices included, My Account as the fallback.
- Sign in runs through the core `Customer::login()` sequence, so account
  maintenance, session values, cart restore, and authorization behavior
  match password login exactly. Banned accounts see an honest message.
- Admin console under Extras: status overview, customer lookup with
  support removal (the lost or stolen device case), recent activity,
  debug log tail, and a maintenance sweep. Settings in Configuration >
  Passkey Login (also linked from the Configuration menu).
- One Page Checkout aware: the shared guest checkout account can never
  hold a passkey, enforced at every layer (UI, endpoints, installer,
  sweep). Works equally on stores without OPC.
- Hardened ceremony handling: database-backed single use challenges
  (multi tab safe, atomic take), signature counter clone detection,
  per IP hourly rate caps on all ceremony endpoints, strict payload
  size limits, and JSON endpoints that cannot be corrupted by stray
  shutdown output.
- InnoDB utf8mb4 schema with full WebAuthn credential id range; data
  tables are PRESERVED on uninstall so customers keep their passkeys
  across reinstall cycles.
- Vendored lbuchs/WebAuthn (MIT) for server side ceremony verification;
  attestation `none` (the retail standard — no authenticator
  fingerprinting).
