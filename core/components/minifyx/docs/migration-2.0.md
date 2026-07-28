# MinifyX 2.0 migration

## Breaking requirements

- MODX Revolution **2.8.x+** with PHP **7.4+**
- MODX Revolution **3.x** with PHP **8.2+**
- Munee is removed. Transport packages must include `vendor/` built with Composer.

## What stays the same

- Snippet properties (`jsSources`, `cssSources`, `jsGroups`, `cssGroups`, hooks, register modes)
- Helper `minify()`
- `config/groups.php`
- File hooks in `hooks/`
- Connector URL `/assets/components/minifyx/munee.php?files=...`

## What changed

- Asset processing is pure PHP (`matthiasmullie/minify`, `scssphp`, `wikimedia/less.php`, native GD/Imagick).
- Optional JS mangling via Terser or esbuild (`mangleJs`, `jsMangler`, `jsManglerPath`). Falls back to PHP minifier only when the binary is missing.
- Optional preload hints for bundled CSS/JS (`preloadCss`, `preloadJs`).
- CSS minifier preserves empty `url()` values and rebases relative URLs for cache output paths.
- Cache files are invalidated by source mtime fingerprint, not only output hash.
- Registered asset rewriting keeps attributes such as `defer`, `async`, `type="module"`, `integrity`, `media`.
- Registered bundles can opt into recalculated SHA-384 SRI with `minifyx_bundleIntegrity`.
- Registered assets are injected before closing head/body tags without relying on an exact rendered block match.
- HTML minify preserves `pre`, `textarea`, script/style blocks and conditional comments.
- Image endpoint rejects path traversal and oversized inputs before decode.
- Connector URLs follow the active HTTPS scheme and `minifyx_cors_origin` can enable an explicit CORS header.
- Build failures expose stable error codes without publishing absolute source paths.
- Raw script/style bundles preserve CSP nonces and separate blocks with different nonce values.
- Optional safe debug comments report source count, cache hit/miss and bundle filename.
- SCSS/LESS cache signatures include local transitive imports, including Sass partials. Resolution stays inside the
  MODX webroot, detects cycles and leaves import semantics to scssphp/less.php.
- `minifyx_bundleJsModules` enables esbuild bundling for registered `type="module"` scripts. It defaults to false.
  Missing or failed esbuild runs leave the original module tags unchanged.
- `minifyx_sourceMaps` writes external maps for Terser and esbuild output. PHP JS minification has no map.
- `minifyx_parallelBuild` allows bounded child-process builds in `bin/warm-cache.php`. Web requests stay sequential.

## Groups format

`config/groups.php` remains a flat map of group names to source lists:

```php
return [
    'baseCss' => ['/assets/css/base.css'],
    'baseJs' => ['/assets/js/base.js'],
];
```

Use `baseCss` in `cssGroups` and `baseJs` in `jsGroups`.

## CoffeeScript

`.coffee` files still compile through a deprecated legacy adapter. Prefers precompiled JavaScript before MinifyX 3.0.

## Optional hardening

Set `minifyx_image_signing_key`. The plugin then appends `&sig=` to rewritten image URLs. With a non-empty key the connector rejects unsigned or invalid signatures with HTTP 403.

Set `minifyx_bundleIntegrity` to add a SHA-384 `integrity` attribute to generated bundles. Set
`minifyx_cors_origin` only to a trusted origin (or `*` for intentionally public images).

For key rotation, set comma/newline-separated `minifyx_image_signing_keys`; the first key signs and all keys
verify. The singular setting remains the fallback. Connector rate limiting is disabled until
`minifyx_image_rate_limit_max` is greater than zero.

Run `bin/health-check.php --base-path=/path/to/modx` from CLI to check cache permissions, image drivers,
Terser/esbuild and signing configuration.

Run `bin/warm-cache.php --base-path=/path/to/modx` to warm every flat group, or pass `--group=name`. The command
returns JSON and exits with 0 on success, 1 on build failures and 2 on configuration/bootstrap errors. Add
`--parallel --jobs=2` for independent groups when `proc_open` is available; otherwise it builds sequentially.

Source maps for SCSS and LESS remain deferred. scssphp 1.13 and less.php 4.4 do not provide reliable maps through
MinifyX's shared compiler abstraction, so the pipeline does not create placeholder or misleading maps.

## Upgrade checklist

1. Backup `config/groups.php` and custom hooks.
2. Install 2.0 transport package (includes Composer vendor).
3. Clear MODX cache (`OnSiteRefresh` clears MinifyX caches).
4. Smoke-test snippet output, plugin registered mode, and image connector.
5. Search the site for `.coffee` sources and plan precompilation.
