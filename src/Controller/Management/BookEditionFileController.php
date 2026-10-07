<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management;

use c975L\BookBundle\Entity\BookEditionFile;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use c975L\UiBundle\Storage\PrivateDirectory;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Opens a file an edition holds to whoever edits the catalog: it lives outside public/, where the web server cannot reach it, and the edition's form links each slot here - the file opened in place, a cover shown as its preview (see BookEditionFileType)
class BookEditionFileController extends AbstractController
{
    public const string ROUTE = 'management_book_edition_file';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly PrivateFileResponseFactoryInterface $privateFileResponseFactory,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // The id in the query rather than in the path, like the other admin routes of the bundle
    #[AdminRoute(
        path: '/book/edition-file',
        name: 'book_edition_file',
        options: ['methods' => ['GET']]
    )]
    public function open(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $file = $this->entityManager->getRepository(BookEditionFile::class)->find($request->query->getInt('id'));
        if (!$file instanceof BookEditionFile || null === $file->getName()) {
            throw $this->createNotFoundException();
        }

        $response = $this->privateFileResponseFactory->createInlineResponse(
            $this->projectDir . '/' . PrivateDirectory::resolve($file) . '/' . $file->getName(),
            basename($file->getName())
        );

        return $response ?? throw $this->createNotFoundException();
    }
}
