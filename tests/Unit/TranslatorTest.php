<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Unit;

use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\MessageFormatter;
use MonkeysLegion\I18n\Pluralizer;
use MonkeysLegion\I18n\Loaders\FileLoader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Translator::class)]
final class TranslatorTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/lang';
        
        // Create fixture directory and files
        if (!is_dir($this->fixturesPath . '/en')) {
            mkdir($this->fixturesPath . '/en', 0755, true);
        }
        
        file_put_contents(
            $this->fixturesPath . '/en/messages.json',
            json_encode([
                'welcome' => 'Welcome!',
                'greeting' => 'Hello, :name!',
                'nested' => [
                    'key' => 'Nested value'
                ]
            ])
        );
    }

    protected function tearDown(): void
    {
        // Clean up fixtures
        if (is_dir($this->fixturesPath)) {
            $this->removeDirectory($this->fixturesPath);
        }
    }

    #[Test]
    public function it_translates_basic_keys(): void
    {
        $translator = new Translator('en', 'en');
        $loader = new FileLoader($this->fixturesPath);
        $translator->addLoader($loader);

        $result = $translator->trans('messages.welcome');

        $this->assertSame('Welcome!', $result);
    }

    #[Test]
    public function it_replaces_parameters(): void
    {
        $translator = new Translator('en', 'en');
        $loader = new FileLoader($this->fixturesPath);
        $translator->addLoader($loader);

        $result = $translator->trans('messages.greeting', ['name' => 'Yorch']);

        $this->assertSame('Hello, Yorch!', $result);
    }

    #[Test]
    public function it_translates_nested_keys(): void
    {
        $translator = new Translator('en', 'en');
        $loader = new FileLoader($this->fixturesPath);
        $translator->addLoader($loader);

        $result = $translator->trans('messages.nested.key');

        $this->assertSame('Nested value', $result);
    }

    #[Test]
    public function it_returns_key_when_translation_not_found(): void
    {
        $translator = new Translator('en', 'en');
        $loader = new FileLoader($this->fixturesPath);
        $translator->addLoader($loader);

        $result = $translator->trans('messages.nonexistent');

        $this->assertSame('messages.nonexistent', $result);
    }

    #[Test]
    public function it_checks_if_translation_exists(): void
    {
        $translator = new Translator('en', 'en');
        $loader = new FileLoader($this->fixturesPath);
        $translator->addLoader($loader);

        $this->assertTrue($translator->has('messages.welcome'));
        $this->assertFalse($translator->has('messages.nonexistent'));
    }

    #[Test]
    public function it_gets_and_sets_locale(): void
    {
        $translator = new Translator('en', 'en');

        $this->assertSame('en', $translator->getLocale());

        $translator->setLocale('es');

        $this->assertSame('es', $translator->getLocale());
    }

    #[Test]
    public function it_tracks_missing_translations_when_enabled(): void
    {
        $translator = new Translator('en', 'en');
        $translator->setTrackMissing(true);
        $loader = new FileLoader($this->fixturesPath);
        $translator->addLoader($loader);

        $translator->trans('messages.missing1');
        $translator->trans('messages.missing2');

        $missing = $translator->getMissingTranslations();

        $this->assertCount(2, $missing);
        $this->assertContains('en.messages.missing1', $missing);
        $this->assertContains('en.messages.missing2', $missing);
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
