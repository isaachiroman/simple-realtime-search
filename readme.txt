=== Simple Realtime Search ===
Contributors: isaachiroman
Tags: search, realtime, json, instant search
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 1.2.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Realtime search functionality using a generated JSON file for high performance.

== Description ==

Simple Realtime Search improves your WordPress search experience by generating a JSON file of your content. This allows for instant, real-time search results without querying the database on every keystroke.

Features:
*   Realtime search results.
*   Two display modes: Inline Dropdown and Popup.
*   Supports Posts, Pages, and Custom Post Types.
*   WooCommerce product support (displays price).
*   Fast and lightweight.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/simple-realtime-search` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to **Settings > Simple Realtime Search** to configure options.
4.  Select the post types you want to include and click **Generate Data**.

== Screenshots ==

1.  Admin Settings Page.
2.  Frontend Search Popup.

== Changelog ==

= 1.2.1 =
*   Admin page colors (switch, radios, checkboxes, icons, notice, progress bar) follow the WordPress admin color scheme instead of the default blue.

= 1.2.0 =
*   Settings below the "Enable Fast Search" switch are locked (dimmed, not clickable, skipped by Tab) while it is off. Values are kept when saving.

= 1.1.1 =
*   Fix: admin button icons sat lower than the label (WP core line-height on button dashicons).

= 1.1.0 =
*   Fix: keyword highlighting broke HTML when one keyword matched inside an inserted tag (e.g. "c" in class=).
*   Fix: accent-insensitive highlighting, HTML-escaped titles, "đ" now matches "d".
*   Performance: merge data by ID (O(n)), titles normalized once on load.
*   Reliability: atomic JSON writes (temp file + rename) under an exclusive lock.
*   JSON URL is versioned with ?v=filemtime; .htaccess adds compression + long cache.
*   Smaller JSON: plain-text prices, unescaped slashes.

= 1.0.0 =
*   Initial release.
