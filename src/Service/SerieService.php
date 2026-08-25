<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\UiBundle\Model\Pagination;
use c975L\UiBundle\Service\Paginator;
use Symfony\Component\HttpFoundation\InputBag;

class SerieService implements SerieServiceInterface
{
    public function __construct(
        private readonly Paginator $paginator,
        private readonly SerieRepository $serieRepository,
    ) {
    }

    // Finds all
    public function findAll(?int $number = null): array
    {
        return $this->serieRepository->findAll($number);
    }

    // Gets the series paginated
    public function findAllPaginated(InputBag $query): Pagination
    {
        return $this->paginate($this->findAll(), $query);
    }

    // Find all random
    public function findAllRandom(int $number): array
    {
        $series = $this->serieRepository->findAll(null);
        shuffle($series);

        return array_slice($series, 0, $number);
    }

    // Finds series that have at least one book
    public function findWithBooks(): array
    {
        return $this->serieRepository->findWithBooks();
    }

    // Finds series that have at least one strip
    public function findWithStrips(): array
    {
        return $this->serieRepository->findWithStrips();
    }

    // What the books' index lists, paginated as it was when it listed every serie
    public function findWithBooksPaginated(InputBag $query): Pagination
    {
        return $this->paginate($this->findWithBooks(), $query);
    }

    // What the planches' index lists: the series telling them, and no longer the planches themselves - those are read inside the serie that tells them (see StripController::index())
    public function findWithStripsPaginated(InputBag $query): Pagination
    {
        return $this->paginate($this->findWithStrips(), $query);
    }

    // Finds all with sorted books by published date, null first
    public function findOneBySlugWithSortedBooks(string $slug): ?Serie
    {
        return $this->serieRepository->findOneBySlugWithSortedBooks($slug);
    }

    // How a page of series is cut, the three listings above sharing it rather than each spelling the page size again
    /** @param Serie[] $series */
    private function paginate(array $series, InputBag $query): Pagination
    {
        return $this->paginator->paginate(
            $series,
            $this->paginator->getPage($query),
            10
        );
    }
}
