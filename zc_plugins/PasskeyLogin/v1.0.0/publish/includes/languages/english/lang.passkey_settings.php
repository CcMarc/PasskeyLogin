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
// Language file for the passkey_settings page. PUBLISHED by the installer
// into includes/languages/english/; deploy changes by reinstalling.
$define = [
    'NAVBAR_TITLE' => 'Passkeys',
    'HEADING_TITLE' => 'Passkeys',
    'PKL_PAGE_INTRO' => 'A passkey lets you sign in with your fingerprint, face, or device PIN instead of typing a password. It is protected by your device or passkey provider and cryptographically tied to this site, making sign in phishing-resistant while keeping private key material out of our systems.',
    'PKL_PAGE_NONE_YET' => 'You have not added any passkeys yet.',
    'PKL_PAGE_MAX_REACHED' => 'You have reached the maximum number of passkeys for this account. Remove one before adding another.',
    'PKL_PAGE_UNAVAILABLE' => 'Passkey sign in is not available right now. Please check back later.',
    'PKL_BACK_TO_ACCOUNT' => 'Back to My Account',
    'PKL_BUTTON_ADD' => 'Add a Passkey',
    'PKL_BUTTON_SAVE_NAME' => 'Save Name',
    'PKL_BUTTON_REMOVE' => 'Remove',
    'PKL_BUTTON_REMOVE_YES' => 'Yes, Remove It',
    'PKL_BUTTON_CANCEL' => 'Cancel',
    'PKL_CONFIRM_REMOVE' => 'Remove this passkey? You will no longer be able to sign in with it.',
    'PKL_LABEL_ADDED' => 'Added',
    'PKL_LABEL_LAST_USED' => 'Last used',
    'PKL_JS_UNSUPPORTED' => 'Your browser does not support passkeys. You can keep signing in with your other options.',
    'PKL_JS_GENERIC' => 'Something went wrong while setting up your passkey. Please try again.',
    'PKL_JS_CANCELLED' => 'Passkey setup was cancelled. You can try again whenever you like.',
    'PKL_JS_ALREADY' => 'This device already has a passkey for your account.',
    'PKL_JS_WORKING' => 'Follow the prompt from your device to finish setting up your passkey.',
    'PKL_SUCCESS_REMOVED' => 'Your passkey has been removed.',
    'PKL_SUCCESS_RENAMED' => 'Your passkey name has been updated.',
    'PKL_ERROR_MAX_KEYS' => 'You have reached the maximum number of passkeys for this account.',
    'PKL_ERROR_REG_FAILED' => 'We could not verify that passkey. Please try again.',
    'PKL_ERROR_ALREADY_REGISTERED' => 'That passkey is already registered to your account.',
    'PKL_ERROR_LOGIN_FAILED' => 'We could not sign you in with that passkey. Please try another sign in option.',
    'PKL_ERROR_UNKNOWN_PASSKEY' => 'That passkey is not connected to an account here. It may have been removed. Please sign in another way, then add a new passkey from your account page.',
    'PKL_ERROR_BANNED' => 'This account is not allowed to sign in. Please contact the store if you need help.',
    'PKL_ERROR_RATE' => 'Too many passkey attempts from this connection. Please wait and try again, or use another sign in option.',
    'PKL_ERROR_SERVER' => 'Passkey sign in is temporarily unavailable. Please use another sign in option.',
    'PKL_ERROR_METHOD' => 'Please reload the page and try again.',
    'PKL_ERROR_LOGIN_REQUIRED' => 'Please sign in to manage your passkeys.',
    'PKL_DEFAULT_LABEL_PREFIX' => 'Passkey added',
    'PKL_LABEL_DEVICE_PHONE' => 'Phone or tablet',
    'PKL_LABEL_DEVICE_KEY' => 'Security key',
    'PKL_LABEL_DEVICE_THIS' => 'This device',
];
return $define;
