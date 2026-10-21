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

    public static function default(): self
    {
        return self::Last12Months;
    }

    /** Anything that is not exactly a known key (missing, unknown, array...) is the default period. */
    public static function fromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::default()) : self::default();
    }

    /** Translation key of the label of the period. */
    public function label(): string
    {
        return 'tmonier_company_insights.period.' . $this->value;
    }
}
