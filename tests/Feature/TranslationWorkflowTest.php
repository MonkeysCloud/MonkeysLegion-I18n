<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Feature;

use MonkeysLegion\I18n\TranslatorFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TranslationWorkflowTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        $this->langPath = TEMP_DIR . '/workflow-test/lang';

        // Create language files
        mkdir($this->langPath . '/en', 0755, true);
        mkdir($this->langPath . '/es', 0755, true);

        // English translations
        file_put_contents(
            $this->langPath . '/en/messages.json',
            json_encode([
                'welcome' => 'Welcome to MonkeysLegion!',
                'greeting' => 'Hello, :name!',
                'items' => 'one: One item|other: :count items',
                'user' => [
                    'profile' => 'Your Profile',
                    'settings' => 'Account Settings'
                ]
            ])
        );

        // Spanish translations
        file_put_contents(
            $this->langPath . '/es/messages.json',
            json_encode([
                'welcome' => '¡Bienvenido a MonkeysLegion!',
                'greeting' => '¡Hola, :name!',
                'items' => 'one: Un artículo|other: :count artículos',
                'user' => [
                    'profile' => 'Tu Perfil',
                    'settings' => 'Configuración de Cuenta'
                ]
            ])
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->langPath));
    }

    #[Test]
    public function it_handles_complete_translation_workflow(): void
    {
        $translator = TranslatorFactory::create([
            'locale' => 'en',
            'fallback' => 'en',
            'path' => $this->langPath,
            'supported_locales' => ['en', 'es'],
        ]);

        // Basic translation
        $this->assertSame(
            'Welcome to MonkeysLegion!',
            $translator->trans('messages.welcome')
        );

        // Translation with parameters
        $this->assertSame(
            'Hello, Yorch!',
            $translator->trans('messages.greeting', ['name' => 'Yorch'])
        );

        // Nested translation
        $this->assertSame(
            'Your Profile',
            $translator->trans('messages.user.profile')
        );

        // Pluralization
        $this->assertStringContainsString(
            'One item',
            $translator->choice('messages.items', 1)
        );

        $this->assertStringContainsString(
            '5 items',
            $translator->choice('messages.items', 5)
        );
    }

    #[Test]
    public function it_switches_locales_correctly(): void
    {
        $translator = TranslatorFactory::create([
            'locale' => 'en',
            'fallback' => 'en',
            'path' => $this->langPath,
            'supported_locales' => ['en', 'es'],
        ]);

        // English
        $this->assertSame(
            'Welcome to MonkeysLegion!',
            $translator->trans('messages.welcome')
        );

        // Switch to Spanish
        $translator->setLocale('es');

        $this->assertSame(
            '¡Bienvenido a MonkeysLegion!',
            $translator->trans('messages.welcome')
        );

        $this->assertSame(
            '¡Hola, Yorch!',
            $translator->trans('messages.greeting', ['name' => 'Yorch'])
        );
    }

    #[Test]
    public function it_falls_back_to_fallback_locale_when_translation_missing(): void
    {
        // Create incomplete Spanish translations
        file_put_contents(
            $this->langPath . '/es/incomplete.json',
            json_encode([
                'existing' => 'Existe'
                // 'missing' key not defined
            ])
        );

        // Create complete English translations
        file_put_contents(
            $this->langPath . '/en/incomplete.json',
            json_encode([
                'existing' => 'Exists',
                'missing' => 'This is the fallback'
            ])
        );

        $translator = TranslatorFactory::create([
            'locale' => 'es',
            'fallback' => 'en',
            'path' => $this->langPath,
        ]);

        // Existing Spanish translation
        $this->assertSame(
            'Existe',
            $translator->trans('incomplete.existing')
        );

        // Falls back to English
        $this->assertSame(
            'This is the fallback',
            $translator->trans('incomplete.missing')
        );
    }

    #[Test]
    public function it_tracks_missing_translations_in_development(): void
    {
        $translator = TranslatorFactory::create([
            'locale' => 'en',
            'path' => $this->langPath,
            'track_missing' => true,
        ]);

        // Request non-existent translations
        $translator->trans('messages.nonexistent1');
        $translator->trans('messages.nonexistent2');

        $missing = $translator->getMissingTranslations();

        $this->assertGreaterThanOrEqual(2, count($missing));
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
