## What does this change?

<!-- One or two sentences. Link the issue: "Closes #123". -->

## Type

- [ ] Bug fix
- [ ] Sanitizer rules (allowlist, references, CSS, parsing)
- [ ] Test corpus (malicious, rejected or valid SVG files)
- [ ] Media library, uploads, settings, WP-CLI or hooks
- [ ] Documentation or translations
- [ ] Tests or tooling

## Checklist

- [ ] Sanitizing rules stay in `src/Sanitizer.php` and have tests.
- [ ] Every newly allowed element or attribute has a payload in `tests/fixtures/malicious/` that tries to abuse it.
- [ ] Output is escaped; forms check a capability and a nonce.
- [ ] No remote requests, no tracking, no new runtime dependencies.
- [ ] `composer test` and `composer phpcs` pass.
- [ ] `composer make-pot` run and `languages/wp-svg-safe.pot` committed (if strings changed).
- [ ] `CHANGELOG.md` updated under "Unreleased".

## How did you test it on a real site?

<!-- WordPress and PHP versions, which SVG files you uploaded, how you opened them. -->
