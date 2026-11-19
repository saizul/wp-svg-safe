# Examples

- [`svg-safe-config.php`](svg-safe-config.php) - a must-use plugin that shows
  every developer hook: who may upload, extra allowed attributes, removed
  elements, the size limit, logging removals and sanitizing inline SVG from
  your own code.
- [`check-svgs.sh`](check-svgs.sh) - run `wp svg-safe check` over a folder of
  icons in CI and fail when a file contains unsafe content.

The test corpus is a good source of examples too:

- [`tests/fixtures/malicious/`](../tests/fixtures/malicious) - 40 XSS and
  external-reference payloads and what the sanitizer must remove.
- [`tests/fixtures/rejected/`](../tests/fixtures/rejected) - XXE, entity
  expansion and non-SVG documents that are refused outright.
- [`tests/fixtures/valid/`](../tests/fixtures/valid) - real-world icons
  (gradients, filters, masks, sprites, Illustrator classes, embedded PNG) that
  must come out unchanged.
