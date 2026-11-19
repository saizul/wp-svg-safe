# SVG Safe

[![CI](https://github.com/saizul/wp-svg-safe/actions/workflows/ci.yml/badge.svg)](https://github.com/saizul/wp-svg-safe/actions/workflows/ci.yml)
[![Release zip](https://github.com/saizul/wp-svg-safe/actions/workflows/release-zip.yml/badge.svg)](https://github.com/saizul/wp-svg-safe/actions/workflows/release-zip.yml)
[![Latest release](https://img.shields.io/github/v/release/saizul/wp-svg-safe?sort=semver)](https://github.com/saizul/wp-svg-safe/releases)
![PHP](https://img.shields.io/badge/PHP-7.4%20to%208.4-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![Good first issues](https://img.shields.io/github/issues/saizul/wp-svg-safe/good%20first%20issue?label=good%20first%20issues)](https://github.com/saizul/wp-svg-safe/labels/good%20first%20issue)

**Allow SVG uploads in WordPress without allowing stored XSS.** Every SVG and
SVGZ file is run through a strict allowlist sanitizer written in the plugin on
top of PHP's `DOMDocument` (no external library), and only the roles you
choose may upload them.

Plugin slug: `wp-svg-safe` - display name: *SVG Safe*.

- Strips `<script>`, event handlers, `javascript:` and `data:` URLs,
  `<foreignObject>`, external references and unsafe CSS. XXE-safe parsing.
- Real width and height in the media library, previews in the media grid.
- Re-sanitize files that are already in the library with WP-CLI.
- Proven by a test corpus of 40+ malicious payloads and 10 real-world icons.
- No tracking, no remote requests, no upsells.

## Contents

- [Why](#why)
- [Quick start](#quick-start)
- [What the sanitizer does](#what-the-sanitizer-does)
- [Media library](#media-library)
- [WP-CLI](#wp-cli)
- [Hooks for developers](#hooks-for-developers)
- [FAQ](#faq)
- [Development and testing](#development-and-testing)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [Security and license](#security-and-license)

## Why

WordPress blocks SVG uploads because an SVG is an XML document that can carry
JavaScript. Open `https://example.org/wp-content/uploads/logo.svg` and an
`onload` handler runs on your domain, with your cookies. The usual
workarounds (allowing the MIME type, or a regex "sanitizer") leave that hole
open. SVG Safe parses the file, keeps only what an image needs, and writes the
clean file back before WordPress stores it.

## Quick start

1. Download `wp-svg-safe.zip` from the
   [latest release](https://github.com/saizul/wp-svg-safe/releases) and upload
   it in **Plugins > Add New > Upload Plugin**, or:

   ```sh
   wp plugin install https://github.com/saizul/wp-svg-safe/releases/latest/download/wp-svg-safe.zip --activate
   ```

2. Open **Settings > SVG Safe** and pick the roles that may upload SVG files
   (Administrators and Editors by default).
3. Upload an SVG in **Media > Add New**. It shows in the grid with its real
   size.
4. Clean the SVG files that were already there:

   ```sh
   wp svg-safe sanitize --dry-run   # see what would change
   wp svg-safe sanitize
   ```

## What the sanitizer does

| Input | Result |
| --- | --- |
| `<script>`, `<foreignObject>`, `<iframe>`, `<embed>`, `<object>`, XML Events `<handler>` / `<listener>` | element removed |
| `<animate>`, `<set>`, `<animateMotion>`, `<animateTransform>` (can rewrite `href` to `javascript:`) | element removed |
| `onload`, `onclick`, `ONFOCUS` ... any `on*` attribute | attribute removed |
| `<a href="javascript:...">` | link unwrapped, children kept |
| `href` / `xlink:href` to another file or URL (`<use>`, `<image>`, `<feImage>`) | attribute removed |
| `<image href="data:image/png;base64,...">` (also JPEG, GIF, WebP) | kept |
| `data:image/svg+xml`, `data:text/html` anywhere | removed |
| `fill="url(https://...)"`, `style="fill:url(//...)"` | removed (`url(#id)` is kept) |
| `<style>` with `@import`, `@font-face`, `expression()`, escapes or remote `url()` | stylesheet removed |
| Unknown CSS properties in `style=""` (`behavior`, `position`...) | declaration removed |
| Inkscape / Sodipodi / XHTML namespaced elements and attributes | removed |
| `xml:base`, comments, `<?xml-stylesheet?>`, DOCTYPE | removed |
| `<!ENTITY ...>` (XXE, billion laughs) | **file rejected** |
| Not well-formed XML, root element not `<svg>` | **file rejected** |
| Larger than the limit, or an SVGZ that inflates past it (gzip bomb) | **file rejected** |

Everything else an icon or illustration needs stays: shapes, paths, text,
gradients, patterns, clip paths, masks, markers, symbols and `<use>`, filters,
`<style>` with classes, `aria-*` attributes, `<title>` and `<desc>`. The
sanitizer is idempotent and the full lists are in
[`src/Sanitizer.php`](src/Sanitizer.php).

The XML is parsed with `LIBXML_NONET`, without entity substitution or DTD
loading.

## Media library

- Width and height are read from `width` / `height` (px, pt, pc, in, cm, mm,
  em) and `viewBox`, and stored in the attachment metadata, so
  `wp_get_attachment_image()` prints correct `width` and `height` attributes.
- `wp_get_attachment_image_src( $id, 'thumbnail' )` returns the SVG URL with
  dimensions scaled to fit that size (no upscaling).
- The media grid and modal get a preview and sizes for every registered image
  size.
- SVGZ files are decompressed, sanitized and compressed again. Switch SVGZ
  off in the settings if your server does not send
  `Content-Encoding: gzip` for `.svgz`.

## WP-CLI

```sh
# Re-sanitize every SVG attachment and refresh its width and height.
wp svg-safe sanitize
wp svg-safe sanitize --dry-run --format=json
wp svg-safe sanitize 42 57

# Check a local file (exit code 1 if anything unsafe is found).
wp svg-safe check assets/icons/logo.svg
wp svg-safe check logo.svg --output=logo.svg   # fix in place
```

Example output:

```
+----+-----------+-----------+----------------------------------+
| id | file      | status    | removed                          |
+----+-----------+-----------+----------------------------------+
| 12 | logo.svg  | clean     |                                  |
| 31 | badge.svg | sanitized | attribute:onload, element:script |
+----+-----------+-----------+----------------------------------+
Success: 1 clean, 1 sanitized, 0 failed.
```

## Hooks for developers

| Hook | Type | Use |
| --- | --- | --- |
| `svg_safe_user_can_upload` | filter `( bool $allowed, array $settings )` | Final say on who may upload SVG files. |
| `svg_safe_allowed_elements` | filter `( string[] $elements )` | Add or remove elements. Dangerous ones can never be added. |
| `svg_safe_allowed_attributes` | filter `( string[] $attributes )` | Add or remove attributes. `on*` can never be added. |
| `svg_safe_max_bytes` | filter `( int $bytes )` | Size limit (inflated size for SVGZ). |
| `svg_safe_capability` | filter `( string $cap )` | Capability for the settings screen (`manage_options`). |
| `svg_safe_removed` | action `( string[] $removed, string $filename )` | Log or report what was removed from an upload. |

Sanitize markup from your own code:

```php
$result = \Saizul\SvgSafe\Plugin::instance()->sanitizer()->sanitize( $markup );
if ( $result->ok() ) {
	echo $result->svg(); // Clean SVG.
	print_r( $result->removed() ); // e.g. [ 'element:script', 'attribute:onload' ]
} else {
	echo $result->error(); // too_large, empty, entities, parse, root
}
```

The sanitizer class has no WordPress dependency (`new \Saizul\SvgSafe\Sanitizer()`).
See [`examples/`](examples) for a complete must-use plugin.

## FAQ

**Is an SVG in an `<img>` tag not safe anyway?**
Scripts do not run in `<img>`, but they do when the file is opened directly,
embedded with `<object>` or `<iframe>`, or inlined by a theme or page builder.
Uploaded files must be safe in every context.

**Why was my `<a>` link removed?**
Links inside SVG images cannot be clicked in an `<img>`, and they are a
classic `javascript:` vector. The link is unwrapped; the shapes inside it stay.

**My icon lost its animation.**
SMIL animation elements can rewrite attributes such as `href` to
`javascript:` URLs, so they are removed. Use CSS animations on the inlined SVG
or a video/Lottie file instead. Safe animation support is on the roadmap.

**An Inkscape file looks the same but is smaller.**
Inkscape and Sodipodi metadata (`sodipodi:namedview`, `inkscape:label`) is
removed. It does not affect rendering.

**What about files that were uploaded before?**
They are not touched until you run `wp svg-safe sanitize`. Run it with
`--dry-run` first.

**Who can upload SVG files?**
Logged-in users with `upload_files` and one of the roles chosen in
Settings > SVG Safe, plus super admins on multisite. Use the
`svg_safe_user_can_upload` filter for anything else.

**Does it work on multisite?**
Yes. Each site has its own settings; uninstall removes them on every site.

**What is removed when I delete the plugin?**
The `wp_svg_safe_settings` option. Uploaded files stay, and they stay
sanitized.

## Development and testing

```sh
composer install
composer test           # PHPUnit on light WordPress stubs, no database needed
composer phpcs          # WordPress Coding Standards and PHP 7.4+ compatibility
composer lint           # php -l on every file
composer make-pot       # regenerate languages/wp-svg-safe.pot
```

Project layout:

```
wp-svg-safe.php             Plugin header and autoloader
uninstall.php               Deletes the option on every site
src/Sanitizer.php           The allowlist sanitizer (pure PHP, no WordPress)
src/Result.php              Sanitizer result: clean markup, removed items, error
src/FileProcessor.php       gzip handling around the sanitizer
src/Svgz.php                gzip detection, capped inflate, deflate
src/Dimensions.php          width/height/viewBox to pixels
src/Uploads.php             upload_mimes, file type check, upload prefilters
src/Media.php               attachment metadata, image_downsize, media grid
src/Permissions.php         role-based upload permission
src/Settings.php            the single option and its sanitizer
src/Admin/SettingsPage.php  Settings > SVG Safe
src/Cli/Command.php         wp svg-safe sanitize | check
tests/fixtures/             malicious, rejected and valid SVG corpus
tests/                      PHPUnit tests and WordPress/WP-CLI stubs
bin/make-pot.php            POT generator
```

CI runs on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4: `composer validate`, `php -l`,
phpcs, PHPUnit and the POT check. Publishing a GitHub release builds
`wp-svg-safe.zip`, smoke-tests the sanitizer from the zip and attaches it to
the release.

Tests run against stubs, not a real WordPress install. The sanitizer tests
need no stubs at all: every file in `tests/fixtures/malicious/` must come out
passing an independent safety check, every file in `tests/fixtures/rejected/`
must be refused, and every file in `tests/fixtures/valid/` must come out
unchanged (compared with canonical XML).

## Roadmap

Items marked **good first issue** are small and well scoped; see
[CONTRIBUTING.md](CONTRIBUTING.md#good-first-issues).

- Add more payloads to the malicious corpus - **good first issue**
- Add icons exported from Figma, Sketch and Affinity to the valid corpus - **good first issue**
- Remove unused namespace declarations from the output - **good first issue**
- "Sanitized" note with the removed items in the attachment details - **good first issue**
- `wp svg-safe sanitize --since=<date>` - **good first issue**
- Optional minification (strip whitespace and metadata) - **good first issue**
- Settings screen button to re-sanitize the library in batches (without WP-CLI)
- Safe subset of SMIL animation (`animateTransform` with numeric values)
- Inline SVG block that uses the sanitizer
- Integration tests against a real WordPress install (wp-env) in CI

## Contributing

Contributions of every size are welcome: new payloads for the corpus, bug
reports, tests, docs, translations and code. Start with
[CONTRIBUTING.md](CONTRIBUTING.md), which has the setup, the coding standards
and a list of beginner tasks, and please follow the
[Code of Conduct](CODE_OF_CONDUCT.md). Report sanitizer bypasses privately
(see below).

## Security and license

- [SECURITY.md](SECURITY.md): please report vulnerabilities privately.
- [CHANGELOG.md](CHANGELOG.md).

SVG Safe is free software under the [GPL-2.0-or-later](LICENSE).
Copyright (c) 2026 Saizul Amin ([saizul.com](https://saizul.com)).
