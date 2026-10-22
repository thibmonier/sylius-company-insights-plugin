<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Controller\Shop;

use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\BreakdownProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Export\CsvExporter;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportLimits;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportRefused;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportRequest;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\FilterChoicesInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\Selection;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\SelectionFactory;
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
        private readonly SelectionFactory $selectionFactory,
        private readonly FilterChoicesInterface $filterChoices,
        private readonly BreakdownProvider $breakdownProvider,
        private readonly CsvExporter $csvExporter,
    ) {
    }

    public function showAction(Request $request): Response
    {
        [$member, $company] = $this->memberAndCompany();
        if (!$this->isGranted(InsightsPermissions::COMPANY_VIEW_INSIGHTS, $company)) {
            return $this->forbidden($company, $member);
        }

        return $this->dashboard($request, $member, $company);
    }

    public function exportAction(Request $request): Response
    {
        [$member, $company] = $this->memberAndCompany();
        if (!$this->isGranted(InsightsPermissions::COMPANY_VIEW_INSIGHTS, $company)) {
            return $this->forbidden($company, $member);
        }

        $selection = $this->selectionFactory->create($company->getId() ?? 0, $request->query->all());
        $exportRequest = new ExportRequest($company->getId() ?? 0, $member->getId(), $selection->periods->current, $selection->filter, $request->getLocale());

        try {
            $this->csvExporter->assertAllowed($exportRequest);
        } catch (ExportRefused $refused) {
            return $this->dashboard($request, $member, $company, $refused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $response = new StreamedResponse(function () use ($exportRequest): void {
            $stream = fopen('php://output', 'wb');
            if (false === $stream) {
                return;
            }
            $this->csvExporter->write($exportRequest, $stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            sprintf('purchases-%s-%s.csv', $selection->periods->current->from->format('Y-m-d'), $selection->periods->current->lastDay()->format('Y-m-d')),
        ));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function rankingAction(Request $request, string $ranking): Response
    {
        $kind = RankingKind::tryFrom($ranking) ?? throw $this->createNotFoundException('No such ranking.');
        [$member, $company] = $this->memberAndCompany();
        if (!$this->isGranted(InsightsPermissions::COMPANY_VIEW_INSIGHTS, $company)) {
            return $this->forbidden($company, $member);
        }

        $query = $request->query->all();
        $selection = $this->selection($company, $query);
        $dashboard = $this->dashboardProvider->provideFor($company->getId() ?? 0, $selection->periods, $this->currencyContext->getCurrencyCode(), $selection->filter);
        $currencies = $this->currencyCodes($dashboard);
        $sort = ProductSort::fromRequest($query['sort'] ?? null);
        $scope = new RankingScope($company->getId() ?? 0, $dashboard->periods->current, $this->chooseCurrency($currencies, $query['currency'] ?? null), $request->getLocale(), $selection->filter);

        return $this->render(self::TEMPLATES . 'ranking.html.twig', [
            'company' => $company,
            'member' => $member,
            'dashboard' => $dashboard,
            'selection' => $selection,
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

    private function dashboard(Request $request, CompanyMemberInterface $member, CompanyInterface $company, ?string $exportError = null, int $status = Response::HTTP_OK): Response
    {
        $query = $request->query->all();
        $companyId = $company->getId() ?? 0;
        $currency = $this->currencyContext->getCurrencyCode();
        $selection = $this->selection($company, $query);
        $dashboard = $this->dashboardProvider->provideFor($companyId, $selection->periods, $currency, $selection->filter);
        $sort = ProductSort::fromRequest($query['sort'] ?? null);
        $currencies = $this->currencyCodes($dashboard);

        return $this->render(self::TEMPLATES . 'show.html.twig', [
            'company' => $company,
            'member' => $member,
            'dashboard' => $dashboard,
            'selection' => $selection,
            'members' => $this->filterChoices->members($companyId),
            'sites' => $this->filterChoices->sites($companyId),
            'monthly' => $this->monthlyPurchasesProvider->provide($companyId, $currency, $selection->filter),
            'rankings' => $this->rankingsProvider->top($companyId, $dashboard->periods->current, $currencies, $request->getLocale(), $sort, $selection->filter),
            'breakdowns' => $this->breakdownProvider->forCurrencies(new RankingScope($companyId, $dashboard->periods->current, $currency, $request->getLocale(), $selection->filter), $currencies),
            'metric' => Metric::fromRequest($query['metric'] ?? null),
            'metrics' => Metric::cases(),
            'sort' => $sort,
            'sorts' => ProductSort::cases(),
            'period_keys' => PeriodKey::presets(),
            'export_allowed' => ExportLimits::allowsPeriod($dashboard->periods->current),
            'export_error' => $exportError ?? '',
        ], new Response(status: $status));
    }

    private function selection(CompanyInterface $company, array $query): Selection
    {
        return $this->selectionFactory->create($company->getId() ?? 0, $query);
    }

    /** @return array{CompanyMemberInterface, CompanyInterface} */
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
