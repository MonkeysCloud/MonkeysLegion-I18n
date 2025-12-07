<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Unit;

use MonkeysLegion\I18n\Contracts\LoaderInterface;
use MonkeysLegion\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorMergeTest extends TestCase
{
    public function test_it_deep_merges_nested_translations_from_multiple_loaders(): void
    {
        $translator = new Translator('en', 'en');

        // Loader 1: File Loader Simulation (Base translations)
        $loader1 = new class implements LoaderInterface {
            public function load(string $locale, string $group, ?string $namespace = null): array
            {
                if ($group === 'messages') {
                    return [
                        'user' => [
                            'profile' => 'Profile',
                            'settings' => 'Settings', // This should be preserved
                            'nested' => [
                                'a' => 'A',
                                'b' => 'B' // This should be preserved
                            ]
                        ]
                    ];
                }
                return [];
            }
            public function addNamespace(string $namespace, string $path): void {}
        };

        // Loader 2: Database Loader Simulation (Overrides)
        $loader2 = new class implements LoaderInterface {
            public function load(string $locale, string $group, ?string $namespace = null): array
            {
                if ($group === 'messages') {
                    return [
                        'user' => [
                            'profile' => 'My Profile', // Override
                            'nested' => [
                                'a' => 'Alpha' // Override
                            ]
                        ]
                    ];
                }
                return [];
            }
            public function addNamespace(string $namespace, string $path): void {}
        };

        $translator->addLoader($loader1);
        $translator->addLoader($loader2);

        // Access trigger load
        $this->assertSame('My Profile', $translator->trans('messages.user.profile'), 'Overridden key incorrect');
        $this->assertSame('Settings', $translator->trans('messages.user.settings'), 'Sibling key lost (shallow merge issue)');
        $this->assertSame('Alpha', $translator->trans('messages.user.nested.a'), 'Nested overridden key incorrect');
        $this->assertSame('B', $translator->trans('messages.user.nested.b'), 'Nested sibling key lost');
    }
}
