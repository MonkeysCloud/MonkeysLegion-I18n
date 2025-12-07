<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Integration;

use MonkeysLegion\I18n\Loaders\FileLoader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(FileLoader::class)]
final class FileLoaderTest extends TestCase
{
    private string $fixturesPath;
    private FileLoader $loader;

    protected function setUp(): void
    {
        $this->fixturesPath = TEMP_DIR . '/file-loader-test';
        $this->loader = new FileLoader($this->fixturesPath);

        // Create test directory structure
        mkdir($this->fixturesPath . '/en', 0755, true);
        mkdir($this->fixturesPath . '/es', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    #[Test]
    public function it_loads_json_translations(): void
    {
        file_put_contents(
            $this->fixturesPath . '/en/messages.json',
            json_encode([
                'welcome' => 'Welcome!',
                'nested' => [
                    'key' => 'Nested value'
                ]
            ])
        );

        $translations = $this->loader->load('en', 'messages');

        $this->assertSame('Welcome!', $translations['welcome']);
        $this->assertSame('Nested value', $translations['nested']['key']);
    }

    #[Test]
    public function it_loads_php_translations(): void
    {
        file_put_contents(
            $this->fixturesPath . '/en/validation.php',
            "<?php\nreturn [\n    'required' => 'Required field',\n    'email' => 'Invalid email'\n];"
        );

        $translations = $this->loader->load('en', 'validation');

        $this->assertSame('Required field', $translations['required']);
        $this->assertSame('Invalid email', $translations['email']);
    }

    #[Test]
    public function it_returns_empty_array_when_file_not_found(): void
    {
        $translations = $this->loader->load('en', 'nonexistent');

        $this->assertEmpty($translations);
    }

    #[Test]
    public function it_loads_namespaced_translations(): void
    {
        $namespacePath = $this->fixturesPath . '/vendor/monkeysmail/lang';
        mkdir($namespacePath . '/en', 0755, true);

        file_put_contents(
            $namespacePath . '/en/emails.json',
            json_encode(['welcome' => 'Welcome Email'])
        );

        $this->loader->addNamespace('monkeysmail', $namespacePath);
        $translations = $this->loader->load('en', 'emails', 'monkeysmail');

        $this->assertSame('Welcome Email', $translations['welcome']);
    }

    #[Test]
    public function it_loads_multiple_locales(): void
    {
        // English
        file_put_contents(
            $this->fixturesPath . '/en/messages.json',
            json_encode(['hello' => 'Hello'])
        );

        // Spanish
        file_put_contents(
            $this->fixturesPath . '/es/messages.json',
            json_encode(['hello' => 'Hola'])
        );

        $enTranslations = $this->loader->load('en', 'messages');
        $esTranslations = $this->loader->load('es', 'messages');

        $this->assertSame('Hello', $enTranslations['hello']);
        $this->assertSame('Hola', $esTranslations['hello']);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }
}
