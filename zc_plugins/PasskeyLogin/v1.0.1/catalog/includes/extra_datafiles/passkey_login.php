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
// Table and filename defines. Only TABLE_/FILENAME_ defines belong in
// extra_datafiles: these load BEFORE the configuration table is read into
// constants, so a PKL_* fallback defined here would freeze the real
// values. Consumers guard config constants with defined() checks instead.
//
if (!defined('TABLE_PASSKEY_CREDENTIALS')) define('TABLE_PASSKEY_CREDENTIALS', DB_PREFIX . 'passkey_credentials');
if (!defined('TABLE_PASSKEY_OPTOUT'))      define('TABLE_PASSKEY_OPTOUT', DB_PREFIX . 'passkey_optout');
if (!defined('TABLE_PASSKEY_AUDIT'))       define('TABLE_PASSKEY_AUDIT', DB_PREFIX . 'passkey_audit');
if (!defined('TABLE_PASSKEY_CHALLENGES')) define('TABLE_PASSKEY_CHALLENGES', DB_PREFIX . 'passkey_challenges');
if (!defined('FILENAME_PASSKEY_SETTINGS')) define('FILENAME_PASSKEY_SETTINGS', 'passkey_settings');
