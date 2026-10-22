<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Filter;

use Tmonier\SyliusCompanyInsightsPlugin\Filter\FilterChoicesInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\MemberChoice;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\SiteChoice;

final class FakeFilterChoices implements FilterChoicesInterface
{
    public ?int $askedFor = null;

    /**
     * @param list<MemberChoice> $members
     * @param list<SiteChoice> $sites
     */
    public function __construct(private readonly array $members = [], private readonly array $sites = [])
    {
    }

    public function members(int $companyId): array
    {
        $this->askedFor = $companyId;

        return $this->members;
    }

    public function sites(int $companyId): array
    {
        $this->askedFor = $companyId;

        return $this->sites;
    }
}
