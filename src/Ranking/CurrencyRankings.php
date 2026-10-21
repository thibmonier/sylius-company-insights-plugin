<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** The three top lists of the dashboard in one currency. */
final readonly class CurrencyRankings
{
    /**
     * @param Ranking<BuyerRow>    $buyers
     * @param Ranking<ProductRow>  $products
     * @param Ranking<CategoryRow> $categories
     */
    public function __construct(
        public string $currencyCode,
        public Ranking $buyers,
        public Ranking $products,
        public Ranking $categories,
    ) {
    }

    public function isEmpty(): bool
    {
        return 0 === $this->buyers->totalRows && 0 === $this->products->totalRows;
    }
}
