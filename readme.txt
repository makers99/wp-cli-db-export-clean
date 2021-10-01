=== wp-cli-db-export-clean ===
Contributors: makers99
Tags: wp-cli, woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Adds the WP-CLI command `wp db export-clean` to create a MySQL database dump
without sensitive data related to customers and API secrets/credentials, while
retaining all administrative users and their related data.

Revisions are excluded as well to minimize the size.

The command accepts the result filename as argument. If omitted, it defaults to
`clean.sql`.


== Installation ==

This is meant to be used as a must-use plugin, installation the steps are:

1. Create a folder for must-use plugins.
    ```sh
    mkdir -p wp-content/mu-plugins
    ```

2. CloneClone this repository as a Git submodule.
    ```sh
    git submodule add --name wp-cli-db-export-clean git@github.com:makers99/wp-cli-db-export-clean.git wp-content/mu-plugins/wp-cli-db-export-clean
    ```

3. Create the main mu-plugin file and ensure cloned plugin is available.
    ```sh
    vi wp-content/mu-plugins/wp-cli-db-export-clean.php
    ```
    ```php
    <?php

    /*
      Plugin Name: wp db export-clean
      Description: Adds WP-CLI command `wp db export-clean` to produce a database dump without sensitive data.
      Version: 1.0.0
      Author: makers99
      Author URI: https://makers99.com/
      License: GPL-2.0+
      License URI: http://www.gnu.org/licenses/gpl-2.0
    */

    include_once __DIR__ . '/wp-cli-db-export-clean/plugin.php';
    ```

4. Register the command for early WP-CLI bootstrap.
    ```sh
    vi wp-cli.yml
    ```
    ```yaml
    require:
      - wp-content/mu-plugins/wp-cli-db-export-clean.php
    ```


= Requirements =

* PHP 7.4 or later.

