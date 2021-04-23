<?php

/**
 * @file
 * Contains \Netzstrategen\WP_CLI_DB_Clean_Export\CliCommand
 */

namespace Netzstrategen\WP_CLI_DB_Clean_Export;

use \WP_CLI;
use \WP_CLI\Utils;
use Ifsnop\Mysqldump\Mysqldump as IMysqldump;

/**
 * Adds 'export' WP CLI command.
 *
 * @example wp export-clean export
 */
class CliCommand extends \WP_CLI_Command {

  public function export() {
    global $wpdb;
    // Set allowed email hosts.
    if (defined('CLEAN_EXPORT_ALLOWED_EMAILS') && CLEAN_EXPORT_ALLOWED_EMAILS) {
      $allowedEmails = array_map('trim', explode(',', DISABLE_EXTERNAL_EMAILS_EXCEPT));
    }
    else {
      $allowedEmails = ['@netzstrategen.com'];
    }

    // Set target options containing credentials to be emptied before dump.
    if (defined('CLEAN_EXPORT_DISPOSE_OPTIONS') && CLEAN_EXPORT_DISPOSE_OPTIONS) {
      $optionsToBlank = (array) CLEAN_EXPORT_DISPOSE_OPTIONS;
    }
    else {
      $optionsToBlank = ['woocommerce_paypal_settings','woocommerce_paypal_express_settings','woocommerce_paypal_plus_settings','woocommerce_ppec_paypal_settings','wplister_paypal_email','rg_gforms_key','woothemes_helper_master_key','optimus_key','elementor_pro_license_key','searchwp_license_key','gf_zero_spam_key','wple_api_key','mbc_woogoogad_api_key','wpla_api_key','woocommerce_amazon_payments_advanced_private_key'];
    }

    // Get total number of tables for the progress bar.
    $databaseTableCount = $wpdb->get_col($wpdb->prepare("SELECT count(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s'", DB_NAME))[0];

    // Retain user ids about to be skipped for further filters.
    $droppedUsersIds = implode(',', $wpdb->get_col(
      $wpdb->prepare("SELECT u.ID FROM {$wpdb->prefix}users u WHERE u.user_email NOT REGEXP ('%s')", implode('|', $allowedEmails)
    )));

    try {
      $dump = new IMysqldump('mysql:host=localhost;dbname=' . DB_NAME, DB_USER, DB_PASSWORD);

      // Set unnecessary/sensitive data.
      $dump->setTableWheres([
        "{$wpdb->prefix}users" => "ID NOT IN ({$droppedUsersIds})",
        "{$wpdb->prefix}usermeta" => "user_id NOT IN ({$droppedUsersIds})",
        "{$wpdb->prefix}posts" => "post_type NOT IN (\"shop_order\", \"shop_subscription\")",
        "{$wpdb->prefix}postmeta" => "post_id NOT IN (SELECT p.ID FROM {$wpdb->prefix}posts p WHERE p.post_type IN (\"shop_order\", \"shop_subscription\"))",
        // Ignore all sessions.
        "{$wpdb->prefix}woocommerce_sessions" => 'session_id = 0',
      ]);

      $dump->setTransformTableRowHook(function ($tableName, array $row) use ($optionsToBlank, $wpdb) {
        if ($tableName === "{$wpdb->prefix}options" && in_array($row['meta_key'], $optionsToBlank)) {
          $row['meta_vaue'] = '';
        }
        return $row;
      });

      $progress = Utils\make_progress_bar('Dumping tables: ', $databaseTableCount);

      // Prompt table counts.
      $dump->setInfoHook(function ($object, $info) use ($progress) {
        if ($object === 'table') {
          $progress->tick();
          // WP_CLI::log($info['name'] . ' -> ' . $info['rowCount']);
        }
      });

      $dump->start(ABSPATH . 'clean-export.sql');

      $progress->finish();

      WP_CLI::success('Dump file is available at ' . ABSPATH . 'clean-export.sql');
    }
    catch (\Exception $e) {
      WP_CLI::error($e->getMessage());
    }
  }

}
