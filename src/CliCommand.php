<?php

namespace Makers99\WpDbExportClean;

use WP_CLI;
use WP_CLI\Utils;
use Ifsnop\Mysqldump\Mysqldump as IMysqldump;

/**
 * WP-CLI command for minimum viable database dump.
 */
class CliCommand extends \WP_CLI_Command {

  /**
   * Sensitive options to blank out.
   *
   * @var array
   */
  const DISPOSE_OPTIONS = [
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
   * Prefix for hook names.
   *
   * @var string
   */
  const PREFIX = 'wp-db-export-clean';

  /**
   * Exports the database to a file without sensitive data.
   *
   * @subcommand export-clean
   * @synopsis [<file>] [--remove-keys]
   * @when after_wp_load
   */
  public function __invoke(array $args, array $options) {
    global $wpdb;

    $options += [
      'remove-keys' => FALSE,
    ];

    // Set allowed email hosts.
    $adminsEmails = array_map(function ($user) {
      return $user->user_email;
    }, get_users([
      'fields' => ['user_email'],
      'role__in' => ['administrator'],
    ]));
    $allowedEmails = apply_filters(static::PREFIX . '/allowed-emails', $adminsEmails);

    // Get total number of tables for the progress bar.
    $databaseTableCount = $wpdb->get_col($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s'", DB_NAME))[0];

    // Retain user ids about to be skipped for further filters.
    $allowedUserIds = implode(',', $wpdb->get_col(
      $wpdb->prepare("SELECT u.ID FROM {$wpdb->prefix}users u WHERE u.user_email REGEXP ('%s')", implode('|', $allowedEmails)
    )));
    $allowedUserIds = apply_filters(static::PREFIX . '/allowed-user-ids', $allowedUserIds);

    // Retain only order/subscription IDs corresponding to allowed users.
    $allowedOrderIds = implode(',', $wpdb->get_col("
      SELECT p.ID FROM {$wpdb->prefix}posts p
        JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID
        WHERE p.post_type IN (\"shop_order\", \"shop_subscription\")
          AND pm.meta_key = '_customer_user'
          AND pm.meta_value IN ({$allowedUserIds})
    "));
    $allowedOrderIds = apply_filters(static::PREFIX . '/allowed-order-ids', $allowedOrderIds);

    $allowedOrderItemIds = !$allowedOrderIds ? '' : implode(',', $wpdb->get_col("
      SELECT oi.order_item_id FROM {$wpdb->prefix}woocommerce_order_items oi
        WHERE oi.order_id IN ({$allowedOrderIds})
    "));

    $postTableWheres = ['post_type NOT IN ("revision", "customize_changeset", "oembed_cache")'];
    if ($allowedOrderIds) {
      $postTableWheres[] = '(post_type NOT IN ("shop_order", "shop_subscription") OR ID IN (' . $allowedOrderIds . '))';
    }

    try {
      $dump = new IMysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, [
        'add-drop-table' => TRUE,
      ]);
      $tableWheres = apply_filters(static::PREFIX . '/table-wheres', [
        "{$wpdb->prefix}comments" => "comment_post_ID IN ({$allowedOrderIds})",
        "{$wpdb->prefix}users" => "ID IN ({$allowedUserIds})",
        "{$wpdb->prefix}usermeta" => "user_id IN ({$allowedUserIds})",
        "{$wpdb->prefix}options" => 'option_name NOT LIKE "_transient_%" AND option_name NOT LIKE "_cache_%"',
        "{$wpdb->prefix}posts" => implode(' AND ', $postTableWheres),
        "{$wpdb->prefix}postmeta" => "post_id NOT IN (SELECT p.ID FROM {$wpdb->prefix}posts p WHERE " . implode(' AND ', $postTableWheres) . ")",
      ]);

      // Remove woocommerce related entries.
      $tableWheres = array_merge($tableWheres, [
        "{$wpdb->prefix}actionscheduler_actions" => '1 = 0',
        "{$wpdb->prefix}actionscheduler_claims" => '1 = 0',
        "{$wpdb->prefix}actionscheduler_groups" => '1 = 0',
        "{$wpdb->prefix}actionscheduler_logs" => '1 = 0',
        "{$wpdb->prefix}woocommerce_order_items" => "order_id IN ({$allowedOrderIds})",
        "{$wpdb->prefix}woocommerce_order_itemmeta" => "order_item_id IN ({$allowedOrderItemIds})",
        "{$wpdb->prefix}woocommerce_sessions" => '1 = 0',
      ]);

      // Remove gravityforms related entries.
      $tableWheres = array_merge($tableWheres, [
        "{$wpdb->prefix}gf_entry" => '1 = 0',
        "{$wpdb->prefix}gf_entry_meta" => '1 = 0',
        "{$wpdb->prefix}gf_entry_notes" => '1 = 0',
        "{$wpdb->prefix}gf_form_revisions" => '1 = 0',
        "{$wpdb->prefix}gf_form_view" => '1 = 0',
      ]);

      // Remove wp-lister-amazon related entries.
      $tableWheres = array_merge($tableWheres, [
        "{$wpdb->prefix}amazon_feeds" => '1 = 0',
        "{$wpdb->prefix}amazon_jobs" => '1 = 0',
        "{$wpdb->prefix}amazon_log" => '1 = 0',
        "{$wpdb->prefix}amazon_orders" => "buyer_userid IN ({$allowedUserIds})",
        "{$wpdb->prefix}amazon_reports" => '1 = 0',
        "{$wpdb->prefix}amazon_stock_log" => '1 = 0',
      ]);

      // Remove wp-lister-ebay related entries.
      $tableWheres = array_merge($tableWheres, [
        "{$wpdb->prefix}ebay_jobs" => '1 = 0',
        "{$wpdb->prefix}ebay_log" => '1 = 0',
        "{$wpdb->prefix}ebay_messages" => '1 = 0',
        "{$wpdb->prefix}ebay_orders" => "buyer_userid IN ({$allowedUserIds})",
        "{$wpdb->prefix}ebay_stocks_log" => '1 = 0',
        "{$wpdb->prefix}ebay_transactions" => "buyer_userid IN ({$allowedUserIds})",
      ]);

      // Remove wordpress-seo (Yoast) related entries.
      $tableWheres = array_merge($tableWheres, [
        "{$wpdb->prefix}yoast_indexable" => '1 = 0',
        "{$wpdb->prefix}yoast_indexable_hierarchy" => '1 = 0',
        "{$wpdb->prefix}yoast_migrations" => '1 = 0',
        "{$wpdb->prefix}yoast_seo_links" => '1 = 0',
        "{$wpdb->prefix}yoast_seo_meta" => '1 = 0',
      ]);

      // Remove options containing license keys and API credentials during dump.
      if ($options['remove-keys']) {
        $removeOptions = apply_filters(static::PREFIX . '/dispose-options', static::DISPOSE_OPTIONS);
        $dump->setTransformTableRowHook(function ($tableName, array $row) use ($removeOptions, $wpdb) {
          if ($tableName === "{$wpdb->prefix}options" && isset($row['meta_key']) && in_array($row['meta_key'], $removeOptions, TRUE)) {
            $row['meta_vaue'] = '';
          }
          return $row;
        });

        // Remove wp-lister-amazon and wp-lister-ebay profiles.
        $tableWheres = array_merge($tableWheres, [
          "{$wpdb->prefix}amazon_accounts" => '1 = 0',
          "{$wpdb->prefix}ebay_accounts" => '1 = 0',
        ]);
      }

      $dump->setTableWheres($tableWheres);

      $progress = Utils\make_progress_bar('Dumping tables: ', $databaseTableCount);

      // Prompt table counts.
      $dump->setInfoHook(function ($object, $info) use ($progress) {
        if ($object === 'table') {
          $progress->tick();
        }
      });

      $file = $args[0] ?? 'clean.sql';
      $dump->start($file);

      $progress->finish();

      WP_CLI::success('Exported clean database dump into ' . $file);
    }
    catch (\Exception $e) {
      WP_CLI::error($e->getMessage());
    }
  }

}
