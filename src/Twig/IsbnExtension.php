<?php

namespace c975L\BookBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class IsbnExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('isbn', $this->isbnFilter(...))];
    }

    //Returns isbn formatted 979-10-92030-14-3
    public function isbnFilter($data)
    {
        return sprintf(
            "%s-%s-%s-%s-%s",
            substr((string) $data, 0, 3),
            substr((string) $data, 3, 2),
            substr((string) $data, 5, 5),
            substr((string) $data, 10, 2),
            substr((string) $data, 12, 1)
        );
    }
}
