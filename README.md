# MousePlayer for WooCommerce

Session replay for WooCommerce, with the GA4-style ecommerce events wired up so the
MousePlayer conversion funnel fills itself.

Sends `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `begin_checkout`
and `purchase` — the six steps of the Standard Ecommerce Funnel.

## Install

Download the latest `mouseplayer-for-woocommerce.zip` from
[Releases](../../releases) and upload it under **Plugins → Add New → Upload Plugin**.

Then go to **Settings → MousePlayer**, paste your Channel ID
(MousePlayer → Project → Code) and enable tracking.

## Requirements

* WordPress 5.8+
* PHP 7.4+
* WooCommerce 6.0+ (only for the ecommerce events; replay works without it)

## Consent

Nothing is sent until a Channel ID is set and tracking is switched on.
To gate it behind a consent management platform:

```php
add_filter( 'mouseplayer_wc_is_active', function ( $active ) {
    return my_cmp_has_analytics_consent();
} );
```

## Building a release

```bash
zip -r mouseplayer-for-woocommerce.zip mouseplayer-for-woocommerce \
  -x '*.git*' '*.DS_Store' '*node_modules*'
```

The zip must contain a single top-level folder named `mouseplayer-for-woocommerce`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
