=== MousePlayer for WooCommerce ===
Contributors: mouseplayer
Tags: woocommerce, session replay, analytics, funnel, ecommerce
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Session replay for WooCommerce, with the GA4-style ecommerce events wired up so your conversion funnel fills itself.

== Description ==

MousePlayer records how people actually use your shop and lets you watch it back. This plugin does two things:

1. Loads the MousePlayer tracking script, so you get session recordings and heatmaps without editing your theme.
2. Sends the six GA4-style ecommerce events — `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `begin_checkout`, `purchase` — which are exactly the steps of the Standard Ecommerce Funnel in MousePlayer. You see where people drop out, and you can watch the recordings of the ones who did.

You need a MousePlayer account and a Channel ID. Every event can be switched off individually.

= External service =

This plugin sends data to MousePlayer, a third-party service, so that your visitors' sessions can be recorded and analysed. **Nothing is transmitted until you enter a Channel ID and switch tracking on.**

Transmitted per visit: page URLs, interaction events (clicks, scrolling, mouse movement), browser and device characteristics, and — if you enable the matching options — order value, currency and a hashed reference to the logged-in customer. The raw email address is never sent.

* Service: MousePlayer — https://mouseplayer.com
* Privacy policy: https://mouseplayer.com/privacy
* Terms of service: https://mouseplayer.com/terms

You are responsible for having a legal basis for this processing and for disclosing it in your own privacy policy. If you use a consent management platform, gate the plugin with the `mouseplayer_wc_is_active` filter.

== Installation ==

1. Install and activate the plugin.
2. Go to Settings → MousePlayer.
3. Paste your Channel ID (MousePlayer → Project → Code).
4. Tick "Enable MousePlayer on this store" and save.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Session replay works without it. The ecommerce events need WooCommerce active.

= Will refreshing the thank-you page count the order twice? =

No. Each order is marked once it has been reported, so a refresh does not send a second purchase.

= How do I stop tracking for visitors who have not consented? =

Use the filter:

`add_filter( 'mouseplayer_wc_is_active', function ( $active ) { return my_cmp_has_consent(); } );`

= Are my own visits recorded? =

Not by default. Administrators and shop managers are excluded unless you turn that off.

== Changelog ==

= 1.0.0 =
* First release: script injection, settings screen, and the six ecommerce funnel events.
