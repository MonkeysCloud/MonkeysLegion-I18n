<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Unit;

use MonkeysLegion\I18n\Pluralizer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Pluralizer::class)]
final class PluralizerTest extends TestCase
{
    private Pluralizer $pluralizer;

    protected function setUp(): void
    {
        $this->pluralizer = new Pluralizer();
    }

    #[Test]
    public function it_handles_simple_english_pluralization(): void
    {
        $message = 'one: One item|other: :count items';

        $this->assertSame('One item', $this->pluralizer->choose($message, 1, 'en'));
        $this->assertSame('5 items', $this->pluralizer->choose($message, 5, 'en'));
    }

    #[Test]
    public function it_handles_explicit_count_forms(): void
    {
        $message = '{0} No items|{1} One item|{2} Two items';

        $this->assertSame('No items', $this->pluralizer->choose($message, 0, 'en'));
        $this->assertSame('One item', $this->pluralizer->choose($message, 1, 'en'));
        $this->assertSame('Two items', $this->pluralizer->choose($message, 2, 'en'));
    }

    #[Test]
    public function it_handles_range_forms(): void
    {
        $message = '[1,5] Low stock|[6,*] In stock';

        $this->assertSame('Low stock', $this->pluralizer->choose($message, 3, 'en'));
        $this->assertSame('In stock', $this->pluralizer->choose($message, 10, 'en'));
    }

    #[Test]
    #[DataProvider('spanishPluralizationProvider')]
    public function it_handles_spanish_pluralization(int $count, string $expected): void
    {
        $message = 'one: Un artículo|other: :count artículos';

        $this->assertSame($expected, $this->pluralizer->choose($message, $count, 'es'));
    }

    public static function spanishPluralizationProvider(): array
    {
        return [
            [1, 'Un artículo'],
            [0, '0 artículos'],
            [5, '5 artículos'],
            [100, '100 artículos'],
        ];
    }

    #[Test]
    public function it_handles_russian_pluralization(): void
    {
        $message = 'one: :count товар|few: :count товара|other: :count товаров';

        // One form (1, 21, 31, etc.)
        $this->assertSame('1 товар', $this->pluralizer->choose($message, 1, 'ru'));
        $this->assertSame('21 товар', $this->pluralizer->choose($message, 21, 'ru'));

        // Few form (2-4, 22-24, etc.)
        $this->assertSame('2 товара', $this->pluralizer->choose($message, 2, 'ru'));
        $this->assertSame('3 товара', $this->pluralizer->choose($message, 3, 'ru'));
        $this->assertSame('22 товара', $this->pluralizer->choose($message, 22, 'ru'));

        // Many form (0, 5-20, 25-30, etc.)
        $this->assertSame('0 товаров', $this->pluralizer->choose($message, 0, 'ru'));
        $this->assertSame('5 товаров', $this->pluralizer->choose($message, 5, 'ru'));
        $this->assertSame('11 товаров', $this->pluralizer->choose($message, 11, 'ru'));
        $this->assertSame('100 товаров', $this->pluralizer->choose($message, 100, 'ru'));
    }

    #[Test]
    public function it_returns_original_message_when_no_plural_forms(): void
    {
        $message = 'Simple message without plural forms';

        $result = $this->pluralizer->choose($message, 5, 'en');

        $this->assertSame($message, $result);
    }

    #[Test]
    public function it_replaces_count_placeholder(): void
    {
        $message = 'one: One item|other: You have :count items';

        $result = $this->pluralizer->choose($message, 5, 'en');

        $this->assertStringContainsString('5', $result);
    }
}
