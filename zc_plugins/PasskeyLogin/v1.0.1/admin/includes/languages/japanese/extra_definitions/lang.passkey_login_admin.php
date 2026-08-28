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
// Admin language constants, array format for the 2.x plugin language
// loader. The legacy define file is kept alongside for older loaders;
// whichever loads first wins and the other is a no-op.
$define = [
    'BOX_PASSKEY_LOGIN' => 'パスキーログイン',

    // Console page (admin/passkey_login_console.php). All strings the
    // console displays live here so translations can be dropped in as
    // additional language files.
    'PKL_CON_STATUS' => '状態',
    // Full sentence template: %s receives PKL_CON_ENABLED or
    // PKL_CON_DISABLED. The whole sentence, including markup and final
    // punctuation, lives here so each language controls word order and
    // its own full stop.
    'PKL_CON_STATE_PREFIX' => 'パスキーによるログインは<strong>%s</strong>です。',
    'PKL_CON_ENABLED' => '有効',
    'PKL_CON_DISABLED' => '無効',
    'PKL_CON_RP_ID' => '依拠当事者ID：',
    'PKL_CON_ENROLLED' => 'パスキーをお持ちのお客様',
    'PKL_CON_TOTAL_KEYS' => 'パスキーの合計数',
    'PKL_CON_LOGINS_30' => '過去30日間のパスキーによるサインイン',
    'PKL_CON_FAILS_30' => '過去30日間の失敗した試行',
    'PKL_CON_CLONES_30' => 'クローンに関する警告（過去30日間）',
    'PKL_CON_OPTOUTS' => '励ましのメッセージは無視された',
    'PKL_CON_OPEN_SETTINGS' => '設定を開く',
    'PKL_CON_MAINTENANCE' => 'メンテナンス',
    'PKL_CON_SWEEP_TEXT' => '削除された顧客および共有ゲストチェックアウト用アカウントのパスキー・レコードを削除し、90日以上経過した監査ログを整理します。いつでも安全に実行できます。',
    'PKL_CON_SWEEP_BUTTON' => 'メンテナンススキャンを実行する',
    'PKL_CON_SWEEP_DONE' => '利用可能なすべてのパスキーログインテーブルのメンテナンスチェックが完了しました。',
    'PKL_CON_LOOKUP' => '顧客検索',
    'PKL_CON_LOOKUP_PLACEHOLDER' => '顧客のメールアドレス',
    'PKL_CON_LOOKUP_BUTTON' => '検索する',
    'PKL_CON_LOOKUP_NONE' => 'そのメールアドレスの顧客は見つかりませんでした。',
    'PKL_CON_LOOKUP_ID' => '（ID %s）',
    'PKL_CON_LOOKUP_NO_KEYS' => 'この顧客はパスキーを所有していません。',
    'PKL_CON_TH_LABEL' => 'ラベル',
    'PKL_CON_TH_ADDED' => '追加した',
    'PKL_CON_TH_LAST_USED' => '最後に使用',
    'PKL_CON_NEVER' => '一度もない',
    'PKL_CON_REMOVE_BUTTON' => '消去',
    'PKL_CON_REMOVE_CONFIRM' => 'このパスキーを削除しますか？削除すると、お客様は別の方法でサインインし、パスキーを再度追加する必要があります。',
    'PKL_CON_REMOVE_DONE' => '顧客ID %s のパスキーが削除されました。',
    'PKL_CON_LOST_DEVICE_NOTE' => 'お客様からデバイスの紛失や盗難の報告があった際に使用します。ここでパスキーを削除すると、そのパスキーを使用したサインインが直ちに無効になります。',
    'PKL_CON_RECENT' => '最近の活動',
    'PKL_CON_RECENT_NONE' => 'まだアクティビティは記録されていません。',
    'PKL_CON_TH_WHEN' => '日時',
    'PKL_CON_TH_EVENT' => 'イベント',
    'PKL_CON_TH_CUSTOMER' => 'お客様',
    'PKL_CON_TH_IP' => 'IP',
    'PKL_CON_DEBUG' => 'デバッグログの末尾表示',
    'PKL_CON_DEBUG_NONE' => 'デバッグログの記録はありません。テスト中にセレモニーの詳細を記録するには、設定でデバッグログを有効にしてください。',
];
return $define;
