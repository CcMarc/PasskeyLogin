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
    'BOX_PASSKEY_LOGIN' => 'Connexion par clé d’accès',

    // Console page (admin/passkey_login_console.php). All strings the
    // console displays live here so translations can be dropped in as
    // additional language files.
    'PKL_CON_STATUS' => 'Statut',
    // Full sentence template: %s receives PKL_CON_ENABLED or
    // PKL_CON_DISABLED. The whole sentence, including markup and final
    // punctuation, lives here so each language controls word order and
    // its own full stop.
    'PKL_CON_STATE_PREFIX' => 'La connexion par clé d’accès est <strong>%s</strong>.',
    'PKL_CON_ENABLED' => 'ACTIVÉ',
    'PKL_CON_DISABLED' => 'DÉSACTIVÉ',
    'PKL_CON_RP_ID' => 'Identifiant de l’utilisateur :',
    'PKL_CON_ENROLLED' => 'Clients disposant d’une clé d’accès',
    'PKL_CON_TOTAL_KEYS' => 'Nombre total de clés d’accès',
    'PKL_CON_LOGINS_30' => 'Connexions par clé d’accès, 30 derniers jours',
    'PKL_CON_FAILS_30' => 'Tentatives infructueuses, 30 derniers jours',
    'PKL_CON_CLONES_30' => 'Avertissements de clonage, 30 derniers jours',
    'PKL_CON_OPTOUTS' => 'Messages d’incitation ignorés',
    'PKL_CON_OPEN_SETTINGS' => 'Ouvrir les paramètres',
    'PKL_CON_MAINTENANCE' => 'Entretien',
    'PKL_CON_SWEEP_TEXT' => 'Supprime les lignes de clés d’accès correspondant aux clients supprimés ainsi qu’au compte partagé de commande en tant qu’invité, et purge les entrées d’audit datant de plus de 90 jours. Peut être exécuté sans risque à tout moment.',
    'PKL_CON_SWEEP_BUTTON' => 'Lancer une vérification de maintenance',
    'PKL_CON_SWEEP_DONE' => 'Vérification de maintenance terminée pour toutes les tables de connexion par clé d’accès disponibles.',
    'PKL_CON_LOOKUP' => 'Recherche par client',
    'PKL_CON_LOOKUP_PLACEHOLDER' => 'adresse e-mail du client',
    'PKL_CON_LOOKUP_BUTTON' => 'Chercher',
    'PKL_CON_LOOKUP_NONE' => 'Aucun client trouvé avec cette adresse e-mail.',
    'PKL_CON_LOOKUP_ID' => '(id %s)',
    'PKL_CON_LOOKUP_NO_KEYS' => 'Ce client ne possède aucune clé d’accès.',
    'PKL_CON_TH_LABEL' => 'Étiquette',
    'PKL_CON_TH_ADDED' => 'Ajouté',
    'PKL_CON_TH_LAST_USED' => 'Dernière utilisation',
    'PKL_CON_NEVER' => 'jamais',
    'PKL_CON_REMOVE_BUTTON' => 'Supprimer',
    'PKL_CON_REMOVE_CONFIRM' => 'Supprimer cette clé d’accès ? Le client devra se connecter par un autre moyen et l’ajouter à nouveau.',
    'PKL_CON_REMOVE_DONE' => 'Clé d’accès supprimée pour l’identifiant client %s.',
    'PKL_CON_LOST_DEVICE_NOTE' => 'Utilisez cette option lorsqu’un client signale la perte ou le vol d’un appareil. La suppression de la clé d’accès à cet endroit bloque immédiatement la connexion avec celle-ci.',
    'PKL_CON_RECENT' => 'Activité récente',
    'PKL_CON_RECENT_NONE' => 'Aucune activité enregistrée pour l’instant.',
    'PKL_CON_TH_WHEN' => 'Quand',
    'PKL_CON_TH_EVENT' => 'Événement',
    'PKL_CON_TH_CUSTOMER' => 'Client',
    'PKL_CON_TH_IP' => 'IP',
    'PKL_CON_DEBUG' => 'Fin du journal de débogage',
    'PKL_CON_DEBUG_NONE' => 'Aucune entrée dans le journal de débogage. Activez la journalisation de débogage dans les paramètres pour enregistrer les détails de la cérémonie lors des tests.',
];
return $define;
