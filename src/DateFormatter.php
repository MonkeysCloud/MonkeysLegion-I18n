<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Locale-aware date and time formatting.
 *
 * Supports: absolute formatting, relative time ("2 hours ago"),
 * and timezone-aware display.
 */
final class DateFormatter
{
    // ── Relative time thresholds ──────────────────────────────────

    private const array RELATIVE_UNITS = [
        ['seconds', 60,       1],
        ['minutes', 3600,     60],
        ['hours',   86400,    3600],
        ['days',    2592000,  86400],
        ['months',  31536000, 2592000],
        ['years',   PHP_INT_MAX, 31536000],
    ];

    // ── Relative time labels per locale ───────────────────────────

    private const array RELATIVE_LABELS = [
        'en' => [
            'just_now'  => 'just now',
            'seconds'   => ['{1} 1 second ago', '{n} :count seconds ago'],
            'minutes'   => ['{1} 1 minute ago', '{n} :count minutes ago'],
            'hours'     => ['{1} 1 hour ago', '{n} :count hours ago'],
            'days'      => ['{1} 1 day ago', '{n} :count days ago'],
            'months'    => ['{1} 1 month ago', '{n} :count months ago'],
            'years'     => ['{1} 1 year ago', '{n} :count years ago'],
            'future'    => 'in :count :unit',
        ],
        'es' => [
            'just_now'  => 'justo ahora',
            'seconds'   => ['{1} hace 1 segundo', '{n} hace :count segundos'],
            'minutes'   => ['{1} hace 1 minuto', '{n} hace :count minutos'],
            'hours'     => ['{1} hace 1 hora', '{n} hace :count horas'],
            'days'      => ['{1} hace 1 día', '{n} hace :count días'],
            'months'    => ['{1} hace 1 mes', '{n} hace :count meses'],
            'years'     => ['{1} hace 1 año', '{n} hace :count años'],
            'future'    => 'en :count :unit',
        ],
    ];

    // ── Public API ────────────────────────────────────────────────

    /**
     * Format a date with a named format.
     */
    public function format(
        DateTimeInterface|int|string $value,
        string $format = 'medium',
        string $locale = 'en',
        ?string $timezone = null,
    ): string {
        $dt = $this->toDateTime($value, $timezone);

        return match ($format) {
            'short'    => $dt->format('n/j/y'),
            'medium'   => $dt->format('M j, Y'),
            'long'     => $dt->format('F j, Y'),
            'full'     => $dt->format('l, F j, Y'),
            'iso'      => $dt->format('Y-m-d'),
            'time'     => $dt->format('g:i A'),
            'datetime' => $dt->format('M j, Y g:i A'),
            default    => $dt->format($format),
        };
    }

    /**
     * Format as relative time ("2 hours ago", "in 3 days").
     */
    public function relative(
        DateTimeInterface|int|string $value,
        string $locale = 'en',
        ?DateTimeInterface $now = null,
    ): string {
        $dt = $this->toDateTime($value);
        $now = $now ?? new DateTimeImmutable('now', $dt->getTimezone());
        $diff = $now->getTimestamp() - $dt->getTimestamp();

        $labels = self::RELATIVE_LABELS[$locale] ?? self::RELATIVE_LABELS['en'];

        // Future
        if ($diff < 0) {
            $diff = abs($diff);
            [$unit, $count] = $this->getRelativeUnit($diff);
            $unitLabel = $unit;

            return str_replace(
                [':count', ':unit'],
                [(string) $count, $unitLabel],
                $labels['future'],
            );
        }

        // Just now (< 10 seconds)
        if ($diff < 10) {
            return $labels['just_now'];
        }

        [$unit, $count] = $this->getRelativeUnit($diff);

        $unitLabels = $labels[$unit] ?? $labels['seconds'];

        $template = $count === 1 ? $unitLabels[0] : $unitLabels[1];

        return str_replace(':count', (string) $count, $template);
    }

    /**
     * Format as ISO 8601.
     */
    public function iso(DateTimeInterface|int|string $value): string
    {
        return $this->toDateTime($value)->format('c');
    }

    /**
     * Format day of week.
     */
    public function dayOfWeek(DateTimeInterface|int|string $value, bool $short = false): string
    {
        $dt = $this->toDateTime($value);

        return $dt->format($short ? 'D' : 'l');
    }

    /**
     * Format month name.
     */
    public function monthName(DateTimeInterface|int|string $value, bool $short = false): string
    {
        $dt = $this->toDateTime($value);

        return $dt->format($short ? 'M' : 'F');
    }

    /**
     * Get human-readable time difference.
     */
    public function diffForHumans(
        DateTimeInterface|int|string $from,
        DateTimeInterface|int|string $to,
        string $locale = 'en',
    ): string {
        $dtFrom = $this->toDateTime($from);
        $dtTo = $this->toDateTime($to);
        $diff = abs($dtTo->getTimestamp() - $dtFrom->getTimestamp());

        [$unit, $count] = $this->getRelativeUnit($diff);
        $labels = self::RELATIVE_LABELS[$locale] ?? self::RELATIVE_LABELS['en'];
        $unitLabels = $labels[$unit] ?? $labels['seconds'];

        $template = $count === 1 ? $unitLabels[0] : $unitLabels[1];

        return str_replace(':count', (string) $count, $template);
    }

    // ── Private methods ───────────────────────────────────────────

    /**
     * Convert value to DateTimeImmutable.
     */
    private function toDateTime(DateTimeInterface|int|string $value, ?string $timezone = null): DateTimeImmutable
    {
        $tz = $timezone !== null ? new \DateTimeZone($timezone) : null;

        if ($value instanceof DateTimeImmutable) {
            return $tz !== null ? $value->setTimezone($tz) : $value;
        }

        if ($value instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromInterface($value);

            return $tz !== null ? $dt->setTimezone($tz) : $dt;
        }

        if (is_int($value)) {
            $dt = (new DateTimeImmutable())->setTimestamp($value);

            return $tz !== null ? $dt->setTimezone($tz) : $dt;
        }

        $dt = new DateTimeImmutable($value, $tz);

        return $dt;
    }

    /**
     * Determine the relative time unit and count.
     *
     * @return array{0: string, 1: int}
     */
    private function getRelativeUnit(int $diff): array
    {
        foreach (self::RELATIVE_UNITS as [$unit, $threshold, $divisor]) {
            if ($diff < $threshold) {
                return [$unit, max(1, (int) floor($diff / $divisor))];
            }
        }

        return ['years', max(1, (int) floor($diff / 31536000))];
    }
}
