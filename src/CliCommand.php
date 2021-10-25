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
   * @synopsis [<file>]
   * @when after_wp_load
   */
  public function __invoke(array $args, array $options) {
    global $wpdb;

    // Set allowed email hosts.
    $adminsEmails = array_map(function ($user) {
      return reset($user);
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

    // Retain only order/subscription ids corresponding to allowed users.
    $allowedOrderIds = implode(',', $wpdb->get_col($d = "
      SELECT p.ID FROM {$wpdb->prefix}posts p
        JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID
        WHERE p.post_type IN (\"shop_order\", \"shop_subscription\")
          AND pm.meta_key = '_customer_user'
          AND pm.meta_value IN ({$allowedUserIds})
    "));
    $allowedOrderIds = apply_filters(static::PREFIX . '/allowed-order-ids', $allowedOrderIds);

    $allowedOrderItemIds = implode(',', $wpdb->get_col($d = "
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
        "{$wpdb->prefix}users" => "ID IN ({$allowedUserIds})",
        "{$wpdb->prefix}usermeta" => "user_id IN ({$allowedUserIds})",
        "{$wpdb->prefix}woocommerce_order_items" => "order_id IN ({$allowedOrderIds})",
        "{$wpdb->prefix}woocommerce_order_itemmeta" => "order_item_id IN ({$allowedOrderItemIds})",
        "{$wpdb->prefix}posts" => implode(' AND ', $postTableWheres),
        "{$wpdb->prefix}postmeta" => "post_id NOT IN (SELECT p.ID FROM {$wpdb->prefix}posts p WHERE p.post_type IN (\"revision\", \"shop_order\", \"shop_subscription\"))",
        // Ignore unnnecessary info.
        "{$wpdb->prefix}actionscheduler_actions" => 'action_id = 0',
        "{$wpdb->prefix}actionscheduler_claims" => 'claim_id = 0',
        "{$wpdb->prefix}actionscheduler_groups" => 'group_id = 0',
        "{$wpdb->prefix}actionscheduler_logs" => 'log_id = 0',
        "{$wpdb->prefix}comments" => "comment_post_ID NOT IN ({$allowedOrderIds})",
        "{$wpdb->prefix}options" => 'option_name NOT LIKE "%_transient_%" AND option_name NOT LIKE "%cache%"',
        "{$wpdb->prefix}woocommerce_sessions" => 'session_id = 0',
      ]);

      // Remove Gravity Forms related entries.
      if (is_plugin_active('gravityforms/gravityforms.php')) {
        $tableWheres = array_merge($tableWheres, [
          "{$wpdb->prefix}gf_entry" => 'id = 0',
          "{$wpdb->prefix}gf_entry_meta" => 'id = 0',
          "{$wpdb->prefix}gf_entry_notes" => 'id = 0',
          "{$wpdb->prefix}gf_form_revisions" => 'id = 0',
          "{$wpdb->prefix}gf_form_view" => 'id = 0',
        ]);
      }

      // Remove wp-lister-amazon related entries.
      if (is_plugin_active('wp-lister-amazon/wp-lister-amazon.php')) {
        $tableWheres = array_merge($tableWheres, [
          "{$wpdb->prefix}amazon_accounts" => 'id = 0',
          "{$wpdb->prefix}amazon_feed_templates" => 'id = 0',
          "{$wpdb->prefix}amazon_feed_tpl_data" => 'id = 0',
          "{$wpdb->prefix}amazon_feed_tpl_values" => 'id = 0',
          "{$wpdb->prefix}amazon_feeds" => 'id = 0',
          "{$wpdb->prefix}amazon_jobs" => 'id = 0',
          "{$wpdb->prefix}amazon_log" => 'id = 0',
          "{$wpdb->prefix}amazon_listings" => 'id = 0',
          "{$wpdb->prefix}amazon_orders" => "buyer_userid IN ({$allowedUserIds})",
          "{$wpdb->prefix}amazon_payments" => "buyer_userid IN ({$allowedUserIds})",
          "{$wpdb->prefix}amazon_reports" => 'id = 0',
          "{$wpdb->prefix}amazon_stock_log" => 'id = 0',
        ]);
      }

      // Remove wp-lister-ebay related entries.
      if (is_plugin_active('wp-lister-ebay/wp-lister-ebay.php')) {
        $tableWheres = array_merge($tableWheres, [
          "{$wpdb->prefix}ebay_accounts" => 'id = 0',
          "{$wpdb->prefix}ebay_auctions" => 'id = 0',
          "{$wpdb->prefix}ebay_categories" => 'cat_id = 0',
          "{$wpdb->prefix}ebay_jobs" => 'id = 0',
          "{$wpdb->prefix}ebay_log" => 'id = 0',
          "{$wpdb->prefix}ebay_messages" => 'id = 0',
          "{$wpdb->prefix}ebay_orders" => "buyer_userid IN ({$allowedUserIds})",
          "{$wpdb->prefix}ebay_shipping" => 'service_id = 0',
          "{$wpdb->prefix}ebay_store_categories" => 'cat_id = 0',
        ]);
      }

      // Remove Yoast related entries.
      if (is_plugin_active('wordpress-seo/wp-seo.php')) {
        $tableWheres = array_merge($tableWheres, [
          "{$wpdb->prefix}yoast_indexable" => 'id = 0',
          "{$wpdb->prefix}yoast_indexable_hierarchy" => 'indexable_id = 0',
          "{$wpdb->prefix}yoast_migrations" => 'id = 0',
          "{$wpdb->prefix}yoast_primary_term" => 'id = 0',
          "{$wpdb->prefix}yoast_seo_links" => 'id = 0',
          "{$wpdb->prefix}yoast_seo_meta" => 'object_id = 0',
        ]);
      }

      $dump->setTableWheres($tableWheres);

      // Set target options containing credentials to be emptied before dump.
      $optionsToBlank = apply_filters(static::PREFIX . '/dispose-options', static::DISPOSE_OPTIONS);
      $dump->setTransformTableRowHook(function ($tableName, array $row) use ($optionsToBlank, $wpdb) {
        if ($tableName === "{$wpdb->prefix}options" && isset($row['meta_key']) && in_array($row['meta_key'], $optionsToBlank)) {
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
