<?php

namespace c975L\BookBundle\Twig\Components;

use c975L\BookBundle\Service\BookServiceInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class BookSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(private BookServiceInterface $bookService)
    {
    }

    public function getBooks()
    {
        if (trim($this->query) === '') {
            return null;
        }

        return $this->bookService->search($this->query);
    }
}
