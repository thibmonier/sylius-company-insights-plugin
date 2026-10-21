<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Security\InsightsPermissions;
use Tmonier\SyliusCompanyInsightsPlugin\Security\InsightsVoter;

final class InsightsVoterTest extends TestCase
{
    public function testAnActiveAdministratorOfTheCompanyIsGranted(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->member(1, admin: true), $this->company(1)));
    }

    public function testBuyersAndApproversAreDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->member(1, admin: false), $this->company(1)));
    }

    public function testAnInactiveAdministratorIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->member(1, admin: true, active: false), $this->company(1)));
    }

    public function testAnAdministratorOfAnotherCompanyIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->member(1, admin: true), $this->company(2)));
    }

    public function testSomeoneWithoutMembershipIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(null, $this->company(1)));
    }

    public function testACompanyNotSavedYetIsNeverGranted(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->member(1, admin: true), $this->company(null)));
    }

    public function testItAbstainsOnOtherAttributesAndSubjects(): void
    {
        $voter = new InsightsVoter($this->resolver($this->member(1, admin: true)));
        $token = $this->createStub(TokenInterface::class);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $this->company(1), ['COMPANY_VIEW']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), [InsightsPermissions::COMPANY_VIEW_INSIGHTS]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, [InsightsPermissions::COMPANY_VIEW_INSIGHTS]));
    }

    private function vote(?CompanyMemberInterface $member, CompanyInterface $subject): int
    {
        return (new InsightsVoter($this->resolver($member)))->vote($this->createStub(TokenInterface::class), $subject, [InsightsPermissions::COMPANY_VIEW_INSIGHTS]);
    }

    private function resolver(?CompanyMemberInterface $member): CurrentMemberResolverInterface
    {
        $resolver = $this->createStub(CurrentMemberResolverInterface::class);
        $resolver->method('getMember')->willReturn($member);

        return $resolver;
    }

    private function member(int $companyId, bool $admin, bool $active = true): CompanyMemberInterface
    {
        $member = $this->createStub(CompanyMemberInterface::class);
        $member->method('getCompany')->willReturn($this->company($companyId));
        $member->method('isAdmin')->willReturn($admin);
        $member->method('isActive')->willReturn($active);

        return $member;
    }

    private function company(?int $id): CompanyInterface
    {
        $company = $this->createStub(CompanyInterface::class);
        $company->method('getId')->willReturn($id);

        return $company;
    }
}
