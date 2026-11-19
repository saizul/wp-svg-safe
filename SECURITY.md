# Security policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | yes       |

## Reporting a vulnerability

Please do **not** open a public issue, and do not attach a working payload to
one. Use GitHub's private vulnerability reporting ("Security" tab, "Report a
vulnerability") on <https://github.com/saizul/wp-svg-safe>.

Include the affected version, the SVG that gets through (or the steps to
reproduce) and where it executes (opened directly, in an `<img>`, inlined by a
theme). You can expect an acknowledgement within 7 days. Please give us
reasonable time to release a fix before disclosing the issue.

**A sanitizer bypass is a security bug**: any SVG that comes out of the
sanitizer and can run script, load an external resource, read local files or
exhaust memory. Fixes come with a new file in `tests/fixtures/malicious/` or
`tests/fixtures/rejected/`.

## How the plugin protects your site

- **Allowlist, not blocklist**: only known SVG elements and attributes are
  kept. Everything else, including elements in other namespaces, is removed.
  `script`, `foreignObject`, animation elements, `iframe`, `embed`, `object`
  and event handlers (`on*`) can never be allowed, even through the filters.
- **References**: `href` / `xlink:href` may only point to `#fragment` in the
  same file (and to base64 PNG, JPEG, GIF or WebP data on `<image>`). Every
  `url()` in attributes and CSS must point to a fragment. `javascript:`,
  `vbscript:` and `data:` are removed after stripping whitespace and control
  characters; CSS with backslash escapes, `@import`, `expression()` or
  comments that could split keywords is removed.
- **XML parsing**: `LIBXML_NONET`, no entity substitution, no DTD loading; on
  PHP 7 the entity loader is disabled while parsing. Any document with an
  `<!ENTITY` declaration is rejected (XXE, billion laughs). Comments,
  processing instructions (`xml-stylesheet`) and the DOCTYPE are removed.
- **Size limits**: the file size is capped (Settings > SVG Safe, filterable);
  SVGZ files are inflated with the same cap, which stops gzip bombs.
- **Every upload**: the sanitizer runs on `wp_handle_upload_prefilter` and
  `wp_handle_sideload_prefilter` for every file with an SVG extension or MIME
  type, also when another plugin allowed the type, and also for users who are
  not allowed to upload SVG (their upload is then refused by WordPress).
- **Permission**: SVG MIME types are only added for logged-in users with
  `upload_files` and one of the chosen roles. The settings screen requires
  `manage_options` and uses the Settings API (nonce and capability checked by
  `options.php`). Output is escaped.
- **No remote requests**, no tracking, no external libraries.

### Limits

- Files that were uploaded before the plugin was active, or copied over
  FTP/SFTP, are not sanitized until you run `wp svg-safe sanitize`.
- Serve uploads with `X-Content-Type-Options: nosniff`, and consider a
  `Content-Security-Policy` for the uploads folder as a second layer.
- The sanitizer makes SVG files safe to display. It does not make an SVG
  inlined into a page (for example with `file_get_contents()` in a theme)
  safe from CSS that styles the rest of that page; use the sanitized output
  and scope your CSS.
