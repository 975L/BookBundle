<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;

interface BookServiceInterface
{
    public function findOneBySlug(string $slug);

    public function findAll(): array;

    public function findAllPaginated($query);

    public function findAllPublished(?int $number = null): array;

    public function findAllToBePublished();

    public function search(string $query);
}
