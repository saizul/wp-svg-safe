# Contributing

Thanks for helping improve SVG Safe. New payloads for the test corpus, bug
reports, fixes, tests, documentation and translations are all welcome, and
first-time contributors are especially welcome: the code base is small and
every part has tests.

By taking part you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Ways to help

- **Pick a beginner task** from the list below or from issues labelled
  [`good first issue`](https://github.com/saizul/wp-svg-safe/labels/good%20first%20issue).
  Comment on the issue first so two people do not work on the same thing.
- **Report a valid icon that gets damaged** with the "Bug report" template.
  Attach the SVG (or a minimal version of it) and say which design tool
  exported it.
- **Grow the corpus.** Known-harmless payloads from public XSS cheat sheets
  that are already neutralised can go straight into a pull request.
- **Translate** the plugin: `languages/wp-svg-safe.pot` is the template.

A payload that **gets through** the sanitizer is a security bug: report it
privately as described in [SECURITY.md](SECURITY.md), not in a public issue
or pull request.

## Good first issues

Each task is small, has a clear "done" and touches one or two files.

1. **Add payloads to the malicious corpus.** Add files to
   `tests/fixtures/malicious/` (one vector per file, numbered, with a
   descriptive name). `tests/Unit/SanitizerTest.php` picks them up
   automatically and checks the output with an independent safety check.
2. **Add real-world icons to the valid corpus.** Export icons from Figma,
   Sketch, Affinity Designer or an icon set (with a compatible license) into
   `tests/fixtures/valid/`. They must come out unchanged; if one does not,
   open an issue with the diff.
3. **Remove unused namespace declarations.** After Inkscape attributes are
   removed, `xmlns:inkscape` stays on the root element. Remove declarations
   that no element or attribute uses any more in `Sanitizer::sanitize()` and
   add a test.
4. **Show what was removed in the attachment details.** Store the removed
   items in post meta in `Uploads::prefilter()` / after the attachment is
   created, and print them with the `attachment_fields_to_edit` filter.
   Escape the output.
5. **Add `--since=<date>` to `wp svg-safe sanitize`.** Only process
   attachments uploaded after a date (`date_query` in
   `Command::all_svg_ids()`), with tests in `tests/Unit/CliTest.php`.
6. **Optional minification.** Add a `svg_safe_minify` filter (default
   false) that removes whitespace-only text nodes outside `<text>` and
   `<style>`, with tests that valid icons still render the same (C14N of the
   minified input).
7. **Document the default allowlists.** Generate a Markdown table of
   `Sanitizer::default_elements()` and `default_attributes()` with a small
   script in `bin/` and link it from the README.
8. **Translate the plugin into your language** with Poedit or Loco Translate
   from `languages/wp-svg-safe.pot`, and open a PR with the `.po` file.

## Development setup

```bash
git clone https://github.com/saizul/wp-svg-safe.git
cd wp-svg-safe
composer install
composer test           # PHPUnit on WordPress stubs
composer phpcs          # WordPress Coding Standards + PHPCompatibilityWP
composer phpcbf         # fix what phpcs can fix automatically
composer lint           # php -l
composer make-pot       # regenerate the translations template
```

PHP 7.4 or newer with the DOM, libxml and zlib extensions is required.
Dependencies are resolved for PHP 7.4 (`config.platform`), so the same lock
file works across the CI matrix. There is no JavaScript build.

To try the plugin in a real site, symlink or copy the folder into
`wp-content/plugins/` of a local WordPress install (wp-env, LocalWP, DDEV or
similar) and activate it. Upload the files from `tests/fixtures/malicious/`
and open them directly in the browser: nothing may run.

## Where things go

- `src/Sanitizer.php` - **all** sanitizing rules. It is pure PHP with no
  WordPress calls; keep it that way so it stays easy to test and reuse.
- `src/Result.php`, `src/FileProcessor.php`, `src/Svgz.php` - result object,
  gzip handling.
- `src/Dimensions.php` - size from width, height and viewBox.
- `src/Uploads.php` - MIME types and the upload prefilters.
- `src/Media.php` - metadata, `image_downsize`, media grid.
- `src/Permissions.php`, `src/Settings.php`, `src/Admin/SettingsPage.php` -
  who may upload, the option and its screen.
- `src/Cli/Command.php` - `wp svg-safe`.
- `tests/fixtures/` - the corpus. `tests/Stubs/` - light WordPress and
  WP-CLI stubs; add a stub when you use a new WordPress function.

## Coding standards

- WordPress Coding Standards (`composer phpcs` must pass) and PHP 7.4
  compatibility: no union types, no `match`, no named arguments, no
  constructor promotion.
- The sanitizer is an allowlist. Do not add blocklist rules for things that
  are not in the allowlist anyway; do add tests for every new allowed element
  or attribute, with a payload that tries to abuse it.
- Escape all output, sanitize all input, check capabilities and nonces.
- No remote requests, no tracking, no new runtime dependencies.
- Translatable strings use the `wp-svg-safe` text domain.
- Keep pull requests focused. Update `CHANGELOG.md` under "Unreleased".

## Pull requests

1. Fork the repository and create a branch (`corpus/mathml-vectors`,
   `fix/viewbox-commas`).
2. Make your change with tests.
3. Run `composer test`, `composer phpcs` and, if strings changed,
   `composer make-pot`, and commit the regenerated file.
4. Open the pull request and fill in the checklist.

CI must pass before a pull request is merged.

## Releases

Maintainers bump the version in `wp-svg-safe.php` (header and
`WP_SVG_SAFE_VERSION`), `src/Plugin.php` (`VERSION`), `readme.txt`
(`Stable tag`) and `CHANGELOG.md`, then publish a GitHub release tagged
`vX.Y.Z`. The release workflow checks that the versions match, builds
`wp-svg-safe.zip`, smoke-tests it and attaches it to the release.
