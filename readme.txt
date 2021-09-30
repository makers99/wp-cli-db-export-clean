=== wp-cli-db-export-clean ===
Contributors: makers99
Tags: wp-cli, woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Adds wp cli command `export-clean` and produces a mysql dump free of customer
related private data and sensitive credentials, while retaining all administrator
users and their related fields.

This accepts a `--file` flag for defining path and filename, if none is provied
it will default to current location and filename `clean-export.sql`.

== Installation ==

This is meant to be used as a must-use plugin, installation the steps are:

1. Create a `wp-content/mu-plugins` folder if it not exists already.
    ```sh
    mkdir wp-content/mu-plugins
    ```
2. Clone this repository in its own foder.
    ```sh
    git submodule add --name wp-cli-db-export-clean -- git@github.com:makers99/wp-cli-db-export-clean.git wp-content/mu-plugins/wp-cli-db-export-clean
    cd wp-content/mu-plugins/wp-cli-db-export-clean
    composer install
    cd ..
    ```
3. Create the main mu-plugin file and ensure cloned plugin is available.
    ```sh
    touch wp-cli-db-export-clean.php
    vi wp-cli-db-export-clean.php
    ```
    The content of `wp-content/mu-plugins/wp-cli-db-export-clean.php` is:
    ```php
    <?php

    /**
     * Plugin Name: WordPress CLI clean database export.
     */

    require_once __DIR__ . '/wp-cli-db-export-clean/plugin.php';
    ```

= Requirements =

* PHP 7.4 or later.

