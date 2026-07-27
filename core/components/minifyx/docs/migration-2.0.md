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

- Asset processing is pure PHP (`matthiasmullie/minify`, `scssphp`, `wikimedia/less.php`, Intervention Image).
- Cache files are invalidated by source mtime fingerprint, not only output hash.
- Registered asset rewriting keeps attributes such as `defer`, `async`, `type="module"`, `integrity`, `media`.
- HTML minify preserves `pre`, `textarea`, script/style blocks and conditional comments.
- Image endpoint rejects path traversal and oversized inputs before decode.

## CoffeeScript

`.coffee` files still compile through a deprecated legacy adapter. Prefers precompiled JavaScript before MinifyX 3.0.

## Optional hardening

Set `minifyx_image_signing_key`. The plugin then appends `&sig=` to rewritten image URLs. With a non-empty key the connector rejects unsigned or invalid signatures with HTTP 403.

## Upgrade checklist

1. Backup `config/groups.php` and custom hooks.
2. Install 2.0 transport package (includes Composer vendor).
3. Clear MODX cache (`OnSiteRefresh` clears MinifyX caches).
4. Smoke-test snippet output, plugin registered mode, and image connector.
5. Search the site for `.coffee` sources and plan precompilation.
