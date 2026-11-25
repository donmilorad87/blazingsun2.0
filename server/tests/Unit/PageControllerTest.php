<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Content\Content;
use BlazingSun\Api\Page\Assets;
use BlazingSun\Api\Page\PageCache;
use BlazingSun\Api\Page\PageController;
use BlazingSun\Api\Page\PageRenderer;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase
{
    private string $directory;

    private int $loads = 0;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blazing-page-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents("$this->directory/asset-manifest.json", '{"src/main.js":{"file":"assets/main-abc.js","css":["assets/main-abc.css"]}}');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->directory/*/*") ?: []);
        array_map('unlink', glob("$this->directory/*.*") ?: []);
        @rmdir("$this->directory/cache");
        rmdir($this->directory);
    }

    private function controller(Content $content): PageController
    {
        return new PageController(
            function () use ($content): Content {
                $this->loads++;
                return $content;
            },
            fn (): Assets => Assets::load("$this->directory/asset-manifest.json", "$this->directory/hot", 'src/main.js'),
            new PageCache("$this->directory/cache"),
            new PageRenderer(dirname(__DIR__, 2) . '/templates/page.php'),
            'https://example.test',
            2026,
        );
    }

    private function content(): Content
    {
        return ContentFixture::make([
            'section:hero' => ['title' => ['en' => 'Building <em>digital</em> <script>alert(1)</script>', 'sr' => 'Gradimo']],
            'section:about' => ['nav_label' => ['en' => 'About & me']],
            'labels' => ['meta_title' => ['en' => 'Site "title"']],
            'header' => ['icon_dark' => ['' => '1']],
            'item:7' => ['label' => ['en' => 'Hire me'], 'href' => ['' => 'https://example.com/?a=1&b="2"'], 'style' => ['' => 'primary']],
        ]);
    }

    public function testRendersTheMainLanguageAtRootWithEscapedContent(): void
    {
        $response = $this->controller($this->content())->handle('/');
        $html = $response['body'];

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('<h1>Building <em>digital</em> &lt;script&gt;alert(1)&lt;/script&gt;</h1>', $html);
        self::assertStringContainsString('<title>Site &quot;title&quot;</title>', $html);
        self::assertStringContainsString('<a href="#about">About &amp; me</a>', $html);
        self::assertStringContainsString('href="https://example.com/?a=1&amp;b=&quot;2&quot;" target="_blank" rel="noopener">Hire me</a>', $html);
        self::assertStringContainsString('<link rel="alternate" hreflang="sr" href="https://example.test/sr/">', $html);
        self::assertStringContainsString('<link rel="stylesheet" href="/assets/main-abc.css">', $html);
        self::assertStringContainsString('/images/responsive/flag-gb-24.webp 24w', $html);
        self::assertStringContainsString('© 2026', $html);
        self::assertStringNotContainsString('<script>alert', $html);
    }

    public function testOtherLanguagesHaveTheirOwnPathAndFallBackToMain(): void
    {
        $html = $this->controller($this->content())->handle('/sr/')['body'];

        self::assertStringContainsString('<html lang="sr">', $html);
        self::assertStringContainsString('<h1>Gradimo</h1>', $html);
        self::assertStringContainsString('Hire me', $html, 'untranslated button falls back to English');
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/sr/">', $html);
        self::assertStringContainsString('<a href="/sr/" hreflang="sr" lang="sr" class="is-active" aria-current="page">', $html);
    }

    public function testServesFromTheCacheWithoutLoadingContent(): void
    {
        $controller = $this->controller($this->content());
        $first = $controller->handle('/');
        $second = $controller->handle('/');

        self::assertSame($first['body'], $second['body']);
        self::assertSame(1, $this->loads);
        (new PageCache("$this->directory/cache"))->clear();
        $controller->handle('/');
        self::assertSame(2, $this->loads);
    }

    public function testMainLanguagePathRedirectsAndUnknownPathsAre404(): void
    {
        $controller = $this->controller($this->content());

        self::assertSame(['status' => 301, 'location' => '/'], ['status' => $controller->handle('/en/')['status'], 'location' => $controller->handle('/en/')['headers']['Location'] ?? null]);
        self::assertSame(404, $controller->handle('/de/')['status']);
        self::assertSame(404, $controller->handle('/about')['status']);
        self::assertSame(404, $controller->handle('/sr/x')['status']);
    }
}
