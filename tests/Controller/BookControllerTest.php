<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\BookController;
use c975L\BookBundle\Entity\BookSettings;
use c975L\BookBundle\Repository\BookSettingsRepository;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\BookTranslatedLocales;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\UiBundle\Model\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

// The catalog's index reads the single settings row for its own line and the blocks above its listing, whose absence is the ordinary state of a catalog that never opened the back-office screen
class BookControllerTest extends TestCase
{
    // The parameters the index hands its template, the row being whatever the test passes
    private function renderIndexWith(?BookSettings $settings): array
    {
        $parameters = [];

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $name, array $context) use (&$parameters): string {
            $parameters = $context;

            return '<index>';
        });

        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findAllPaginated')->willReturn(new Pagination([], 1, 10, 0));

        $settingsRepository = $this->createStub(BookSettingsRepository::class);
        $settingsRepository->method('findSingle')->willReturn($settings);

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new BookController(
            $bookService,
            $settingsRepository,
            $this->createStub(BookTranslatedLocales::class),
            $this->createStub(BookTranslator::class),
            $this->createNegotiator(),
            $this->createStub(LocalizedUrlGenerator::class),
        );
        $controller->setContainer($container);
        $controller->index(new Request());

        return $parameters;
    }

    public function testIndexPassesTheIntroWrittenInTheBackOffice(): void
    {
        $settings = new BookSettings()->setIntro('Nos livres, à lire dès 3 ans.');

        $this->assertSame('Nos livres, à lire dès 3 ans.', $this->renderIndexWith($settings)['bookIntro']);
    }

    // Null rather than a string: Book:Explanation then puts the shipped sentence back
    public function testIndexPassesNoIntroWhenTheCatalogHasNoSettingsRow(): void
    {
        $this->assertNull($this->renderIndexWith(null)['bookIntro']);
    }

    // The row itself, whose blocks the template renders above the listing, beside the books it lists
    public function testIndexPassesTheSettingsRowBesideTheBooks(): void
    {
        $settings = new BookSettings();
        $parameters = $this->renderIndexWith($settings);

        $this->assertSame($settings, $parameters['bookSettings']);
        $this->assertArrayHasKey('books', $parameters);
    }

    // The negotiator as a screen answering in one language alone meets it: nobody to move, and the response handed straight back
    private function createNegotiator(): LocalizedRouteNegotiator
    {
        $negotiator = $this->createStub(LocalizedRouteNegotiator::class);
        $negotiator->method('redirectToAskedLanguage')->willReturn(null);
        $negotiator->method('vary')->willReturnCallback(static fn (Request $request, Response $response): Response => $response);

        return $negotiator;
    }
}
