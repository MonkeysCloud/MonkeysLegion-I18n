<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Template;

use MonkeysLegion\I18n\Translator;

/**
 * Template directive provider for MonkeysLegion-Template.
 *
 * Provides: @lang, @choice, @locale, @date, @currency, @number, @time, @datetime
 */
final class I18nDirectives
{
    // ── Properties ────────────────────────────────────────────────

    private readonly Translator $translator;

    // ── Constructor ───────────────────────────────────────────────

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    // ── Directives ────────────────────────────────────────────────

    /**
     * Get all directives.
     *
     * @return array<string, callable>
     */
    public function getDirectives(): array
    {
        return [
            'lang'     => $this->langDirective(),
            'choice'   => $this->choiceDirective(),
            'locale'   => $this->localeDirective(),
            'date'     => $this->dateDirective(),
            'time'     => $this->timeDirective(),
            'datetime' => $this->datetimeDirective(),
            'currency' => $this->currencyDirective(),
            'number'   => $this->numberDirective(),
        ];
    }

    private function langDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__translator->trans({$expression}); ?>";
    }

    private function choiceDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__translator->choice({$expression}); ?>";
    }

    private function localeDirective(): callable
    {
        return function (string $expression): string {
            if (trim($expression) === '') {
                return "<?php echo \$__translator->getLocale(); ?>";
            }

            return "<?php \$__translator->setLocale({$expression}); ?>";
        };
    }

    private function dateDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__formatter->format('{value}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
    }

    private function timeDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__formatter->format('{value|time}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
    }

    private function datetimeDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__formatter->format('{value|datetime}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
    }

    private function currencyDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__formatter->format('{value|currency}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
    }

    private function numberDirective(): callable
    {
        return fn(string $expression): string =>
            "<?php echo \$__formatter->format('{value|number}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
    }
}
