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
use c975L\ConfigBundle\EventSubscriber\RedirectSubscriber;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

// Puts the configured prefixes in the router's request context so path('book_display', {slug: ...}) keeps taking the slug alone (matching is BookRoutePrefix's business). Runs before ConfigBundle's RedirectSubscriber, as the error page a "gone" row leads to still renders links
#[AsEventListener(event: KernelEvents::REQUEST, priority: RedirectSubscriber::PRIORITY + 1)]
class BookRoutePrefixListener
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly BookRoutePrefix $routePrefix,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $context = $this->router->getContext();

        // A fragment renders inside a page whose context is already set, but the error page of a request aborted before this listener ran is a sub-request on an empty one
        if (!$event->isMainRequest() && $context->hasParameter('books_prefix')) {
            return;
        }

        foreach (BookRoutePrefix::PREFIXES as $slug => $parameter) {
            $context->setParameter($parameter, $this->routePrefix->get($slug));
        }
    }
}
