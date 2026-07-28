# MinifyX 3.0 migration

MinifyX 3.0 requires MODX Revolution 3.x and PHP 8.2 or newer. Keep MinifyX 2.x on sites that still run
MODX 2.8 or PHP 7.4.

## Before upgrading

1. Back up MODX system settings, `config/groups.php`, hooks, and generated assets.
2. Replace every `.coffee` source with precompiled `.js`.
3. Record custom values for `munee_cache` and `munee_imageProcessor`.
4. Test the package on a staging copy running the same PHP and MODX versions as production.

## Breaking changes

### CoffeeScript

MinifyX no longer ships a CoffeeScript compiler. A `.coffee` input returns a failed build result with
`errorCode=unsupported_source_type` and asks you to precompile the file.

Compile CoffeeScript during your application build, commit or deploy the resulting `.js`, and point `jsSources` or
`groups.php` at that file.

### Connector and settings

The image endpoint moved to `/assets/components/minifyx/minifyx.php`. The old
`/assets/components/minifyx/munee.php` file remains as a deprecated alias for the 3.x cycle.

MinifyX renamed these settings:

- `munee_cache` to `minifyx_cache`
- `munee_imageProcessor` to `minifyx_imageProcessor`

The installer copies an old value only if the new setting does not exist. Runtime reads follow the same rule, so an
explicit new value wins even when the old key remains.

### MODX service resolution

The snippet, plugin, and `minify()` function check the MODX 3 service container for `minifyx` first. If the container
does not expose that service, they use the existing `getService()` integration for the 3.x cycle. MinifyX 4.0 may
remove this fallback.

MODX 2.8 is unsupported. MinifyX 3.0 does not claim or test compatibility with its global `modX` runtime.

### SCSS and source maps

MinifyX uses `scssphp/scssphp` 2.x. It consumes `CompilationResult::getCss()`, tracks included files, and publishes a
real source map for a single unminified SCSS or indented Sass input when `sourceMaps` is enabled.

MinifyX does not publish an SCSS map after CSS minification because a second optimizer changes generated positions.
Bundles containing multiple SCSS inputs also omit a map until MinifyX can compose maps. The current LESS adapter does
not expose a reliable map, so LESS output remains map-free.

scssphp 2.1 does not implement the Sass module system. Existing `@use` or `@forward` sources may still require Dart
Sass in your build pipeline; stable `@import` compilation remains covered by MinifyX tests.

## Rollback

1. Restore the MinifyX 2.x transport package and its matching `vendor/` directory.
2. Point `minifyx_connector` back to `/assets/components/minifyx/munee.php`.
3. Restore the saved settings and clear both MODX and MinifyX caches.
4. Restore `.coffee` sources only if the 2.x deployment still relies on runtime compilation.

Do not run MinifyX 3.0 code with a 2.x dependency lock. Restore code and Composer dependencies as one release unit.
