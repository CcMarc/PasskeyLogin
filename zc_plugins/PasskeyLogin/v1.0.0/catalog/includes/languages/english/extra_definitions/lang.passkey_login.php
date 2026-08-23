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
// Storefront language constants, array format for the 2.x plugin language
// loader. The legacy define file sits alongside for older loaders; edit
// HERE to change the customer-facing copy.
$define = [
    'PKL_TILE_LABEL' => 'Passkeys',
    'PKL_NUDGE_TITLE' => 'Sign in faster next time',
    'PKL_NUDGE_TEXT' => 'Add a passkey and sign in with your fingerprint, face, or device PIN. No password to type, nothing to remember.',
    'PKL_NUDGE_ADD_BUTTON' => 'Add a Passkey',
    'PKL_NUDGE_DISMISS' => 'No Thanks',
];
return $define;
