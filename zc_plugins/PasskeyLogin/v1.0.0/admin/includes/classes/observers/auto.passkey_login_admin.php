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
// When a customer record is deleted, removes their passkey credential,
// opt-out, and audit rows. Attached to both the legacy and current delete
// notifier names because different admin code paths fire different ones;
// the handler is idempotent so double firing is harmless.
//
class zcObserverPasskeyLoginAdmin extends base
{
    public function __construct()
    {
        $this->attach($this, [
            'NOTIFIER_ADMIN_ZEN_CUSTOMERS_DELETE_CONFIRM',
            'NOTIFY_CUSTOMER_AFTER_RECORD_DELETED',
        ]);
    }

    public function update(&$class, $eventID, $p1 = null, &$p2 = null, &$p3 = null, &$p4 = null, &$p5 = null)
    {
        $customersId = 0;
        if (is_array($p1)) {
            $customersId = (int)($p1['customers_id'] ?? $p1['customer_id'] ?? $p1[0] ?? 0);
        } elseif (is_numeric($p1)) {
            $customersId = (int)$p1;
        }
        if ($customersId <= 0) return;

        global $db;
        $tables = [
            'TABLE_PASSKEY_CREDENTIALS',
            'TABLE_PASSKEY_OPTOUT',
            'TABLE_PASSKEY_AUDIT',
        ];
        foreach ($tables as $constant) {
            if (!defined($constant)) continue;
            $table = constant($constant);
            $exists = $db->Execute("SHOW TABLES LIKE '" . zen_db_input($table) . "'");
            if ($exists->EOF) continue;
            $db->Execute("DELETE FROM " . $table . " WHERE customers_id = " . $customersId);
        }
    }
}
