# MousePlayer for WooCommerce

Session replay and heatmaps for WooCommerce, with consent handling, masked checkout pages and the six steps of the ecommerce funnel.

- Adds the MousePlayer snippet to the shop once a Channel ID is entered and tracking is switched on
- Records nothing without consent: blocking by your consent tool, the WP Consent API or automatic detection; Global Privacy Control means no recording
- Masks checkout, order confirmation, order tracking and My Account in the browser before anything is sent (classic and block pages); typing in shop forms is not recorded
- Sends `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `begin_checkout` and `purchase` for the Standard Ecommerce Funnel; `purchase` once per order and not for failed or cancelled orders
- Excludes logged-in team members, shows the setup status and helps with domain verification and the privacy policy

Needs a MousePlayer account (free plan available). Requires WordPress 6.5 and PHP 7.4; the ecommerce events need WooCommerce. Details in [readme.txt](readme.txt).

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
