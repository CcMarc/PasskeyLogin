# Passkey Login for Zen Cart

![PasskeyLogin](https://socialify.git.ci/CcMarc/PasskeyLogin/image?custom_description=Passwordless+passkey+%28WebAuthn%29+sign+in+for+Zen+Cart&description=1&font=Inter&forks=1&issues=1&language=1&name=1&owner=0&pattern=Signal&pulls=1&stargazers=1&theme=Auto)

[![PHP](https://badgen.net/badge/php/8.0%2B/777BB4)](https://www.php.net/)
[![Zen Cart](https://badgen.net/badge/zen%20cart/2.1.0%2B/F6851F)](https://www.zen-cart.com/)
[![License](https://badgen.net/badge/license/GPL-2.0/blue)](https://www.zen-cart.com/license/2_0.txt)
[![Last Commit](https://badgen.net/github/last-commit/CcMarc/PasskeyLogin/main)](https://github.com/CcMarc/PasskeyLogin/commits/main)
[![Release](https://badgen.net/github/release/CcMarc/PasskeyLogin)](https://github.com/CcMarc/PasskeyLogin/releases/latest)

Passwordless sign in with passkeys (WebAuthn / FIDO2) for Zen Cart 2.1+.

Customers add a passkey from their account page, then sign in with Face ID,
a fingerprint, Windows Hello, or a hardware security key. Passkeys are
phishing resistant: each one is cryptographically bound to your domain and
the private key never leaves the customer's device, so there is nothing for
an attacker to steal from the store database except a public key.

## How it presents

- **Login page**: no new buttons. The plugin uses WebAuthn conditional UI,
  so a customer's saved passkey appears inside the browser's own autofill
  when they tap the email field. Customers without a passkey see a page
  identical to stock.
- **My Account**: a Passkeys tile is added to the account tiles, and
  customers without a passkey see a one time dismissible invitation banner
  (only when their device actually supports platform passkeys).
- **Passkeys page**: customers add, rename, and remove their passkeys.
- **Admin**: a console under Extras with status, per customer lookup and
  removal (for lost or stolen devices), recent activity, a debug log tail,
  and a maintenance sweep. Settings live in Configuration > Passkey Login.

## Requirements

- Zen Cart 2.1.0 or later (developed and tested on 2.2.2 — see
  [Compatibility](#compatibility))
- PHP 8.0 or later with the OpenSSL extension
- HTTPS (required by WebAuthn in every browser)

## Install

As with any plugin, install on a development or staging copy of your
store first.

1. Upload the `zc_plugins/PasskeyLogin/` folder into your store's
   `zc_plugins/` directory.
2. Admin > Modules > Plugin Manager > Passkey Login > Install.
3. That is all. The installer creates the tables and configuration group,
   registers the admin pages, and publishes the storefront page files.

To uninstall, use Plugin Manager. Published files and configuration are
removed; the data tables are preserved (see [Data](#data)) so customers
keep their passkeys if you reinstall.

### Published files

Three files are copied into the live catalog tree at install time:

- `includes/modules/pages/passkey_settings/header_php.php`
- `includes/languages/english/lang.passkey_settings.php`
- `includes/templates/<your template>/templates/tpl_passkey_settings_default.php`

Because these are published, changes to them ship by REINSTALLING the
plugin. A bare `zc_plugins` folder swap updates everything else but not
these three.

The template file is published into your ACTIVE template's directory. If
you change your store's template later, reinstall the plugin so it is
published into the new template's directory.

### Write permissions

Publishing writes outside the `zc_plugins/` folder, so during install
(and uninstall) the web server (PHP) user must be able to write to these
directories under your catalog root:

- `includes/modules/pages/` (a `passkey_settings/` folder is created inside it)
- `includes/languages/english/`
- `includes/templates/<your template>/templates/`

On most hosts this already works and there is nothing to do. If any file
cannot be written, the install still completes and Plugin Manager shows a
caution listing the exact files that need copying, with their source
paths under `zc_plugins/PasskeyLogin/v1.0.0/publish/`.

If you see that caution, either copy the listed files manually (FTP or
file manager — safest on shared hosting), or make the three directories
writable by the web server user and reinstall. For example, on a typical
Linux host, from the catalog root (substitute your web server user and
your template directory):

```
chown www-data:www-data includes/modules/pages includes/languages/english includes/templates/YOUR_TEMPLATE/templates
```

Uninstalling removes the published files and needs the same write
access. Anything it cannot remove is inert once the plugin is gone and
can be deleted manually.

## Works with

- **One Page Checkout (OPC)** — developed and tested alongside OPC,
  including guest checkout. OPC guest checkout shares a single guest
  customer account; a passkey on that account would act as a shared key
  to every guest session, so this plugin blocks the guest account in
  depth: the nudge never shows in guest sessions, the settings page
  requires a real login, the registration and login endpoints reject the
  guest account server side, and the installer plus the admin sweep
  delete any guest rows that ever appear. OPC is NOT required — the
  plugin works the same on stores without it.
- **SEO / URL rewriters** — the ceremony endpoints deliberately call
  `index.php?main_page=...` directly, so pretty-URL rewriters that drop
  query parameters do not break passkey sign in.
- **Any template** — no template edits. The login and account
  enhancements are injected at a core notifier point, all injected UI is
  self-contained inline styling, and the one published page template can
  be adapted per template like any Zen Cart page.
- **Your existing sign in options** — passkeys are added alongside
  password login, not instead of it. Customers who never add a passkey
  see no change, and password sign in remains the recovery path for a
  lost device.

## Relying Party ID and staging

By default the plugin derives the registrable domain from your store URL,
so `alpha.example.com` and `www.example.com` share `example.com` passkeys:
a passkey added on production works on staging and the other way around.
Stores on multi part TLDs (such as `.co.uk`) must set the Relying Party ID
override in settings.

## Data

Four tables, all PRESERVED on uninstall so customers keep their passkeys
across reinstalls: `passkey_credentials` (public keys only), `passkey_optout`
(nudge dismissals), `passkey_audit` (event log, pruned after 90 days), and
`passkey_challenges` (short lived ceremony challenges, pruned automatically).

## Library

Server side verification uses the vendored lbuchs/WebAuthn library (MIT,
license included at `catalog/lib/WebAuthn/LICENSE`).

## Compatibility

| Zen Cart | Status |
| -------- | ------ |
| 2.2.2 | Developed and tested here, including live production use |
| 2.1.0 – 2.2.1 | Should work — every core API this plugin uses was verified present in the 2.1.0 source (`Customer::login()`, the scripted installer base, the plugin language loaders, the notifier hooks). Not yet tested on a live 2.1.x store; reports welcome |
| 2.0.x and earlier | Not supported |

### Customer devices and browsers

Passkeys are a browser and operating system feature, so what each
customer can do depends on their hardware:

- Creating and using a passkey directly on a device requires iOS 16+
  (iPhone 8 or newer), Android 9+ with Google Play services, Windows
  10+ with Windows Hello, or macOS 13+ with Safari or Chrome.
- The QR code cross device flow (signing in on a computer with a
  passkey stored on a phone) additionally requires Bluetooth ON for
  both devices; the proximity check is part of the phishing protection
  and cannot be disabled. Phones that cannot update to iOS 16 /
  Android 9 (for example the iPhone 7 and earlier) show a connecting
  screen that never completes; that is a platform limit no website can
  work around.
- Login page suggestion (conditional UI): Chrome, Edge, and Safari
  show the passkey in the email field's autofill suggestions. Firefox
  does not yet implement conditional UI and cannot access Apple
  Passwords on macOS, so Firefox users will not see a passkey option
  on the login page; they continue signing in normally and nothing on
  the page breaks.
- The easiest first enrollment is directly on a supported phone: sign
  in with a password there, open My Account, then Passkeys, and add
  the passkey with the phone's own biometric. The QR flow is mainly
  for signing in on a computer afterwards.

Customers on unsupported devices simply keep using their password;
the login page shows them nothing new.

## Support

This plugin is provided as-is, on a best-effort basis. There is no
guaranteed support or response time.

- Questions and general help: the
  [Zen Cart support thread](https://www.zen-cart.com/threads/207288)
  or [GitHub issues](https://github.com/CcMarc/PasskeyLogin/issues).
- Security reports: see [SECURITY.md](SECURITY.md) — please report
  privately, never in a public issue.

## Disclaimer

Provided as-is, without warranty of any kind, under the GPL (see the
license link above). Authentication touches every customer, so test on a
development or staging copy before installing on a live store. You are
responsible for your own store, its data, and its backups.
