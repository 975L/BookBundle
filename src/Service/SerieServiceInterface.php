<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Serie;

interface SerieServiceInterface
{
    public function findAll(?int $number = null): array;

    public function findAllPaginated($query);

    public function findAllRandom(int $number): array;

    public function findOneBySlugWithSortedBooks(string $slug): ?Serie;
}
