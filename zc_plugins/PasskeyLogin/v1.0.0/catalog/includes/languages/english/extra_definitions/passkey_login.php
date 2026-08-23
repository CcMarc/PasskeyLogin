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
// Legacy define-format storefront language constants. Guarded fallbacks
// only: the array-format lang file is the one the 2.x loader reads.
if (!defined('PKL_TILE_LABEL')) define('PKL_TILE_LABEL', 'Passkeys');
if (!defined('PKL_NUDGE_TITLE')) define('PKL_NUDGE_TITLE', 'Sign in faster next time');
if (!defined('PKL_NUDGE_TEXT')) define('PKL_NUDGE_TEXT', 'Add a passkey and sign in with your fingerprint, face, or device PIN. No password to type, nothing to remember.');
if (!defined('PKL_NUDGE_ADD_BUTTON')) define('PKL_NUDGE_ADD_BUTTON', 'Add a Passkey');
if (!defined('PKL_NUDGE_DISMISS')) define('PKL_NUDGE_DISMISS', 'No Thanks');
