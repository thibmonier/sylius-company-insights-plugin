<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** A page of a ranking. The `page` query parameter is a positive whole number or it is the first page. */
final readonly class PageRequest
{
    public const TOP = 10;

    public const PER_PAGE = 25;

    public function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    public static function top(): self
    {
        return new self(1, self::TOP);
    }

    public static function fromRequest(mixed $value, int $perPage = self::PER_PAGE): self
    {
        $page = is_string($value) && 1 === preg_match('/^[1-9][0-9]{0,5}$/', $value) ? (int) $value : 1;

        return new self($page, $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function onPage(int $page): self
    {
        return new self($page, $this->perPage);
    }
}
