<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Processor\RegisteredAssetOutputInjector;

final class RegisteredAssetOutputInjectorTest extends TestCase
{
    public function testInjectsBeforeClosingTagsWhenRegisteredBlocksDiffer(): void
    {
        $html = "<html><head>\n<link rel=\"stylesheet\" href=\"/old.css\" />\n</head>"
            . "<body>\n<script src=\"/old.js\"></script>\n</body></html>";
        $registeredHead = "<link rel=\"stylesheet\" href=\"/old.css\" />";
        $registeredBody = '<script src="/old.js"></script>';

        $result = (new RegisteredAssetOutputInjector())->inject(
            $html,
            $registeredHead,
            $registeredBody,
            ['<link rel="stylesheet" href="/bundle.css" />'],
            ['<script src="/bundle.js"></script>']
        );

        self::assertStringContainsString('/bundle.css', $result);
        self::assertStringContainsString('/bundle.js', $result);
        self::assertStringNotContainsString('/old.css', $result);
        self::assertStringNotContainsString('/old.js', $result);
    }

    public function testInjectsWhenClosingTagsHaveWhitespace(): void
    {
        $html = "<html><head></head ><body></body ></html>";

        $result = (new RegisteredAssetOutputInjector())->inject(
            $html,
            '',
            '',
            ['<!-- minifyx head -->'],
            ['<!-- minifyx body -->']
        );

        self::assertStringContainsString("<!-- minifyx head -->\n</head >", $result);
        self::assertStringContainsString("<!-- minifyx body -->\n</body >", $result);
    }

    public function testReplacesRegisteredBlocksWhenClosingTagsAreMissing(): void
    {
        $head = '<link href="/old.css" rel="stylesheet">';
        $body = '<script src="/old.js"></script>';
        $html = '<section>' . $head . '<main>Content</main>' . $body . '</section>';

        $result = (new RegisteredAssetOutputInjector())->inject(
            $html,
            $head,
            $body,
            ['<link href="/bundle.css" rel="stylesheet">'],
            ['<script src="/bundle.js"></script>']
        );

        self::assertStringContainsString('/bundle.css', $result);
        self::assertStringContainsString('/bundle.js', $result);
        self::assertStringNotContainsString('/old.css', $result);
        self::assertStringNotContainsString('/old.js', $result);
    }

    public function testAppendsPreparedTagsWhenNoClosingTagOrRegisteredBlockExists(): void
    {
        $result = (new RegisteredAssetOutputInjector())->inject(
            '<main>Fragment</main>',
            '<link href="/missing.css" rel="stylesheet">',
            '',
            ['<link href="/bundle.css" rel="stylesheet">'],
            ['<script src="/bundle.js"></script>']
        );

        self::assertStringContainsString('/bundle.css', $result);
        self::assertStringContainsString('/bundle.js', $result);
    }

    public function testReplacesRegisteredBlockWithoutMovingItPastInlineCode(): void
    {
        $registered = '<script src="/old.js"></script>';
        $inline = '<script>window.depReady = true;</script>';
        $html = '<html><head>' . $registered . $inline . '</head></html>';

        $result = (new RegisteredAssetOutputInjector())->inject(
            $html,
            $registered,
            '',
            ['<script src="/bundle.js"></script>'],
            []
        );

        self::assertLessThan(strpos($result, $inline), strpos($result, '/bundle.js'));
    }
}
