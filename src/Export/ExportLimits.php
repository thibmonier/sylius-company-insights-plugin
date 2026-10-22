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
        return self::lastAllowedEnd($period->from) >= $period->to;
    }

    /** From plus 24 calendar months; the 29 February ends on 28 February instead of overflowing to 1 March. */
    private static function lastAllowedEnd(\DateTimeImmutable $from): \DateTimeImmutable
    {
        $firstOfMonth = $from->modify('first day of this month')->modify(sprintf('+%d months', self::MAX_MONTHS));

        return $firstOfMonth->setDate(
            (int) $firstOfMonth->format('Y'),
            (int) $firstOfMonth->format('n'),
            min((int) $from->format('j'), (int) $firstOfMonth->format('t')),
        );
    }
}
