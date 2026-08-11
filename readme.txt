=== WP Chinese Converter ===
Contributors: wpfanyi
Tags: chinese, traditional chinese, simplified chinese, localization, converter
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.5.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Server-side conversion between Simplified Chinese and Traditional Chinese for WordPress content, links, search, and language variants.

== Description ==

WP Chinese Converter provides server-side conversion between Simplified Chinese and Traditional Chinese variants. It supports language switcher blocks and shortcodes, query-string or permalink language URLs, OpenCC and MediaWiki conversion engines, hreflang output, search conversion, and optional multisite defaults.

Stored-content conversion and full-page conversion are disabled by default. Stored-content conversion changes database content and should only be enabled after a backup has been created.

Primary features:

* Simplified, Traditional, Taiwan, Hong Kong, Singapore, and generic Hans/Hant variants.
* OpenCC phrase conversion and a MediaWiki compatibility engine.
* Gutenberg language switcher, conversion status, and no-conversion blocks.
* `[wp-chinese-converter]`, `[wpcc_nc]`, and `[wpcs_nc]` shortcodes.
* Canonical and hreflang integration with query-string and permalink modes.
* Administrator-only REST conversion endpoints by default.
* Optional network defaults for WordPress multisite.

== Installation ==

1. Upload the `wp-chinese-converter` directory to `/wp-content/plugins/`.
2. Activate WP Chinese Converter from the Plugins screen.
3. Open Settings > WP Chinese Converter and choose the required variants.
4. Save WordPress permalinks after changing the URL mode.

== Frequently Asked Questions ==

= Does conversion overwrite stored posts? =

Normal front-end conversion does not alter stored content. The separate stored-content conversion option does overwrite titles and content, is disabled by default, and creates a protected backup before a changed save.

= Does the REST API allow anonymous conversion? =

No. REST conversion requires the `manage_options` capability by default. Developers may change the required capability with the `wpcc_rest_api_capability` filter.

= Which conversion engine should I use? =

OpenCC provides phrase and regional vocabulary conversion. PHP 8.1 or later is required by the plugin and its locked runtime dependencies.

== Changelog ==

= 1.5.1 =

* Restrict REST conversion to administrators by default and add payload limits.
* Add CSRF protection to settings, cache compatibility, and uninstall actions.
* Fix MediaWiki table loading, engine-aware cache keys, canonical URLs, and sitemap compatibility.
* Add safe defaults, dependency updates, automated tests, and release-package checks.

= 1.1.0 =

* Added OpenCC conversion, Gutenberg blocks, shortcodes, and Hong Kong Traditional Chinese support.

= 1.0.0 =

* Initial release.
