<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Tests\Unit;

use MonkeysLegion\I18n\Contract\LoaderInterface;
use MonkeysLegion\I18n\DateFormatter;
use MonkeysLegion\I18n\Enum\Direction;
use MonkeysLegion\I18n\Enum\PluralCategory;
use MonkeysLegion\I18n\Event\LocaleChangedEvent;
use MonkeysLegion\I18n\Exceptions\InvalidLocaleException;
use MonkeysLegion\I18n\Loaders\CompiledLoader;
use MonkeysLegion\I18n\Loaders\DatabaseLoader;
use MonkeysLegion\I18n\Loaders\FileLoader;
use MonkeysLegion\I18n\LocaleManager;
use MonkeysLegion\I18n\MessageFormatter;
use MonkeysLegion\I18n\NumberFormatter;
use MonkeysLegion\I18n\Pluralizer;
use MonkeysLegion\I18n\Support\LocaleInfo;
use MonkeysLegion\I18n\Translator;
use MonkeysLegion\I18n\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use DateTimeImmutable;

/**
 * Comprehensive v2 test suite — covering all new features, security, and performance.
 */
#[CoversClass(Translator::class)]
#[CoversClass(Pluralizer::class)]
#[CoversClass(MessageFormatter::class)]
#[CoversClass(NumberFormatter::class)]
#[CoversClass(DateFormatter::class)]
#[CoversClass(LocaleManager::class)]
#[CoversClass(LocaleInfo::class)]
#[CoversClass(FileLoader::class)]
#[CoversClass(CompiledLoader::class)]
final class I18nV2Test extends TestCase
{
    private string $fixturesPath;
    private string $compilePath;

    // ── Setup / teardown ──────────────────────────────────────────

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/v2lang';
        $this->compilePath = __DIR__ . '/../fixtures/v2compiled';

        // Create fixture directories
        @mkdir($this->fixturesPath . '/en', 0755, true);
        @mkdir($this->fixturesPath . '/es', 0755, true);
        @mkdir($this->compilePath, 0755, true);

        // English translations
        file_put_contents(
            $this->fixturesPath . '/en/messages.json',
            json_encode([
                'welcome'  => 'Welcome!',
                'greeting' => 'Hello, :name!',
                'farewell' => 'Goodbye, :NAME!',
                'title'    => 'Hello :Name',
                'items'    => '{0} No items|{1} One item|[2,*] :count items',
                'nested'   => ['key' => 'Nested value', 'deep' => ['value' => 'Deep']],
                'html'     => 'Hello <b>:name</b>',
            ]),
        );

        file_put_contents(
            $this->fixturesPath . '/en/validation.json',
            json_encode([
                'required' => 'The :field field is required.',
                'email'    => 'Please enter a valid email.',
            ]),
        );

        // Spanish translations
        file_put_contents(
            $this->fixturesPath . '/es/messages.json',
            json_encode([
                'welcome'  => '¡Bienvenido!',
                'greeting' => '¡Hola, :name!',
                'items'    => '{0} Sin artículos|{1} Un artículo|[2,*] :count artículos',
            ]),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
        $this->removeDirectory($this->compilePath);
    }

    // ═══════════════════════════════════════════════════════════════
    // Translator Core
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function translator_translates_basic_key(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Welcome!', $t->trans('messages.welcome'));
    }

    #[Test]
    public function translator_replaces_lowercase_parameter(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Hello, Yorch!', $t->trans('messages.greeting', ['name' => 'Yorch']));
    }

    #[Test]
    public function translator_replaces_uppercase_parameter(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Goodbye, YORCH!', $t->trans('messages.farewell', ['name' => 'Yorch']));
    }

    #[Test]
    public function translator_replaces_ucfirst_parameter(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Hello Yorch', $t->trans('messages.title', ['name' => 'yorch']));
    }

    #[Test]
    public function translator_resolves_nested_key(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Nested value', $t->trans('messages.nested.key'));
    }

    #[Test]
    public function translator_resolves_deep_nested_key(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Deep', $t->trans('messages.nested.deep.value'));
    }

    #[Test]
    public function translator_returns_key_when_not_found(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('messages.nonexistent', $t->trans('messages.nonexistent'));
    }

    #[Test]
    public function translator_has_returns_true_for_existing_key(): void
    {
        $t = $this->createTranslator();
        $this->assertTrue($t->has('messages.welcome'));
    }

    #[Test]
    public function translator_has_returns_false_for_missing_key(): void
    {
        $t = $this->createTranslator();
        $this->assertFalse($t->has('messages.nonexistent'));
    }

    #[Test]
    public function translator_falls_back_to_fallback_locale(): void
    {
        $t = $this->createTranslator('es');
        // 'validation.required' only exists in English
        $this->assertSame('The :field field is required.', $t->trans('validation.required'));
    }

    #[Test]
    public function translator_switches_locale(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('Welcome!', $t->trans('messages.welcome'));

        $t->setLocale('es');
        $this->assertSame('¡Bienvenido!', $t->trans('messages.welcome'));
    }

    #[Test]
    public function translator_tracks_missing_translations(): void
    {
        $t = $this->createTranslator();
        $t->setTrackMissing(true);

        $t->trans('messages.miss1');
        $t->trans('messages.miss2');
        $t->trans('messages.miss1'); // Duplicate

        $missing = $t->getMissingTranslations();
        $this->assertCount(2, $missing);
        $this->assertContains('en.messages.miss1', $missing);
        $this->assertContains('en.messages.miss2', $missing);
    }

    #[Test]
    public function translator_clears_missing_translations(): void
    {
        $t = $this->createTranslator();
        $t->setTrackMissing(true);
        $t->trans('messages.miss1');

        $t->clearMissingTranslations();
        $this->assertEmpty($t->getMissingTranslations());
    }

    #[Test]
    public function translator_warmup_preloads_groups(): void
    {
        $t = $this->createTranslator();
        $t->warmUp('en', ['messages', 'validation']);

        $groups = $t->getLoadedGroups();
        $this->assertContains('messages.en', $groups);
        $this->assertContains('validation.en', $groups);
    }

    #[Test]
    public function translator_choice_with_zero(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('No items', $t->choice('messages.items', 0));
    }

    #[Test]
    public function translator_choice_with_one(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('One item', $t->choice('messages.items', 1));
    }

    #[Test]
    public function translator_choice_with_many(): void
    {
        $t = $this->createTranslator();
        $this->assertSame('5 items', $t->choice('messages.items', 5));
    }

    #[Test]
    public function translator_choice_for_spanish(): void
    {
        $t = $this->createTranslator('es');
        $this->assertSame('Sin artículos', $t->choice('messages.items', 0));
        $this->assertSame('Un artículo', $t->choice('messages.items', 1));
        $this->assertSame('3 artículos', $t->choice('messages.items', 3));
    }

    #[Test]
    public function translator_dispatches_locale_changed_event(): void
    {
        $t = $this->createTranslator();
        $events = [];

        $t->setEventDispatcher(function (LocaleChangedEvent $e) use (&$events): void {
            $events[] = $e;
        });

        $t->setLocale('es');

        $this->assertCount(1, $events);
        $this->assertSame('en', $events[0]->previousLocale);
        $this->assertSame('es', $events[0]->newLocale);
    }

    // ═══════════════════════════════════════════════════════════════
    // Locale Validation (Security)
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function translator_rejects_invalid_locale(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator('../etc/passwd');
    }

    #[Test]
    public function translator_rejects_locale_with_path_traversal(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator('en/../../');
    }

    #[Test]
    public function translator_rejects_locale_with_null_byte(): void
    {
        $this->expectException(InvalidLocaleException::class);
        new Translator("en\0");
    }

    #[Test]
    public function translator_accepts_valid_locale_with_region(): void
    {
        $t = new Translator('en_US');
        $this->assertSame('en_US', $t->getLocale());
    }

    // ═══════════════════════════════════════════════════════════════
    // FileLoader Security
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function fileloader_rejects_path_traversal_in_locale(): void
    {
        $loader = new FileLoader($this->fixturesPath);

        $this->expectException(\MonkeysLegion\I18n\Exceptions\LoaderException::class);
        $loader->load('../etc', 'passwd');
    }

    #[Test]
    public function fileloader_rejects_null_byte_in_group(): void
    {
        $loader = new FileLoader($this->fixturesPath);

        $this->expectException(\MonkeysLegion\I18n\Exceptions\LoaderException::class);
        $loader->load('en', "messages\0");
    }

    #[Test]
    public function fileloader_rejects_slash_in_locale(): void
    {
        $loader = new FileLoader($this->fixturesPath);

        $this->expectException(\MonkeysLegion\I18n\Exceptions\LoaderException::class);
        $loader->load('en/../../', 'messages');
    }

    // ═══════════════════════════════════════════════════════════════
    // DatabaseLoader Security
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function databaseloader_rejects_invalid_table_name(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $this->expectException(\InvalidArgumentException::class);
        new DatabaseLoader($pdo, 'DROP TABLE users; --');
    }

    #[Test]
    public function databaseloader_accepts_valid_table_name(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $loader = new DatabaseLoader($pdo, 'app_translations');
        $this->assertInstanceOf(DatabaseLoader::class, $loader);
    }

    // ═══════════════════════════════════════════════════════════════
    // MessageFormatter Security (XSS)
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function formatter_does_not_escape_by_default(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Hello :name', ['name' => '<script>alert(1)</script>']);
        $this->assertSame('Hello <script>alert(1)</script>', $result);
    }

    #[Test]
    public function formatter_escapes_with_auto_escape_enabled(): void
    {
        $f = new MessageFormatter(autoEscape: true);
        $result = $f->format('Hello :name', ['name' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function formatter_applies_upper_modifier(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Name: {name|upper}', ['name' => 'yorch']);
        $this->assertSame('Name: YORCH', $result);
    }

    #[Test]
    public function formatter_applies_lower_modifier(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Name: {name|lower}', ['name' => 'YORCH']);
        $this->assertSame('Name: yorch', $result);
    }

    #[Test]
    public function formatter_applies_title_modifier(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Name: {name|title}', ['name' => 'hello world']);
        $this->assertSame('Name: Hello World', $result);
    }

    #[Test]
    public function formatter_applies_truncate_modifier(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Test: {text|truncate:5}', ['text' => 'Hello World']);
        $this->assertSame('Test: Hello...', $result);
    }

    #[Test]
    public function formatter_handles_boolean_values(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Active: :status', ['status' => true]);
        $this->assertSame('Active: true', $result);
    }

    #[Test]
    public function formatter_handles_array_values(): void
    {
        $f = new MessageFormatter();
        $result = $f->format('Tags: :tags', ['tags' => ['php', 'i18n']]);
        $this->assertSame('Tags: php, i18n', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // PluralCategory Enum
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function plural_category_has_all_values(): void
    {
        $cases = PluralCategory::cases();
        $this->assertCount(6, $cases);
        $this->assertSame('other', PluralCategory::Other->value);
    }

    #[Test]
    public function plural_category_other_is_default(): void
    {
        $this->assertTrue(PluralCategory::Other->isDefault());
        $this->assertFalse(PluralCategory::One->isDefault());
    }

    #[Test]
    public function plural_category_ordered(): void
    {
        $ordered = PluralCategory::ordered();
        $this->assertSame(PluralCategory::Zero, $ordered[0]);
        $this->assertSame(PluralCategory::Other, $ordered[5]);
    }

    #[Test]
    public function pluralizer_returns_category_for_count(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'en'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(5, 'en'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Direction Enum
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function direction_from_english_is_ltr(): void
    {
        $this->assertSame(Direction::LTR, Direction::fromLocale('en'));
    }

    #[Test]
    public function direction_from_arabic_is_rtl(): void
    {
        $this->assertSame(Direction::RTL, Direction::fromLocale('ar'));
    }

    #[Test]
    public function direction_from_hebrew_is_rtl(): void
    {
        $this->assertSame(Direction::RTL, Direction::fromLocale('he'));
    }

    #[Test]
    public function direction_css_attribute(): void
    {
        $this->assertSame('dir="ltr"', Direction::LTR->cssAttribute());
        $this->assertSame('dir="rtl"', Direction::RTL->cssAttribute());
    }

    // ═══════════════════════════════════════════════════════════════
    // LocaleInfo
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function locale_info_returns_english_name(): void
    {
        $this->assertSame('Spanish', LocaleInfo::name('es'));
    }

    #[Test]
    public function locale_info_returns_native_name(): void
    {
        $this->assertSame('Español', LocaleInfo::nativeName('es'));
    }

    #[Test]
    public function locale_info_returns_direction(): void
    {
        $this->assertSame(Direction::LTR, LocaleInfo::direction('en'));
        $this->assertSame(Direction::RTL, LocaleInfo::direction('ar'));
    }

    #[Test]
    public function locale_info_returns_flag(): void
    {
        $this->assertSame('🇪🇸', LocaleInfo::flag('es'));
        $this->assertSame('🇺🇸', LocaleInfo::flag('en'));
    }

    #[Test]
    public function locale_info_detects_rtl(): void
    {
        $this->assertTrue(LocaleInfo::isRtl('ar'));
        $this->assertTrue(LocaleInfo::isRtl('he'));
        $this->assertFalse(LocaleInfo::isRtl('en'));
    }

    #[Test]
    public function locale_info_is_known(): void
    {
        $this->assertTrue(LocaleInfo::isKnown('en'));
        $this->assertTrue(LocaleInfo::isKnown('ja'));
        $this->assertFalse(LocaleInfo::isKnown('xx'));
    }

    #[Test]
    public function locale_info_all_codes_has_50_plus(): void
    {
        $codes = LocaleInfo::allCodes();
        $this->assertGreaterThanOrEqual(50, count($codes));
        $this->assertContains('en', $codes);
        $this->assertContains('ar', $codes);
    }

    #[Test]
    public function locale_info_script(): void
    {
        $this->assertSame('Latn', LocaleInfo::script('en'));
        $this->assertSame('Arab', LocaleInfo::script('ar'));
        $this->assertSame('Cyrl', LocaleInfo::script('ru'));
    }

    #[Test]
    public function locale_info_unknown_locale_returns_code(): void
    {
        $this->assertSame('xx', LocaleInfo::name('xx'));
        $this->assertSame('xx', LocaleInfo::nativeName('xx'));
        $this->assertSame('🏳️', LocaleInfo::flag('xx'));
    }

    // ═══════════════════════════════════════════════════════════════
    // NumberFormatter
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function number_formatter_decimal(): void
    {
        $f = new NumberFormatter();
        $this->assertSame('1,234', $f->decimal(1234, 'en'));
        $this->assertSame('3.14', $f->decimal(3.14159, 'en', 2));
    }

    #[Test]
    public function number_formatter_compact(): void
    {
        $f = new NumberFormatter();
        $this->assertSame('1.2K', $f->compact(1234));
        $this->assertSame('1.5M', $f->compact(1_500_000));
        $this->assertSame('2.3B', $f->compact(2_345_000_000));
        $this->assertSame('500', $f->compact(500));
    }

    #[Test]
    public function number_formatter_compact_negative(): void
    {
        $f = new NumberFormatter();
        $this->assertSame('-1.2K', $f->compact(-1234));
    }

    #[Test]
    public function number_formatter_ordinal(): void
    {
        $f = new NumberFormatter();
        // Fallback if no intl
        $result = $f->ordinal(1, 'en');
        $this->assertStringContainsString('1', $result);
    }

    #[Test]
    public function number_formatter_file_size(): void
    {
        $f = new NumberFormatter();
        $this->assertSame('0 B', $f->fileSize(0));
        $this->assertSame('1.00 KB', $f->fileSize(1024));
        $this->assertSame('1.50 MB', $f->fileSize(1572864));
    }

    #[Test]
    public function number_formatter_percent(): void
    {
        $f = new NumberFormatter();
        $result = $f->percent(0.15, 'en');
        $this->assertStringContainsString('15', $result);
    }

    #[Test]
    public function number_formatter_currency(): void
    {
        $f = new NumberFormatter();
        $result = $f->currency(42.50, 'USD', 'en');
        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString('$', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // DateFormatter
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function date_formatter_short(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $result = $f->format($dt, 'short');
        $this->assertSame('1/15/26', $result);
    }

    #[Test]
    public function date_formatter_medium(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $result = $f->format($dt, 'medium');
        $this->assertSame('Jan 15, 2026', $result);
    }

    #[Test]
    public function date_formatter_long(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $result = $f->format($dt, 'long');
        $this->assertSame('January 15, 2026', $result);
    }

    #[Test]
    public function date_formatter_iso(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $result = $f->format($dt, 'iso');
        $this->assertSame('2026-01-15', $result);
    }

    #[Test]
    public function date_formatter_relative_just_now(): void
    {
        $f = new DateFormatter();
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $dt = new DateTimeImmutable('2026-01-15 12:00:05');
        $result = $f->relative($dt, 'en', $now);
        // 5 seconds in the future
        $this->assertStringContainsString('in', $result);
    }

    #[Test]
    public function date_formatter_relative_past(): void
    {
        $f = new DateFormatter();
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $past = new DateTimeImmutable('2026-01-15 10:00:00');
        $result = $f->relative($past, 'en', $now);
        $this->assertStringContainsString('2 hours ago', $result);
    }

    #[Test]
    public function date_formatter_relative_spanish(): void
    {
        $f = new DateFormatter();
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $past = new DateTimeImmutable('2026-01-15 10:00:00');
        $result = $f->relative($past, 'es', $now);
        $this->assertStringContainsString('hace', $result);
    }

    #[Test]
    public function date_formatter_from_timestamp(): void
    {
        $f = new DateFormatter();
        $result = $f->format(0, 'iso');
        $this->assertSame('1970-01-01', $result);
    }

    #[Test]
    public function date_formatter_from_string(): void
    {
        $f = new DateFormatter();
        $result = $f->format('2026-01-15', 'iso');
        $this->assertSame('2026-01-15', $result);
    }

    #[Test]
    public function date_formatter_day_of_week(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15'); // Thursday
        $this->assertSame('Thursday', $f->dayOfWeek($dt));
        $this->assertSame('Thu', $f->dayOfWeek($dt, short: true));
    }

    #[Test]
    public function date_formatter_month_name(): void
    {
        $f = new DateFormatter();
        $dt = new DateTimeImmutable('2026-01-15');
        $this->assertSame('January', $f->monthName($dt));
        $this->assertSame('Jan', $f->monthName($dt, short: true));
    }

    #[Test]
    public function date_formatter_diff_for_humans(): void
    {
        $f = new DateFormatter();
        $from = new DateTimeImmutable('2026-01-15 10:00:00');
        $to = new DateTimeImmutable('2026-01-15 12:30:00');
        $result = $f->diffForHumans($from, $to, 'en');
        $this->assertStringContainsString('2 hours ago', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // LocaleManager
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function locale_manager_default_locale(): void
    {
        $m = new LocaleManager('en', ['en', 'es']);
        $this->assertSame('en', $m->getLocale());
    }

    #[Test]
    public function locale_manager_set_supported_locale(): void
    {
        $m = new LocaleManager('en', ['en', 'es']);
        $m->setLocale('es');
        $this->assertSame('es', $m->getLocale());
    }

    #[Test]
    public function locale_manager_rejects_unsupported_locale(): void
    {
        $m = new LocaleManager('en', ['en', 'es']);
        $this->expectException(\InvalidArgumentException::class);
        $m->setLocale('fr');
    }

    #[Test]
    public function locale_manager_rejects_invalid_locale_format(): void
    {
        $m = new LocaleManager('en', ['en']);
        $this->expectException(InvalidLocaleException::class);
        $m->setLocale('../../etc');
    }

    #[Test]
    public function locale_manager_add_supported_locale(): void
    {
        $m = new LocaleManager('en', ['en']);
        $this->assertFalse($m->isSupported('fr'));

        $m->addSupportedLocale('fr');
        $this->assertTrue($m->isSupported('fr'));
    }

    #[Test]
    public function locale_manager_parse_locale(): void
    {
        $m = new LocaleManager();
        $this->assertSame('en', $m->parseLocale('en-US'));
        $this->assertSame('en', $m->parseLocale('en_US'));
        $this->assertSame('es', $m->parseLocale('es'));
    }

    #[Test]
    public function locale_manager_reset(): void
    {
        $m = new LocaleManager('en', ['en', 'es']);
        $m->setLocale('es');
        $this->assertSame('es', $m->getLocale());

        $m->reset();
        $this->assertSame('en', $m->getLocale()); // Re-detects to default
    }

    #[Test]
    public function locale_manager_asymmetric_visibility(): void
    {
        $m = new LocaleManager('en', ['en', 'es'], 'en');
        $this->assertSame('en', $m->defaultLocale);
        $this->assertSame('en', $m->fallbackLocale);
        $this->assertSame(['en', 'es'], $m->supportedLocales);
    }

    #[Test]
    public function locale_manager_get_locale_name(): void
    {
        $m = new LocaleManager();
        $this->assertSame('Español', $m->getLocaleName('es'));
    }

    // ═══════════════════════════════════════════════════════════════
    // CompiledLoader
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function compiled_loader_falls_back_to_source(): void
    {
        $fileLoader = new FileLoader($this->fixturesPath);
        $compiled = new CompiledLoader($fileLoader, $this->compilePath);

        // No compiled file → should fallback to source loader
        $messages = $compiled->load('en', 'messages');
        $this->assertSame('Welcome!', $messages['welcome']);
    }

    #[Test]
    public function compiled_loader_compile_and_load(): void
    {
        $fileLoader = new FileLoader($this->fixturesPath);
        $compiled = new CompiledLoader($fileLoader, $this->compilePath);

        // Compile
        $path = $compiled->compile('en', $this->fixturesPath);
        $this->assertFileExists($path);

        // Load from compiled
        $compiled2 = new CompiledLoader($fileLoader, $this->compilePath);
        $messages = $compiled2->load('en', 'messages');
        $this->assertSame('Welcome!', $messages['welcome']);
    }

    #[Test]
    public function compiled_loader_is_fresh(): void
    {
        $fileLoader = new FileLoader($this->fixturesPath);
        $compiled = new CompiledLoader($fileLoader, $this->compilePath);

        $this->assertFalse($compiled->isFresh('en', $this->fixturesPath));

        $compiled->compile('en', $this->fixturesPath);
        $this->assertTrue($compiled->isFresh('en', $this->fixturesPath));
    }

    #[Test]
    public function compiled_loader_invalidate(): void
    {
        $fileLoader = new FileLoader($this->fixturesPath);
        $compiled = new CompiledLoader($fileLoader, $this->compilePath);

        $compiled->compile('en', $this->fixturesPath);
        $this->assertTrue($compiled->isFresh('en', $this->fixturesPath));

        $compiled->invalidate('en');
        $this->assertFalse($compiled->isFresh('en', $this->fixturesPath));
    }

    // ═══════════════════════════════════════════════════════════════
    // TranslatorFactory
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function factory_creates_translator(): void
    {
        $t = TranslatorFactory::create([
            'locale'  => 'en',
            'fallback' => 'en',
            'path'    => $this->fixturesPath,
        ]);

        $this->assertSame('Welcome!', $t->trans('messages.welcome'));
    }

    #[Test]
    public function factory_creates_compiled_translator(): void
    {
        $t = TranslatorFactory::create([
            'locale'        => 'en',
            'path'          => $this->fixturesPath,
            'compiled_path' => $this->compilePath,
        ]);

        $this->assertInstanceOf(Translator::class, $t);
    }

    #[Test]
    public function factory_creates_locale_manager(): void
    {
        $m = TranslatorFactory::createLocaleManager([
            'default'   => 'en',
            'supported' => ['en', 'es', 'fr'],
        ]);

        $this->assertSame('en', $m->getLocale());
        $this->assertTrue($m->isSupported('es'));
    }

    #[Test]
    public function factory_creates_number_formatter(): void
    {
        $nf = TranslatorFactory::createNumberFormatter();
        $this->assertInstanceOf(NumberFormatter::class, $nf);
    }

    #[Test]
    public function factory_creates_date_formatter(): void
    {
        $df = TranslatorFactory::createDateFormatter();
        $this->assertInstanceOf(DateFormatter::class, $df);
    }

    // ═══════════════════════════════════════════════════════════════
    // Event
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function locale_changed_event_is_immutable(): void
    {
        $event = new LocaleChangedEvent('en', 'es');
        $this->assertSame('en', $event->previousLocale);
        $this->assertSame('es', $event->newLocale);
    }

    // ═══════════════════════════════════════════════════════════════
    // Pluralizer — Extended
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function pluralizer_arabic(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::Zero, $p->getCategoryForCount(0, 'ar'));
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'ar'));
        $this->assertSame(PluralCategory::Two, $p->getCategoryForCount(2, 'ar'));
    }

    #[Test]
    public function pluralizer_polish(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'pl'));
        $this->assertSame(PluralCategory::Few, $p->getCategoryForCount(2, 'pl'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(5, 'pl'));
    }

    #[Test]
    public function pluralizer_russian(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'ru'));
        $this->assertSame(PluralCategory::Few, $p->getCategoryForCount(2, 'ru'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(5, 'ru'));
    }

    #[Test]
    public function pluralizer_japanese_always_other(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(1, 'ja'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(100, 'ja'));
    }

    #[Test]
    public function pluralizer_french_one_with_zero(): void
    {
        $p = new Pluralizer();
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(0, 'fr'));
        $this->assertSame(PluralCategory::One, $p->getCategoryForCount(1, 'fr'));
        $this->assertSame(PluralCategory::Other, $p->getCategoryForCount(2, 'fr'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Attribute
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function translatable_attribute_instantiates(): void
    {
        $attr = new \MonkeysLegion\I18n\Attribute\Translatable(
            group: 'products',
            keyPrefix: 'title',
        );
        $this->assertSame('products', $attr->group);
        $this->assertSame('title', $attr->keyPrefix);
        $this->assertTrue($attr->fallbackToValue);
    }

    #[Test]
    public function locale_attribute_instantiates(): void
    {
        $attr = new \MonkeysLegion\I18n\Attribute\Locale(validated: false);
        $this->assertFalse($attr->validated);
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    private function createTranslator(string $locale = 'en'): Translator
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
