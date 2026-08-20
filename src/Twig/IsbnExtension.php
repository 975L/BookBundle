<?php

namespace c975L\BookBundle\Twig;

use Twig\Attribute\AsTwigFilter;

class IsbnExtension
{
    // Returns isbn formatted 979-10-92030-14-3
    #[AsTwigFilter('isbn')]
    public function isbnFilter($data)
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr((string) $data, 0, 3),
            substr((string) $data, 3, 2),
            substr((string) $data, 5, 5),
            substr((string) $data, 10, 2),
            substr((string) $data, 12, 1)
        );
    }
}
