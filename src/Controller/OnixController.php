<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Enum\BookChannel;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\BookOnixBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// The catalog as a public ONIX 3.0 feed, the editions ticked "ONIX" alone (see BookChannel), the books still to come included - what a store, or a script uploading to one, reads (see BookOnixBuilder). Served at the address the "book-route-onix" entry names ("onix.xml"), empty by default: a site turns its feed on by naming it, the same way as the listening pages
class OnixController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly BookOnixBuilder $onixBuilder,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The publisher the feed speaks for is the "book-onix-publisher" entry, the site's name for want of one (see BookOnixBuilder::publisher())
    #[Route(
        '/{onix_prefix}',
        name: 'book_onix',
        methods: ['GET'],
        condition: "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-onix', params['onix_prefix'])"
    )]
    public function feed(Request $request): Response
    {
        $xml = $this->onixBuilder->build($this->bookRepository->findAllForOnix(), BookOnixBuilder::publisher($this->configService), $request->getSchemeAndHttpHost(), static fn (BookEdition $edition): bool => $edition->hasChannel(BookChannel::Onix));

        $response = new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
