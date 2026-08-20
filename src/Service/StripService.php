<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\StripRepository;
use Knp\Component\Pager\PaginatorInterface;

class StripService implements StripServiceInterface
{
    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly StripRepository $stripRepository,
    ) {
    }

    public function findAllPublished(?int $number = null): array
    {
        return $this->stripRepository->findAllPublished($number);
    }

    public function findAllPaginated($query)
    {
        return $this->paginator->paginate(
            $this->findAllPublished(),
            (int) $query->get('p') > 0 ? (int) $query->get('p') : 1,
            24
        );
    }

    public function findAllPublishedBySerie(Serie $serie, ?int $number = null): array
    {
        return $this->stripRepository->findAllPublishedBySerie($serie, $number);
    }

    public function findAllByCharacter(string $character): array
    {
        return $this->stripRepository->findAllByCharacter($character);
    }

    public function findAllByCharacterPaginated(string $character, $query)
    {
        return $this->paginator->paginate(
            $this->findAllByCharacter($character),
            (int) $query->get('p') > 0 ? (int) $query->get('p') : 1,
            24
        );
    }

    public function findPreviousNext(Strip $strip): array
    {
        return $this->stripRepository->findPreviousNext($strip);
    }

    public function search(string $query): array
    {
        return $this->stripRepository->search($query);
    }
}
