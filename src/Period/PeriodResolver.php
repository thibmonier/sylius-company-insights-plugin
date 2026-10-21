<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Period;

use Psr\Clock\ClockInterface;

/**
 * Turns a period key into dates and into the previous period of the same duration. Today is part
 * of the period (the exclusive upper bound is tomorrow 00:00). Rolling periods (30 days, 12 months)
 * are followed by their predecessor; the quarter and the year so far are compared with the same
 * elapsed days of the previous quarter and year.
 */
final readonly class PeriodResolver
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function resolve(PeriodKey $key): PeriodComparison
    {
        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
        $to = $today->modify('+1 day');

        return match ($key) {
            PeriodKey::Last30Days => $this->rolling($key, $to, '-30 days'),
            PeriodKey::Last12Months => $this->rolling($key, $to, '-12 months'),
            PeriodKey::CurrentQuarter => $this->sinceStart($key, $this->quarterStart($today), $to, '-3 months'),
            PeriodKey::CurrentYear => $this->sinceStart($key, $today->setDate((int) $today->format('Y'), 1, 1), $to, '-1 year'),
        };
    }

    private function rolling(PeriodKey $key, \DateTimeImmutable $to, string $length): PeriodComparison
    {
        $from = $to->modify($length);

        return new PeriodComparison($key, new Period($from, $to), new Period($from->modify($length), $from));
    }

    private function sinceStart(PeriodKey $key, \DateTimeImmutable $from, \DateTimeImmutable $to, string $shift): PeriodComparison
    {
        $current = new Period($from, $to);
        $previousFrom = $from->modify($shift);

        return new PeriodComparison($key, $current, new Period($previousFrom, $previousFrom->modify(sprintf('+%d days', $current->days()))));
    }

    private function quarterStart(\DateTimeImmutable $day): \DateTimeImmutable
    {
        $month = intdiv((int) $day->format('n') - 1, 3) * 3 + 1;

        return $day->setDate((int) $day->format('Y'), $month, 1);
    }
}
