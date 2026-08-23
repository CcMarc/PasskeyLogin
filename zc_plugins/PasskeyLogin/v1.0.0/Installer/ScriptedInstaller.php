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
// Install and upgrade run the same unconditional idempotent helpers (no
// version-gated dispatch). Data tables are PRESERVED on uninstall so
// customers keep their passkeys across reinstall cycles; only published
// files, admin page registrations, and configuration keys are removed.
//
// Publishing: the passkey_settings page, its language file, and its
// template are copied into the live catalog tree. A failed copy never
// aborts the install (errorContainer aborts ALL plugin installs); one
// warning lists the exact files to copy manually.

// The ONLY version literals in this plugin live here and in the manifest.
// Everything that stamps a version (PKL_VERSION config value) reads this
// constant, never a literal.
if (!defined('PASSKEYLOGIN_CURRENT_VERSION')) define('PASSKEYLOGIN_CURRENT_VERSION', '1.0.0');
if (!defined('PASSKEYLOGIN_HOTFIX_LEVEL')) define('PASSKEYLOGIN_HOTFIX_LEVEL', 9);

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'Passkey Login';

    protected function executeInstall()
    {
        $this->defineTables();
        $this->ensureSchema();
        $this->cleanupOrphans();
        $this->insertConfigurationKeys();
        $this->registerAdminPages();
        $this->publishCatalogFiles();
        return true;
    }

    protected function executeUpgrade($oldVersion)
    {
        $this->defineTables();
        $this->ensureSchema();
        $this->cleanupOrphans();
        $this->insertConfigurationKeys();
        $this->registerAdminPages();
        $this->publishCatalogFiles();
        return true;
    }

    protected function executeUninstall()
    {
        $this->defineTables();
        $this->removePublishedFiles();

        if (function_exists('zen_deregister_admin_pages')) {
            zen_deregister_admin_pages(['pluginPasskeyLoginConsole', 'configPasskeyLogin']);
        }

        $gid = $this->getConfigGroupId(false);
        $this->dbConn->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'PKL\_%'");
        if ($gid !== null) {
            $this->dbConn->Execute("DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id = " . (int)$gid);
        }
        // Tables passkey_credentials, passkey_optout, and passkey_audit are
        // intentionally preserved: customers keep their passkeys across an
        // uninstall/reinstall cycle. Drop them manually only if you are
        // removing the plugin for good.
        return true;
    }

    /* ------------------------------------------------------------------ */

    protected function defineTables(): void
    {
        if (!defined('TABLE_PASSKEY_CREDENTIALS')) define('TABLE_PASSKEY_CREDENTIALS', DB_PREFIX . 'passkey_credentials');
        if (!defined('TABLE_PASSKEY_OPTOUT'))      define('TABLE_PASSKEY_OPTOUT', DB_PREFIX . 'passkey_optout');
        if (!defined('TABLE_PASSKEY_AUDIT'))       define('TABLE_PASSKEY_AUDIT', DB_PREFIX . 'passkey_audit');
        if (!defined('TABLE_PASSKEY_CHALLENGES')) define('TABLE_PASSKEY_CHALLENGES', DB_PREFIX . 'passkey_challenges');
    }

    protected function tableExists(string $table): bool
    {
        $r = $this->dbConn->Execute("SHOW TABLES LIKE '" . zen_db_input($table) . "'");
        return !$r->EOF;
    }

    protected function ensureSchema(): void
    {
        $this->dbConn->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PASSKEY_CREDENTIALS . " (
                passkey_id INT(11) NOT NULL AUTO_INCREMENT,
                customers_id INT(11) NOT NULL,
                credential_id VARCHAR(1364) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                public_key TEXT NOT NULL,
                sign_count INT UNSIGNED NOT NULL DEFAULT 0,
                transports VARCHAR(120) NOT NULL DEFAULT '',
                device_label VARCHAR(80) NOT NULL DEFAULT '',
                date_added DATETIME DEFAULT NULL,
                last_used DATETIME DEFAULT NULL,
                PRIMARY KEY (passkey_id),
                UNIQUE KEY idx_pkl_credential (credential_id),
                KEY idx_pkl_customer (customers_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        // Convert any pre-existing table to InnoDB BEFORE reconciling columns:
        // the row lock transactions require it, and MyISAM's 1000 byte index
        // key limit would reject the 1364 byte unique credential id widen if
        // the engine were converted afterwards.
        $this->ensureTableEngine(TABLE_PASSKEY_CREDENTIALS);
        $this->ensureTableEngine(TABLE_PASSKEY_OPTOUT);
        $this->ensureTableEngine(TABLE_PASSKEY_AUDIT);
        $this->ensureTableEngine(TABLE_PASSKEY_CHALLENGES);

        // Upgrade hf1's 255-character, database-collated credential id to
        // the full WebAuthn range (1023 raw bytes => 1364 base64url chars)
        // with byte-for-byte case-sensitive comparison semantics.
        $credentialColumn = $this->dbConn->Execute(
            "SHOW FULL COLUMNS FROM " . TABLE_PASSKEY_CREDENTIALS . " LIKE 'credential_id'"
        );
        if (!$credentialColumn->EOF) {
            $type = strtolower((string)($credentialColumn->fields['Type'] ?? ''));
            $collation = strtolower((string)($credentialColumn->fields['Collation'] ?? ''));
            if ($type !== 'varchar(1364)' || $collation !== 'ascii_bin') {
                $this->dbConn->Execute(
                    "ALTER TABLE " . TABLE_PASSKEY_CREDENTIALS . "
                     MODIFY credential_id VARCHAR(1364) CHARACTER SET ascii COLLATE ascii_bin NOT NULL"
                );
            }
        }
        $signCountColumn = $this->dbConn->Execute(
            "SHOW COLUMNS FROM " . TABLE_PASSKEY_CREDENTIALS . " LIKE 'sign_count'"
        );
        if (!$signCountColumn->EOF) {
            $type = strtolower((string)($signCountColumn->fields['Type'] ?? ''));
            if (strpos($type, 'unsigned') === false) {
                $this->dbConn->Execute(
                    "ALTER TABLE " . TABLE_PASSKEY_CREDENTIALS . "
                     MODIFY sign_count INT UNSIGNED NOT NULL DEFAULT 0"
                );
            }
        }

        $this->dbConn->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PASSKEY_OPTOUT . " (
                customers_id INT(11) NOT NULL,
                date_added DATETIME DEFAULT NULL,
                PRIMARY KEY (customers_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $this->dbConn->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PASSKEY_AUDIT . " (
                audit_id INT(11) NOT NULL AUTO_INCREMENT,
                event VARCHAR(40) NOT NULL DEFAULT '',
                customers_id INT(11) NOT NULL DEFAULT 0,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                date_added DATETIME DEFAULT NULL,
                PRIMARY KEY (audit_id),
                KEY idx_pkl_audit (event, ip_address, date_added)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $this->dbConn->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PASSKEY_CHALLENGES . " (
                challenge_id INT(11) NOT NULL AUTO_INCREMENT,
                session_id VARCHAR(128) NOT NULL DEFAULT '',
                purpose VARCHAR(10) NOT NULL DEFAULT '',
                challenge_hex VARCHAR(64) NOT NULL DEFAULT '',
                date_added DATETIME DEFAULT NULL,
                PRIMARY KEY (challenge_id),
                KEY idx_pkl_chal (session_id, purpose, date_added),
                KEY idx_pkl_chal_age (date_added)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

    }

    protected function ensureTableEngine(string $table): void
    {
        $r = $this->dbConn->Execute("SHOW TABLE STATUS LIKE '" . zen_db_input($table) . "'");
        if ($r->EOF) return;
        $engine = strtolower((string)($r->fields['Engine'] ?? ''));
        if ($engine !== '' && $engine !== 'innodb') {
            $this->dbConn->Execute("ALTER TABLE " . $table . " ENGINE=InnoDB");
        }
    }

    /**
     * Runs after ensureSchema on every install and upgrade so an interrupted
     * or partial schema is repaired before any sweep touches it. Each table
     * is still guarded independently to keep maintenance tolerant of damage.
     */
    protected function cleanupOrphans(): void
    {
        $hasCredentials = $this->tableExists(TABLE_PASSKEY_CREDENTIALS);
        $hasOptout = $this->tableExists(TABLE_PASSKEY_OPTOUT);
        $hasAudit = $this->tableExists(TABLE_PASSKEY_AUDIT);

        if ($hasCredentials) {
            $this->dbConn->Execute(
                "DELETE pc FROM " . TABLE_PASSKEY_CREDENTIALS . " pc
                 LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = pc.customers_id
                 WHERE c.customers_id IS NULL"
            );
        }
        if ($hasOptout) {
            $this->dbConn->Execute(
                "DELETE po FROM " . TABLE_PASSKEY_OPTOUT . " po
                 LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = po.customers_id
                 WHERE c.customers_id IS NULL"
            );
        }
        if (defined('CHECKOUT_ONE_GUEST_CUSTOMER_ID') && (int)CHECKOUT_ONE_GUEST_CUSTOMER_ID > 0) {
            $guestId = (int)CHECKOUT_ONE_GUEST_CUSTOMER_ID;
            if ($hasCredentials) $this->dbConn->Execute("DELETE FROM " . TABLE_PASSKEY_CREDENTIALS . " WHERE customers_id = " . $guestId);
            if ($hasOptout) $this->dbConn->Execute("DELETE FROM " . TABLE_PASSKEY_OPTOUT . " WHERE customers_id = " . $guestId);
        }
        if ($hasAudit) {
            $this->dbConn->Execute("DELETE FROM " . TABLE_PASSKEY_AUDIT . " WHERE date_added < DATE_SUB(now(), INTERVAL 90 DAY)");
        }
        if ($this->tableExists(TABLE_PASSKEY_CHALLENGES)) {
            $this->dbConn->Execute("DELETE FROM " . TABLE_PASSKEY_CHALLENGES . " WHERE date_added < DATE_SUB(now(), INTERVAL 1 HOUR)");
        }
    }

    /* ------------------------------------------------------------------ */

    protected function getConfigGroupId(bool $createIfMissing = true): ?int
    {
        $check = $this->dbConn->Execute(
            "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . "
             WHERE configuration_group_title = '" . zen_db_input($this->configGroupTitle) . "' LIMIT 1"
        );
        if (!$check->EOF) return (int)$check->fields['configuration_group_id'];
        if (!$createIfMissing) return null;
        $this->dbConn->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
             (configuration_group_title, configuration_group_description, sort_order, visible)
             VALUES ('" . zen_db_input($this->configGroupTitle) . "', 'Passkey (WebAuthn) sign in settings', 1, 1)"
        );
        $gid = (int)$this->dbConn->Insert_ID();
        $this->dbConn->Execute("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = configuration_group_id WHERE configuration_group_id = " . $gid);
        return $gid;
    }

    protected function insertConfigurationKeys(): void
    {
        $gid = $this->getConfigGroupId(true);

        $keys = [
            ['PKL_ENABLED', 'Enable Passkey Login', 'true', 'Master switch. When false the plugin contributes nothing to any page.', 10, "zen_cfg_select_option(array('true','false'),"],
            ['PKL_NUDGE_ENABLED', 'Show Enrollment Nudge', 'true', 'Show a one time banner on the My Account page inviting customers without a passkey to add one. Customers can dismiss it permanently.', 20, "zen_cfg_select_option(array('true','false'),"],
            ['PKL_MAX_KEYS_PER_CUSTOMER', 'Maximum Passkeys per Customer', '5', 'How many passkeys one account may hold. Typical customers register one or two devices.', 30, null],
            ['PKL_RATE_IP_HOUR', 'Hourly Rate Cap per IP', '120', 'Maximum passkey challenge requests per IP address per hour. Set 0 to disable the cap.', 40, null],
            ['PKL_RP_ID', 'Relying Party ID Override', '', 'Normally leave blank: the registrable domain is derived automatically, which also lets a staging subdomain share production passkeys. Set explicitly only for multi part TLDs such as .co.uk.', 50, null],
            ['PKL_RP_NAME', 'Relying Party Display Name', '', 'Shown by the browser in passkey prompts. Leave blank to use the store name.', 60, null],
            ['PKL_DEBUG_LOG', 'Debug Logging', 'false', 'When true, writes JSON lines to logs/passkey_login_debug.log. Leave off in normal operation.', 70, "zen_cfg_select_option(array('true','false'),"],
        ];

        foreach ($keys as [$key, $title, $value, $desc, $sort, $setFunc]) {
            $exists = $this->dbConn->Execute(
                "SELECT configuration_id FROM " . TABLE_CONFIGURATION . "
                 WHERE configuration_key = '" . zen_db_input($key) . "' LIMIT 1"
            );
            if (!$exists->EOF) {
                $this->dbConn->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_group_id = " . (int)$gid . " WHERE configuration_key = '" . zen_db_input($key) . "'");
                // hf4 default migration: hf1 through hf3 shipped a default cap
                // of 30 before the conditional UI re-arm raised per-tab usage.
                // Move a store still on the old default to the new one, but
                // never touch a value the admin customized to anything else.
                if ($key === 'PKL_RATE_IP_HOUR') {
                    $this->dbConn->Execute("UPDATE " . TABLE_CONFIGURATION . "
                                            SET configuration_value = '120'
                                            WHERE configuration_key = 'PKL_RATE_IP_HOUR'
                                            AND configuration_value = '30'");
                }
                continue;
            }
            $setFuncSql = ($setFunc === null) ? 'NULL' : "'" . zen_db_input($setFunc) . "'";
            $this->dbConn->Execute(
                "INSERT INTO " . TABLE_CONFIGURATION . "
                 (configuration_title, configuration_key, configuration_value, configuration_description,
                  configuration_group_id, sort_order, set_function, date_added)
                 VALUES ('" . zen_db_input($title) . "', '" . zen_db_input($key) . "', '" . zen_db_input($value) . "',
                         '" . zen_db_input($desc) . "', " . (int)$gid . ", " . (int)$sort . ", " . $setFuncSql . ", now())"
            );
        }

        // Version stamp: read only display key, always written from the
        // constant so the grep audit and the upgrade procedure both cover it.
        $vExists = $this->dbConn->Execute("SELECT configuration_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'PKL_VERSION' LIMIT 1");
        if ($vExists->EOF) {
            $this->dbConn->Execute(
                "INSERT INTO " . TABLE_CONFIGURATION . "
                 (configuration_title, configuration_key, configuration_value, configuration_description,
                  configuration_group_id, sort_order, set_function, date_added)
                 VALUES ('Passkey Login Version', 'PKL_VERSION', '" . zen_db_input(PASSKEYLOGIN_CURRENT_VERSION) . "',
                         'Installed plugin version. Managed automatically.', " . (int)$gid . ", 90,
                         'zen_cfg_read_only(', now())"
            );
        } else {
            $this->dbConn->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . zen_db_input(PASSKEYLOGIN_CURRENT_VERSION) . "' WHERE configuration_key = 'PKL_VERSION'");
        }
    }

    protected function registerAdminPages(): void
    {
        $gid = $this->getConfigGroupId(true);
        if (function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) {
            if (!zen_page_key_exists('pluginPasskeyLoginConsole')) {
                zen_register_admin_page('pluginPasskeyLoginConsole', 'BOX_PASSKEY_LOGIN', 'FILENAME_PASSKEY_LOGIN_CONSOLE', '', 'extras', 'Y', 100);
            }
            if (!zen_page_key_exists('configPasskeyLogin')) {
                zen_register_admin_page('configPasskeyLogin', 'BOX_PASSKEY_LOGIN', 'FILENAME_CONFIGURATION', 'gID=' . (int)$gid, 'configuration', 'Y', (int)$gid);
            }
        }
    }

    /* ------------------------------------------------------------------ */

    protected function activeTemplateDir(): string
    {
        $r = $this->dbConn->Execute("SELECT template_dir FROM " . TABLE_TEMPLATE_SELECT . " WHERE template_language = 0 LIMIT 1");
        if (!$r->EOF && trim((string)$r->fields['template_dir']) !== '') return trim($r->fields['template_dir']);
        return 'default';
    }

    protected function publishTargets(): array
    {
        $src = dirname(__DIR__) . '/publish';
        $cat = rtrim(DIR_FS_CATALOG, '/') . '/';
        $tpl = $this->activeTemplateDir();
        return [
            [$src . '/includes/modules/pages/passkey_settings/header_php.php',
             $cat . 'includes/modules/pages/passkey_settings/header_php.php'],
            [$src . '/includes/languages/english/lang.passkey_settings.php',
             $cat . 'includes/languages/english/lang.passkey_settings.php'],
            [$src . '/includes/templates/TEMPLATE_DIR/templates/tpl_passkey_settings_default.php',
             $cat . 'includes/templates/' . $tpl . '/templates/tpl_passkey_settings_default.php'],
        ];
    }

    protected function publishCatalogFiles(): void
    {
        global $messageStack;
        $failed = [];
        foreach ($this->publishTargets() as [$from, $to]) {
            $dir = dirname($to);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (!is_file($from)) { $failed[] = $to . ' (source missing in plugin package)'; continue; }
            if (!@copy($from, $to)) {
                $reason = !is_writable($dir) ? 'directory not writable by the web server user' : 'copy failed';
                $failed[] = $to . ' (' . $reason . ')';
            }
        }
        if (count($failed) > 0 && isset($messageStack)) {
            $messageStack->add_session(
                'Passkey Login installed, but ' . count($failed) . ' file(s) could not be published. Copy them manually from zc_plugins/PasskeyLogin/v' . PASSKEYLOGIN_CURRENT_VERSION . '/publish/ then reload: ' . implode(' | ', $failed),
                'caution'
            );
        }
    }

    protected function removePublishedFiles(): void
    {
        foreach ($this->publishTargets() as [$from, $to]) {
            if (is_file($to)) @unlink($to);
        }

        // The active template can change after installation. Remove this
        // plugin-specific published template from every template directory,
        // not just whichever template happens to be active at uninstall time.
        $catalog = rtrim(DIR_FS_CATALOG, '/') . '/';
        $matches = glob($catalog . 'includes/templates/*/templates/tpl_passkey_settings_default.php');
        if (is_array($matches)) {
            foreach ($matches as $file) {
                if (is_file($file)) @unlink($file);
            }
        }

        $pageDir = $catalog . 'includes/modules/pages/passkey_settings';
        if (is_dir($pageDir)) @rmdir($pageDir);
    }
}
