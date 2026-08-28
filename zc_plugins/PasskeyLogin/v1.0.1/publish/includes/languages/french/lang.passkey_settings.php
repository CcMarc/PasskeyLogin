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
    'NAVBAR_TITLE' => 'Clés d’accès',
    'HEADING_TITLE' => 'Clés d’accès',
    'PKL_PAGE_INTRO' => 'Une clé d’accès vous permet de vous connecter à l’aide de votre empreinte digitale, de la reconnaissance faciale ou du code PIN de votre appareil, plutôt que de saisir un mot de passe. Elle est protégée par votre appareil ou votre fournisseur de clés d’accès et liée cryptographiquement à ce site ; la connexion est ainsi résistante au hameçonnage, tout en garantissant que les données de clé privée ne transitent pas par nos systèmes.',
    'PKL_PAGE_NONE_YET' => 'Vous n’avez pas encore ajouté de clés d’accès.',
    'PKL_PAGE_MAX_REACHED' => 'Vous avez atteint le nombre maximal de clés d’accès pour ce compte. Supprimez-en une avant d’en ajouter une autre.',
    'PKL_PAGE_UNAVAILABLE' => 'La connexion par clé d’accès n’est pas disponible pour le moment. Veuillez réessayer plus tard.',
    'PKL_BACK_TO_ACCOUNT' => 'Retour à mon compte',
    'PKL_BUTTON_ADD' => 'Ajouter une clé d’accès',
    'PKL_BUTTON_SAVE_NAME' => 'Enregistrer le nom',
    'PKL_BUTTON_REMOVE' => 'Retirer',
    'PKL_BUTTON_REMOVE_YES' => 'Oui, supprimez-la',
    'PKL_BUTTON_CANCEL' => 'Annuler',
    'PKL_CONFIRM_REMOVE' => 'Supprimer cette clé d’accès ? Vous ne pourrez plus vous connecter avec.',
    'PKL_LABEL_ADDED' => 'Ajoutée',
    'PKL_LABEL_LAST_USED' => 'Dernière utilisation',
    'PKL_JS_UNSUPPORTED' => 'Votre navigateur ne prend pas en charge les clés d’accès. Vous pouvez continuer à vous connecter en utilisant vos autres options.',
    'PKL_JS_GENERIC' => 'Un problème est survenu lors de la configuration de votre clé d’accès. Veuillez réessayer.',
    'PKL_JS_CANCELLED' => 'La configuration de la clé d’accès a été annulée. Vous pouvez réessayer à tout moment.',
    'PKL_JS_ALREADY' => 'Cet appareil possède déjà une clé d’accès pour votre compte.',
    'PKL_JS_WORKING' => 'Suivez les instructions qui s’affichent sur votre appareil pour terminer la configuration de votre clé d’accès.',
    'PKL_SUCCESS_REMOVED' => 'Votre clé d’accès a été supprimée.',
    'PKL_SUCCESS_RENAMED' => 'Le nom de votre clé d’accès a été mis à jour.',
    'PKL_ERROR_MAX_KEYS' => 'Vous avez atteint le nombre maximal de clés d’accès pour ce compte.',
    'PKL_ERROR_REG_FAILED' => 'Nous n’avons pas pu vérifier cette clé d’accès. Veuillez réessayer.',
    'PKL_ERROR_ALREADY_REGISTERED' => 'Cette clé d’accès est déjà associée à votre compte.',
    'PKL_ERROR_LOGIN_FAILED' => 'Nous n’avons pas pu vous connecter avec cette clé d’accès. Veuillez essayer une autre méthode de connexion.',
    'PKL_ERROR_UNKNOWN_PASSKEY' => 'Cette clé d’accès n’est associée à aucun compte sur ce site. Elle a peut-être été supprimée. Veuillez vous connecter par un autre moyen, puis ajouter une nouvelle clé d’accès depuis la page de votre compte.',
    'PKL_ERROR_BANNED' => 'Ce compte n’est pas autorisé à se connecter. Veuillez contacter le magasin si vous avez besoin d’aide.',
    'PKL_ERROR_RATE' => 'Trop de tentatives de clé d’accès depuis cette connexion. Veuillez patienter et réessayer, ou utiliser une autre option de connexion.',
    'PKL_ERROR_SERVER' => 'La connexion par clé d’accès est temporairement indisponible. Veuillez utiliser une autre option de connexion.',
    'PKL_ERROR_METHOD' => 'Veuillez recharger la page et réessayer.',
    'PKL_ERROR_LOGIN_REQUIRED' => 'Veuillez vous connecter pour gérer vos clés d’accès.',
    'PKL_DEFAULT_LABEL_PREFIX' => 'Clé d’accès ajoutée',
    'PKL_LABEL_DEVICE_PHONE' => 'Téléphone ou tablette',
    'PKL_LABEL_DEVICE_KEY' => 'Clé de sécurité',
    'PKL_LABEL_DEVICE_THIS' => 'Cet appareil',
];
return $define;
