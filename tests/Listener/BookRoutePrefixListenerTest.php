<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Listener;

use c975L\BookBundle\Listener\BookRoutePrefixListener;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

// Generating a catalog url: the router fills the prefix parameters from its context, which is what this listener puts there, so path('book_display', {slug: ...}) keeps taking the slug alone
class BookRoutePrefixListenerTest extends TestCase
{
    // The five parameters the routes are written with, each holding the word the back office was given
    public function testEveryConfiguredPrefixIsPutInTheRoutersContext(): void
    {
        $context = $this->handle([
            'book-route-books' => 'livres',
            'book-route-book' => 'livre',
            'book-route-series' => 'series',
            'book-route-strips' => 'planches',
            'book-route-strip' => 'planche',
        ]);

        $this->assertSame('livres', $context->getParameter('books_prefix'));
        $this->assertSame('livre', $context->getParameter('book_prefix'));
        $this->assertSame('series', $context->getParameter('series_prefix'));
        $this->assertSame('planches', $context->getParameter('strips_prefix'));
        $this->assertSame('planche', $context->getParameter('strip_prefix'));
    }

    // A family served elsewhere leaves an empty parameter rather than none at all: the generator would otherwise raise a missing parameter where the route simply doesn't match
    public function testAFamilyServedElsewhereLeavesItsParameterEmptyRatherThanUnset(): void
    {
        $context = $this->handle(['book-route-books' => 'livres']);

        $this->assertSame('livres', $context->getParameter('books_prefix'));
        $this->assertSame('', $context->getParameter('strip_prefix'));
    }

    // The word is read at each request, the entry being editable with no cache to clear
    public function testTheWordIsReadAgainOnEveryRequest(): void
    {
        $entries = ['book-route-books' => 'livres'];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService
            ->method('get')
            ->willReturnCallback(static function (string $slug) use (&$entries) {
                return $entries[$slug] ?? null;
            })
        ;

        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context = new RequestContext());

        $listener = new BookRoutePrefixListener($router, new BookRoutePrefix($configService));
        $listener($this->event());

        $this->assertSame('livres', $context->getParameter('books_prefix'));

        $entries['book-route-books'] = 'books';
        $listener($this->event());

        $this->assertSame('books', $context->getParameter('books_prefix'));
    }

    // A sub-request renders a fragment inside a page whose context is already set: reading the six entries again would buy nothing
    public function testASubRequestIsLeftAlone(): void
    {
        $context = new RequestContext();
        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('livres');

        new BookRoutePrefixListener($router, new BookRoutePrefix($configService))($this->event(HttpKernelInterface::SUB_REQUEST));

        $this->assertFalse($context->hasParameter('books_prefix'));
    }

    /** @param array<string, string> $entries */
    private function handle(array $entries): RequestContext
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService
            ->method('get')
            ->willReturnCallback(static fn (string $slug): ?string => $entries[$slug] ?? null)
        ;

        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context = new RequestContext());

        new BookRoutePrefixListener($router, new BookRoutePrefix($configService))($this->event());

        return $context;
    }

    private function event(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }
}
