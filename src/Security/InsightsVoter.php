<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;

/**
 * Only an enabled administrator of the company (and of that company only) sees its purchases.
 * Buyers, approvers, disabled members, members of another company, staff users and visitors are
 * denied.
 *
 * @extends Voter<string, CompanyInterface>
 */
final class InsightsVoter extends Voter
{
    public function __construct(private readonly CurrentMemberResolverInterface $currentMemberResolver)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return InsightsPermissions::COMPANY_VIEW_INSIGHTS === $attribute && $subject instanceof CompanyInterface;
    }

    /** @param CompanyInterface $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, mixed $vote = null): bool
    {
        $member = $this->currentMemberResolver->getMember($token);
        $company = $member?->getCompany();
        if (!$member instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface || !$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || null === $subject->getId()) {
            return false;
        }

        return $member->isActive() && $member->isAdmin() && $company->getId() === $subject->getId();
    }
}
