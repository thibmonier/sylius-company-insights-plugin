<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Period;

/** The periods the dashboard offers. The values are the `period` query parameter (allow-list). */
enum PeriodKey: string
{
    case Last30Days = 'last_30_days';
    case CurrentQuarter = 'current_quarter';
    case Last12Months = 'last_12_months';
    case CurrentYear = 'current_year';
    case Custom = 'custom';

    public static function default(): self
    {
        return self::Last12Months;
    }

    /** Anything that is not exactly a known key (missing, unknown, array...) is the default period. */
    public static function fromRequest(mixed $value): self
    {
        $key = is_string($value) ? self::tryFrom($value) : null;

        return null === $key || self::Custom === $key ? self::default() : $key;
    }

    /** The periods the member picks with one click; the custom one comes from two dates. */
    public static function presets(): array
    {
        return [self::Last30Days, self::CurrentQuarter, self::Last12Months, self::CurrentYear];
    }

    /** Translation key of the label of the period. */
    public function label(): string
    {
        return 'tmonier_company_insights.period.' . $this->value;
    }
}
