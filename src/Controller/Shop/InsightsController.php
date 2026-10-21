<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Controller\Shop;

use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\CurrencyIndicators;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\Dashboard;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\DashboardProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\Metric;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyPurchasesProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\BuyerRow;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\CategoryRow;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\PageRequest;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\ProductRow;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\ProductSort;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\Ranking;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingKind;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingsProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Security\InsightsPermissions;

/**
 * "Purchasing dashboard" of the customer account (behind the Sylius `/account` access control) and
 * its "see all" pages. The company is the one of the signed-in member, never a parameter of the
 * request; the only parameters are the period, the metric of the monthly chart, the sort of the
 * products, the page, the currency and the ranking, all checked against an allow-list. No
 * membership: 404; not an administrator: 403 with an explanation.
 */
final class InsightsController extends AbstractController
{
    private const TEMPLATES = '@TmonierSyliusCompanyInsightsPlugin/shop/account/company/insights/';

    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly DashboardProvider $dashboardProvider,
        private readonly MonthlyPurchasesProvider $monthlyPurchasesProvider,
        private readonly RankingsProvider $rankingsProvider,
        private readonly CurrencyContextInterface $currencyContext,
    ) {
    }

    public function showAction(Request $request): Response
    {
        [$member, $company] = $this->memberAndCompany();
        if (!$this->isGranted(InsightsPermissions::COMPANY_VIEW_INSIGHTS, $company)) {
            return $this->forbidden($company, $member);
        }

        $query = $request->query->all();
        $currency = $this->currencyContext->getCurrencyCode();
        $dashboard = $this->dashboardProvider->provide($company->getId() ?? 0, PeriodKey::fromRequest($query['period'] ?? null), $currency);
        $sort = ProductSort::fromRequest($query['sort'] ?? null);

        return $this->render(self::TEMPLATES . 'show.html.twig', [
            'company' => $company,
            'member' => $member,
            'dashboard' => $dashboard,
            'monthly' => $this->monthlyPurchasesProvider->provide($company->getId() ?? 0, $currency),
            'rankings' => $this->rankingsProvider->top($company->getId() ?? 0, $dashboard->periods->current, $this->currencyCodes($dashboard), $request->getLocale(), $sort),
            'metric' => Metric::fromRequest($query['metric'] ?? null),
            'metrics' => Metric::cases(),
            'sort' => $sort,
            'sorts' => ProductSort::cases(),
            'period_keys' => PeriodKey::cases(),
        ]);
    }

    public function rankingAction(Request $request, string $ranking): Response
    {
        $kind = RankingKind::tryFrom($ranking) ?? throw $this->createNotFoundException('No such ranking.');
        [$member, $company] = $this->memberAndCompany();
        if (!$this->isGranted(InsightsPermissions::COMPANY_VIEW_INSIGHTS, $company)) {
            return $this->forbidden($company, $member);
        }

        $query = $request->query->all();
        $dashboard = $this->dashboardProvider->provide($company->getId() ?? 0, PeriodKey::fromRequest($query['period'] ?? null), $this->currencyContext->getCurrencyCode());
        $currencies = $this->currencyCodes($dashboard);
        $sort = ProductSort::fromRequest($query['sort'] ?? null);
        $scope = new RankingScope($company->getId() ?? 0, $dashboard->periods->current, $this->chooseCurrency($currencies, $query['currency'] ?? null), $request->getLocale());

        return $this->render(self::TEMPLATES . 'ranking.html.twig', [
            'company' => $company,
            'member' => $member,
            'dashboard' => $dashboard,
            'kind' => $kind,
            'ranking' => $this->ranking($kind, $scope, $sort, PageRequest::fromRequest($query['page'] ?? null)),
            'currencies' => $currencies,
            'sort' => $sort,
            'sorts' => ProductSort::cases(),
        ]);
    }

    /** @return Ranking<BuyerRow>|Ranking<ProductRow>|Ranking<CategoryRow> */
    private function ranking(RankingKind $kind, RankingScope $scope, ProductSort $sort, PageRequest $page): Ranking
    {
        return match ($kind) {
            RankingKind::Buyers => $this->rankingsProvider->buyers($scope, $page),
            RankingKind::Products => $this->rankingsProvider->products($scope, $sort, $page),
            RankingKind::Categories => $this->rankingsProvider->categories($scope, $page),
        };
    }

    /** @return array{0: CompanyMemberInterface, 1: CompanyInterface} */
    private function memberAndCompany(): array
    {
        $member = $this->currentMemberResolver->getMember();
        $company = $member?->getCompany();
        if (!$member instanceof CompanyMemberInterface || !$company instanceof CompanyInterface || null === $company->getId()) {
            throw $this->createNotFoundException('No company.');
        }

        return [$member, $company];
    }

    private function forbidden(CompanyInterface $company, CompanyMemberInterface $member): Response
    {
        return $this->render(self::TEMPLATES . 'forbidden.html.twig', [
            'company' => $company,
            'member' => $member,
        ], new Response(status: Response::HTTP_FORBIDDEN));
    }

    /** @return non-empty-list<string> */
    private function currencyCodes(Dashboard $dashboard): array
    {
        return array_map(static fn (CurrencyIndicators $currency): string => $currency->currencyCode, $dashboard->currencies);
    }

    /**
     * A currency the company has spent in, else the one of the shop when it is, else the first.
     *
     * @param non-empty-list<string> $currencies
     */
    private function chooseCurrency(array $currencies, mixed $requested): string
    {
        if (is_string($requested) && in_array($requested, $currencies, true)) {
            return $requested;
        }

        $current = $this->currencyContext->getCurrencyCode();

        return in_array($current, $currencies, true) ? $current : $currencies[0];
    }
}
