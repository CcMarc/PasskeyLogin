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
// Shared service layer used by the observer, the passkey_settings page,
// and the admin console. Wraps the vendored lbuchs/WebAuthn library.
//
if (!function_exists('pkl_is_available')) {
    /**
     * True when the plugin is installed, enabled, and its tables exist.
     * Safe to call from templates: guarded for the plugin-removed case.
     */
    function pkl_is_available(): bool
    {
        if (!class_exists('PasskeyLoginService')) return false;
        return PasskeyLoginService::enabled();
    }
}

class PasskeyLoginService
{
    protected static $libLoaded = false;
    protected static $tablesChecked = null;

    /* ------------------------------------------------------------------ */
    /* Availability                                                        */
    /* ------------------------------------------------------------------ */

    public static function enabled(): bool
    {
        if (!defined('PKL_ENABLED') || PKL_ENABLED !== 'true') return false;
        return self::tablesExist();
    }

    public static function tablesExist(): bool
    {
        global $db;
        if (self::$tablesChecked !== null) return self::$tablesChecked;
        if (!isset($db)
            || !defined('TABLE_PASSKEY_CREDENTIALS')
            || !defined('TABLE_PASSKEY_OPTOUT')
            || !defined('TABLE_PASSKEY_AUDIT')
            || !defined('TABLE_PASSKEY_CHALLENGES')) {
            return (self::$tablesChecked = false);
        }
        foreach ([TABLE_PASSKEY_CREDENTIALS, TABLE_PASSKEY_OPTOUT, TABLE_PASSKEY_AUDIT, TABLE_PASSKEY_CHALLENGES] as $table) {
            $r = $db->Execute("SHOW TABLES LIKE '" . zen_db_input($table) . "'");
            if ($r->RecordCount() <= 0) return (self::$tablesChecked = false);
        }
        return (self::$tablesChecked = true);
    }

    /* ------------------------------------------------------------------ */
    /* WebAuthn library                                                    */
    /* ------------------------------------------------------------------ */

    protected static function loadLib(): void
    {
        if (self::$libLoaded) return;
        $base = defined('PKL_PLUGIN_DIR') ? PKL_PLUGIN_DIR : dirname(__DIR__, 2);
        $src = $base . '/lib/WebAuthn/src/';
        spl_autoload_register(function ($class) use ($src) {
            $prefix = 'lbuchs\\WebAuthn\\';
            if (strpos($class, $prefix) !== 0) return;
            $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $file = $src . $rel;
            if (is_file($file)) require_once $file;
        });
        self::$libLoaded = true;
    }

    /**
     * Build a WebAuthn instance. Formats locked to 'none': the library then
     * requests attestation 'none' from the browser, which is the standard
     * for retail sites and avoids any authenticator fingerprinting.
     */
    public static function webAuthn(): \lbuchs\WebAuthn\WebAuthn
    {
        self::loadLib();
        $rpName = (defined('PKL_RP_NAME') && PKL_RP_NAME !== '') ? PKL_RP_NAME : (defined('STORE_NAME') ? STORE_NAME : 'Store');
        return new \lbuchs\WebAuthn\WebAuthn($rpName, self::rpId(), ['none']);
    }

    /**
     * Relying Party ID. Config override first; otherwise derive the
     * registrable domain from HTTP_SERVER by stripping one leading label
     * from hosts with three or more labels (www.example.com and
     * alpha.example.com both become example.com). Stores on multi part
     * TLDs such as .co.uk must set PKL_RP_ID explicitly.
     */
    public static function rpId(): string
    {
        if (defined('PKL_RP_ID') && trim((string)PKL_RP_ID) !== '') {
            $raw = trim((string)PKL_RP_ID);
            if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
                $host = parse_url($raw, PHP_URL_HOST);
                if (is_string($host) && $host !== '') return $host;
            }
            $raw = trim($raw, '/');
            if (strpos($raw, '/') !== false) $raw = explode('/', $raw, 2)[0];
            if ($raw !== '') return $raw;
        }
        $host = parse_url(HTTP_SERVER, PHP_URL_HOST);
        if (!is_string($host) || $host === '') return 'localhost';
        $labels = explode('.', $host);
        if (count($labels) > 2) $labels = array_slice($labels, 1);
        return implode('.', $labels);
    }

    /* ------------------------------------------------------------------ */
    /* Guest guards (OPC single shared guest account model)                */
    /* ------------------------------------------------------------------ */

    public static function guestCustomerId(): int
    {
        return defined('CHECKOUT_ONE_GUEST_CUSTOMER_ID') ? (int)CHECKOUT_ONE_GUEST_CUSTOMER_ID : -1;
    }

    /** True when the current session is an OPC guest checkout session. */
    public static function isGuestSession(): bool
    {
        if (!empty($_SESSION['is_guest_checkout'])) return true;
        $cid = (int)($_SESSION['customer_id'] ?? 0);
        if ($cid <= 0) return false;
        return self::isGuestCustomerId($cid);
    }

    /** True when the given customers_id is the shared OPC guest account. */
    public static function isGuestCustomerId(int $customersId): bool
    {
        if ($customersId <= 0) return true;
        $guestId = self::guestCustomerId();
        return ($guestId > 0 && $customersId === $guestId);
    }

    /** Logged in as a real (non guest) customer. */
    public static function isRealCustomerSession(): bool
    {
        $cid = (int)($_SESSION['customer_id'] ?? 0);
        return ($cid > 0 && !self::isGuestSession());
    }

    /* ------------------------------------------------------------------ */
    /* Challenges (DATABASE backed, single use, short lived)               */
    /* ------------------------------------------------------------------ */
    // Challenges deliberately do NOT live in the session: Zen Cart's
    // database session handler is last-write-wins with no locking, so any
    // overlapping request (tracker beacons, a second tab) can rewrite the
    // session from a stale snapshot and silently erase a stashed challenge
    // between the options call and the verify call. That exact race broke
    // registration on a live store. Rows in passkey_challenges are keyed
    // by session id and purpose, capped at five per pair, expire after
    // five minutes, and single use is enforced atomically by the DELETE
    // of the matched row.

    protected static function sessionKey(): string
    {
        $sid = session_id();
        if (!is_string($sid) || $sid === '') $sid = 'nosession';
        return substr($sid, 0, 128);
    }

    public static function stashChallenge(string $purpose, $challenge): void
    {
        global $db;
        $sid = self::sessionKey();
        $purpose = substr(preg_replace('/[^a-z]/', '', $purpose), 0, 10);

        // Expire old rows for this session/purpose, then cap the list at
        // five so normal multi-tab use works without unbounded growth.
        $db->Execute("DELETE FROM " . TABLE_PASSKEY_CHALLENGES . "
                      WHERE session_id = '" . zen_db_input($sid) . "'
                      AND purpose = '" . zen_db_input($purpose) . "'
                      AND date_added < DATE_SUB(now(), INTERVAL 300 SECOND)");
        $r = $db->Execute("SELECT challenge_id FROM " . TABLE_PASSKEY_CHALLENGES . "
                           WHERE session_id = '" . zen_db_input($sid) . "'
                           AND purpose = '" . zen_db_input($purpose) . "'
                           ORDER BY challenge_id ASC");
        $ids = [];
        while (!$r->EOF) { $ids[] = (int)$r->fields['challenge_id']; $r->MoveNext(); }
        while (count($ids) >= 5) {
            $oldest = array_shift($ids);
            $db->Execute("DELETE FROM " . TABLE_PASSKEY_CHALLENGES . " WHERE challenge_id = " . (int)$oldest);
        }

        $db->Execute("INSERT INTO " . TABLE_PASSKEY_CHALLENGES . "
                      (session_id, purpose, challenge_hex, date_added)
                      VALUES ('" . zen_db_input($sid) . "',
                              '" . zen_db_input($purpose) . "',
                              '" . zen_db_input(bin2hex($challenge->getBinaryString())) . "', now())");
    }

    /**
     * Returns one matching binary challenge and deletes only that row.
     * When clientDataJSON is supplied its embedded WebAuthn challenge
     * selects the correct outstanding ceremony, allowing multiple tabs.
     * Single use is atomic: the row DELETE decides the winner if two
     * requests race for the same challenge.
     */
    public static function takeChallenge(string $purpose, string $clientDataJson = ''): ?string
    {
        global $db;
        $sid = self::sessionKey();
        $purpose = substr(preg_replace('/[^a-z]/', '', $purpose), 0, 10);

        $wanted = null;
        if ($clientDataJson !== '') {
            $client = json_decode($clientDataJson, true);
            if (!is_array($client) || !isset($client['challenge']) || !is_string($client['challenge'])) {
                self::debug(['event' => 'challenge_clientdata_bad', 'purpose' => $purpose,
                    'json_ok' => is_array($client) ? 1 : 0,
                    'keys' => is_array($client) ? implode(',', array_slice(array_keys($client), 0, 8)) : '']);
                return null;
            }
            $wanted = self::b64urlDecode($client['challenge']);
            if ($wanted === '') {
                self::debug(['event' => 'challenge_decode_empty', 'purpose' => $purpose,
                    'len' => strlen($client['challenge'])]);
                return null;
            }
        }

        $r = $db->Execute("SELECT challenge_id, challenge_hex, date_added FROM " . TABLE_PASSKEY_CHALLENGES . "
                           WHERE session_id = '" . zen_db_input($sid) . "'
                           AND purpose = '" . zen_db_input($purpose) . "'
                           AND date_added > DATE_SUB(now(), INTERVAL 300 SECOND)
                           ORDER BY challenge_id ASC");
        $rows = [];
        while (!$r->EOF) { $rows[] = $r->fields; $r->MoveNext(); }

        if ($rows === []) {
            self::debug(['event' => 'challenge_none', 'purpose' => $purpose]);
            return null;
        }

        foreach ($rows as $row) {
            $bin = @hex2bin((string)$row['challenge_hex']);
            if ($bin === false || $bin === '') continue;
            if ($wanted !== null && !hash_equals($bin, $wanted)) continue;
            $db->Execute("DELETE FROM " . TABLE_PASSKEY_CHALLENGES . "
                          WHERE challenge_id = " . (int)$row['challenge_id'] . " LIMIT 1");
            if ((int)$db->affectedRows() > 0) {
                return $bin;
            }
            // Another request consumed this row first; keep looking.
        }

        self::debug(['event' => 'challenge_no_match', 'purpose' => $purpose,
            'outstanding' => count($rows),
            'wanted_len' => ($wanted === null ? -1 : strlen($wanted))]);
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Credential storage                                                  */
    /* ------------------------------------------------------------------ */

    public static function credentialsForCustomer(int $customersId): array
    {
        global $db;
        $rows = [];
        $r = $db->Execute("SELECT passkey_id, credential_id, device_label, transports, sign_count, date_added, last_used
                           FROM " . TABLE_PASSKEY_CREDENTIALS . "
                           WHERE customers_id = " . (int)$customersId . "
                           ORDER BY date_added ASC");
        while (!$r->EOF) {
            $rows[] = $r->fields;
            $r->MoveNext();
        }
        return $rows;
    }

    public static function countForCustomer(int $customersId): int
    {
        global $db;
        $r = $db->Execute("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_CREDENTIALS . "
                           WHERE customers_id = " . (int)$customersId);
        return (int)$r->fields['cnt'];
    }

    public static function maxKeysPerCustomer(): int
    {
        $v = defined('PKL_MAX_KEYS_PER_CUSTOMER') ? (int)PKL_MAX_KEYS_PER_CUSTOMER : 5;
        return ($v > 0) ? $v : 5;
    }

    public static function findByCredentialId(string $credentialIdB64url): ?array
    {
        global $db;
        if ($credentialIdB64url === '' || strlen($credentialIdB64url) > 1364) return null;
        $r = $db->Execute("SELECT * FROM " . TABLE_PASSKEY_CREDENTIALS . "
                           WHERE credential_id = '" . zen_db_input($credentialIdB64url) . "'
                           LIMIT 1");
        return $r->EOF ? null : $r->fields;
    }

    public static function insertCredential(int $customersId, string $credentialIdB64url, string $publicKeyPem, int $signCount, string $transports, string $label): bool
    {
        global $db;
        if (self::isGuestCustomerId($customersId) || $credentialIdB64url === '' || strlen($credentialIdB64url) > 1364) {
            self::debug(['event' => 'insert_precheck_fail', 'customers_id' => $customersId,
                'cred_len' => strlen($credentialIdB64url)]);
            return false;
        }

        // Serialize registrations for one customer so the configured maximum
        // remains a hard limit even when two authenticated sessions register
        // passkeys at the same time.
        $db->Execute('START TRANSACTION');
        $lock = $db->Execute("SELECT customers_id FROM " . TABLE_CUSTOMERS . "
                              WHERE customers_id = " . (int)$customersId . " FOR UPDATE");
        if ($lock->EOF) {
            $db->Execute('ROLLBACK');
            self::debug(['event' => 'insert_lock_no_customer_row', 'customers_id' => $customersId]);
            return false;
        }
        if (self::countForCustomer($customersId) >= self::maxKeysPerCustomer()) {
            $db->Execute('ROLLBACK');
            self::debug(['event' => 'insert_max_reached', 'customers_id' => $customersId]);
            return false;
        }
        if (self::findByCredentialId($credentialIdB64url) !== null) {
            $db->Execute('ROLLBACK');
            self::debug(['event' => 'insert_duplicate', 'customers_id' => $customersId]);
            return false;
        }

        $db->Execute("INSERT IGNORE INTO " . TABLE_PASSKEY_CREDENTIALS . "
                      (customers_id, credential_id, public_key, sign_count, transports, device_label, date_added, last_used)
                      VALUES (" . (int)$customersId . ",
                              '" . zen_db_input($credentialIdB64url) . "',
                              '" . zen_db_input($publicKeyPem) . "',
                              " . max(0, (int)$signCount) . ",
                              '" . zen_db_input(substr($transports, 0, 120)) . "',
                              '" . zen_db_input(substr($label, 0, 80)) . "',
                              now(), NULL)");
        // Confirm the insert via affectedRows, NEVER via a repeated SELECT:
        // Zen Cart's QueryCache memoizes SELECT results per request by the
        // exact SQL string and is blind to writes, so re-running the same
        // findByCredentialId query here returns the stale pre-insert empty
        // result and reports the row missing. That stale read made this
        // method roll back its own successful insert and broke every
        // registration on a live store. affectedRows is authoritative for the
        // INSERT and does not touch the cache. INSERT IGNORE affecting zero
        // rows can only mean a concurrent duplicate won the unique index.
        if ((int)$db->affectedRows() < 1) {
            $db->Execute('ROLLBACK');
            self::debug(['event' => 'insert_ignored', 'customers_id' => $customersId]);
            return false;
        }
        $db->Execute('COMMIT');
        return true;
    }

    public static function deleteCredential(int $passkeyId, int $customersId): bool
    {
        global $db;
        if ($passkeyId <= 0 || $customersId <= 0) return false;
        $db->Execute("DELETE FROM " . TABLE_PASSKEY_CREDENTIALS . "
                      WHERE passkey_id = " . (int)$passkeyId . "
                      AND customers_id = " . (int)$customersId . "
                      LIMIT 1");
        return ((int)$db->affectedRows() > 0);
    }

    public static function renameCredential(int $passkeyId, int $customersId, string $label): void
    {
        global $db;
        $label = trim($label);
        if ($label === '') return;
        $db->Execute("UPDATE " . TABLE_PASSKEY_CREDENTIALS . "
                      SET device_label = '" . zen_db_input(substr($label, 0, 80)) . "'
                      WHERE passkey_id = " . (int)$passkeyId . "
                      AND customers_id = " . (int)$customersId . "
                      LIMIT 1");
    }

    /**
     * Signature counter handling. Many passkey providers (iCloud Keychain
     * among them) always report 0, so the clone check only applies when the
     * stored counter has actually been increasing. Returns false when the
     * assertion must be rejected as a possible cloned authenticator.
     */
    public static function updateSignCount(array $credRow, int $newCount): bool
    {
        global $db;
        $passkeyId = (int)($credRow['passkey_id'] ?? 0);
        $customersId = (int)($credRow['customers_id'] ?? 0);
        if ($passkeyId <= 0 || $customersId <= 0) return false;
        $newCount = max(0, $newCount);

        // Lock and re-read the current counter so concurrent assertions can
        // never move the persisted counter backwards.
        $db->Execute('START TRANSACTION');
        $current = $db->Execute("SELECT sign_count FROM " . TABLE_PASSKEY_CREDENTIALS . "
                                 WHERE passkey_id = " . $passkeyId . "
                                 AND customers_id = " . $customersId . " FOR UPDATE");
        if ($current->EOF) {
            $db->Execute('ROLLBACK');
            return false;
        }
        $stored = (int)$current->fields['sign_count'];
        if ($stored > 0 && $newCount <= $stored) {
            $db->Execute('ROLLBACK');
            self::audit('clone_warning', $customersId);
            self::debug(['event' => 'clone_warning', 'passkey_id' => $passkeyId, 'stored' => $stored, 'reported' => $newCount]);
            return false;
        }
        $keep = max($stored, $newCount);
        $db->Execute("UPDATE " . TABLE_PASSKEY_CREDENTIALS . "
                      SET sign_count = " . $keep . ", last_used = now()
                      WHERE passkey_id = " . $passkeyId . "
                      AND customers_id = " . $customersId . " LIMIT 1");
        $db->Execute('COMMIT');
        return true;
    }

    /**
     * Friendly default label for a newly added passkey. Best case the
     * AAGUID resolves to the provider name from the community registry
     * ("Apple Passwords", "Google Password Manager"). Otherwise fall back
     * to what the transports imply, then to the generic dated prefix.
     * Naming help only, never a security signal.
     */
    public static function labelForNewCredential($aaguid, string $transports): string
    {
        $when = date('M Y');

        $bin = '';
        if (is_object($aaguid) && method_exists($aaguid, 'getBinaryString')) {
            $bin = (string)$aaguid->getBinaryString();
        } elseif (is_string($aaguid)) {
            $bin = $aaguid;
        }
        if (strlen($bin) === 16) {
            $hex = bin2hex($bin);
            $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
            if ($uuid !== '00000000-0000-0000-0000-000000000000') {
                $base = defined('PKL_PLUGIN_DIR') ? PKL_PLUGIN_DIR : dirname(__DIR__, 2);
                $mapFile = $base . '/includes/classes/pkl_aaguid_map.php';
                static $map = null;
                if ($map === null) $map = is_file($mapFile) ? (require $mapFile) : [];
                if (is_array($map) && !empty($map[$uuid])) {
                    return $map[$uuid] . ' (' . $when . ')';
                }
            }
        }

        $t = ',' . strtolower($transports) . ',';
        if (strpos($t, ',hybrid,') !== false) {
            return (defined('PKL_LABEL_DEVICE_PHONE') ? PKL_LABEL_DEVICE_PHONE : 'Phone or tablet') . ' (' . $when . ')';
        }
        if (strpos($t, ',usb,') !== false || strpos($t, ',nfc,') !== false || strpos($t, ',ble,') !== false) {
            return (defined('PKL_LABEL_DEVICE_KEY') ? PKL_LABEL_DEVICE_KEY : 'Security key') . ' (' . $when . ')';
        }
        if (strpos($t, ',internal,') !== false) {
            return (defined('PKL_LABEL_DEVICE_THIS') ? PKL_LABEL_DEVICE_THIS : 'This device') . ' (' . $when . ')';
        }
        return (defined('PKL_DEFAULT_LABEL_PREFIX') ? PKL_DEFAULT_LABEL_PREFIX : 'Passkey added') . ' ' . $when;
    }

    /* ------------------------------------------------------------------ */
    /* Nudge opt out                                                       */
    /* ------------------------------------------------------------------ */

    public static function nudgeOptedOut(int $customersId): bool
    {
        global $db;
        $r = $db->Execute("SELECT customers_id FROM " . TABLE_PASSKEY_OPTOUT . "
                           WHERE customers_id = " . (int)$customersId . " LIMIT 1");
        return !$r->EOF;
    }

    public static function setNudgeOptOut(int $customersId): void
    {
        global $db;
        if ($customersId <= 0 || self::isGuestCustomerId($customersId)) return;
        $db->Execute("INSERT IGNORE INTO " . TABLE_PASSKEY_OPTOUT . " (customers_id, date_added)
                      VALUES (" . (int)$customersId . ", now())");
    }

    /* ------------------------------------------------------------------ */
    /* Audit and rate limiting                                             */
    /* ------------------------------------------------------------------ */

    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr(preg_replace('/[^0-9a-fA-F:.]/', '', $ip), 0, 45);
    }

    public static function audit(string $event, int $customersId = 0): void
    {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_PASSKEY_AUDIT . " (event, customers_id, ip_address, date_added)
                      VALUES ('" . zen_db_input(substr($event, 0, 40)) . "',
                              " . (int)$customersId . ",
                              '" . zen_db_input(self::clientIp()) . "', now())");
    }

    /** True when this IP is still under the hourly cap for the given event. */
    public static function rateOk(string $event): bool
    {
        global $db;
        $limit = defined('PKL_RATE_IP_HOUR') ? (int)PKL_RATE_IP_HOUR : 120;
        if ($limit <= 0) return true;
        $r = $db->Execute("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_AUDIT . "
                           WHERE event = '" . zen_db_input($event) . "'
                           AND ip_address = '" . zen_db_input(self::clientIp()) . "'
                           AND date_added > DATE_SUB(now(), INTERVAL 1 HOUR)");
        return ((int)$r->fields['cnt'] < $limit);
    }

    /* ------------------------------------------------------------------ */
    /* Login                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Sign the customer in through Zen Cart's Customer::login() so the
     * passkey path receives the same account maintenance, session values,
     * cart restoration, and authorization data as password login.
     * Returns the redirect URL. Refuses the shared guest and banned accounts.
     */
    public static function loginCustomer(int $customersId, ?string &$failReason = null): ?string
    {
        global $messageStack, $zco_notifier;

        if (self::isGuestCustomerId($customersId) || !class_exists('Customer')) {
            $failReason = 'guest';
            return null;
        }

        $customer = new Customer($customersId);
        if ((int)$customer->getData('customers_authorization') === 4) {
            if (isset($zco_notifier)) $zco_notifier->notify('NOTIFY_LOGIN_BANNED');
            $failReason = 'banned';
            return null;
        }

        $basketBefore = 0;
        if (defined('SHOW_SHOPPING_CART_COMBINED') && (int)SHOW_SHOPPING_CART_COMBINED > 0
            && isset($_SESSION['cart']) && is_object($_SESSION['cart'])) {
            $basketBefore = (int)$_SESSION['cart']->count_contents();
        }

        // OPC can reach the login page while the shared guest account is in
        // session. Clear only the guest-state marker; Customer::login replaces
        // the customer session values and merges/restores the cart itself.
        unset($_SESSION['is_guest_checkout']);
        if (!$customer->login($customersId, true)) {
            $failReason = 'login';
            return null;
        }

        if (defined('SESSION_RECREATE') && SESSION_RECREATE === 'True' && function_exists('zen_session_recreate')) {
            zen_session_recreate();
        }
        unset($_SESSION['pkl_challenge_login'], $_SESSION['pkl_challenge_reg']);
        if (isset($zco_notifier)) $zco_notifier->notify('NOTIFY_LOGIN_SUCCESS');

        $basketAfter = (isset($_SESSION['cart']) && is_object($_SESSION['cart']))
            ? (int)$_SESSION['cart']->count_contents() : 0;
        if (defined('SHOW_SHOPPING_CART_COMBINED') && (int)SHOW_SHOPPING_CART_COMBINED > 0
            && $basketAfter > 0 && $basketBefore !== $basketAfter && isset($messageStack)) {
            if ((int)SHOW_SHOPPING_CART_COMBINED === 2) {
                $messageStack->add_session('header', WARNING_SHOPPING_CART_COMBINED, 'caution');
            } elseif ((int)SHOW_SHOPPING_CART_COMBINED === 1) {
                $snapshotGet = $_SESSION['navigation']->snapshot['get'] ?? [];
                $hasGvNo = isset($_GET['gv_no']) || (is_array($snapshotGet) && isset($snapshotGet['gv_no']));
                if (!$hasGvNo) {
                    $messageStack->add_session('shopping_cart', WARNING_SHOPPING_CART_COMBINED, 'caution');
                    return zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL');
                }
                $messageStack->add_session('header', WARNING_SHOPPING_CART_COMBINED, 'caution');
            }
        }

        if ($customer->getData('activation_required')) {
            if (isset($_SESSION['navigation']) && is_object($_SESSION['navigation'])) {
                $_SESSION['navigation']->clear_snapshot();
            }
            return zen_href_link(CUSTOMERS_AUTHORIZATION_FILENAME, '', 'SSL');
        }

        if (isset($_SESSION['navigation']) && is_object($_SESSION['navigation'])
            && !empty($_SESSION['navigation']->snapshot['page'])
            && is_array($_SESSION['navigation']->snapshot['get'] ?? [])
            && function_exists('zen_array_to_string')) {
            $snapshot = $_SESSION['navigation']->snapshot;
            $url = zen_href_link(
                $snapshot['page'],
                zen_array_to_string($snapshot['get'], [zen_session_name()]),
                $snapshot['mode'] ?? 'SSL'
            );
            $_SESSION['navigation']->clear_snapshot();
            return $url;
        }
        return zen_href_link(FILENAME_ACCOUNT, '', 'SSL');
    }

    /* ------------------------------------------------------------------ */
    /* Debug log (JSON lines, ASCII only, QuickCart convention)            */
    /* ------------------------------------------------------------------ */

    public static function debug(array $data): void
    {
        if (!defined('PKL_DEBUG_LOG') || PKL_DEBUG_LOG !== 'true') return;
        $data = ['ts' => date('Y-m-d H:i:s')] + $data;
        $line = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($line === false) return;
        $line = preg_replace('/[^\x20-\x7E]/', '?', $line);
        $dir = defined('DIR_FS_LOGS') ? DIR_FS_LOGS : (defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'logs' : sys_get_temp_dir());
        @file_put_contents(rtrim($dir, '/') . '/passkey_login_debug.log', $line . "\n", FILE_APPEND);
    }

    /* ------------------------------------------------------------------ */
    /* Encoding helpers                                                    */
    /* ------------------------------------------------------------------ */

    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad > 0) $s .= str_repeat('=', 4 - $pad);
        $out = base64_decode(strtr($s, '-_', '+/'), true);
        return ($out === false) ? '' : $out;
    }
}
