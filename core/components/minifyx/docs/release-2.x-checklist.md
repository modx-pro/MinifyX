# MinifyX 2.x release checklist

## Automated gates

- [ ] Run `composer test`.
- [ ] Run `composer phpstan` at level 8.
- [ ] Run `composer audit`.
- [ ] Validate PHP syntax for `src`, `model`, `elements`, `functions`, `tests`, and `bin`.
- [ ] Confirm CI passes on PHP 7.4, 8.2, 8.3, 8.4, and the Imagick job.
- [ ] Confirm `git diff --check` and the 120-character PHP line limit.

## Compatibility

- [ ] Smoke-test MODX 2.8 snippet, plugin registered-assets mode, `minify()`, hooks, and `groups.php`.
- [ ] Verify all new settings remain disabled by default.
- [ ] Verify `/assets/components/minifyx/munee.php` and legacy `munee_*` settings still work.
- [ ] Test HTML fragments without closing `head` or `body` tags.
- [ ] Test HTTPS behind the production reverse proxy or CDN.

## Production features

- [ ] If enabled, verify bundle SRI and CSP nonce behavior against the production CSP.
- [ ] If enabled, restrict `minifyx_cors_origin` to the intended origin.
- [ ] Configure signing-key rotation before removing an old image key.
- [ ] Size external-process and image rate limits for the deployment.
- [ ] Run `bin/health-check.php` and `bin/warm-cache.php` as the deployment user.
- [ ] Verify Terser/esbuild paths and source-map access rules.

## Release

- [ ] Back up `config/groups.php`, hooks, and system settings.
- [ ] Build the transport with production Composer dependencies.
- [ ] Install in staging, clear MODX and MinifyX caches, then run smoke tests.
- [ ] Review the final diff and changelog.
- [ ] Tag and publish only after every required gate is green.
- [ ] Keep MODX 2.8 support on the 2.x branch before starting the separate 3.0 migration.
