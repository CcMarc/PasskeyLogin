<?php
/**
 * Module: PasskeyLogin
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 8.0+ with OpenSSL
 * @author      Marcopolo
 * @copyright   2026
 * @license     GNU General Public License (GPL) - https://www.zen-cart.com/license/2_0.txt
 * @version     1.0.1
 * @updated     08-27-2026
 * @github      https://github.com/CcMarc/PasskeyLogin
 */
// Admin console (Extras menu): status overview, customer passkey lookup
// with support removal (the "lost my phone" case), recent activity from
// the audit table, debug log tail, and a maintenance sweep. Requires are
// resolved relative to this file so the page keeps working wherever the
// plugin version folder lands.
//
require 'includes/application_top.php';

if (!defined('PKL_PLUGIN_DIR')) define('PKL_PLUGIN_DIR', dirname(__DIR__) . '/catalog');
require_once PKL_PLUGIN_DIR . '/includes/classes/PasskeyLoginService.php';

$action = (isset($_GET['action']) && is_string($_GET['action'])) ? $_GET['action'] : '';
$emailInput = $_GET['email'] ?? '';
$lookupEmail = is_string($emailInput) ? trim(substr($emailInput, 0, 254)) : '';

$tableExists = static function (string $table) use ($db): bool {
    $r = $db->Execute("SHOW TABLES LIKE '" . zen_db_input($table) . "'");
    return !$r->EOF;
};
$hasCredentials = defined('TABLE_PASSKEY_CREDENTIALS') && $tableExists(TABLE_PASSKEY_CREDENTIALS);
$hasOptout = defined('TABLE_PASSKEY_OPTOUT') && $tableExists(TABLE_PASSKEY_OPTOUT);
$hasAudit = defined('TABLE_PASSKEY_AUDIT') && $tableExists(TABLE_PASSKEY_AUDIT);

/* ---------------------------------------------------------------------- */
/* Actions                                                                 */
/* ---------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove_key') {
    $passkeyId = (isset($_POST['passkey_id']) && is_scalar($_POST['passkey_id'])) ? (int)$_POST['passkey_id'] : 0;
    $ownerId = (isset($_POST['customers_id']) && is_scalar($_POST['customers_id'])) ? (int)$_POST['customers_id'] : 0;
    if ($hasCredentials && $passkeyId > 0 && $ownerId > 0) {
        if (PasskeyLoginService::deleteCredential($passkeyId, $ownerId)) {
            if ($hasAudit) PasskeyLoginService::audit('admin_removed', $ownerId);
            $messageStack->add_session(sprintf(PKL_CON_REMOVE_DONE, (int)$ownerId), 'success');
        }
    }
    zen_redirect(zen_href_link(FILENAME_PASSKEY_LOGIN_CONSOLE, ($lookupEmail !== '' ? 'email=' . urlencode($lookupEmail) : '')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sweep') {
    if ($hasCredentials) {
        $db->Execute("DELETE pc FROM " . TABLE_PASSKEY_CREDENTIALS . " pc
                      LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = pc.customers_id
                      WHERE c.customers_id IS NULL");
    }
    if ($hasOptout) {
        $db->Execute("DELETE po FROM " . TABLE_PASSKEY_OPTOUT . " po
                      LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = po.customers_id
                      WHERE c.customers_id IS NULL");
    }
    if (defined('CHECKOUT_ONE_GUEST_CUSTOMER_ID') && (int)CHECKOUT_ONE_GUEST_CUSTOMER_ID > 0) {
        if ($hasCredentials) $db->Execute("DELETE FROM " . TABLE_PASSKEY_CREDENTIALS . " WHERE customers_id = " . (int)CHECKOUT_ONE_GUEST_CUSTOMER_ID);
        if ($hasOptout) $db->Execute("DELETE FROM " . TABLE_PASSKEY_OPTOUT . " WHERE customers_id = " . (int)CHECKOUT_ONE_GUEST_CUSTOMER_ID);
    }
    if ($hasAudit) {
        $db->Execute("DELETE FROM " . TABLE_PASSKEY_AUDIT . " WHERE date_added < DATE_SUB(now(), INTERVAL 90 DAY)");
    }
    $messageStack->add_session(PKL_CON_SWEEP_DONE, 'success');
    zen_redirect(zen_href_link(FILENAME_PASSKEY_LOGIN_CONSOLE));
}

/* ---------------------------------------------------------------------- */
/* Data                                                                    */
/* ---------------------------------------------------------------------- */

$pklEnabled = (defined('PKL_ENABLED') && PKL_ENABLED === 'true' && PasskeyLoginService::tablesExist());
$rpId = class_exists('PasskeyLoginService') ? PasskeyLoginService::rpId() : '(unavailable)';

$stat = static function (string $sql) use ($db): int {
    $r = $db->Execute($sql);
    return $r->EOF ? 0 : (int)$r->fields['cnt'];
};
$totalKeys = $hasCredentials ? $stat("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_CREDENTIALS) : 0;
$enrolledCustomers = $hasCredentials ? $stat("SELECT COUNT(DISTINCT customers_id) AS cnt FROM " . TABLE_PASSKEY_CREDENTIALS) : 0;
$logins30 = $hasAudit ? $stat("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_AUDIT . " WHERE event = 'login_verify_ok' AND date_added > DATE_SUB(now(), INTERVAL 30 DAY)") : 0;
$fails30 = $hasAudit ? $stat("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_AUDIT . " WHERE event = 'login_verify_fail' AND date_added > DATE_SUB(now(), INTERVAL 30 DAY)") : 0;
$clones30 = $hasAudit ? $stat("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_AUDIT . " WHERE event = 'clone_warning' AND date_added > DATE_SUB(now(), INTERVAL 30 DAY)") : 0;
$optouts = $hasOptout ? $stat("SELECT COUNT(*) AS cnt FROM " . TABLE_PASSKEY_OPTOUT) : 0;

$lookupCustomer = null;
$lookupKeys = [];
if ($lookupEmail !== '') {
    $r = $db->Execute("SELECT customers_id, customers_firstname, customers_lastname, customers_email_address
                       FROM " . TABLE_CUSTOMERS . "
                       WHERE customers_email_address = '" . zen_db_input($lookupEmail) . "' LIMIT 1");
    if (!$r->EOF) {
        $lookupCustomer = $r->fields;
        if ($hasCredentials) {
            $k = $db->Execute("SELECT passkey_id, device_label, transports, sign_count, date_added, last_used
                               FROM " . TABLE_PASSKEY_CREDENTIALS . "
                               WHERE customers_id = " . (int)$r->fields['customers_id'] . "
                               ORDER BY date_added ASC");
            while (!$k->EOF) { $lookupKeys[] = $k->fields; $k->MoveNext(); }
        }
    }
}

$recent = [];
if ($hasAudit) {
    $r = $db->Execute("SELECT event, customers_id, ip_address, date_added FROM " . TABLE_PASSKEY_AUDIT . " ORDER BY audit_id DESC LIMIT 25");
    while (!$r->EOF) { $recent[] = $r->fields; $r->MoveNext(); }
}

$debugTail = [];
$debugFile = (defined('DIR_FS_LOGS') ? rtrim(DIR_FS_LOGS, '/') : rtrim(DIR_FS_CATALOG, '/') . '/logs') . '/passkey_login_debug.log';
if (is_file($debugFile)) {
    $size = @filesize($debugFile);
    $fh = ($size !== false && $size > 0) ? @fopen($debugFile, 'rb') : false;
    if ($fh) {
        $max = 32768;
        if ($size > $max) @fseek($fh, -1 * $max, SEEK_END);
        $chunk = stream_get_contents($fh);
        fclose($fh);
        if (is_string($chunk) && $chunk !== '') {
            $lines = explode("\n", str_replace("\r", '', $chunk));
            if ($size > $max) array_shift($lines);
            $kept = [];
            foreach ($lines as $ln) {
                if ($ln !== '') $kept[] = $ln;
            }
            $debugTail = array_slice($kept, -40);
        }
    }
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <title><?php echo BOX_PASSKEY_LOGIN; ?></title>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid" style="max-width:1200px;">
    <h1 style="margin:14px 0;"><?php echo BOX_PASSKEY_LOGIN; ?> <small>v<?php echo PKL_VERSION ?? '?'; ?></small></h1>
    <?php if ($messageStack->size > 0) echo $messageStack->output(); ?>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong><?php echo PKL_CON_STATUS; ?></strong></div>
                <div class="panel-body">
                    <p><?php echo PKL_CON_STATE_PREFIX; ?> <strong><?php echo $pklEnabled ? PKL_CON_ENABLED : PKL_CON_DISABLED; ?></strong>.
                       <?php echo PKL_CON_RP_ID; ?> <code><?php echo htmlspecialchars($rpId, ENT_QUOTES); ?></code></p>
                    <table class="table table-condensed" style="margin-bottom:0;">
                        <tr><td><?php echo PKL_CON_ENROLLED; ?></td><td><strong><?php echo $enrolledCustomers; ?></strong></td></tr>
                        <tr><td><?php echo PKL_CON_TOTAL_KEYS; ?></td><td><strong><?php echo $totalKeys; ?></strong></td></tr>
                        <tr><td><?php echo PKL_CON_LOGINS_30; ?></td><td><strong><?php echo $logins30; ?></strong></td></tr>
                        <tr><td><?php echo PKL_CON_FAILS_30; ?></td><td><strong><?php echo $fails30; ?></strong></td></tr>
                        <tr><td><?php echo PKL_CON_CLONES_30; ?></td><td><strong><?php echo $clones30; ?></strong></td></tr>
                        <tr><td><?php echo PKL_CON_OPTOUTS; ?></td><td><strong><?php echo $optouts; ?></strong></td></tr>
                    </table>
                    <?php
                    // Link straight to the plugin's configuration group by gID.
                    // (v1.0.0 used a nonexistent action=locate URL, which fell
                    // through to the configuration page while core's
                    // init_templates.php warned about the missing gID on PHP 8.)
                    $pklSettingsGid = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'PKL_ENABLED' LIMIT 1");
                    if (!$pklSettingsGid->EOF) {
                    ?>
                    <p style="margin-top:10px;"><a href="<?php echo zen_href_link(FILENAME_CONFIGURATION, 'gID=' . (int)$pklSettingsGid->fields['configuration_group_id']); ?>"><?php echo PKL_CON_OPEN_SETTINGS; ?></a></p>
                    <?php } ?>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><strong><?php echo PKL_CON_MAINTENANCE; ?></strong></div>
                <div class="panel-body">
                    <p style="margin-bottom:8px;"><?php echo PKL_CON_SWEEP_TEXT; ?></p>
                    <?php echo zen_draw_form('pkl_sweep', FILENAME_PASSKEY_LOGIN_CONSOLE, 'action=sweep', 'post'); ?>
                        <button type="submit" class="btn btn-default"><?php echo PKL_CON_SWEEP_BUTTON; ?></button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong><?php echo PKL_CON_LOOKUP; ?></strong></div>
                <div class="panel-body">
                    <?php echo zen_draw_form('pkl_lookup', FILENAME_PASSKEY_LOGIN_CONSOLE, '', 'get'); ?>
                        <div class="input-group" style="max-width:420px;">
                            <input type="text" name="email" class="form-control" placeholder="<?php echo htmlspecialchars(PKL_CON_LOOKUP_PLACEHOLDER, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($lookupEmail, ENT_QUOTES); ?>">
                            <span class="input-group-btn"><button type="submit" class="btn btn-default"><?php echo PKL_CON_LOOKUP_BUTTON; ?></button></span>
                        </div>
                    </form>
                    <?php if ($lookupEmail !== '' && $lookupCustomer === null) { ?>
                        <p style="margin-top:10px;"><?php echo PKL_CON_LOOKUP_NONE; ?></p>
                    <?php } elseif ($lookupCustomer !== null) { ?>
                        <p style="margin-top:12px;"><strong><?php echo htmlspecialchars($lookupCustomer['customers_firstname'] . ' ' . $lookupCustomer['customers_lastname'], ENT_QUOTES); ?></strong>
                        (id <?php echo (int)$lookupCustomer['customers_id']; ?>)</p>
                        <?php if (count($lookupKeys) === 0) { ?>
                            <p><?php echo PKL_CON_LOOKUP_NO_KEYS; ?></p>
                        <?php } else { ?>
                            <table class="table table-condensed">
                                <tr><th><?php echo PKL_CON_TH_LABEL; ?></th><th><?php echo PKL_CON_TH_ADDED; ?></th><th><?php echo PKL_CON_TH_LAST_USED; ?></th><th></th></tr>
                                <?php foreach ($lookupKeys as $k) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($k['device_label'], ENT_QUOTES); ?><br><small><?php echo htmlspecialchars($k['transports'], ENT_QUOTES); ?></small></td>
                                    <td><?php echo htmlspecialchars((string)$k['date_added'], ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars((string)($k['last_used'] ?? PKL_CON_NEVER), ENT_QUOTES); ?></td>
                                    <td>
                                        <?php echo zen_draw_form('pkl_rm_' . (int)$k['passkey_id'], FILENAME_PASSKEY_LOGIN_CONSOLE, 'action=remove_key&email=' . urlencode($lookupEmail), 'post'); ?>
                                            <input type="hidden" name="passkey_id" value="<?php echo (int)$k['passkey_id']; ?>">
                                            <input type="hidden" name="customers_id" value="<?php echo (int)$lookupCustomer['customers_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-xs" onclick="return window.confirm(<?php echo htmlspecialchars(json_encode(PKL_CON_REMOVE_CONFIRM), ENT_QUOTES); ?>);"><?php echo PKL_CON_REMOVE_BUTTON; ?></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php } ?>
                            </table>
                            <p><small><?php echo PKL_CON_LOST_DEVICE_NOTE; ?></small></p>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong><?php echo PKL_CON_RECENT; ?></strong></div>
                <div class="panel-body" style="max-height:340px;overflow:auto;">
                    <?php if (count($recent) === 0) { ?>
                        <p><?php echo PKL_CON_RECENT_NONE; ?></p>
                    <?php } else { ?>
                        <table class="table table-condensed" style="margin-bottom:0;">
                            <tr><th><?php echo PKL_CON_TH_WHEN; ?></th><th><?php echo PKL_CON_TH_EVENT; ?></th><th><?php echo PKL_CON_TH_CUSTOMER; ?></th><th><?php echo PKL_CON_TH_IP; ?></th></tr>
                            <?php foreach ($recent as $a) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$a['date_added'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($a['event'], ENT_QUOTES); ?></td>
                                <td><?php echo ((int)$a['customers_id'] > 0) ? (int)$a['customers_id'] : ''; ?></td>
                                <td><?php echo htmlspecialchars($a['ip_address'], ENT_QUOTES); ?></td>
                            </tr>
                            <?php } ?>
                        </table>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong><?php echo PKL_CON_DEBUG; ?></strong> <small>(logs/passkey_login_debug.log)</small></div>
                <div class="panel-body" style="max-height:340px;overflow:auto;">
                    <?php if (count($debugTail) === 0) { ?>
                        <p><?php echo PKL_CON_DEBUG_NONE; ?></p>
                    <?php } else { ?>
                        <pre style="font-size:11px;white-space:pre-wrap;margin:0;"><?php echo htmlspecialchars(implode("\n", $debugTail), ENT_QUOTES); ?></pre>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
