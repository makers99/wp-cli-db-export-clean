<?php

// phpcs:disable
/*
  Plugin Name: WordPress CLI clean database export.
  Version: 1.0.0
  Text Domain: wp-cli-db-clean-export.
  Description: Adds wp cli command `export-clean` and produces a mysql dump free of customer related private data and sensitive credentials.
  Author: netzstrategen
  Author URI: https://netzstrategen.com
  License: GPL-3.0
  License URI: http://choosealicense.com/licenses/gpl-3.0/
*/
// phpcs:enable

namespace Netzstrategen\WP_CLI_DB_Clean_Export;

if (!defined('ABSPATH')) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
  exit;
}

use \WP_CLI;

/**
 * Loads PSR-4-style plugin classes.
 */
function classloader($class) {
  static $ns_offset;
  if (strpos($class, __NAMESPACE__ . '\\') === 0) {
    if ($ns_offset === NULL) {
      $ns_offset = strlen(__NAMESPACE__) + 1;
    }
    include __DIR__ . '/src/' . strtr(substr($class, $ns_offset), '\\', '/') . '.php';
  }
}

spl_autoload_register(__NAMESPACE__ . '\classloader');

require __DIR__ . '/vendor/autoload.php';

add_action('cli_init', function() {
  WP_CLI::add_command('export-clean', __NAMESPACE__ . '\CliCommand');
});
