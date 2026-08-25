<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use c975L\UiBundle\Model\Pagination;
use c975L\UiBundle\Service\Paginator;
use Symfony\Component\HttpFoundation\InputBag;

class BookService implements BookServiceInterface
{
    public function __construct(
        private readonly Paginator $paginator,
        private readonly BookRepository $bookRepository,
        private readonly BookCatalogRegistry $catalogRegistry,
    ) {
    }

    // Finds all
    public function findAll(): array
    {
        return $this->bookRepository->findAll();
    }

    // The site's own list when it declares one, the bundle's otherwise: a catalog published in editions does not say "out" the way a book with a single date does (see BookCatalogProviderInterface)
    public function findAllPaginated(InputBag $query): Pagination
    {
        return $this->paginator->paginate(
            $this->catalogRegistry->getBooks() ?? $this->findAllPublished(),
            $this->paginator->getPage($query),
            10
        );
    }

    // Finds all published
    public function findAllPublished(?int $number = null, ?string $language = null): array
    {
        return $this->bookRepository->findAllPublished($number, $language);
    }

    // Every book whose page answers, the ones a newer version replaces included
    public function findAllOnline(): array
    {
        return $this->bookRepository->findAllOnline();
    }

    // How many books are out, without building a single one of them
    public function countPublished(?string $language = null): int
    {
        return $this->bookRepository->countPublished($language);
    }

    // The languages the catalog is written in
    public function findLanguages(): array
    {
        return $this->bookRepository->findLanguages();
    }

    // The book carrying that slug, which the page of a book asks for before falling back on the number (see BookController::display())
    public function findOneBySlug(string $slug): ?Book
    {
        return $this->bookRepository->findOneBy(['slug' => $slug]);
    }

    // The book a number leads to, the one the catalog still lists
    public function findOneByNumber(int $number): ?Book
    {
        return $this->bookRepository->findOneByNumber($number);
    }

    // Finds all to be published
    public function findAllToBePublished(): array
    {
        return $this->bookRepository->findAllToBePublished();
    }

    // Searches for book
    public function search(string $query, ?int $serieId = null): array
    {
        return $this->bookRepository->search($query, $serieId);
    }
}
