<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** Shares of a total, in whole percent. */
final class Shares
{
    /** The nearest percent. */
    public static function percent(int $amount, int $total): int
    {
        return $total <= 0 ? 0 : (int) round($amount * 100 / $total);
    }

    /**
     * The shares of the parts of a whole, in whole percent, adding up to 100 when the parts add up
     * to the total (largest remainder: the missing percents go to the largest fractions, earlier
     * rows first).
     *
     * @param list<int> $amounts
     *
     * @return list<int>
     */
    public static function distribute(array $amounts, int $total): array
    {
        if ($total <= 0) {
            return array_fill(0, count($amounts), 0);
        }

        $shares = [];
        $remainders = [];
        foreach ($amounts as $index => $amount) {
            $shares[$index] = intdiv($amount * 100, $total);
            $remainders[$index] = ($amount * 100) % $total;
        }
        $missing = 100 - array_sum($shares);
        $indexes = array_keys($remainders);
        usort($indexes, static fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach (array_slice($indexes, 0, max(0, $missing)) as $index) {
            ++$shares[$index];
        }

        return array_values($shares);
    }
}
