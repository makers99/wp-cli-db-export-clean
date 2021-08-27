<?php

/**
 * WP-CLI extension for minimum viable database dump.
 *
 * @file
 * @contains \Netzstrategen\WP_CLI_DB_Clean_Export\CliCommand
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

  /**
   * Sensitive wp options which can be blanked out.
   *
   * @var array
   */
  const CLEAN_EXPORT_DISPOSE_OPTIONS = [
    'woocommerce_paypal_settings',
    'woocommerce_paypal_express_settings',
    'woocommerce_paypal_plus_settings',
    'woocommerce_ppec_paypal_settings',
    'wplister_paypal_email',
    'rg_gforms_key',
    'woothemes_helper_master_key',
    'optimus_key',
    'elementor_pro_license_key',
    'searchwp_license_key',
    'gf_zero_spam_key',
    'wple_api_key',
    'mbc_woogoogad_api_key',
    'wpla_api_key',
    'woocommerce_amazon_payments_advanced_private_key',
  ];

  /**
   * Prefix for naming.
   *
   * @var string
   */
  const PREFIX = 'clean-export';

  public function export() {
    global $wpdb;
    // Set allowed email hosts.
    if (defined('CLEAN_EXPORT_ALLOWED_EMAILS') && CLEAN_EXPORT_ALLOWED_EMAILS) {
      $allowedEmails = array_map('trim', explode(',', DISABLE_EXTERNAL_EMAILS_EXCEPT));
    }
    else {
      $allowedEmails = ['@netzstrategen.com'];
    }

    // Get total number of tables for the progress bar.
    $databaseTableCount = $wpdb->get_col($wpdb->prepare("SELECT count(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s'", DB_NAME))[0];

    // Retain user ids about to be skipped for further filters.
    $allowedUserIds = implode(',', $wpdb->get_col(
      $wpdb->prepare("SELECT u.ID FROM {$wpdb->prefix}users u WHERE u.user_email REGEXP ('%s')", implode('|', $allowedEmails)
    )));
    $allowedUserIds = apply_filters(static::PREFIX . '-allowed-user-ids', $allowedUserIds);

    $allowedPostIds = implode(',', $wpdb->get_col($d = "
      SELECT p.ID FROM {$wpdb->prefix}posts p
        JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID
        WHERE p.post_type IN (\"shop_order\", \"shop_subscription\")
          AND pm.meta_key = '_customer_user'
          AND pm.meta_value IN ({$allowedUserIds})
    "));

    $postTableWheres = ['post_type NOT IN ("revision")'];
    if ($allowedPostIds) {
      $postTableWheres[] = '(post_type NOT IN ("shop_order", "shop_subscription") OR ID IN (' . $allowedPostIds . '))';
    }

    try {
      $dump = new IMysqldump('mysql:host=localhost;dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
      $tableWheres = apply_filters(static::PREFIX . '-table-wheres', [
        "{$wpdb->prefix}users" => "ID IN ({$allowedUserIds})",
        "{$wpdb->prefix}usermeta" => "user_id IN ({$allowedUserIds})",
        "{$wpdb->prefix}posts" => implode(' AND ', $postTableWheres),
        "{$wpdb->prefix}postmeta" => "post_id NOT IN (SELECT p.ID FROM {$wpdb->prefix}posts p WHERE p.post_type IN (\"revision\", \"shop_order\", \"shop_subscription\"))",
        // Ignore all sessions.
        "{$wpdb->prefix}woocommerce_sessions" => 'session_id = 0',
      ]);
      $dump->setTableWheres($tableWheres);

      // Set target options containing credentials to be emptied before dump.
      $optionsToBlank = apply_filters(static::PREFIX . '-dispose-options', static::CLEAN_EXPORT_DISPOSE_OPTIONS);
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
