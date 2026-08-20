<?php

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\StripServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Same as BookController: the first segment is a ConfigBundle entry, empty when the strips are not served here (see BookRoutePrefix)
class StripController extends AbstractController
{
    private const string INDEX_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-strips', params['strips_prefix'])";

    private const string DISPLAY_CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-strip', params['strip_prefix'])";

    public function __construct(
        private readonly StripServiceInterface $stripService,
    ) {
    }

    #[Route(
        '/{strips_prefix}',
        name: 'strip_index',
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function index(Request $request): Response
    {
        return $this->render(
            '@c975LBook/strip/index.html.twig',
            ['strips' => $this->stripService->findAllPaginated($request->query)]
        )->setMaxAge(3600);
    }

    #[Route(
        '/{strips_prefix}/character/{character}',
        name: 'strip_character',
        requirements: ['character' => '^([a-z0-9\-]+)'],
        methods: ['GET'],
        condition: self::INDEX_CONDITION
    )]
    public function character(string $character, Request $request): Response
    {
        return $this->render(
            '@c975LBook/strip/index.html.twig',
            [
                'strips' => $this->stripService->findAllByCharacterPaginated($character, $request->query),
                'character' => $character,
            ]
        )->setMaxAge(3600);
    }

    #[Route(
        '/{strip_prefix}/{slug:strip}',
        name: 'strip_display',
        requirements: ['slug' => '^([a-z0-9\-]+)'],
        methods: ['GET'],
        condition: self::DISPLAY_CONDITION
    )]
    public function display(Strip $strip): Response
    {
        // A strip that has never been published is not publicly reachable
        if (null === $strip->getPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            '@c975LBook/strip/display.html.twig',
            [
                'strip' => $strip,
                'previousNext' => $this->stripService->findPreviousNext($strip),
            ]
        )->setMaxAge(3600);
    }
}
