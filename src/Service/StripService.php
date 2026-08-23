<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\StripRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\InputBag;

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

    public function findAllPublishedBySerie(Serie $serie, ?int $number = null, ?string $character = null): array
    {
        return $this->stripRepository->findAllPublishedBySerie($serie, $number, $character);
    }

    public function findAllBySeriePaginated(Serie $serie, InputBag $query, ?string $character = null): PaginationInterface
    {
        return $this->paginator->paginate(
            $this->findAllPublishedBySerie($serie, null, $character),
            (int) $query->get('p') > 0 ? (int) $query->get('p') : 1,
            24
        );
    }

    public function findCharactersBySerie(Serie $serie): array
    {
        return $this->stripRepository->findCharactersBySerie($serie);
    }

    public function findAllByCharacter(string $character): array
    {
        return $this->stripRepository->findAllByCharacter($character);
    }

    // The planche carrying that slug, which the page of a planche asks for before falling back on the number (see StripController::display())
    public function findOneBySlug(string $slug): ?Strip
    {
        return $this->stripRepository->findOneBy(['slug' => $slug]);
    }

    // The planche a number leads to
    public function findOneByNumber(int $number): ?Strip
    {
        return $this->stripRepository->findOneByNumber($number);
    }

    public function findPreviousNext(Strip $strip): array
    {
        return $this->stripRepository->findPreviousNext($strip);
    }

    public function search(string $query, ?int $serieId = null): array
    {
        return $this->stripRepository->search($query, $serieId);
    }
}
