# Passkey Login authentication result notifier

`NOTIFY_PASSKEY_LOGIN_RESULT` is a generic, optional Zen Cart catalog notification emitted by Passkey Login's published `passkey_settings` controller. The plugin does not import or depend on any observer or diagnostics module.

The notifier receives one array as its second `notify()` argument:

```php
[
    'outcome' => 'success', // 'success' or 'failure'
    'stage' => 'verify',   // 'options' or 'verify'
    'reason' => '',        // empty on success; stable reason code on failure
    'customers_id' => 123 // 0 when not yet known
]
```

**Semantics:**

- `ajax_login_options`: notify only on server-side failure. Successfully obtaining WebAuthn options is not a login.
- `ajax_login_verify`: notify once on the server response for success or failure.
- The event does not fire for client-side cancellation, failed browser-to-server delivery, registration, account-management actions, or requests blocked before reaching this controller.
- `customers_id` is populated only after a credential has been matched; never use it as proof of successful sign-in unless `outcome` is `success`.
- `reason` values are machine-readable categories including `service_unavailable`, `method_not_allowed`, `rate_limited`, `options_unavailable`, `invalid_assertion`, `challenge_unavailable`, `unknown_credential`, `account_unavailable`, `invalid_user_handle`, `verification_failed`, `counter_rejected`, `account_banned` and `login_failed`. Unknown future reason codes must be handled gracefully.
- No raw WebAuthn payloads, assertion IDs, cryptographic keys, challenge values, security tokens, passwords, or session cookies are emitted.

Integration notes: Zen Cart also emits `NOTIFY_LOGIN_SUCCESS` from the normal customer-login flow. If you subscribe to both, de-duplicate successful passkey logins rather than counting a single login twice. Observer implementations should not modify the authentication result or send output to the AJAX response.

Deployment: The controller is a **published** file at `includes/modules/pages/passkey_settings/header_php.php`. A Plugin Manager upgrade or install publishes it. Copying the plugin folder alone does not replace an already-published controller.
