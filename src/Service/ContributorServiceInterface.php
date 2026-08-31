<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Contributor;
use c975L\UiBundle\Model\Pagination;
use Symfony\Component\HttpFoundation\InputBag;

interface ContributorServiceInterface
{
    /**
     * @param int|null $number caps the result, null returning them all
     *
     * @return Contributor[]
     */
    public function findAll(?int $number = null): array;

    /**
     * Every person, 10 per page.
     *
     * @return Pagination<Contributor>
     */
    public function findAllPaginated(InputBag $query): Pagination;

    /**
     * @return Contributor[] the people a shown book or serie still credits
     */
    public function findCredited(): array;

    // What the index lists, paginated.
    public function findCreditedPaginated(InputBag $query): Pagination;

    // The person and everything they are credited on.
    public function findOneBySlugWithWorks(string $slug): ?Contributor;

    // The person a name stands for, null when nobody carries it - what an import falls back on for an archive predating this entity.
    public function findOneByName(string $name): ?Contributor;
}
