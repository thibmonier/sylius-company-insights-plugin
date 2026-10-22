<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

use Tmonier\SyliusCompanyInsightsPlugin\Ranking\Shares;

final readonly class Breakdown
{
    public const SITES = 'sites';

    /** @param list<BreakdownRow> $rows biggest spend first, the row without label last */
    public function __construct(
        public string $code,
        public string $title,
        public array $rows,
        public int $totalSpend,
    ) {
    }

    /** @return list<int> percentages adding up to 100, in the order of the rows */
    public function shares(): array
    {
        return Shares::distribute(array_map(static fn (BreakdownRow $row): int => $row->spend, $this->rows), $this->totalSpend);
    }

    public function isEmpty(): bool
    {
        return [] === $this->rows;
    }

    /**
     * Rows of the same label added up, the biggest spend first, then the label; the row without label last.
     *
     * @param iterable<BreakdownRow> $rows
     */
    public static function of(string $code, string $title, iterable $rows): self
    {
        $merged = [];
        $unlabeled = null;
        foreach ($rows as $row) {
            if (null === $row->label) {
                $unlabeled = new BreakdownRow(null, ($unlabeled->ordersCount ?? 0) + $row->ordersCount, ($unlabeled->spend ?? 0) + $row->spend);

                continue;
            }
            $known = $merged[$row->label] ?? null;
            $merged[$row->label] = new BreakdownRow($row->label, ($known->ordersCount ?? 0) + $row->ordersCount, ($known->spend ?? 0) + $row->spend);
        }
        $labeled = array_values($merged);
        usort($labeled, static fn (BreakdownRow $a, BreakdownRow $b): int => [$b->spend, $a->label] <=> [$a->spend, $b->label]);
        $all = $unlabeled instanceof \Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownRow ? [...$labeled, $unlabeled] : $labeled;

        return new self($code, $title, $all, array_sum(array_map(static fn (BreakdownRow $row): int => $row->spend, $all)));
    }
}
