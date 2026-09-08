<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\ContributorCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\MediaRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Management\AbstractDeclaredFilesHealthCheckProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The files this bundle's own rows declare: the covers, videos, press and marketing files of a book, plus a serie's, a strip's and a contributor's own pictures. Everything the check does is in the parent (see UiBundle's AbstractDeclaredFilesHealthCheckProvider), this only names what to look for
class BookFilesHealthCheckProvider extends AbstractDeclaredFilesHealthCheckProvider
{
    // Named here rather than restated as a literal wherever a row of this kind is picked out
    public const string KIND = 'files-book';

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        ConfigServiceInterface $configService,
        TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        parent::__construct($configService, $translator, $projectDir);
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    protected function declaredFiles(): iterable
    {
        foreach ($this->mediaRepository->findWithFilename() as $media) {
            [$controller, $owner] = $this->ownerOf($media);

            yield [
                'filename' => (string) $media->getName(),
                'label' => null === $owner ? (string) $media->getName() : (string) $owner,
                'editUrl' => $this->editUrl($controller, $owner?->getId()),
                'directory' => $media instanceof VichPrivateFileInterface ? $media->getPrivateDirectory() : self::PUBLIC_DIRECTORY,
            ];
        }
    }

    // Four owners behind one table (see Media's single-table inheritance): each is edited from its own screen, and a book's four kinds of file all hang off the book itself
    private function ownerOf(Media $media): array
    {
        return match (true) {
            $media instanceof SerieMedia => [SerieCrudController::class, $media->getSerie()],
            $media instanceof StripMedia => [StripCrudController::class, $media->getStrip()],
            $media instanceof ContributorMedia => [ContributorCrudController::class, $media->getContributor()],
            default => [BookCrudController::class, method_exists($media, 'getBook') ? $media->getBook() : null],
        };
    }

    private function editUrl(string $controller, ?int $id): ?string
    {
        return null === $id ? null : $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction(Action::EDIT)
            ->setEntityId($id)
            ->generateUrl()
        ;
    }
}
