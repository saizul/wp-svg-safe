# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-19

### Added

- SVG and SVGZ uploads for the roles chosen in Settings > SVG Safe
  (Administrator and Editor by default; users also need `upload_files`).
- Server-side sanitizer written in the plugin on top of `DOMDocument`, with no
  external library: strict allowlist of SVG elements and attributes; removes
  `<script>`, `<foreignObject>`, animation elements, event handlers,
  `javascript:` / `vbscript:` / `data:` URLs (base64 PNG, JPEG, GIF and WebP
  stay allowed on `<image>`), external references in `href` and `url()`,
  unsafe CSS, comments and processing instructions. `<a>` is unwrapped.
- XXE-safe parsing: `LIBXML_NONET`, no entity substitution or DTD loading;
  documents that declare entities are rejected.
- Every SVG upload and sideload is sanitized, whoever uploads it, including
  REST API uploads.
- SVGZ handling: decompressed with a size cap (gzip bomb protection),
  sanitized and compressed again.
- Correct width and height from `width`, `height` (px, pt, pc, in, cm, mm)
  and `viewBox` in attachment metadata and `wp_get_attachment_image_src()`.
- Media grid and modal previews with sizes for every registered image size.
- WP-CLI: `wp svg-safe sanitize [<id>...] [--dry-run]` re-sanitizes existing
  attachments and refreshes their dimensions; `wp svg-safe check <file>
  [--output=<file>]` for build scripts.
- Filters: `svg_safe_user_can_upload`, `svg_safe_allowed_elements`,
  `svg_safe_allowed_attributes`, `svg_safe_max_bytes`, `svg_safe_capability`;
  action `svg_safe_removed`.
- Test corpus of 40 malicious payloads, 7 rejected documents and 10 valid
  icons; PHPUnit on WordPress stubs, phpcs, CI on PHP 7.4 to 8.4 and a
  release workflow that builds the plugin zip.

[Unreleased]: https://github.com/saizul/wp-svg-safe/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/saizul/wp-svg-safe/releases/tag/v0.1.0
