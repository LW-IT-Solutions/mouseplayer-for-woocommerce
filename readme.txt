=== MousePlayer for WooCommerce ===
Contributors: lwitsolutions
Tags: woocommerce, session replay, heatmaps, conversion funnel, analytics
Requires at least: 6.5
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Session replay and heatmaps for WooCommerce, with consent handling, masked checkout pages and the six steps of the ecommerce funnel.

== Description ==

[MousePlayer](https://www.mouseplayer.com/) records how visitors use a website and lets you watch the sessions back, with heatmaps, funnels and a timeline of what happened. This plugin connects a WooCommerce shop to MousePlayer without editing the theme.

**What the plugin does**

* **Adds the MousePlayer snippet** to every page of the shop once you enter the Channel ID of your MousePlayer project and switch tracking on.
* **Waits for consent.** The MousePlayer script, loaded from mouseplayer.com, records nothing until it receives a positive consent signal, and the plugin hands shop events to it only after that. Choose how the signal comes: your consent tool blocks the snippet until the visitor agrees (Consented.eu, Usercentrics, Cookiebot, OneTrust, consentmanager, Borlabs Cookie, Complianz), the WP Consent API, or automatic detection and your own banner. The MousePlayer script treats a Global Privacy Control signal of the browser as no consent.
* **Masks checkout, order confirmation, order tracking and My Account.** The content of these pages is replaced by asterisks in the browser before anything is sent: names, addresses, email addresses and order details stay in the shop. Optionally the cart page as well. Works with the classic shortcodes and with the cart, checkout and order confirmation blocks.
* **Keeps typing out of the recording.** Typing in checkout, address and account fields (classic and block checkout, login, registration, account details, addresses, order tracking) is not recorded at all. Independently of this plugin, MousePlayer never records what is typed into password or payment fields, and the card fields of most payment services run in their own frames.
* **Sends the six ecommerce events** that make up the Standard Ecommerce Funnel in MousePlayer: `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `begin_checkout` and `purchase`, with prices, order value and currency if you want. `add_to_cart` works on product pages, with AJAX buttons and with the product blocks. `purchase` is sent once per order, only for orders that were not cancelled or failed.
* **Sends the page context** (page type, product ID, category, stock status, cart size; with prices switched on also product price, cart value and currency), which MousePlayer uses to describe sessions.
* **Excludes your team.** Logged-in users with the roles you choose (by default administrators and shop managers) are never recorded.
* **Shows the status** of the setup and checks the connection on request. With a read-only API key (Business and Enterprise plans) the check confirms the project, its domain and the latest recording.
* **Helps with the domain verification** of MousePlayer by adding the verification meta element to the home page.
* **Helps with your privacy policy**: a suggested text in Settings > Privacy > Policy guide, and the shortcode `[mouseplayer_privacy_link]`, which links visitors to the MousePlayer page that shows the data recorded about their browser.

You need a MousePlayer account (a free plan is available) and a project for the domain of your shop.

== External services ==

This plugin connects your shop to **MousePlayer**, a session replay and analytics service operated by LW IT Solutions Company Lukas Wójcik, Łódź, Poland (www.mouseplayer.com). It is needed for the purpose of the plugin: recording sessions and showing them to you in MousePlayer.

**What is sent and when**

* No visitor data is sent while tracking is switched off or no Channel ID is set. The only request to MousePlayer then is "Check connection", and only when you click it (see below).
* When tracking is on, every front-end page of the shop loads the MousePlayer script from `https://www.mouseplayer.com/mp.js` (or the MousePlayer host you entered), unless the visitor is a logged-in user with an excluded role. Loading the script transmits the visitor's IP address and browser information to MousePlayer. If you choose the blocking option, the browser loads the script only after the visitor has agreed in your consent tool; with the WP Consent API and with automatic detection the script loads with the page and waits for a consent signal before it records.
* After the visitor has consented, the script sends the recording to MousePlayer: page addresses, the content of the pages as the visitor sees them (masked areas replaced by asterisks), clicks, taps, scrolling, mouse movements, window size, browser and device characteristics, the referrer, and a random visitor ID stored in the browser's local storage. Depending on your settings the plugin hands it the shop events with product IDs, SKUs, variation IDs, product names, the name of the product list or category, quantities and numbers of items, prices, order number, order value and currency, the page context (with prices switched on including product price, cart value and currency), and a pseudonymous customer ID for logged-in customers (derived from the user ID and a secret key of your site, never the email address). Once events have been handed over, the plugin keeps their IDs in the browser's session storage (key `mouseplayer_wc_sent`, until the tab is closed) so that none is sent twice.
* When you click "Check connection" on the settings page, your server sends a HEAD request to `https://www.mouseplayer.com/mp.js` (or the MousePlayer host you entered) to see whether the script is reachable. If you saved an API key, it also calls the MousePlayer REST API (`/api/v1/projects` and `/api/v1/projects/<channel ID>/sessions`) with that key and your Channel ID. Like every request WordPress makes, these carry your site address in the user agent. No visitor data is sent by this check.
* The shortcode `[mouseplayer_privacy_link]` links to `https://www.mouseplayer.com/my-data`; the visitor's browser opens it only when the visitor clicks the link.

Terms of service: https://www.mouseplayer.com/en/company/terms-of-service
Privacy policy: https://www.mouseplayer.com/en/company/privacy-policy

You are responsible for a legal basis for this processing (usually the visitor's consent) and for describing it in your own privacy policy.

== Installation ==

1. Install and activate the plugin. WooCommerce must be active.
2. In MousePlayer, create a project for the domain of your shop and copy its Channel ID (Project > Code).
3. Go to WooCommerce > MousePlayer, paste the Channel ID and choose how consent is given.
4. Tick "Record sessions on this shop" and save.
5. Open the shop in a private window, accept the consent banner and click around. The session appears in MousePlayer within a few seconds.

== Frequently Asked Questions ==

= Why do I not see my own visits? =

Logged-in administrators and shop managers are excluded by default. Test in a private window without logging in, and accept the consent banner there.

= Nothing is recorded. What should I check? =

First the status box on WooCommerce > MousePlayer, then "Check connection". The most common causes: no consent signal arrives (choose the right consent option, and in your consent tool make sure MousePlayer belongs to the category the visitor accepted), the MousePlayer project is registered for another domain, or the browser sends a Global Privacy Control signal.

= Which consent tools are supported? =

Blocking until consent is prepared for Consented.eu, Usercentrics, Cookiebot, OneTrust, consentmanager, Borlabs Cookie and Complianz. Automatic detection reads the decision from the same tools. Consent plugins that support the WP Consent API work through the statistics category. With your own banner, call `MousePlayer.setConsent(true)` after consent and `MousePlayer.setConsent(false)` on withdrawal, or push `['consent', true]` to `window.mp_q`.

= Are payment details or passwords recorded? =

No. MousePlayer never records what is typed into password or payment fields; such input is replaced by asterisks before it is sent. Card fields of payment services such as Stripe or PayPal usually run in frames of the payment service and are not part of the recording. With the default settings the checkout and account pages are masked and typing in shop forms is not recorded at all.

= Can I mask other parts of the shop? =

Yes. Add the attribute `data-mp-mask` to any element to replace its content with asterisks in the recording, and `data-mp-ignore` to a form field to keep its input out of the recording.

= Will reloading the order confirmation page count the order twice? =

No. Each order is marked when its confirmation page is shown for the first time, and a reload does not send the purchase again. If the visitor has not consented on that first view, the purchase is not sent at all. Failed, cancelled and unpaid pending orders are not reported; the filter `mouseplayer_wc_purchase_statuses` changes the list.

= Does it work with page caching? =

Yes for product, category and other public pages. Cart, checkout and the order confirmation must not be cached, which is the default of WooCommerce and of common caching plugins.

= Does it work with the block checkout and HPOS? =

Yes. The plugin declares compatibility with the cart and checkout blocks and with order tables (HPOS).

= How can I switch tracking off for some requests? =

Use the filter `mouseplayer_wc_is_active`, for example `add_filter( 'mouseplayer_wc_is_active', '__return_false' );` on a staging site.

== Screenshots ==

1. Settings page with status, connection check and connection settings.
2. Consent, privacy and masking options.
3. Shop events and domain verification.

== Changelog ==

= 1.2.0 =
* Consent: a time line per consent option shows when the MousePlayer script loads (and with it the IP address and browser information) and when recording starts.
* Privacy: a preview of what a recording shows of the checkout and the cart follows the masking options, including typing that is not recorded.
* Do not record: every role shows its number of user accounts, and a bar counts the accounts that are never recorded. The checkbox has room again and the text has the size of the rest of the page.
* Data sent with events: an example of a product page view shows which keys are sent and which are left out with the current settings.
* Funnel: a click on a step switches its event, a tooltip names the event and where it is sent, and the bars grow once when they come into view (not with reduced motion).
* Select fields no longer put their arrow right next to the text. No change to what is sent.

= 1.1.0 =
* Settings page: a status badge next to the title, tiles for connection, consent, privacy and shop events, and a section menu that stays in view.
* Every settings section is a card with an icon; the save button stays reachable at the bottom while scrolling.
* Shop events: a funnel diagram of the six steps that follows the checkboxes and shows which steps are switched off.
* Consent options as selectable cards, tracking as a switch, roles as chips; the Channel ID is checked while typing with the rule used on saving.
* Results of the connection check are coloured by status and show how long ago the check ran; the button shows that the check is running.
* Layout for phones and keyboard focus improved. No change to what is sent.

= 1.0.1 =
* Readme and settings texts describe exactly what is sent and when: which requests the MousePlayer script makes, what the plugin hands to it, and that Check connection contacts MousePlayer on click.
* mp.js is loaded with wp_enqueue_script (async) unless a consent tool has to block the snippet.
* Typing is kept out of the recording in all checkout and account forms, also without masking.
* Event IDs are kept in the session storage only after the event has been handed to MousePlayer.
* Answers of the MousePlayer API and add to cart data are sanitized where they are read.
* The API key is stored in its own option that is not autoloaded. Uninstalling cleans every site of a network and all order marks.

= 1.0.0 =
* First release on WordPress.org.
* Consent options: blocking by the consent tool, WP Consent API, automatic detection.
* Masking of checkout, order confirmation, order tracking and My Account (classic and block), optional for the cart.
* Typing in shop forms is not recorded.
* Six ecommerce funnel events; add_to_cart also for AJAX buttons and product blocks; purchase once per order and not for failed or cancelled orders.
* Page context for MousePlayer.
* Excluded roles, status box, connection check with optional API key, domain verification, privacy policy text and privacy link shortcode.

== Upgrade Notice ==

= 1.2.0 =
Consent time line, masking preview, event example and account counts per role. No change to what is sent.

= 1.1.0 =
Clearer settings page with status tiles and a funnel diagram. No change to what is sent.

= 1.0.1 =
Exact texts about what is sent and when, mp.js loaded through wp_enqueue_script, typing kept out of all checkout and account forms. Your settings are kept.

= 1.0.0 =
First release.
