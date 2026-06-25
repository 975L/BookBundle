<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\SerieRepository;
use Knp\Component\Pager\PaginatorInterface;

class SerieService implements SerieServiceInterface
{
    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly SerieRepository $serieRepository,
    ) {
    }

    // Finds all
    public function findAll(?int $number = null): array
    {
        return $this->serieRepository->findAll($number);
    }

    // Gets the series paginated
    public function findAllPaginated($query)
    {
        return $this->paginator->paginate(
            $this->findAll(),
            (int) $query->get('p') > 0 ? (int) $query->get('p') : 1,
            10
        );
    }

    // Find all random
    public function findAllRandom(int $number): array
    {
        $series = $this->serieRepository->findAll(null);
        shuffle($series);

        return array_slice($series, 0, $number);
    }

    // Finds all with sorted books by published date, null first
    public function findOneBySlugWithSortedBooks(string $slug): ?Serie
    {
        return $this->serieRepository->findOneBySlugWithSortedBooks($slug);
    }
}
