<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Routing\BookRoutePrefix;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

// Matching a catalog url is the routes' condition business (see BookRoutePrefix); generating one is this listener's: the generator fills a route parameter it wasn't given from the request context, which is where the configured prefixes are put here, so path('book_display', {slug: ...}) keeps taking the slug alone
// Runs just before Symfony's own RouterListener (priority 32), the parameters having to be in the context before anything generates a url
#[AsEventListener(event: KernelEvents::REQUEST, priority: 33)]
class BookRoutePrefixListener
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly BookRoutePrefix $routePrefix,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $context = $this->router->getContext();

        foreach (BookRoutePrefix::PREFIXES as $slug => $parameter) {
            $context->setParameter($parameter, $this->routePrefix->get($slug));
        }
    }
}
