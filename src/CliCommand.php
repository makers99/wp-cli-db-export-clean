<?php

/**
 * @file
 * Contains \Netzstrategen\WP_CLI_DB_Clean_Export\CliCommand
 */

namespace Netzstrategen\WP_CLI_DB_Clean_Export;

use \WP_CLI;
use Ifsnop\Mysqldump\Mysqldump as IMysqldump;

/**
 * Adds 'export' WP CLI command.
 *
 * @example wp export-clean export
 */
class CliCommand extends \WP_CLI_Command {

  public function export() {
    global $wpdb;
    $allowedEmails = ['@netzstrategen.com' , '@bnn.de'];
    $droppedUsersIds = implode(',', $wpdb->get_col(
      $wpdb->prepare("SELECT u.ID FROM {$wpdb->prefix}users u WHERE u.user_email NOT REGEXP ('%s')", implode('|', $allowedEmails)
    )));

    try {
      // Add constructor config overwrites.
      $dumpSettings = [
        'include-tables' => [
          "{$wpdb->prefix}users",
          "{$wpdb->prefix}usermeta",
        ],
      ];

      $dump = new IMysqldump('mysql:host=localhost;dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $dumpSettings);

      // Set unnecessary/sensitive data.
      $dump->setTableWheres([
        'wp_users' => "ID NOT IN ({$droppedUsersIds})",
        'wp_usermeta' => "user_id NOT IN ({$droppedUsersIds})",
      ]);

      // Prompt table counts.
      $dump->setInfoHook(function ($object, $info) {
        if ($object === 'table') {
          WP_CLI::log($info['name'] . ' -> ' . $info['rowCount']);
        }
      });

      $dump->start(ABSPATH . 'clean-export.sql');

      WP_CLI::success('Dump file is available at ' . ABSPATH . 'clean-export.sql');
    }
    catch (\Exception $e) {
      WP_CLI::error($e->getMessage());
    }

  }

}
