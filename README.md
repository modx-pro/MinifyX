# MinifyX

MODX Revolution extra: combine and minify CSS/JS, compile SCSS/LESS, resize images on request, optionally minify HTML.

Version **3.0** targets MODX 3 and PHP 8.2, upgrades scssphp to 2.x, and removes runtime CoffeeScript compilation.

## Requirements

- MODX Revolution **3.x**
- PHP **8.2+**
- `vendor/` from Composer (included in the transport package)
- GD or Imagick if you enable image processing

## Usage

| Entry | Role |
|---|---|
| Snippet `MinifyX` | Explicit CSS/JS groups and sources |
| `minify()` | Same API from PHP after `OnMODXInit` |
| Plugin | Opt-in: registered assets, images, HTML minify |
| `/assets/components/minifyx/minifyx.php` | Image connector (GD/Imagick backend) |

Enable plugin modes with system settings: `minifyx_process_registered`, `minifyx_process_images`, `minifyx_minifyHtml`.

### Asset groups

Copy `core/components/minifyx/config/groups.php.example` to `groups.php`. Groups are flat named lists:

```php
return [
    'baseCss' => ['/assets/css/base.css'],
    'baseJs' => ['/assets/js/base.js'],
];
```

Set `cssGroups` to `baseCss` and `jsGroups` to `baseJs` in the snippet call.

### Optional production hardening

- `minifyx_bundleIntegrity`: calculate SHA-384 SRI for generated bundles.
- `minifyx_cors_origin`: emit `Access-Control-Allow-Origin` from the image connector.
- `minifyx_image_signing_key`: require signed image transformation URLs.
- `minifyx_image_signing_keys`: rotate signing keys; the first signs and all listed keys verify.
- `minifyx_image_rate_limit_max` / `minifyx_image_rate_limit_window`: optional file-based connector rate limit.
- `minifyx_debug`: safe bundle comments with source count, cache status and bundle filename.
- `minifyx_jsManglerMaxInputBytes`: cap external optimizer input before process launch.
- `minifyx_preloadCss` / `minifyx_preloadJs`: emit preload hints for bundles.
- `minifyx_mangleJs`: use Terser or esbuild when available.
- `minifyx_bundleJsModules`: bundle registered `type="module"` scripts with esbuild. If esbuild fails or is
  unavailable, MinifyX keeps the original module tags.
- `minifyx_esbuildPath`: optional dedicated esbuild binary path for module bundling.
- `minifyx_sourceMaps`: write external `.map` files for Terser and esbuild output.
- `minifyx_parallelBuild`: allow bounded parallel group builds in the warm-cache CLI.

Run `php core/components/minifyx/bin/health-check.php --base-path=/path/to/modx` to inspect cache,
image drivers, external JS tools and signing configuration. The command is CLI-only.

SCSS and LESS imports remain under their compilers' control. MinifyX tracks local transitive
`@import`/`@use`/`@forward` files inside the webroot so edits invalidate the bundle cache. Source maps cover Terser,
esbuild, and a single unminified SCSS/Sass input. Minified or multi-input SCSS and LESS output remains map-free.

Warm all groups, or select one group:

```bash
php core/components/minifyx/bin/warm-cache.php --base-path=/path/to/modx
php core/components/minifyx/bin/warm-cache.php --base-path=/path/to/modx --group=baseCss
```

Add `--parallel --jobs=2` to build independent groups in bounded child processes when `proc_open` is available.
Without it, the command keeps deterministic group ordering and builds sequentially.

## Stack

- CSS/JS: [matthiasmullie/minify](https://github.com/matthiasmullie/minify)
- SCSS: [scssphp/scssphp](https://github.com/scssphp/scssphp)
- LESS: [wikimedia/less.php](https://github.com/wikimedia/less.php)
- Images: native GD / optional Imagick via `ImageProcessorFactory`

Precompile CoffeeScript to `.js`. MinifyX returns `unsupported_source_type` for `.coffee` input.

## Development

```bash
cd core/components/minifyx
composer install
composer test
composer phpstan
```

Build the transport package only after a production Composer install:

```bash
cd core/components/minifyx
composer install --no-dev --optimize-autoloader
```

Then run `_build/build.transport.php` against a MODX install. Package version lives in `_build/build.config.php`.

## Docs

- [Changelog](core/components/minifyx/docs/changelog.txt)
- [Migration 1.x → 2.0](core/components/minifyx/docs/migration-2.0.md)
- [Migration 2.x → 3.0](core/components/minifyx/docs/migration-3.0.md)
- [3.x release checklist](core/components/minifyx/docs/release-3.x-checklist.md)
