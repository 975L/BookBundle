<?php

namespace c975L\BookBundle\Twig\Components;

use c975L\BookBundle\Service\StripServiceInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'StripSearch', template: '@c975LBook/components/StripSearch.html.twig')]
class StripSearchComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    // The serie the search is held inside, when a serie's own page carries it - null everywhere else, where the field asks the whole site
    #[LiveProp]
    public ?int $serieId = null;

    public function __construct(private StripServiceInterface $stripService)
    {
    }

    public function getStrips()
    {
        if ('' === trim($this->query)) {
            return null;
        }

        return $this->stripService->search($this->query, $this->serieId);
    }
}
