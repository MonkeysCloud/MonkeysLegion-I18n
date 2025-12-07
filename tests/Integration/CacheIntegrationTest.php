<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Integration;

use MonkeysLegion\I18n\Loaders\CacheLoader;
use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\TranslatorFactory;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CacheIntegrationTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/lang';

        if (!is_dir($this->fixturesPath . '/en')) {
            mkdir($this->fixturesPath . '/en', 0755, true);
        }

        file_put_contents(
            $this->fixturesPath . '/en/messages.json',
            json_encode(['welcome' => 'Welcome Cached!'])
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixturesPath)) {
            $this->removeDirectory($this->fixturesPath);
        }
    }

    public function test_it_uses_cache_to_store_and_retrieve_translations(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        // Expect cache->get called
        $cache->expects($this->once())
            ->method('get')
            ->with('i18n.en.messages')
            ->willReturn(null); // First time miss

        // Expect cache->set called
        $cache->expects($this->once())
            ->method('set')
            ->with('i18n.en.messages', ['welcome' => 'Welcome Cached!'], 3600);

        $translator = TranslatorFactory::create([
            'locale' => 'en',
            'path' => $this->fixturesPath,
            'cache' => $cache
        ]);

        $this->assertSame('Welcome Cached!', $translator->trans('messages.welcome'));
    }

    public function test_it_returns_cached_value_without_loading_file(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        // Expect cache->get called and return hit
        $cache->expects($this->once())
            ->method('get')
            ->with('i18n.en.messages')
            ->willReturn(['welcome' => 'Cached Value']);

        // Expect cache->set NEVER called
        $cache->expects($this->never())->method('set');

        $translator = TranslatorFactory::create([
            'locale' => 'en',
            'path' => $this->fixturesPath,
            'cache' => $cache
        ]);

        $this->assertSame('Cached Value', $translator->trans('messages.welcome'));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
