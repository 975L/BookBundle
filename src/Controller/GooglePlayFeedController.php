<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Routing\BookRoutePrefix;
use c975L\BookBundle\Service\GooglePlayFeed;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// The folders Google Play Books' crawler reads on its own schedule ("automated content fetching"): plain directory listings as a web server prints them, the ONIX of the ebooks and their files, behind HTTP Basic. Served under the segment the "book-route-google" entry names, empty by default, and only once the collection code, the user and the password are all set - the files being what a buyer pays for, nothing is ever served without them
class GooglePlayFeedController extends AbstractController
{
    private const string CONDITION = "service('" . BookRoutePrefix::ALIAS . "').matches('book-route-google', params['google_prefix'])";

    public function __construct(
        private readonly GooglePlayFeed $feed,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The two top folders
    #[Route('/{google_prefix}/', name: 'book_google', methods: ['GET'], condition: self::CONDITION)]
    public function root(Request $request): Response
    {
        return $this->guard($request) ?? $this->listing($request, ['onix/' => null, 'ebooks/' => null]);
    }

    // The ONIX folder, holding only the rights one: the ebooks are sold, not merely described
    #[Route('/{google_prefix}/onix/', name: 'book_google_onix', methods: ['GET'], condition: self::CONDITION)]
    public function onixFolder(Request $request): Response
    {
        return $this->guard($request) ?? $this->listing($request, [$this->feed->collection() . '-rights/' => null]);
    }

    // The rights folder, holding the one ONIX file under its dated name
    #[Route('/{google_prefix}/onix/{folder}/', name: 'book_google_onix_rights', methods: ['GET'], condition: self::CONDITION)]
    public function onixRights(Request $request, string $folder): Response
    {
        if (null !== $denied = $this->guard($request)) {
            return $denied;
        }
        $this->checkFolder($folder, $this->feed->collection() . '-rights');

        $onix = $this->feed->onix($request->getSchemeAndHttpHost());

        return $this->listing($request, [$onix['name'] => [\strlen($onix['content']), $onix['modified']]]);
    }

    // The ONIX file itself
    #[Route('/{google_prefix}/onix/{folder}/{name}', name: 'book_google_onix_file', methods: ['GET'], condition: self::CONDITION)]
    public function onix(Request $request, string $folder, string $name): Response
    {
        if (null !== $denied = $this->guard($request)) {
            return $denied;
        }
        $this->checkFolder($folder, $this->feed->collection() . '-rights');

        $onix = $this->feed->onix($request->getSchemeAndHttpHost());
        if ($name !== $onix['name']) {
            throw new NotFoundHttpException();
        }

        $response = new Response($onix['content'], Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setLastModified($onix['modified']);

        return $this->private($response);
    }

    // The folder of the ebooks' files
    #[Route('/{google_prefix}/ebooks/', name: 'book_google_ebooks', methods: ['GET'], condition: self::CONDITION)]
    public function ebooksFolder(Request $request): Response
    {
        return $this->guard($request) ?? $this->listing($request, [$this->feed->collection() . '/' => null]);
    }

    // The collection's folder, every ebook's file and cover
    #[Route('/{google_prefix}/ebooks/{folder}/', name: 'book_google_ebooks_collection', methods: ['GET'], condition: self::CONDITION)]
    public function ebooksCollection(Request $request, string $folder): Response
    {
        if (null !== $denied = $this->guard($request)) {
            return $denied;
        }
        $this->checkFolder($folder, $this->feed->collection());

        return $this->listing($request, array_map(static fn (array $file): array => [(int) filesize($file['path']), $file['modified']], $this->feed->ebooks()));
    }

    // An ebook's file or cover, the cover as the JPEG the feed made of it (see GooglePlayFeed)
    #[Route('/{google_prefix}/ebooks/{folder}/{name}', name: 'book_google_ebook', methods: ['GET'], condition: self::CONDITION)]
    public function ebook(Request $request, string $folder, string $name): Response
    {
        if (null !== $denied = $this->guard($request)) {
            return $denied;
        }
        $this->checkFolder($folder, $this->feed->collection());

        $file = $this->feed->ebooks()[$name] ?? throw new NotFoundHttpException();
        if (!is_file($file['path'])) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($file['path']);
        $response->setLastModified($file['modified']);

        return $this->private($response);
    }

    // Null when the request carries the user and password the settings hold; a 404 while the feed is not set up, so its address says nothing; a 401 asking for them otherwise
    private function guard(Request $request): ?Response
    {
        $user = (string) $this->configService->get('book-google-user');
        $password = (string) $this->configService->get('book-google-password');
        if ('' === $this->feed->collection() || '' === $user || '' === $password) {
            throw new NotFoundHttpException();
        }

        if (hash_equals($user, (string) $request->getUser()) && hash_equals($password, (string) $request->getPassword())) {
            return null;
        }

        return new Response('', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Basic realm="Google Play Books", charset="UTF-8"']);
    }

    // Only the collection's own folder exists
    private function checkFolder(string $folder, string $expected): void
    {
        if ($folder !== $expected) {
            throw new NotFoundHttpException();
        }
    }

    // A folder as Apache prints it - a link, the date and the size per entry - which is what the crawler reads to find the files; a sub-folder has neither
    /** @param array<string, array{int, \DateTimeInterface}|null> $entries */
    private function listing(Request $request, array $entries): Response
    {
        $response = $this->render('@c975LBook/google/listing.html.twig', [
            'path' => $request->getPathInfo(),
            'entries' => $entries,
        ]);

        return $this->private($response);
    }

    // Never kept by a proxy: everything here sits behind a password
    private function private(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
