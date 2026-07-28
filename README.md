# MinifyX

MODX Revolution extra: combine and minify CSS/JS, compile SCSS/LESS, resize images on request, optionally minify HTML.

Version **2.0** replaces Munee with a pure-PHP pipeline. Snippet parameters, `minify()`, `groups.php`, hooks, and the connector URL stay compatible with 1.x.

## Requirements

- MODX Revolution **2.8.x+**: PHP **7.4+**
- MODX Revolution **3.x**: PHP **8.2+**
- `vendor/` from Composer (included in the transport package)
- GD or Imagick if you enable image processing

## Usage

| Entry | Role |
|---|---|
| Snippet `MinifyX` | Explicit CSS/JS groups and sources |
| `minify()` | Same API from PHP after `OnMODXInit` |
| Plugin | Opt-in: registered assets, images, HTML minify |
| `/assets/components/minifyx/munee.php` | Image connector (GD/Imagick backend) |

Enable plugin modes with system settings: `minifyx_process_registered`, `minifyx_process_images`, `minifyx_minifyHtml`.

## Stack

- CSS/JS: [matthiasmullie/minify](https://github.com/matthiasmullie/minify)
- SCSS: [scssphp/scssphp](https://github.com/scssphp/scssphp)
- LESS: [wikimedia/less.php](https://github.com/wikimedia/less.php)
- Images: native GD / optional Imagick via `ImageProcessorFactory`

CoffeeScript uses a deprecated legacy adapter. Precompile to `.js` before MinifyX 3.0.

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
