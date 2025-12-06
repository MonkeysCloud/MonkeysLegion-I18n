<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Template;

use MonkeysLegion\I18n\Translator;

/**
 * Template directive provider for MonkeysLegion-Template
 * Provides: @lang, @choice, @locale, @date, @currency
 */
final class I18nDirectives
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Get all directives
     * 
     * @return array<string, callable>
     */
    public function getDirectives(): array
    {
        return [
            'lang' => $this->langDirective(),
            'choice' => $this->choiceDirective(),
            'locale' => $this->localeDirective(),
            'date' => $this->dateDirective(),
            'time' => $this->timeDirective(),
            'datetime' => $this->datetimeDirective(),
            'currency' => $this->currencyDirective(),
            'number' => $this->numberDirective(),
        ];
    }

    /**
     * @lang directive - Translate a key
     * Usage: @lang('welcome.message')
     * Usage: @lang('welcome.user', ['name' => $user->name])
     */
    private function langDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__translator->trans({$expression}); ?>";
        };
    }

    /**
     * @choice directive - Translate with pluralization
     * Usage: @choice('messages.count', $count)
     * Usage: @choice('messages.count', $count, ['email' => $email])
     */
    private function choiceDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__translator->choice({$expression}); ?>";
        };
    }

    /**
     * @locale directive - Display current locale or locale switcher
     * Usage: @locale (displays current locale)
     * Usage: @locale('es') (sets locale)
     */
    private function localeDirective(): callable
    {
        return function(string $expression): string {
            if (trim($expression) === '') {
                return "<?php echo \$__translator->getLocale(); ?>";
            }
            
            return "<?php \$__translator->setLocale({$expression}); ?>";
        };
    }

    /**
     * @date directive - Format date
     * Usage: @date($timestamp)
     * Usage: @date($timestamp, 'long')
     */
    private function dateDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__formatter->format('{value}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
        };
    }

    /**
     * @time directive - Format time
     * Usage: @time($timestamp)
     * Usage: @time($timestamp, 'short')
     */
    private function timeDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__formatter->format('{value|time}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
        };
    }

    /**
     * @datetime directive - Format datetime
     * Usage: @datetime($timestamp)
     * Usage: @datetime($timestamp, 'medium')
     */
    private function datetimeDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__formatter->format('{value|datetime}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
        };
    }

    /**
     * @currency directive - Format currency
     * Usage: @currency($amount)
     * Usage: @currency($amount, 'EUR')
     */
    private function currencyDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__formatter->format('{value|currency}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
        };
    }

    /**
     * @number directive - Format number
     * Usage: @number($value)
     * Usage: @number($value, 2)
     */
    private function numberDirective(): callable
    {
        return function(string $expression): string {
            return "<?php echo \$__formatter->format('{value|number}', ['value' => {$expression}], \$__translator->getLocale()); ?>";
        };
    }
}

/**
 * Template extension for registering I18n directives
 */
final class I18nTemplateExtension
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Register directives with the template engine
     * 
     * @param object $engine Template engine instance
     */
    public function register($engine): void
    {
        $directives = new I18nDirectives($this->translator);
        
        foreach ($directives->getDirectives() as $name => $handler) {
            $engine->directive($name, $handler);
        }
        
        // Make translator available in templates
        $engine->share('__translator', $this->translator);
    }
}
