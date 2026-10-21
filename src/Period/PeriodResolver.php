<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Period;

use Psr\Clock\ClockInterface;

/**
 * Turns a period key into dates and into the previous period of the same duration. Today is part
 * of the period (the exclusive upper bound is tomorrow 00:00). The 30 days are followed by their
 * predecessor; the 12 months are the current calendar month and the 11 before it, followed by the
 * 12 calendar months before; the quarter and the year so far are compared with the same elapsed
 * days of the previous quarter and year. Days and months are those of the timezone (the one of the
 * clock when none is given).
 */
final readonly class PeriodResolver
{
    public function __construct(private ClockInterface $clock, private ?string $timezone = null)
    {
    }

    public function resolve(PeriodKey $key): PeriodComparison
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $today = (null === $this->timezone ? $now : $now->setTimezone(new \DateTimeZone($this->timezone)))->setTime(0, 0);
        $to = $today->modify('+1 day');

        return match ($key) {
            PeriodKey::Last30Days => $this->rolling($key, $to, '-30 days'),
            PeriodKey::Last12Months => $this->calendarMonths($key, $today, $to),
            PeriodKey::CurrentQuarter => $this->sinceStart($key, $this->quarterStart($today), $to, '-3 months'),
            PeriodKey::CurrentYear => $this->sinceStart($key, $today->setDate((int) $today->format('Y'), 1, 1), $to, '-1 year'),
        };
    }

    private function rolling(PeriodKey $key, \DateTimeImmutable $to, string $length): PeriodComparison
    {
        $from = $to->modify($length);

        return new PeriodComparison($key, new Period($from, $to), new Period($from->modify($length), $from));
    }

    private function calendarMonths(PeriodKey $key, \DateTimeImmutable $today, \DateTimeImmutable $to): PeriodComparison
    {
        $from = $today->modify('first day of this month')->modify('-11 months');

        return new PeriodComparison($key, new Period($from, $to), new Period($from->modify('-12 months'), $from));
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
