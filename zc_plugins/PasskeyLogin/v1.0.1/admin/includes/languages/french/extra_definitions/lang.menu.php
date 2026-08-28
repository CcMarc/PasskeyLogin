<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: piloujp 2026 Aug 27 Modified in v2.2.2 $
*/

$define = [
    'ADMIN_PLUGIN_MANAGER_NAME_FOR_PASSKEYLOGIN' => 'Connexion par clé d’accès',
    'ADMIN_PLUGIN_MANAGER_DESCRIPTION_FOR_PASSKEYLOGIN' => 'Permet aux clients de se connecter à l’aide d’une clé d’accès (Face ID, empreinte digitale, Windows Hello ou clé de sécurité) plutôt que de saisir un mot de passe. Utilise la norme WebAuthn. Les clés d’accès apparaissent dans la fonction de remplissage automatique du navigateur sur la page de connexion ; aucun bouton supplémentaire n’est donc ajouté pour les clients qui ne les utilisent pas.',
// Admin configuration
    'CFGTITLE_PKL_ENABLED' => 'Activer la connexion par clé d’accès',
    'CFGDESC_PKL_ENABLED' => 'Interrupteur général. Lorsqu’il est désactivé, le plugin n’intervient sur aucune page.',
    'CFGTITLE_PKL_NUDGE_ENABLED' => 'Afficher une incitation à l’inscription',
    'CFGDESC_PKL_NUDGE_ENABLED' => 'Afficher une bannière unique sur la page « Mon compte » invitant les clients ne disposant pas de clé d’accès à en ajouter une. Les clients peuvent la masquer définitivement.',
    'CFGTITLE_PKL_MAX_KEYS_PER_CUSTOMER' => 'Nombre maximal de clés d’accès par client',
    'CFGDESC_PKL_MAX_KEYS_PER_CUSTOMER' => 'Combien de clés d’accès un compte peut-il contenir ? En général, les clients enregistrent un ou deux appareils.',
    'CFGTITLE_PKL_RATE_IP_HOUR' => 'Plafond du taux horaire par adresse IP',
    'CFGDESC_PKL_RATE_IP_HOUR' => 'Nombre maximal de demandes de défi de clé d’accès par adresse IP et par heure. Réglez sur 0 pour désactiver la limite.',
    'CFGTITLE_PKL_RP_ID' => 'Remplacement de l’identifiant de la partie de confiance',
    'CFGDESC_PKL_RP_ID' => 'Laissez généralement ce champ vide : le domaine enregistrable est déterminé automatiquement, ce qui permet également à un sous-domaine de préproduction de partager les clés d’accès de la production. Ne le définissez explicitement que pour les domaines de premier niveau (TLD) composés de plusieurs parties, tels que .co.uk.',
    'CFGTITLE_PKL_RP_NAME' => 'Nom d’affichage de la partie de confiance',
    'CFGDESC_PKL_RP_NAME' => 'Affiché par le navigateur dans les invites de clé d’accès. Laissez vide pour utiliser le nom de la boutique.',
    'CFGTITLE_PKL_DEBUG_LOG' => 'Journalisation de débogage',
    'CFGDESC_PKL_DEBUG_LOG' => 'Si cette option est activée, des lignes JSON sont écrites dans le fichier logs/passkey_login_debug.log. Désactivez-la en mode de fonctionnement normal.',
    'CFGTITLE_PKL_VERSION' => 'Version de « Connexion par clé d’accès »',
    'CFGDESC_PKL_VERSION' => 'Version du plugin installée. Gestion automatique.',
// Configuration_group
    'CFG_GRP_TITLE_PASSKEY_LOGIN' => 'Connexion par clé d’accès',
    'CFG_GRP_DESC_PASSKEY_LOGIN' => 'Paramètres de connexion par clé d’accès (WebAuthn)',
];

return $define;
