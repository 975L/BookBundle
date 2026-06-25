<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Repository\BookRepository;
use Knp\Component\Pager\PaginatorInterface;

class BookService implements BookServiceInterface
{
    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly BookRepository $bookRepository
    ) {
    }

    // Finds by slug
    public function findOneBySlug(string $slug): ?Book
    {
        return $this->bookRepository->findOneBySlug($slug);
    }

    // Finds all
    public function findAll(): array
    {
        return $this->bookRepository->findAll();
    }

    // Gets the stories paginated
    public function findAllPaginated($query)
    {
        return $this->paginator->paginate(
            $this->findAllPublished(),
            (int) $query->get('p') > 0 ? (int) $query->get('p') : 1,
            10
        );
    }

    // Finds all published
    public function findAllPublished(?int $number = null): array
    {
        return $this->bookRepository->findAllPublished($number);
    }

    // Finds all to be published
    public function findAllToBePublished(): array
    {
        return $this->bookRepository->findAllToBePublished();
    }

    // Searches for book
    public function search(string $query)
    {
        return $this->bookRepository->search($query);
    }
}
