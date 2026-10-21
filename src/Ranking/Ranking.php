<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/**
 * A page of a ranking in one currency, with what is needed to show it: the whole spend, the number
 * of rows of the whole ranking and, when the ranking has shares, the share of each row (whole percent).
 *
 * @template TRow of BuyerRow|ProductRow|CategoryRow
 */
final readonly class Ranking
{
    /**
     * @param list<TRow> $rows
     * @param list<int>  $shares Same order as the rows, empty for a ranking without shares
     */
    public function __construct(
        public string $currencyCode,
        public array $rows,
        public array $shares,
        public int $totalSpend,
        public int $totalRows,
        public PageRequest $page,
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->totalRows / $this->page->perPage));
    }

    public function hasMore(): bool
    {
        return $this->totalRows > $this->page->offset() + count($this->rows);
    }

    /** Spend of the rows that are not on this page, and their share: the "others" of a short list. */
    public function othersSpend(): int
    {
        return $this->totalSpend - array_sum(array_map(static fn (BuyerRow|ProductRow|CategoryRow $row): int => $row->spend, $this->rows));
    }

    public function othersShare(): int
    {
        return [] === $this->shares ? 0 : 100 - array_sum($this->shares);
    }
}
