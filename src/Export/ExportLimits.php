<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

final class ExportLimits
{
    public const MAX_MONTHS = 24;

    public const MAX_LINES = 50_000;

    private function __construct()
    {
    }

    public static function allowsPeriod(Period $period): bool
    {
        return $period->from->modify(sprintf('+%d months', self::MAX_MONTHS)) >= $period->to;
    }
}
