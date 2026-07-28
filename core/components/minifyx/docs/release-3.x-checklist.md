# MinifyX 3.x release checklist

## Automated gates

- [ ] Run `composer test` with PHPUnit 11.
- [ ] Run `composer phpstan` at level 8 or higher.
- [ ] Run `composer audit`.
- [ ] Validate PHP syntax in runtime, build, connector, and test PHP files.
- [ ] Confirm CI passes on PHP 8.2, 8.3, 8.4, 8.5, and the Imagick job.
- [ ] Run `git diff --check` and enforce the 120-character PHP line limit.

## Migration

- [ ] Install only on MODX 3 with PHP 8.2 or newer.
- [ ] Verify `.coffee` input returns `unsupported_source_type`.
- [ ] Verify `minifyx.php` and the deprecated `munee.php` alias.
- [ ] Verify old setting values migrate only when the new keys are absent.
- [ ] Smoke-test native container lookup and the isolated `getService()` fallback.
- [ ] Verify SCSS compile, imports, included files, and single-input source maps.
- [ ] Confirm LESS output does not claim source-map support.

## Release

- [ ] Build the transport with production Composer dependencies.
- [ ] Back up settings, `groups.php`, hooks, and generated assets.
- [ ] Install in staging and run snippet, plugin, image connector, and `minify()` smoke tests.
- [ ] Test rollback to the matching 2.x code and dependency lock.
- [ ] Review the final diff and changelog before tagging.
