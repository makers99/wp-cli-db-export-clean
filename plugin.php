<?php

// phpcs:disable
/*
  Plugin Name: wp db export-clean
  Version: 1.0.0
  Text Domain: wp-db-export-clean
  Description: Adds WP-CLI command `wp db export-clean` to produce a database dump without sensitive data.
  Author: makers99
  Author URI: https://makers99.com
  License: GPL-2.0+
  License URI: https://www.gnu.org/licenses/gpl-2.0
*/
// phpcs:enable

namespace Makers99\WpDbExportClean;

use \WP_CLI;

if (!defined('ABSPATH')) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
  exit;
}

include_once __DIR__ . '/vendor/autoload.php';

add_action('cli_init', function () {
  WP_CLI::add_command('db export-clean', __NAMESPACE__ . '\CliCommand');
});
