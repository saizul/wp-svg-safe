=== SVG Safe ===
Contributors: saizul
Tags: svg, svg upload, sanitize, security, media library
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow SVG and SVGZ uploads safely: role-based permission, a strict allowlist sanitizer, real dimensions, media grid previews and WP-CLI.

== Description ==

SVG files can carry JavaScript. SVG Safe lets the roles you choose upload SVG and SVGZ files and sanitizes every one of them on the server before WordPress stores it. The sanitizer is written in the plugin on top of PHP's DOMDocument, with no external library.

**Removed**

* script, foreignObject, iframe, embed, object and animation elements.
* Every event handler attribute (onload, onclick and so on).
* javascript:, vbscript: and data: URLs (base64 PNG, JPEG, GIF and WebP images stay allowed).
* External references in href, xlink:href and url().
* Unsafe CSS: @import, expression(), escapes, remote url().
* Comments, processing instructions, the DOCTYPE and editor metadata.

**Rejected**

* Files that declare XML entities (XXE, billion laughs).
* Files that are not well-formed XML or whose root is not svg.
* Files larger than the limit, and SVGZ files that inflate past it.

**Media library**

* Real width and height in the attachment metadata, from width, height and viewBox.
* Correct sizes from wp_get_attachment_image_src() and previews in the media grid.

**WP-CLI**

* wp svg-safe sanitize re-sanitizes existing SVG attachments and refreshes their dimensions.
* wp svg-safe check file.svg checks a local file, for build scripts.

No tracking, no remote requests, no upsells.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > SVG Safe and choose the roles that may upload SVG files.
4. Run wp svg-safe sanitize to clean SVG files that were already in the media library.

== Frequently Asked Questions ==

= Why was my animation or link removed? =

SMIL animation and links are classic script vectors in SVG files, so they are removed. The shapes inside links are kept.

= Are old uploads sanitized? =

Only when you run wp svg-safe sanitize. Use --dry-run first to see what would change.

= Who can upload SVG files? =

Logged-in users with the upload_files capability and one of the roles chosen in the settings, and super admins on multisite. Developers can change this with the svg_safe_user_can_upload filter.

= What is removed when I delete the plugin? =

The wp_svg_safe_settings option, on every site of a network. Uploaded files stay.

== Screenshots ==

1. Settings > SVG Safe.
2. SVG files with previews in the media grid.

== Changelog ==

= 0.1.0 =
* First release: role-based SVG and SVGZ uploads, allowlist sanitizer with XXE-safe parsing, gzip bomb protection, real dimensions, media grid previews, WP-CLI sanitize and check commands, developer hooks.

== Upgrade Notice ==

= 0.1.0 =
First release.
