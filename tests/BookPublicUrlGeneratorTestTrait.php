<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

// Shared by every test constructing a real BookPublicUrlResolver - a real UrlGenerator over the same route shapes as BookController, SerieController and StripController, so tests exercise the actual routing rather than a hand-duplicated string
trait BookPublicUrlGeneratorTestTrait
{
    // The prefixes a fresh install carries, one per ConfigBundle entry of BookRoutePrefix::PREFIXES
    private const array DEFAULT_ROUTE_PREFIXES = [
        'book-route-books' => 'livres',
        'book-route-book' => 'livre',
        'book-route-series' => 'series',
        'book-route-contributors' => 'auteurs',
        'book-route-contributor' => 'auteur',
        'book-route-strips' => 'strips',
        'book-route-strip' => 'strip',
    ];

    private function createUrlGenerator(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('book_index', new Route('/{books_prefix}'));
        $routes->add('book_display', new Route('/{book_prefix}/{slug}', [], ['slug' => '^([a-z0-9\-]+)']));
        $routes->add('serie_index', new Route('/{series_prefix}'));
        $routes->add('serie_display', new Route('/{series_prefix}/{slug}', [], ['slug' => '^([a-z0-9\-]+)']));
        $routes->add('contributor_index', new Route('/{contributors_prefix}'));
        $routes->add('contributor_display', new Route('/{contributor_prefix}/{slug}', [], ['slug' => '^([a-z0-9\-]+)']));
        $routes->add('strip_index', new Route('/{strips_prefix}'));
        $routes->add('strip_serie_display', new Route('/{strips_prefix}/{slug}', [], ['slug' => '^([a-z0-9\-]+)']));
        $routes->add('strip_display', new Route('/{strip_prefix}/{slug}', [], ['slug' => '^([a-z0-9\-]+)']));

        return new UrlGenerator($routes, new RequestContext());
    }

    // The configured first segments, the defaults unless a test overrides one - mapping a slug to an empty string is how a site says that family is read elsewhere
    private function createRoutePrefix(array $prefixes = []): BookRoutePrefix
    {
        $prefixes = [...self::DEFAULT_ROUTE_PREFIXES, ...$prefixes];

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(fn (string $slug): string => $prefixes[$slug] ?? '');

        return new BookRoutePrefix($configService);
    }
}
