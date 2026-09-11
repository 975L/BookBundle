<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookLinkLocalizer;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\ConfigBundle\Service\SiteLocales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The first segment of every one of these urls is an entry an editor writes (see BookRoutePrefix), so what this recognises is read from the configuration at each call and never from a constant
class BookLinkLocalizerTest extends TestCase
{
    // The prefixes this site happens to have chosen, French words and not the bundle's own defaults
    private const array PREFIXES = [
        'book-route-books' => 'livres',
        'book-route-book' => 'livre',
        'book-route-series' => 'series',
        'book-route-categories' => 'categories',
        'book-route-contributors' => 'auteurs',
        'book-route-contributor' => 'auteur',
        'book-route-strips' => 'planches',
        'book-route-strip' => 'planche',
    ];

    // A card pointing at the catalog from an English page leads into the English catalog, not back into the writing language
    public function testAnIndexLinkIsReadInTheLanguageThePageAroundItIsReadIn(): void
    {
        $this->assertSame('/en/livres', $this->localizer('en')->localize('/livres'));
    }

    // A book, a serie, a category and a person are read at that language's url too: "/en" is the language the catalog is read in, not a claim about the row (see BookTranslatedLocales)
    public function testEachKindOfPageIsLinkedInTheLanguageBeingRead(): void
    {
        $localizer = $this->localizer('en');

        $this->assertSame('/en/livre/la-licorne', $localizer->localize('/livre/la-licorne'));
        $this->assertSame('/en/series/le-fil-rouge', $localizer->localize('/series/le-fil-rouge'));
        $this->assertSame('/en/categories/romans', $localizer->localize('/categories/romans'));
        $this->assertSame('/en/auteur/camille-ferrand', $localizer->localize('/auteur/camille-ferrand'));
    }

    // A word linked inside a rich text is a link like any other
    public function testTheLinksOfARichTextAreRewritten(): void
    {
        $this->assertSame(
            'lire <a href="/en/livre/la-licorne">le livre</a>',
            $this->localizer('en')->localize('lire <a href="/livre/la-licorne">le livre</a>'),
        );
    }

    // A family this site does not serve has no route answering at all, and a path that merely looks like one belongs to whatever else wears that segment
    public function testAFamilyThisSiteDoesNotServeIsGivenBackUntouched(): void
    {
        $localizer = $this->localizer('en', ['book-route-strips' => '', 'book-route-strip' => '']);

        $this->assertSame('/planches', $localizer->localize('/planches'));
        $this->assertSame('/planche/003', $localizer->localize('/planche/003'));
    }

    // Anything else this site serves is none of this bundle's business
    public function testAPathOfAnotherKindIsGivenBackUntouched(): void
    {
        $localizer = $this->localizer('en');

        foreach (['/shop', '/pages/contact', '/livres/', '/livre/la-licorne/extrait', 'https://example.com/livres', '#ancre'] as $path) {
            $this->assertSame($path, $localizer->localize($path));
        }
    }

    // The no-regression contract: the language the site is written in keeps the urls it always had
    public function testTheWritingLanguageIsLeftExactlyAsItWas(): void
    {
        $this->assertSame('/livre/la-licorne', $this->localizer(null)->localize('/livre/la-licorne'));
    }

    /** @param array<string, string> $overrides */
    private function localizer(?string $readingLocale, array $overrides = []): BookLinkLocalizer
    {
        $request = Request::create('/');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

        $prefixes = [...self::PREFIXES, ...$overrides];

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): ?string => $prefixes[$slug] ?? null);

        // The segment is read off the parameters handed over and nowhere else, as the real router does: a prefix left to the request context is missing here exactly as it is on a render with no request (a command, a message, an email)
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = []): string {
                $segment = static function (string $entry) use ($route, $parameters): string {
                    $name = BookRoutePrefix::PREFIXES[$entry];

                    return $parameters[$name] ?? throw new MissingMandatoryParametersException($route, [$name]);
                };

                $bare = str_ends_with($route, '_localized') ? substr($route, 0, -\strlen('_localized')) : $route;
                $segments = match ($bare) {
                    'book_index' => [$segment('book-route-books')],
                    'book_display' => [$segment('book-route-book'), $parameters['slug']],
                    'serie_index' => [$segment('book-route-series')],
                    'serie_display' => [$segment('book-route-series'), $parameters['slug']],
                    'book_category_index' => [$segment('book-route-categories')],
                    'book_category_display' => [$segment('book-route-categories'), $parameters['slug']],
                    'contributor_index' => [$segment('book-route-contributors')],
                    'contributor_display' => [$segment('book-route-contributor'), $parameters['slug']],
                    'strip_index' => [$segment('book-route-strips')],
                    'strip_serie_display' => [$segment('book-route-strips'), $parameters['slug']],
                    'strip_display' => [$segment('book-route-strip'), $parameters['slug']],
                    default => throw new RouteNotFoundException($route),
                };

                return '/' . implode('/', $route === $bare ? $segments : [$parameters['_locale'], ...$segments]);
            }
        );

        $siteLocales = new SiteLocales(['fr', 'en'], 'fr');
        $routePrefix = new BookRoutePrefix($configService);

        return new BookLinkLocalizer(
            $routePrefix,
            new BookPublicUrlResolver($configService, $routePrefix, new LocalizedUrlGenerator($router, $siteLocales, new RequestStack([$request])), $router, $siteLocales),
        );
    }
}
