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
// Admin language constants, array format for the 2.x plugin language
// loader. The legacy define file is kept alongside for older loaders;
// whichever loads first wins and the other is a no-op.
$define = [
    'BOX_PASSKEY_LOGIN' => 'Passkey Login',

    // Console page (admin/passkey_login_console.php). All strings the
    // console displays live here so translations can be dropped in as
    // additional language files.
    'PKL_CON_STATUS' => 'Status',
    'PKL_CON_STATE_PREFIX' => 'Passkey login is',
    'PKL_CON_ENABLED' => 'ENABLED',
    'PKL_CON_DISABLED' => 'DISABLED',
    'PKL_CON_RP_ID' => 'Relying Party ID:',
    'PKL_CON_ENROLLED' => 'Customers with a passkey',
    'PKL_CON_TOTAL_KEYS' => 'Total passkeys',
    'PKL_CON_LOGINS_30' => 'Passkey sign ins, last 30 days',
    'PKL_CON_FAILS_30' => 'Failed attempts, last 30 days',
    'PKL_CON_CLONES_30' => 'Clone warnings, last 30 days',
    'PKL_CON_OPTOUTS' => 'Nudge opt outs',
    'PKL_CON_OPEN_SETTINGS' => 'Open settings',
    'PKL_CON_MAINTENANCE' => 'Maintenance',
    'PKL_CON_SWEEP_TEXT' => 'Removes passkey rows for deleted customers and for the shared guest checkout account, and prunes audit entries older than 90 days. Safe to run any time.',
    'PKL_CON_SWEEP_BUTTON' => 'Run Maintenance Sweep',
    'PKL_CON_SWEEP_DONE' => 'Maintenance sweep complete for all available Passkey Login tables.',
    'PKL_CON_LOOKUP' => 'Customer Lookup',
    'PKL_CON_LOOKUP_PLACEHOLDER' => 'customer email address',
    'PKL_CON_LOOKUP_BUTTON' => 'Look Up',
    'PKL_CON_LOOKUP_NONE' => 'No customer found with that email address.',
    'PKL_CON_LOOKUP_NO_KEYS' => 'This customer has no passkeys.',
    'PKL_CON_TH_LABEL' => 'Label',
    'PKL_CON_TH_ADDED' => 'Added',
    'PKL_CON_TH_LAST_USED' => 'Last used',
    'PKL_CON_NEVER' => 'never',
    'PKL_CON_REMOVE_BUTTON' => 'Remove',
    'PKL_CON_REMOVE_CONFIRM' => 'Remove this passkey? The customer will need to sign in another way and re add it.',
    'PKL_CON_REMOVE_DONE' => 'Passkey removed for customer id %s.',
    'PKL_CON_LOST_DEVICE_NOTE' => 'Use this when a customer reports a lost or stolen device. Removing the passkey here immediately blocks sign in with it.',
    'PKL_CON_RECENT' => 'Recent Activity',
    'PKL_CON_RECENT_NONE' => 'No activity recorded yet.',
    'PKL_CON_TH_WHEN' => 'When',
    'PKL_CON_TH_EVENT' => 'Event',
    'PKL_CON_TH_CUSTOMER' => 'Customer',
    'PKL_CON_TH_IP' => 'IP',
    'PKL_CON_DEBUG' => 'Debug Log Tail',
    'PKL_CON_DEBUG_NONE' => 'No debug log entries. Enable Debug Logging in settings to record ceremony details while testing.',
];
return $define;
