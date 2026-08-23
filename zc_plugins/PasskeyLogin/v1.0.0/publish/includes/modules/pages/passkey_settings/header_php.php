<?php
/**
 * Module: PasskeyLogin
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 8.0+ with OpenSSL
 * @author      Marcopolo
 * @copyright   2026
 * @license     GNU General Public License (GPL) - https://www.zen-cart.com/license/2_0.txt
 * @version     1.0.0
 * @updated     08-23-2026
 * @github      https://github.com/CcMarc/PasskeyLogin
 */
// Controller for the passkey_settings page: the settings UI for logged-in
// customers plus the JSON endpoints for both WebAuthn ceremonies. This
// file is PUBLISHED by the installer into includes/modules/pages/, so a
// bare zc_plugins folder swap does NOT update it: deploy changes to this
// file by reinstalling.
//
// Endpoint notes:
// - Zen Cart validates securityToken on EVERY POST before this code runs,
//   so every AJAX call must send the token (the observer and template
//   scripts always include it).
// - AJAX URLs are always index.php?main_page=... and never zen_href_link,
//   because SEO rewriters can drop query params on pretty URLs.
// - The login endpoints are the only anonymous ones. Registration and
//   settings actions require a real (non-guest) customer session: the OPC
//   shared guest account must never hold a passkey.
//
$zco_notifier->notify('NOTIFY_HEADER_START_PASSKEY_SETTINGS');
require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

if (!function_exists('pkl_msg')) {
    function pkl_msg(string $const, string $fallback): string
    {
        return defined($const) ? constant($const) : $fallback;
    }
}

if (!function_exists('pkl_json_exit')) {
    function pkl_json_exit(array $payload)
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('pkl_json_post')) {
    function pkl_json_post(string $key, int $maxBytes): ?array
    {
        $raw = $_POST[$key] ?? null;
        if (!is_string($raw) || $raw === '' || strlen($raw) > $maxBytes) return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = json_decode(stripslashes($raw), true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('pkl_required_b64')) {
    function pkl_required_b64(array $data, string $key, int $maxEncodedBytes)
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])
            || $data[$key] === '' || strlen($data[$key]) > $maxEncodedBytes) return false;
        $bin = base64_decode($data[$key], true);
        return ($bin === false || $bin === '') ? false : $bin;
    }
}

$pkl_available = defined('PKL_PLUGIN_DIR') && class_exists('PasskeyLoginService') && pkl_is_available();
$action = (isset($_GET['action']) && is_string($_GET['action'])) ? $_GET['action'] : '';
$isAjax = (strpos($action, 'ajax_') === 0);

if (!$pkl_available) {
    if ($isAjax) pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_PAGE_UNAVAILABLE', 'Passkey sign in is not available right now. Please check back later.')]);
    // Page stays reachable so the template can explain, rather than 404.
}

/* ---------------------------------------------------------------------- */
/* Anonymous endpoints: the login ceremony                                 */
/* ---------------------------------------------------------------------- */

if ($action === 'ajax_login_options') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_METHOD', 'Please reload the page and try again.')]);
    if (!PasskeyLoginService::rateOk('login_options')) {
        PasskeyLoginService::debug(['event' => 'rate_capped', 'endpoint' => 'login_options']);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_RATE', 'Too many passkey attempts from this connection. Please wait and try again, or use another sign in option.')]);
    }
    PasskeyLoginService::audit('login_options');
    try {
        $webAuthn = PasskeyLoginService::webAuthn();
        $getArgs = $webAuthn->getGetArgs([], 300, true, true, true, true, true, true);
        if (is_object($getArgs) && isset($getArgs->publicKey) && is_object($getArgs->publicKey)) {
            unset($getArgs->publicKey->timeout);
        }
        PasskeyLoginService::stashChallenge('login', $webAuthn->getChallenge());
        PasskeyLoginService::debug(['event' => 'login_options', 'rpId' => PasskeyLoginService::rpId()]);
        pkl_json_exit(['ok' => true, 'getArgs' => $getArgs]);
    } catch (Throwable $e) {
        PasskeyLoginService::debug(['event' => 'login_options_error', 'msg' => $e->getMessage()]);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_SERVER', 'Passkey sign in is temporarily unavailable. Please use another sign in option.')]);
    }
}

if ($action === 'ajax_login_verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_METHOD', 'Please reload the page and try again.')]);
    if (!PasskeyLoginService::rateOk('login_verify')) {
        PasskeyLoginService::debug(['event' => 'rate_capped', 'endpoint' => 'login_verify']);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_RATE', 'Too many passkey attempts from this connection. Please wait and try again, or use another sign in option.')]);
    }
    // Count every request that passes the cap so malformed traffic cannot
    // sidestep accounting, while capped traffic causes no further audit writes.
    PasskeyLoginService::audit('login_verify');

    $assertion = pkl_json_post('assertion', 32768);
    if (!is_array($assertion)) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
    }

    $rawId = pkl_required_b64($assertion, 'id', 1364);
    $clientData = pkl_required_b64($assertion, 'clientDataJSON', 16384);
    $authenticatorData = pkl_required_b64($assertion, 'authenticatorData', 16384);
    $signature = pkl_required_b64($assertion, 'signature', 4096);
    if ($rawId === false || strlen($rawId) > 1023 || $clientData === false
        || $authenticatorData === false || $signature === false) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
    }

    $challenge = PasskeyLoginService::takeChallenge('login', $clientData);
    if ($challenge === null) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
    }

    $cred = PasskeyLoginService::findByCredentialId(PasskeyLoginService::b64urlEncode($rawId));
    if ($cred === null) {
        PasskeyLoginService::audit('login_verify_fail');
        PasskeyLoginService::debug(['event' => 'login_unknown_credential']);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_UNKNOWN_PASSKEY', 'That passkey is not connected to an account here. It may have been removed. Please sign in another way, then add a new passkey from your account page.')]);
    }

    $customersId = (int)$cred['customers_id'];
    if (PasskeyLoginService::isGuestCustomerId($customersId)) {
        PasskeyLoginService::audit('login_verify_fail', $customersId);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
    }

    // userHandle is optional, but when supplied it must be a valid base64
    // string containing exactly the customers_id string registered as userId.
    if (array_key_exists('userHandle', $assertion) && $assertion['userHandle'] !== null) {
        if (!is_string($assertion['userHandle']) || $assertion['userHandle'] === ''
            || strlen($assertion['userHandle']) > 256) {
            PasskeyLoginService::audit('login_verify_fail', $customersId);
            pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
        }
        $handle = base64_decode($assertion['userHandle'], true);
        if ($handle === false || $handle === '' || !hash_equals((string)$customersId, $handle)) {
            PasskeyLoginService::audit('login_verify_fail', $customersId);
            PasskeyLoginService::debug(['event' => 'login_userhandle_mismatch', 'customers_id' => $customersId]);
            pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
        }
    }

    try {
        $webAuthn = PasskeyLoginService::webAuthn();
        $webAuthn->processGet(
            $clientData,
            $authenticatorData,
            $signature,
            $cred['public_key'],
            $challenge,
            null,
            true,
            true
        );
        $newCount = $webAuthn->getSignatureCounter();
    } catch (Throwable $e) {
        PasskeyLoginService::audit('login_verify_fail', $customersId);
        PasskeyLoginService::debug(['event' => 'login_assertion_error', 'msg' => $e->getMessage()]);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
    }

    if (!PasskeyLoginService::updateSignCount($cred, is_int($newCount) ? $newCount : 0)) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.')]);
    }

    $failReason = null;
    $redirect = PasskeyLoginService::loginCustomer($customersId, $failReason);
    if ($redirect === null) {
        PasskeyLoginService::audit('login_verify_fail', $customersId);
        $err = ($failReason === 'banned')
            ? pkl_msg('PKL_ERROR_BANNED', 'This account is not allowed to sign in. Please contact the store if you need help.')
            : pkl_msg('PKL_ERROR_LOGIN_FAILED', 'We could not sign you in with that passkey. Please try another sign in option.');
        pkl_json_exit(['ok' => false, 'error' => $err]);
    }

    PasskeyLoginService::audit('login_verify_ok', $customersId);
    PasskeyLoginService::debug(['event' => 'login_ok', 'customers_id' => $customersId]);
    pkl_json_exit(['ok' => true, 'redirect' => $redirect]);
}

/* ---------------------------------------------------------------------- */
/* Everything below requires a real (non guest) customer session           */
/* ---------------------------------------------------------------------- */

$pkl_real_session = $pkl_available && PasskeyLoginService::isRealCustomerSession();

if ($isAjax && !$pkl_real_session) {
    pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_LOGIN_REQUIRED', 'Please sign in to manage your passkeys.')]);
}

if (!$isAjax && $pkl_available && !$pkl_real_session) {
    $_SESSION['navigation']->set_snapshot();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$pkl_customer_id = (int)($_SESSION['customer_id'] ?? 0);

if ($action === 'ajax_nudge_optout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_METHOD', 'Please reload the page and try again.')]);
    PasskeyLoginService::setNudgeOptOut($pkl_customer_id);
    pkl_json_exit(['ok' => true]);
}

if ($action === 'ajax_reg_options') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_METHOD', 'Please reload the page and try again.')]);
    if (PasskeyLoginService::countForCustomer($pkl_customer_id) >= PasskeyLoginService::maxKeysPerCustomer()) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_MAX_KEYS', 'You have reached the maximum number of passkeys for this account.')]);
    }
    if (!PasskeyLoginService::rateOk('reg_options')) pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_RATE', 'Too many passkey attempts from this connection. Please wait and try again, or use another sign in option.')]);
    PasskeyLoginService::audit('reg_options', $pkl_customer_id);
    try {
        $email = (string)($_SESSION['customers_email_address'] ?? '');
        $display = trim((string)($_SESSION['customer_first_name'] ?? '') . ' ' . (string)($_SESSION['customer_last_name'] ?? ''));
        $display = preg_replace('/[\x00-\x1F\x7F]/u', '', $display);
        if (!is_string($display) || trim($display) === '') $display = $email;
        $display = trim((string)$display);
        if (function_exists('mb_substr')) $display = mb_substr($display, 0, 64);
        else $display = substr($display, 0, 64);

        $exclude = [];
        foreach (PasskeyLoginService::credentialsForCustomer($pkl_customer_id) as $row) {
            $bin = PasskeyLoginService::b64urlDecode($row['credential_id']);
            if ($bin !== '') $exclude[] = $bin;
        }

        $webAuthn = PasskeyLoginService::webAuthn();
        $createArgs = $webAuthn->getCreateArgs((string)$pkl_customer_id, $email, $display, 120, true, true, null, $exclude);
        PasskeyLoginService::stashChallenge('reg', $webAuthn->getChallenge());
        PasskeyLoginService::debug(['event' => 'reg_options', 'customers_id' => $pkl_customer_id]);
        pkl_json_exit(['ok' => true, 'createArgs' => $createArgs]);
    } catch (Throwable $e) {
        PasskeyLoginService::debug(['event' => 'reg_options_error', 'msg' => $e->getMessage()]);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_SERVER', 'Passkey sign in is temporarily unavailable. Please use another sign in option.')]);
    }
}

if ($action === 'ajax_reg_verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_METHOD', 'Please reload the page and try again.')]);
    if (PasskeyLoginService::countForCustomer($pkl_customer_id) >= PasskeyLoginService::maxKeysPerCustomer()) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_MAX_KEYS', 'You have reached the maximum number of passkeys for this account.')]);
    }
    if (!PasskeyLoginService::rateOk('reg_verify')) {
        PasskeyLoginService::debug(['event' => 'rate_capped', 'endpoint' => 'reg_verify']);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_RATE', 'Too many passkey attempts from this connection. Please wait and try again, or use another sign in option.')]);
    }
    PasskeyLoginService::audit('reg_verify', $pkl_customer_id);

    $resp = pkl_json_post('attestation', 131072);
    if (!is_array($resp)) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }
    $clientData = pkl_required_b64($resp, 'clientDataJSON', 16384);
    $attestationObject = pkl_required_b64($resp, 'attestationObject', 131072);
    if ($clientData === false || $attestationObject === false) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }
    if (array_key_exists('transports', $resp) && !is_array($resp['transports'])) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }

    $challenge = PasskeyLoginService::takeChallenge('reg', $clientData);
    if ($challenge === null) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }
    try {
        $webAuthn = PasskeyLoginService::webAuthn();
        $data = $webAuthn->processCreate(
            $clientData,
            $attestationObject,
            $challenge,
            true,
            true,
            false
        );
    } catch (Throwable $e) {
        PasskeyLoginService::debug(['event' => 'reg_verify_error', 'customers_id' => $pkl_customer_id, 'msg' => $e->getMessage()]);
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }

    if (!is_string($data->credentialId) || $data->credentialId === '' || strlen($data->credentialId) > 1023) {
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }
    $credB64url = PasskeyLoginService::b64urlEncode($data->credentialId);
    $existing = PasskeyLoginService::findByCredentialId($credB64url);
    if ($existing !== null) {
        $msg = ((int)$existing['customers_id'] === $pkl_customer_id)
            ? pkl_msg('PKL_ERROR_ALREADY_REGISTERED', 'That passkey is already registered to your account.')
            : pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.');
        pkl_json_exit(['ok' => false, 'error' => $msg]);
    }

    $transports = '';
    if (!empty($resp['transports'])) {
        if (count($resp['transports']) > 16) pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
        $clean = [];
        foreach ($resp['transports'] as $t) {
            if (!is_string($t) || strlen($t) > 32) pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
            $clean[] = preg_replace('/[^a-z-]/', '', strtolower($t));
        }
        $transports = implode(',', array_filter($clean));
    }

    $label = PasskeyLoginService::labelForNewCredential($data->AAGUID ?? null, $transports);
    $inserted = PasskeyLoginService::insertCredential(
        $pkl_customer_id,
        $credB64url,
        $data->credentialPublicKey,
        is_int($data->signatureCounter) ? $data->signatureCounter : 0,
        $transports,
        $label
    );
    if (!$inserted) {
        $existing = PasskeyLoginService::findByCredentialId($credB64url);
        if ($existing !== null && (int)$existing['customers_id'] === $pkl_customer_id) {
            pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_ALREADY_REGISTERED', 'That passkey is already registered to your account.')]);
        }
        if (PasskeyLoginService::countForCustomer($pkl_customer_id) >= PasskeyLoginService::maxKeysPerCustomer()) {
            pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_MAX_KEYS', 'You have reached the maximum number of passkeys for this account.')]);
        }
        pkl_json_exit(['ok' => false, 'error' => pkl_msg('PKL_ERROR_REG_FAILED', 'We could not verify that passkey. Please try again.')]);
    }

    PasskeyLoginService::audit('reg_ok', $pkl_customer_id);
    PasskeyLoginService::debug(['event' => 'reg_ok', 'customers_id' => $pkl_customer_id]);
    pkl_json_exit(['ok' => true]);
}

/* ---------------------------------------------------------------------- */
/* Plain form actions (rename, remove)                                     */
/* ---------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove' && $pkl_available) {
    $passkeyId = (isset($_POST['passkey_id']) && is_scalar($_POST['passkey_id'])) ? (int)$_POST['passkey_id'] : 0;
    if ($passkeyId > 0) {
        if (PasskeyLoginService::deleteCredential($passkeyId, $pkl_customer_id)) {
            PasskeyLoginService::audit('removed', $pkl_customer_id);
            $messageStack->add_session('passkey_settings', pkl_msg('PKL_SUCCESS_REMOVED', 'Your passkey has been removed.'), 'success');
        }
    }
    zen_redirect(zen_href_link(FILENAME_PASSKEY_SETTINGS, '', 'SSL'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'rename' && $pkl_available) {
    $passkeyId = (isset($_POST['passkey_id']) && is_scalar($_POST['passkey_id'])) ? (int)$_POST['passkey_id'] : 0;
    $labelInput = $_POST['device_label'] ?? '';
    $label = is_string($labelInput) ? zen_db_prepare_input($labelInput) : '';
    if ($passkeyId > 0 && trim($label) !== '') {
        PasskeyLoginService::renameCredential($passkeyId, $pkl_customer_id, $label);
        $messageStack->add_session('passkey_settings', PKL_SUCCESS_RENAMED, 'success');
    }
    zen_redirect(zen_href_link(FILENAME_PASSKEY_SETTINGS, '', 'SSL'));
}

/* ---------------------------------------------------------------------- */
/* Page render                                                             */
/* ---------------------------------------------------------------------- */

$pkl_passkeys = $pkl_available ? PasskeyLoginService::credentialsForCustomer($pkl_customer_id) : [];
$pkl_max_reached = $pkl_available && (count($pkl_passkeys) >= PasskeyLoginService::maxKeysPerCustomer());
$pkl_token = $_SESSION['securityToken'] ?? '';

$breadcrumb->add(NAVBAR_TITLE);

$zco_notifier->notify('NOTIFY_HEADER_END_PASSKEY_SETTINGS');
