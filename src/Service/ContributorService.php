<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\UiBundle\Model\Pagination;
use c975L\UiBundle\Service\Paginator;
use Symfony\Component\HttpFoundation\InputBag;

class ContributorService implements ContributorServiceInterface
{
    public function __construct(
        private readonly Paginator $paginator,
        private readonly ContributorRepository $contributorRepository,
    ) {
    }

    // Finds all
    public function findAll(?int $number = null): array
    {
        return $this->contributorRepository->findAll($number);
    }

    // Gets the people paginated
    public function findAllPaginated(InputBag $query): Pagination
    {
        return $this->paginate($this->findAll(), $query);
    }

    // Finds the people a shown book or serie still credits
    public function findCredited(): array
    {
        return $this->contributorRepository->findCredited();
    }

    // What the index lists, paginated as the series' own index is
    public function findCreditedPaginated(InputBag $query): Pagination
    {
        return $this->paginate($this->findCredited(), $query);
    }

    // Finds one with everything they are credited on
    public function findOneBySlugWithWorks(string $slug): ?Contributor
    {
        return $this->contributorRepository->findOneBySlugWithWorks($slug);
    }

    // Finds one by the name they are recorded under
    public function findOneByName(string $name): ?Contributor
    {
        return $this->contributorRepository->findOneByName($name);
    }

    // How a page of people is cut, the two listings above sharing it rather than each spelling the page size again
    /** @param Contributor[] $contributors */
    private function paginate(array $contributors, InputBag $query): Pagination
    {
        return $this->paginator->paginate(
            $contributors,
            $this->paginator->getPage($query),
            10
        );
    }
}
