<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Twig\Components;

use c975L\BookBundle\Service\BookServiceInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'BookSearch', template: '@c975LBook/components/BookSearch.html.twig')]
class BookSearchComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    // The serie the search is held inside, when a serie's own page carries it - null everywhere else, where the field asks the whole site
    #[LiveProp]
    public ?int $serieId = null;

    public function __construct(private BookServiceInterface $bookService)
    {
    }

    public function getBooks()
    {
        if ('' === trim($this->query)) {
            return null;
        }

        return $this->bookService->search($this->query, $this->serieId);
    }
}
