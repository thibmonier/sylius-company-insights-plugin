<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;

/**
 * Builds the selection from the query string. Nothing of the request is trusted: the period is two real
 * dates (which win) or a known key, the member and the site must be one of the choices of the company of
 * the signed-in member (anything else is ignored, as if it was not asked).
 */
final readonly class SelectionFactory
{
    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(
        private PeriodResolver $periodResolver,
        private FilterChoicesInterface $choices,
    ) {
    }

    public function create(int $companyId, array $query): Selection
    {
        $periods = $this->customPeriod($query['from'] ?? null, $query['to'] ?? null);
        $periods ??= $this->periodResolver->resolve(PeriodKey::fromRequest($query['period'] ?? null));
        $carried = ['period' => $periods->key->value];
        if (PeriodKey::Custom === $periods->key) {
            $carried['from'] = $periods->current->from->format('Y-m-d');
            $carried['to'] = $periods->current->lastDay()->format('Y-m-d');
        }

        $customerId = $this->allowed($query['member'] ?? null, array_map(static fn (MemberChoice $choice): int => $choice->customerId, $this->choices->members($companyId)));
        $addressId = $this->allowed($query['site'] ?? null, array_map(static fn (SiteChoice $choice): int => $choice->addressId, $this->choices->sites($companyId)));
        if (null !== $customerId) {
            $carried['member'] = (string) $customerId;
        }
        if (null !== $addressId) {
            $carried['site'] = (string) $addressId;
        }

        return new Selection($periods, new PurchaseFilter($customerId, $addressId), $carried);
    }

    private function customPeriod(mixed $from, mixed $to): ?PeriodComparison
    {
        $first = $this->date($from);
        $last = $this->date($to);
        if (!$first instanceof \DateTimeImmutable || !$last instanceof \DateTimeImmutable || $first > $last) {
            return null;
        }

        return $this->periodResolver->custom($first, $last);
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || 1 !== preg_match(self::DATE, $value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value && (int) $date->format('Y') >= 2000 ? $date : null;
    }

    /** @param list<int> $allowed */
    private function allowed(mixed $value, array $allowed): ?int
    {
        if (!is_string($value) || 1 !== preg_match('/^\d{1,18}$/', $value)) {
            return null;
        }

        return in_array((int) $value, $allowed, true) ? (int) $value : null;
    }
}
