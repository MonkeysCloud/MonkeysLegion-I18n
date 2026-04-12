<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Support;

use MonkeysLegion\I18n\Enum\Direction;

/**
 * Metadata about locales — name, native name, direction, script, flag.
 */
final class LocaleInfo
{
    /**
     * Locale metadata registry.
     *
     * @var array<string, array{name: string, native: string, direction: Direction, script: string, flag: string}>
     */
    private const array LOCALES = [
        'en' => ['name' => 'English',      'native' => 'English',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇺🇸'],
        'es' => ['name' => 'Spanish',      'native' => 'Español',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇪🇸'],
        'fr' => ['name' => 'French',       'native' => 'Français',     'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇫🇷'],
        'de' => ['name' => 'German',       'native' => 'Deutsch',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇩🇪'],
        'it' => ['name' => 'Italian',      'native' => 'Italiano',     'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇮🇹'],
        'pt' => ['name' => 'Portuguese',   'native' => 'Português',    'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇧🇷'],
        'ru' => ['name' => 'Russian',      'native' => 'Русский',      'direction' => Direction::LTR, 'script' => 'Cyrl', 'flag' => '🇷🇺'],
        'ja' => ['name' => 'Japanese',     'native' => '日本語',        'direction' => Direction::LTR, 'script' => 'Jpan', 'flag' => '🇯🇵'],
        'ko' => ['name' => 'Korean',       'native' => '한국어',        'direction' => Direction::LTR, 'script' => 'Kore', 'flag' => '🇰🇷'],
        'zh' => ['name' => 'Chinese',      'native' => '中文',          'direction' => Direction::LTR, 'script' => 'Hans', 'flag' => '🇨🇳'],
        'ar' => ['name' => 'Arabic',       'native' => 'العربية',       'direction' => Direction::RTL, 'script' => 'Arab', 'flag' => '🇸🇦'],
        'he' => ['name' => 'Hebrew',       'native' => 'עברית',         'direction' => Direction::RTL, 'script' => 'Hebr', 'flag' => '🇮🇱'],
        'fa' => ['name' => 'Persian',      'native' => 'فارسی',         'direction' => Direction::RTL, 'script' => 'Arab', 'flag' => '🇮🇷'],
        'ur' => ['name' => 'Urdu',         'native' => 'اردو',          'direction' => Direction::RTL, 'script' => 'Arab', 'flag' => '🇵🇰'],
        'hi' => ['name' => 'Hindi',        'native' => 'हिन्दी',         'direction' => Direction::LTR, 'script' => 'Deva', 'flag' => '🇮🇳'],
        'nl' => ['name' => 'Dutch',        'native' => 'Nederlands',   'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇳🇱'],
        'pl' => ['name' => 'Polish',       'native' => 'Polski',       'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇵🇱'],
        'tr' => ['name' => 'Turkish',      'native' => 'Türkçe',       'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇹🇷'],
        'sv' => ['name' => 'Swedish',      'native' => 'Svenska',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇸🇪'],
        'da' => ['name' => 'Danish',       'native' => 'Dansk',        'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇩🇰'],
        'no' => ['name' => 'Norwegian',    'native' => 'Norsk',        'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇳🇴'],
        'fi' => ['name' => 'Finnish',      'native' => 'Suomi',        'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇫🇮'],
        'el' => ['name' => 'Greek',        'native' => 'Ελληνικά',     'direction' => Direction::LTR, 'script' => 'Grek', 'flag' => '🇬🇷'],
        'cs' => ['name' => 'Czech',        'native' => 'Čeština',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇨🇿'],
        'sk' => ['name' => 'Slovak',       'native' => 'Slovenčina',   'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇸🇰'],
        'ro' => ['name' => 'Romanian',     'native' => 'Română',       'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇷🇴'],
        'hu' => ['name' => 'Hungarian',    'native' => 'Magyar',       'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇭🇺'],
        'uk' => ['name' => 'Ukrainian',    'native' => 'Українська',   'direction' => Direction::LTR, 'script' => 'Cyrl', 'flag' => '🇺🇦'],
        'th' => ['name' => 'Thai',         'native' => 'ไทย',           'direction' => Direction::LTR, 'script' => 'Thai', 'flag' => '🇹🇭'],
        'vi' => ['name' => 'Vietnamese',   'native' => 'Tiếng Việt',   'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇻🇳'],
        'id' => ['name' => 'Indonesian',   'native' => 'Indonesia',    'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇮🇩'],
        'ms' => ['name' => 'Malay',        'native' => 'Bahasa Melayu','direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇲🇾'],
        'bn' => ['name' => 'Bengali',      'native' => 'বাংলা',          'direction' => Direction::LTR, 'script' => 'Beng', 'flag' => '🇧🇩'],
        'ta' => ['name' => 'Tamil',        'native' => 'தமிழ்',         'direction' => Direction::LTR, 'script' => 'Taml', 'flag' => '🇮🇳'],
        'bg' => ['name' => 'Bulgarian',    'native' => 'Български',    'direction' => Direction::LTR, 'script' => 'Cyrl', 'flag' => '🇧🇬'],
        'sr' => ['name' => 'Serbian',      'native' => 'Српски',       'direction' => Direction::LTR, 'script' => 'Cyrl', 'flag' => '🇷🇸'],
        'hr' => ['name' => 'Croatian',     'native' => 'Hrvatski',     'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇭🇷'],
        'ca' => ['name' => 'Catalan',      'native' => 'Català',       'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🏴'],
        'eu' => ['name' => 'Basque',       'native' => 'Euskara',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🏴'],
        'gl' => ['name' => 'Galician',     'native' => 'Galego',       'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🏴'],
        'lt' => ['name' => 'Lithuanian',   'native' => 'Lietuvių',     'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇱🇹'],
        'lv' => ['name' => 'Latvian',      'native' => 'Latviešu',     'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇱🇻'],
        'et' => ['name' => 'Estonian',     'native' => 'Eesti',        'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇪🇪'],
        'sl' => ['name' => 'Slovenian',    'native' => 'Slovenščina',  'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇸🇮'],
        'is' => ['name' => 'Icelandic',    'native' => 'Íslenska',     'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇮🇸'],
        'ga' => ['name' => 'Irish',        'native' => 'Gaeilge',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇮🇪'],
        'cy' => ['name' => 'Welsh',        'native' => 'Cymraeg',      'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🏴'],
        'af' => ['name' => 'Afrikaans',    'native' => 'Afrikaans',    'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇿🇦'],
        'sw' => ['name' => 'Swahili',      'native' => 'Kiswahili',    'direction' => Direction::LTR, 'script' => 'Latn', 'flag' => '🇰🇪'],
        'am' => ['name' => 'Amharic',      'native' => 'አማርኛ',         'direction' => Direction::LTR, 'script' => 'Ethi', 'flag' => '🇪🇹'],
    ];

    /**
     * Get human-readable name (English).
     */
    public static function name(string $locale): string
    {
        return self::LOCALES[$locale]['name'] ?? $locale;
    }

    /**
     * Get native name.
     */
    public static function nativeName(string $locale): string
    {
        return self::LOCALES[$locale]['native'] ?? $locale;
    }

    /**
     * Get text direction.
     */
    public static function direction(string $locale): Direction
    {
        return self::LOCALES[$locale]['direction'] ?? Direction::fromLocale($locale);
    }

    /**
     * Get writing script.
     */
    public static function script(string $locale): string
    {
        return self::LOCALES[$locale]['script'] ?? 'Latn';
    }

    /**
     * Get flag emoji.
     */
    public static function flag(string $locale): string
    {
        return self::LOCALES[$locale]['flag'] ?? '🏳️';
    }

    /**
     * Check if locale is known.
     */
    public static function isKnown(string $locale): bool
    {
        return isset(self::LOCALES[$locale]);
    }

    /**
     * Get all known locale codes.
     *
     * @return list<string>
     */
    public static function allCodes(): array
    {
        return array_keys(self::LOCALES);
    }

    /**
     * Check if locale is RTL.
     */
    public static function isRtl(string $locale): bool
    {
        return self::direction($locale) === Direction::RTL;
    }
}
