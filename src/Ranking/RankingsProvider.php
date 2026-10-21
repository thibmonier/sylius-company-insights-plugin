<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

/**
 * The rankings of the purchasing dashboard. Every list is about ONE company and ONE currency; a
 * page beyond the last one is the last one. The shares are in whole percent: the categories
 * (which are all read) add up to 100, the buyers are rounded to the nearest percent.
 */
final readonly class RankingsProvider
{
    public function __construct(
        private BuyerRankingInterface $buyers,
        private ProductRankingInterface $products,
        private CategoryRankingInterface $categories,
    ) {
    }

    /**
     * The top 10 of each ranking, one set per currency.
     *
     * @param list<string> $currencyCodes
     *
     * @return list<CurrencyRankings>
     */
    public function top(int $companyId, Period $period, array $currencyCodes, string $localeCode, ProductSort $sort): array
    {
        $top = PageRequest::top();

        return array_map(function (string $code) use ($companyId, $period, $localeCode, $sort, $top): CurrencyRankings {
            $scope = new RankingScope($companyId, $period, $code, $localeCode);

            return new CurrencyRankings($code, $this->buyers($scope, $top), $this->products($scope, $sort, $top), $this->categories($scope, $top));
        }, $currencyCodes);
    }

    /** @return Ranking<BuyerRow> */
    public function buyers(RankingScope $scope, PageRequest $page): Ranking
    {
        $totals = $this->buyers->totals($scope);
        $page = $this->clamp($page, $totals->rows);
        $rows = $this->buyers->rows($scope, $page);
        $shares = array_map(static fn (BuyerRow $row): int => Shares::percent($row->spend, $totals->spend), $rows);

        return new Ranking($scope->currencyCode, $rows, $shares, $totals->spend, $totals->rows, $page);
    }

    /** @return Ranking<ProductRow> */
    public function products(RankingScope $scope, ProductSort $sort, PageRequest $page): Ranking
    {
        $totals = $this->products->totals($scope);
        $page = $this->clamp($page, $totals->rows);

        return new Ranking($scope->currencyCode, $this->products->rows($scope, $sort, $page), [], $totals->spend, $totals->rows, $page);
    }

    /** @return Ranking<CategoryRow> */
    public function categories(RankingScope $scope, PageRequest $page): Ranking
    {
        $all = $this->categories->rows($scope);
        $spend = array_sum(array_map(static fn (CategoryRow $row): int => $row->spend, $all));
        $page = $this->clamp($page, count($all));
        $shares = Shares::distribute(array_map(static fn (CategoryRow $row): int => $row->spend, $all), $spend);

        return new Ranking(
            $scope->currencyCode,
            array_slice($all, $page->offset(), $page->perPage),
            array_slice($shares, $page->offset(), $page->perPage),
            $spend,
            count($all),
            $page,
        );
    }

    private function clamp(PageRequest $page, int $rows): PageRequest
    {
        $last = max(1, (int) ceil($rows / $page->perPage));

        return $page->page > $last ? $page->onPage($last) : $page;
    }
}
