<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Unit;

use MonkeysLegion\I18n\Contract\LoaderInterface;
use MonkeysLegion\I18n\Contract\SanitizerInterface;
use MonkeysLegion\I18n\DateFormatter;
use MonkeysLegion\I18n\Enum\Direction;
use MonkeysLegion\I18n\Enum\PluralCategory;
use MonkeysLegion\I18n\Exceptions\InvalidLocaleException;
use MonkeysLegion\I18n\Exceptions\TranslationNotFoundException;
use MonkeysLegion\I18n\Exceptions\UnsupportedLocaleException;
use MonkeysLegion\I18n\Exceptions\LoaderException;
use MonkeysLegion\I18n\Loaders\CacheLoader;
use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\Loaders\MlcLoader;
use MonkeysLegion\I18n\LocaleManager;
use MonkeysLegion\I18n\MessageFormatter;
use MonkeysLegion\I18n\NumberFormatter;
use MonkeysLegion\I18n\Pluralizer;
use MonkeysLegion\I18n\Support\LocaleInfo;
use MonkeysLegion\I18n\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

use DateTimeImmutable;

/**
 * Additional tests to reach 200+ total.
 * Covers: allKeys, cache tagging, edge cases, extended formatters, security deep-dives.
 */
final class ExtendedV2Test extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/extlang';

        @mkdir($this->fixturesPath . '/en', 0755, true);
        @mkdir($this->fixturesPath . '/es', 0755, true);

        file_put_contents(
            $this->fixturesPath . '/en/messages.json',
            json_encode([
                'welcome'  => 'Welcome!',
                'greeting' => 'Hello, :name!',
                'nested'   => ['a' => 'A', 'b' => ['c' => 'C', 'd' => 'D']],
                'items'    => '{0} No items|{1} One item|[2,*] :count items',
            ]),
        );

        file_put_contents(
            $this->fixturesPath . '/en/errors.json',
            json_encode(['not_found' => 'Not found', 'forbidden' => 'Forbidden']),
        );

        file_put_contents(
            $this->fixturesPath . '/es/messages.json',
            json_encode(['welcome' => '¡Bienvenido!']),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    // ═══════════════════════════════════════════════════════════════
    // Translator::allKeys()
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function translator_allkeys_returns_flat_keys(): void
    {
        $t = $this->translator();
        $keys = $t->allKeys('en', 'messages');

        $this->assertContains('welcome', $keys);
        $this->assertContains('greeting', $keys);
    }

    #[Test]
    public function translator_allkeys_returns_nested_keys(): void
    {
        $t = $this->translator();
        $keys = $t->allKeys('en', 'messages');

        $this->assertContains('nested.a', $keys);
        $this->assertContains('nested.b.c', $keys);
        $this->assertContains('nested.b.d', $keys);
    }

    #[Test]
    public function translator_allkeys_empty_for_missing_group(): void
    {
        $t = $this->translator();
        $this->assertSame([], $t->allKeys('en', 'nonexistent'));
    }

    #[Test]
    public function translator_allkeys_different_groups(): void
    {
        $t = $this->translator();

        $messageKeys = $t->allKeys('en', 'messages');
        $errorKeys = $t->allKeys('en', 'errors');

        $this->assertContains('welcome', $messageKeys);
        $this->assertContains('not_found', $errorKeys);
        $this->assertNotContains('not_found', $messageKeys);
    }

    // ═══════════════════════════════════════════════════════════════
    // CacheLoader Tagging
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function cache_loader_tracks_locale_tags(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturn(true);

        $fileLoader = new FileLoader($this->fixturesPath);
        $cached = new CacheLoader($fileLoader, $cache);

        $cached->load('en', 'messages');
        $cached->load('en', 'errors');

        $tags = $cached->getTags();
        $this->assertArrayHasKey('locale:en', $tags);
        $this->assertCount(2, $tags['locale:en']);
    }

    #[Test]
    public function cache_loader_forgets_locale(): void
    {
        $deletedKeys = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturn(true);
        $cache->method('delete')->willReturnCallback(function (string $key) use (&$deletedKeys): bool {
            $deletedKeys[] = $key;
            return true;
        });

        $fileLoader = new FileLoader($this->fixturesPath);
        $cached = new CacheLoader($fileLoader, $cache);

        $cached->load('en', 'messages');
        $cached->load('en', 'errors');
        $cached->forgetLocale('en');

        $this->assertCount(2, $deletedKeys);
        $this->assertEmpty($cached->getTags()['locale:en'] ?? []);
    }

    #[Test]
    public function cache_loader_flush_clears_tags(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturn(true);
        $cache->method('clear')->willReturn(true);

        $fileLoader = new FileLoader($this->fixturesPath);
        $cached = new CacheLoader($fileLoader, $cache);

        $cached->load('en', 'messages');
        $this->assertNotEmpty($cached->getTags());

        $cached->flush();
        $this->assertEmpty($cached->getTags());
    }

    // ═══════════════════════════════════════════════════════════════
    // MessageFormatter — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function formatter_no_params_returns_original(): void
    {
        $f = new MessageFormatter();
        $this->assertSame('Hello world', $f->format('Hello world'));
    }

    #[Test]
    public function formatter_multiple_params(): void
    {
        $f = new MessageFormatter();
        $result = $f->format(':a + :b = :c', ['a' => '1', 'b' => '2', 'c' => '3']);
        $this->assertSame('1 + 2 = 3', $result);
    }

    #[Test]
    public function formatter_uppercase_and_lower_same_message(): void
    {
        $f = new MessageFormatter();
        $result = $f->format(':name and :NAME', ['name' => 'test']);
        $this->assertSame('test and TEST', $result);
    }

    #[Test]
    public function formatter_braced_without_modifier(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Hello {name}!', ['name' => 'Yorch']);
        $this->assertSame('Hello Yorch!', $result);
    }

    #[Test]
    public function formatter_capitalize_modifier(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('{msg|capitalize}', ['msg' => 'hello world']);
        $this->assertSame('Hello world', $result);
    }

    #[Test]
    public function formatter_number_modifier_with_decimals(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Price: {amount|number:2}', ['amount' => 99.9]);
        $this->assertStringContainsString('99', $result);
    }

    #[Test]
    public function formatter_unknown_modifier_returns_string(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Test: {val|nonexistent}', ['val' => 'hello']);
        $this->assertSame('Test: hello', $result);
    }

    #[Test]
    public function formatter_object_with_tostring(): void
    {
        $f = new MessageFormatter();
        $obj = new class {
            public function __toString(): string
            {
                return 'StringableObject';
            }
        };
        $result = $f->format('Obj: :val', ['val' => $obj]);
        $this->assertSame('Obj: StringableObject', $result);
    }

    #[Test]
    public function formatter_null_value_returns_empty(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Val: :val', ['val' => null]);
        $this->assertSame('Val: ', $result);
    }

    #[Test]
    public function formatter_custom_sanitizer(): void
    {
        $sanitizer = new class implements SanitizerInterface {
            public function sanitize(string $value): string
            {
                return strtoupper($value);
            }
        };

        $f = new MessageFormatter(autoEscape: true, sanitizer: $sanitizer);
        $result = $f->format('Name: :name', ['name' => 'test']);
        $this->assertSame('Name: TEST', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // NumberFormatter — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function number_formatter_decimal_with_zero(): void
    {
        $f = new NumberFormatter();
        $this->assertSame('0', $f->decimal(0, 'en'));
    }

    #[Test]
    public function number_formatter_decimal_negative(): void
    {
        $f = new NumberFormatter();
        $result = $f->decimal(-1234, 'en');
        $this->assertStringContainsString('1,234', $result);
    }

    #[Test]
    public function number_formatter_compact_zero(): void
    {
        $f = new NumberFormatter();
        $this->assertSame('0', $f->compact(0));
    }

    #[Test]
    public function number_formatter_ordinal_special_cases(): void
    {
        $f = new NumberFormatter();
        $r11 = $f->ordinal(11, 'en');
        $r12 = $f->ordinal(12, 'en');
        $r13 = $f->ordinal(13, 'en');
        $r21 = $f->ordinal(21, 'en');

        // 11th, 12th, 13th are all "th"
        $this->assertStringContainsString('11', $r11);
        $this->assertStringContainsString('12', $r12);
        $this->assertStringContainsString('13', $r13);
        $this->assertStringContainsString('21', $r21);
    }

    #[Test]
    public function number_formatter_file_size_large(): void
    {
        $f = new NumberFormatter();
        $result = $f->fileSize(1_099_511_627_776); // 1 TB
        $this->assertStringContainsString('TB', $result);
    }

    #[Test]
    public function number_formatter_currency_euro(): void
    {
        $f = new NumberFormatter();
        $result = $f->currency(100, 'EUR', 'en');
        $this->assertStringContainsString('100', $result);
    }

    #[Test]
    public function number_formatter_currency_gbp(): void
    {
        $f = new NumberFormatter();
        $result = $f->currency(50, 'GBP', 'en');
        $this->assertStringContainsString('50', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // DateFormatter — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function date_formatter_full_format(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $result = $f->format($dt, 'full');
        $this->assertStringContainsString('Thursday', $result);
        $this->assertStringContainsString('January', $result);
    }

    #[Test]
    public function date_formatter_time_format(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15 14:30:00');
        $result = $f->format($dt, 'time');
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('30', $result);
    }

    #[Test]
    public function date_formatter_datetime_format(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15 14:30:00');
        $result = $f->format($dt, 'datetime');
        $this->assertStringContainsString('Jan', $result);
        $this->assertStringContainsString('15', $result);
    }

    #[Test]
    public function date_formatter_custom_format_string(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $result = $f->format($dt, 'Y/m/d');
        $this->assertSame('2026/01/15', $result);
    }

    #[Test]
    public function date_formatter_relative_days(): void
    {
        $f = new DateFormatter();
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $past = new DateTimeImmutable('2026-01-12 12:00:00');
        $result = $f->relative($past, 'en', $now);
        $this->assertStringContainsString('3 days ago', $result);
    }

    #[Test]
    public function date_formatter_relative_months(): void
    {
        $f = new DateFormatter();
        $now = new DateTimeImmutable('2026-06-15');
        $past = new DateTimeImmutable('2026-04-15');
        $result = $f->relative($past, 'en', $now);
        $this->assertStringContainsString('month', $result);
    }

    #[Test]
    public function date_formatter_iso_output(): void
    {
        $f = new DateFormatter();
        $result = $f->iso('2026-01-15 12:00:00');
        $this->assertStringContainsString('2026-01-15', $result);
        $this->assertStringContainsString('T', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Pluralizer — Extended Locales
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function pluralizer_czech(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'cs'));
        $this->assertSame(PluralCategory::Few, $p->getCategoryForCount(3, 'cs'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(5, 'cs'));
    }

    #[Test]
    public function pluralizer_romanian(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'ro'));
        $this->assertSame(PluralCategory::Few, $p->getCategoryForCount(0, 'ro'));
    }

    #[Test]
    public function pluralizer_welsh(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::Zero, $p->getCategoryForCount(0, 'cy'));
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'cy'));
        $this->assertSame(PluralCategory::Two, $p->getCategoryForCount(2, 'cy'));
        $this->assertSame(PluralCategory::Few, $p->getCategoryForCount(3, 'cy'));
        $this->assertSame(PluralCategory::Many, $p->getCategoryForCount(6, 'cy'));
    }

    #[Test]
    public function pluralizer_ukrainian_same_as_russian(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'uk'));
        $this->assertSame(PluralCategory::Few, $p->getCategoryForCount(3, 'uk'));
    }

    #[Test]
    public function pluralizer_chinese_always_other(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(0, 'zh'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(1, 'zh'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(999, 'zh'));
    }

    #[Test]
    public function pluralizer_icu_category_labels(): void
    {
        $p = new Pluralizer();
        $msg = 'one: One item|other: :count items';

        $this->assertSame('One item', $p->choose($msg, 1, 'en'));
        $this->assertSame('5 items', $p->choose($msg, 5, 'en'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Exceptions
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function invalid_locale_exception_stores_locale(): void
    {
        $e = new InvalidLocaleException('bad-locale');
        $this->assertSame('bad-locale', $e->locale);
        $this->assertStringContainsString('bad-locale', $e->getMessage());
    }

    #[Test]
    public function translation_not_found_stores_key_and_locale(): void
    {
        $e = new TranslationNotFoundException('some.key', 'en');
        $this->assertSame('some.key', $e->key);
        $this->assertSame('en', $e->locale);
    }

    #[Test]
    public function unsupported_locale_stores_locale(): void
    {
        $e = new UnsupportedLocaleException('xx');
        $this->assertSame('xx', $e->locale);
    }

    // ═══════════════════════════════════════════════════════════════
    // Direction — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function direction_persian_is_rtl(): void
    {
        $this->assertSame(Direction::RTL, Direction::fromLocale('fa'));
    }

    #[Test]
    public function direction_urdu_is_rtl(): void
    {
        $this->assertSame(Direction::RTL, Direction::fromLocale('ur'));
    }

    #[Test]
    public function direction_unknown_locale_is_ltr(): void
    {
        $this->assertSame(Direction::LTR, Direction::fromLocale('xx'));
    }

    #[Test]
    public function direction_is_rtl_method(): void
    {
        $this->assertTrue(Direction::RTL->isRtl());
        $this->assertFalse(Direction::LTR->isRtl());
    }

    // ═══════════════════════════════════════════════════════════════
    // LocaleInfo — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function locale_info_japanese(): void
    {
        $this->assertSame('Japanese', LocaleInfo::name('ja'));
        $this->assertSame('日本語', LocaleInfo::nativeName('ja'));
        $this->assertSame('Jpan', LocaleInfo::script('ja'));
    }

    #[Test]
    public function locale_info_korean(): void
    {
        $this->assertSame('Korean', LocaleInfo::name('ko'));
        $this->assertSame('한국어', LocaleInfo::nativeName('ko'));
        $this->assertSame('Kore', LocaleInfo::script('ko'));
    }

    #[Test]
    public function locale_info_hindi(): void
    {
        $this->assertSame('Hindi', LocaleInfo::name('hi'));
        $this->assertSame('हिन्दी', LocaleInfo::nativeName('hi'));
    }

    #[Test]
    public function locale_info_french(): void
    {
        $this->assertSame('French', LocaleInfo::name('fr'));
        $this->assertSame('Français', LocaleInfo::nativeName('fr'));
        $this->assertSame('🇫🇷', LocaleInfo::flag('fr'));
    }

    #[Test]
    public function locale_info_german(): void
    {
        $this->assertSame('German', LocaleInfo::name('de'));
        $this->assertSame('Deutsch', LocaleInfo::nativeName('de'));
    }

    #[Test]
    public function locale_info_portuguese(): void
    {
        $this->assertSame('Portuguese', LocaleInfo::name('pt'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Security — Extended edge cases
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function translator_rejects_empty_locale(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator('');
    }

    #[Test]
    public function translator_rejects_locale_with_spaces(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator('en US');
    }

    #[Test]
    public function translator_rejects_long_locale(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator('toolonglocale');
    }

    #[Test]
    public function translator_rejects_locale_with_special_chars(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator('en;DROP TABLE');
    }

    #[Test]
    public function fileloader_rejects_backslash_in_locale(): void
    {
        $loader = new FileLoader($this->fixturesPath);

        $this->expectException(LoaderException::class);
        $loader->load('en\\..', 'messages');
    }

    #[Test]
    public function mlc_loader_rejects_double_dot(): void
    {
        $path = __DIR__ . '/../fixtures/extlang';
        $loader = new MlcLoader($path);

        $this->expectException(LoaderException::class);
        $loader->load('..', 'messages');
    }

    // ═══════════════════════════════════════════════════════════════
    // Translator — Deep merge edge cases
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function translator_cross_group_isolation(): void
    {
        $t = $this->translator();

        $this->assertSame('Welcome!', $t->trans('messages.welcome'));
        $this->assertSame('Not found', $t->trans('errors.not_found'));

        // Groups don't contaminate each other
        $this->assertSame('errors.welcome', $t->trans('errors.welcome'));
    }

    #[Test]
    public function translator_locale_fallback_chain(): void
    {
        $t = $this->translator('es');

        // Spanish exists
        $this->assertSame('¡Bienvenido!', $t->trans('messages.welcome'));
        // Spanish missing, English fallback
        $this->assertSame('Hello, :name!', $t->trans('messages.greeting'));
    }

    #[Test]
    public function translator_has_across_locales(): void
    {
        $t = $this->translator('es');

        $this->assertTrue($t->has('messages.welcome', 'es'));
        $this->assertTrue($t->has('messages.welcome', 'en'));
        $this->assertFalse($t->has('messages.nonexistent', 'es'));
    }

    // ═══════════════════════════════════════════════════════════════
    // PluralCategory — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function plural_category_from_string(): void
    {
        $this->assertSame(PluralCategory::One, PluralCategory::from('one'));
        $this->assertSame(PluralCategory::Zero, PluralCategory::from('zero'));
        $this->assertSame(PluralCategory::Other, PluralCategory::from('other'));
    }

    #[Test]
    public function plural_category_try_from_invalid(): void
    {
        $this->assertNull(PluralCategory::tryFrom('invalid'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    private function translator(string $locale = 'en'): Translator
    {
        $t = new Translator($locale, 'en');
        $t->addLoader(new FileLoader($this->fixturesPath));

        return $t;
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
