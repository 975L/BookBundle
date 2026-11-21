<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Repository;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookReleaseAlert;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\BookReleaseAlertRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Who the nightly run writes to, read on a real database: a book or a planche out, never one still to come nor one the site no longer shows
#[RequiresPhpExtension('pdo_sqlite')]
class BookReleaseAlertRepositoryTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../../src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setUseSchemaEditorApi(true);
        // The site's user is the application's own entity: any mapped one stands in for it
        $eventManager = new EventManager();
        $resolver = new ResolveTargetEntityListener();
        $resolver->addResolveTargetEntity(UserInterface::class, Contributor::class, []);
        $eventManager->addEventSubscriber($resolver);
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config, $eventManager);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function repository(): BookReleaseAlertRepository
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        return new BookReleaseAlertRepository($registry);
    }

    private function serie(string $slug, bool $hidden = false): Serie
    {
        $serie = new Serie()->setTitle($slug)->setSlug($slug)->setHidden($hidden)
            ->setCreation(new \DateTimeImmutable())
            ->setModification(new \DateTimeImmutable())
        ;
        $this->em->persist($serie);

        return $serie;
    }

    private function strip(string $slug, string $published, ?Serie $serie = null): Strip
    {
        $serie ??= $this->serie('serie-' . $slug);
        $strip = new Strip()
            ->setTitle($slug)
            ->setSlug($slug)
            ->setPublished(new \DateTimeImmutable($published))
            ->setSerie($serie)
            ->setCreation(new \DateTimeImmutable())
            ->setModification(new \DateTimeImmutable())
        ;
        $this->em->persist($strip);

        return $strip;
    }

    private function alert(Book | Strip $target): BookReleaseAlert
    {
        $alert = $target instanceof Strip ? new BookReleaseAlert()->setStrip($target) : new BookReleaseAlert()->setBook($target);
        $alert->setEmail('waiting-' . $target->getSlug() . '@example.org')->setLocale('fr');
        $this->em->persist($alert);

        return $alert;
    }

    // Only what came out, on either side of the list, and nothing the site sets aside
    public function testOnlyWhatCameOutAndIsStillShownIsReleased(): void
    {
        $hiddenSerie = $this->serie('item-1', true);

        $book = new Book()->setTitle('book-out')->setSlug('book-out')->setPublished(new \DateTimeImmutable('-1 day'))
            ->setCreation(new \DateTimeImmutable())
            ->setModification(new \DateTimeImmutable())
        ;
        $this->em->persist($book);
        $bookOut = $this->alert($book);
        $out = $this->alert($this->strip('out', '-1 day'));
        $this->alert($this->strip('ahead', '+3 days'));
        $this->alert($this->strip('hidden', '-1 day')->setHidden(true));
        $this->alert($this->strip('trashed', '-1 day')->setIsDeleted(true));
        $this->alert($this->strip('of-a-hidden-serie', '-1 day', $hiddenSerie));
        $this->em->flush();

        $this->assertSame([$bookOut, $out], $this->repository()->findReleased(50));
    }

    // The same address waits on two planches, the unique constraint being per planche
    public function testTheSameAddressCanWaitOnTwoPlanches(): void
    {
        $first = $this->alert($this->strip('first', '+3 days'));
        $second = $this->alert($this->strip('second', '+4 days'));
        $second->setEmail($first->getEmail());
        $this->em->flush();

        $this->assertSame($second, $this->repository()->findOneByStripAndEmail($second->getStrip(), $first->getEmail()));
    }
}
