<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;

/**
 * Known bound: the spend of every counted order of the selection (id => tax-exclusive amount, integers only) is
 * read in one query and kept in memory, because the analytical axes come from the application, which labels
 * orders by id and cannot be grouped in SQL here. An order costs a few dozen bytes, so a selection of some
 * hundreds of thousands of orders stays within a few tens of MB; the values of the axes are requested by
 * chunks of 1 000 ids. Nothing is truncated. The sites breakdown has no such bound (grouped in SQL).
 */
final readonly class BreakdownProvider
{
    private const CHUNK = 1000;

    private LoggerInterface $logger;

    /** @param iterable<OrderDimensionProviderInterface> $dimensionProviders */
    public function __construct(
        private SiteBreakdownInterface $sites,
        private OrderSpendInterface $orderSpend,
        private iterable $dimensionProviders = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /** @param list<string> $currencyCodes */
    public function forCurrencies(RankingScope $scope, array $currencyCodes): array
    {
        return array_map(function (string $code) use ($scope): CurrencyBreakdowns {
            $inCurrency = new RankingScope($scope->companyId, $scope->period, $code, $scope->localeCode, $scope->filter);

            return new CurrencyBreakdowns($code, $this->sites($inCurrency), $this->dimensions($inCurrency));
        }, $currencyCodes);
    }

    public function sites(RankingScope $scope): Breakdown
    {
        return Breakdown::of(Breakdown::SITES, 'tmonier_company_insights.breakdown.sites.title', $this->sites->rows($scope));
    }

    /** @return list<Breakdown> */
    public function dimensions(RankingScope $scope): array
    {
        $providers = [...$this->dimensionProviders];
        if ([] === $providers) {
            return [];
        }

        $spend = $this->orderSpend->perOrder($scope);
        $breakdowns = [];
        foreach ($providers as $provider) {
            try {
                $breakdowns[] = $this->breakdown($provider, $spend);
            } catch (\Throwable $exception) {
                // The block of an axis that fails is left out: the rest of the dashboard stays.
                $this->logger->error('Company insights: a dimension provider failed.', ['provider' => $provider->code(), 'exception_class' => $exception::class]);
            }
        }

        return $breakdowns;
    }

    /** @param array<int, int> $spend */
    private function breakdown(OrderDimensionProviderInterface $provider, array $spend): Breakdown
    {
        $values = [];
        foreach (array_chunk(array_keys($spend), self::CHUNK) as $ids) {
            $values += $provider->values($ids);
        }

        $rows = [];
        foreach ($spend as $orderId => $amount) {
            $value = $values[$orderId] ?? null;
            $label = is_string($value) && '' !== trim($value) ? trim($value) : null;
            $rows[] = new BreakdownRow($label, 1, $amount);
        }

        return Breakdown::of($provider->code(), $provider->label(), $rows);
    }
}
