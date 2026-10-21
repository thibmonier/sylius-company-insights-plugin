<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Controller\Shop;

use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\DashboardProvider;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Security\InsightsPermissions;

/**
 * "Purchasing dashboard" of the customer account (behind the Sylius `/account` access control).
 * The company is the one of the signed-in member, never a parameter of the request; the only
 * parameter is the period, from an allow-list. No membership: 404; not an administrator: 403
 * with an explanation.
 */
final class InsightsController extends AbstractController
{
    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly DashboardProvider $dashboardProvider,
        private readonly CurrencyContextInterface $currencyContext,
    ) {
    }

    public function showAction(Request $request): Response
    {
        $member = $this->currentMemberResolver->getMember();
        $company = $member?->getCompany();
        if (!$member instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface || !$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || null === $company->getId()) {
            throw $this->createNotFoundException('No company.');
        }

        if (!$this->isGranted(InsightsPermissions::COMPANY_VIEW_INSIGHTS, $company)) {
            return $this->render('@TmonierSyliusCompanyInsightsPlugin/shop/account/company/insights/forbidden.html.twig', [
                'company' => $company,
                'member' => $member,
            ], new Response(status: Response::HTTP_FORBIDDEN));
        }

        $key = PeriodKey::fromRequest($request->query->all()['period'] ?? null);

        return $this->render('@TmonierSyliusCompanyInsightsPlugin/shop/account/company/insights/show.html.twig', [
            'company' => $company,
            'member' => $member,
            'dashboard' => $this->dashboardProvider->provide($company->getId(), $key, $this->currencyContext->getCurrencyCode()),
            'period_keys' => PeriodKey::cases(),
        ]);
    }
}
