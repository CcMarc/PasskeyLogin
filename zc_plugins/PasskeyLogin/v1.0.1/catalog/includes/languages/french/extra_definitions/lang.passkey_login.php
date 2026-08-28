<?php
/**
 * Module: PasskeyLogin
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 8.0+ with OpenSSL
 * @author      Marcopolo
 * @contributor piloujp
 * @copyright   2026
 * @license     GNU General Public License (GPL) - https://www.zen-cart.com/license/2_0.txt
 * @version     1.0.1
 * @updated     08-28-2026
 * @github      https://github.com/CcMarc/PasskeyLogin
 */
// Storefront language constants, array format for the 2.x plugin language
// loader. The legacy define file sits alongside for older loaders; edit
// HERE to change the customer-facing copy.
$define = [
    'PKL_TILE_LABEL' => 'Clés d’accès',
    'PKL_NUDGE_TITLE' => 'Connectez-vous plus rapidement la prochaine fois',
    'PKL_NUDGE_TEXT' => 'Ajoutez une clé d’accès et connectez-vous à l’aide de votre empreinte digitale, de votre visage ou du code PIN de votre appareil. Aucun mot de passe à saisir, rien à mémoriser.',
    'PKL_NUDGE_ADD_BUTTON' => 'Ajouter une clé d’accès',
    'PKL_NUDGE_DISMISS' => 'Non merci',
];
return $define;
