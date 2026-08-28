<?php
/**
 * Module: PasskeyLogin
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 8.0+ with OpenSSL
 * @author      Marcopolo
 * @copyright   2026
 * @license     GNU General Public License (GPL) - https://www.zen-cart.com/license/2_0.txt
 * @version     1.0.1
 * @updated     08-28-2026
 * @github      https://github.com/CcMarc/PasskeyLogin
 */
return [
    'pluginVersion'     => 'v1.0.1',
    'pluginHotfixLevel' => 3,
    'pluginName'        => 'Passkey Login',
    'pluginDescription' => 'Lets customers sign in with a passkey (Face ID, fingerprint, Windows Hello, or a security key) instead of typing a password. Uses the WebAuthn standard. Passkeys appear in the browser autofill on the login page, so no extra buttons are added for customers who do not use them.',
    'pluginAuthor'      => 'Marcopolo',
    'pluginId'          => 0,
    'zcVersions'        => ['v210', 'v222'],
    'changelog'         => 'https://github.com/CcMarc/PasskeyLogin/blob/main/CHANGELOG.md',
    'github_repo'       => 'https://github.com/CcMarc/PasskeyLogin',
    'pluginGroups'      => [],
];
