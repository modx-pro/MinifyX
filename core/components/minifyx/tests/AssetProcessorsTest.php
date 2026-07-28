<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Image\PathGuard;
use MinifyX\Processor\CssJsProcessor;
use MinifyX\Processor\LessCompiler;
use MinifyX\Processor\ScssCompiler;
use MinifyX\Processor\UnsupportedSourceTypeException;

final class AssetProcessorsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minifyx-assets-' . uniqid('', true) . '/';
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testCombineAndMinifyJsCss(): void
    {
        file_put_contents($this->dir . 'a.js', "var a = 1;\n");
        file_put_contents($this->dir . 'b.js', "var b = 2;\n");
        file_put_contents($this->dir . 'a.css', "body { color: red; }\n");

        $processor = new CssJsProcessor();
        $js = $processor->process([$this->dir . 'a.js', $this->dir . 'b.js'], ['minify' => true, 'type' => 'js']);
        $css = $processor->process([$this->dir . 'a.css'], ['minify' => true, 'type' => 'css']);

        $jsCompact = str_replace(' ', '', $js);
        $cssCompact = str_replace(' ', '', $css);
        self::assertStringContainsString('vara=1', $jsCompact);
        self::assertStringContainsString('varb=2', $jsCompact);
        self::assertStringContainsString('body{color:red}', $cssCompact);
    }

    public function testCompileScssAndLess(): void
    {
        file_put_contents($this->dir . 'a.scss', '$c: #f00; body { color: $c; }');
        file_put_contents($this->dir . 'a.less', '@c: #0f0; body { color: @c; }');

        $processor = new CssJsProcessor([
            new ScssCompiler(),
            new LessCompiler(),
        ]);

        $scss = $processor->process([$this->dir . 'a.scss'], ['type' => 'css']);
        $less = $processor->process([$this->dir . 'a.less'], ['type' => 'css']);

        self::assertStringContainsString('color: #f00', $scss);
        self::assertStringContainsString('color: #0f0', $less);
    }

    public function testScssCompileImportAndSourceMap(): void
    {
        file_put_contents($this->dir . '_colors.scss', '$accent: #123456;');
        $path = $this->dir . 'mapped.scss';
        file_put_contents($path, '@import "colors"; body { color: $accent; }');
        $compiler = new ScssCompiler();
        $processor = new CssJsProcessor([$compiler]);

        $css = $processor->process([$path], ['type' => 'css', 'sourceMaps' => true]);
        $map = $processor->getSourceMap();

        self::assertStringContainsString('#123456', $css);
        self::assertNotNull($map);
        self::assertSame(3, json_decode($map, true, 512, JSON_THROW_ON_ERROR)['version']);
        self::assertContains(realpath($this->dir . '_colors.scss'), $compiler->getIncludedFiles());
    }

    public function testCoffeeScriptIsUnsupported(): void
    {
        $path = $this->dir . 'a.coffee';
        file_put_contents($path, 'square = (x) -> x * x');

        $this->expectException(UnsupportedSourceTypeException::class);
        $this->expectExceptionMessage('Precompile');

        (new CssJsProcessor())->process([$path], ['type' => 'js']);
    }

    public function testPathGuardBlocksTraversal(): void
    {
        file_put_contents($this->dir . 'ok.png', 'x');
        $guard = new PathGuard($this->dir);
        self::assertSame(realpath($this->dir . 'ok.png'), $guard->resolve('ok.png'));

        $this->expectException(\InvalidArgumentException::class);
        $guard->resolve('../ok.png');
    }
}
