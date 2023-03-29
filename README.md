# makers99/wp-cli-db-export-clean

Adds the WP-CLI command `wp db export-clean` to create a MySQL database dump
without sensitive data related to customers and optionally API secrets/credentials,
while retaining all administrative users and their related data.

Revisions are excluded as well to minimize the dump file size.

Quick links: [Usage](#usage) | [Integration](#integration) | [Installation](#installation) | [Support](#support)

## Usage

```
wp db export-clean [<filepath>] [--remove-keys]
```

### Arguments

The command accepts the result filename as argument. If omitted, it defaults to
`./clean.sql`.

### Options

|Option|Description|Default|
|------|-----------|-------|
|`--remove-keys`|Additionally remove options containing license keys and API credentials during dump.|`false`|


### Examples

- Create clean database dump in `./clean.sql`:
    ```console
    $ wp db export-clean
    ```

- Create clean database dump in the user's home directory:
    ```console
    $ wp db export-clean ~/clean.sql
    ```

- Exclude plugin license keys and API keys in the clean database dump – useful when working with plugin vendor support or unknown freelancers:
    ```console
    $ wp db export-clean --remove-keys
    ```




## Integration

### Supported plugins

- WordPress Core (keeping only posts and comments from retained users, omitting revisions, transients and caches)
- [WooCommerce](https://wordpress.org/plugins/woocommerce/) (only orders from retained users, omitting scheduled actions and sessions)
- [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) (only subscriptions from retained users)
- [Gravityforms](https://www.gravityforms.com/) (omitting revisions, entries, and statistics)
- [wp-lister-amazon](https://www.wplab.com/plugins/wp-lister-for-amazon/), [wp-lister-ebay](https://www.wplab.com/plugins/wp-lister-for-ebay/) (omitting feeds, jobs, logs)
- [Yoast wordpress-seo](https://wordpress.org/plugins/wordpress-seo/) (omitting index tracking, migrations, links)

### Placing the filter hooks

`wp db export-clean` runs directly after WordPress is loaded (like `wp db export`), which means that only wp-config.php and plugins are loaded but WordPress is not bootstrapped with init hooks.  You can place your hooks into a must-use plugin; for example:

`wp-content/mu-plugins/wp-cli-db-export-clean.php`:
```php
<?php

/*
  Plugin Name: wp db export-clean Customizations
  Version: 1.0.0
  Description: Includes test users in the clean database dump.
*/

add_filter('wp-db-export-clean/allowed-emails', function ($allowed_emails) {
  return array_unique(array_merge($allowed_emails, [
    'test@example.com',
  ]));
});
```

### Including more users in the database export

`wp db export-clean` only includes users having the role Administrator by default. Use the filter hook `'wp-db-export-clean/allowed-emails'` to include more users in the database dump:

```php
/**
 * Customizes list of email addresses to retain in clean database dump.
 *
 * @return array
 *   An array whose items are email addresses to keep.
 */
add_filter('wp-db-export-clean/allowed-emails', function ($allowed_emails) {
  global $wpdb;
  $users = $wpdb->get_col(
    $wpdb->prepare("SELECT u.user_email FROM {$wpdb->prefix}users u WHERE u.user_email LIKE '%%%s'", '@example.com')
  );
  return array_unique(array_merge($allowed_emails, $users));
});
```
In addition, you can include users by ID:
```php
/**
 * Customizes list of user IDs to retain in clean database dump.
 *
 * @return array
 *   An array whose items are user IDs to keep.
 */
add_filter('wp-db-export-clean/allowed-user-ids', function ($allowedUserIds) {
  $allowedUserIds[] = 123;
  $allowedUserIds[] = 456;
  return array_unique($allowedUserIds);
});
```

### Including more WooCommerce orders or subscriptions in the database export

```php
/**
 * Customizes list of shop order/subscription IDs to retain in clean database dump.
 *
 * @return array
 *   An array whose items are shop_order IDs to keep.
 */
add_filter('wp-db-export-clean/allowed-order-ids', function ($allowedOrderIds) {
  $allowedOrderIds[] = 123456;
  return array_unique($allowedOrderIds);
});
```

### Excluding more/custom data in the database export

Implement the following filter hook to customize the where conditions for all tables.

```php
/**
 * Customizes select query conditions for each table in clean database dump.
 *
 * @return array
 *   An array whose keys are table names and whose values are SQL WHERE clause conditions.
 */
add_filter('wp-db-export-clean/table-wheres', function ($tableWheres) {
  global $wpdb;

  $tableWheres = array_merge($tableWheres, [
    "{$wpdb->prefix}my_log" => '1 = 0',
    "{$wpdb->prefix}my_userdata" => "user_id IN ({$allowedUserIds})",
  ]);
  return $tableWheres;
});
```

### Excluded licenses and API keys

When passing the `--remove-keys` option, the following plugins are currently supported:

- WooCommerce (PayPal)
- [WooCommerce PayPal Plus](https://www.angelleye.com/product/woocommerce-paypal-plus-plugin/)
- [WooCommerce PayPal Express Checkout](https://woocommerce.com/document/paypal-express-checkout/) (woocommerce-gateway-paypal-express-checkout)
- [wp-lister-amazon](https://www.wplab.com/plugins/wp-lister-for-amazon/)
- [wp-lister-ebay](https://www.wplab.com/plugins/wp-lister-for-ebay/)
- [Gravityforms](https://www.gravityforms.com/)
- [WooThemes](https://www.woothemes-plugins.com/) (wp-all-import, wp-all-export)
- [Optimus](https://optimus.io/)
- [Elementor](https://elementor.com/)
- [SearchWP](https://searchwp.com/)
- [Gravityforms Zero Spam](https://www.gravityforms.com/add-ons/zero-spam/)
- [WooCommerce Amazon Payments](https://wordpress.org/plugins/woocommerce-gateway-amazon-payments-advanced/)


## Installation

### Install as package

1. To install the latest version of this package for the current user:
    ```sh
    wp package install makers99/wp-cli-db-export-clean
    ```

### Install as Git submodule

1. Add the package as submodule.
    ```sh
    git submodule add --name wp-cli-db-export-clean git@github.com:makers99/wp-cli-db-export-clean.git .wp-cli/packages/db-export-clean
    ```

2. Register the command for early WP-CLI bootstrap.
    ```sh
    echo -e "require:\n  - .wp-cli/packages/db-export-clean/package.php" >> wp-cli.yml
    ```
    Or manually:
    ```sh
    vi wp-cli.yml
    ```
    ```yaml
    require:
      - .wp-cli/packages/db-export-clean/plugin.php
    ```

### Install with Composer

1. Install the package with Composer.
    ```sh
    composer config repositories.wp-cli-db-export-clean git https://github.com/makers99/wp-cli-db-export-clean.git
    composer require makers99/wp-cli-db-export-clean:dev-master
    ```
    Note: Do not use `--dev` to install as `require-dev`, because export-clean
    is typically used in production.

2. Register the command for early WP-CLI bootstrap.
    ```sh
    echo -e "require:\n  - vendor/makers99/wp-cli-db-export-clean/package.php" >> wp-cli.yml
    ```
    Or manually:
    ```sh
    vi wp-cli.yml
    ```
    ```yaml
    require:
      - vendor/makers99/wp-cli-db-export-clean/package.php
    ```


## Support

### MySQL errors during export

Add to `wp-cli.yml` in your site root folder:
```yml
db export:
  max-allowed-packet: 1G
```


## Come create with us!

Originally authored by [Bogdan Arizancu](https://github.com/bogdanarizancu) and [Daniel Kudwien](https://github.com/sun).

<p align="center">
<a href="https://makers99.com/#jobs"><img src="https://raw.githubusercontent.com/makers99/makers99/main/assets/makers99-github-banner.png" width="100%"></a>
</p>
