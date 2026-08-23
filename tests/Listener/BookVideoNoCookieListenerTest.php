<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Listener;

use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Listener\BookVideoNoCookieListener;
use c975L\UiBundle\Twig\VideoExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

// A video's address is brought back once to its platform's canonical form, before reaching the database - the template rendering it never has to care
class BookVideoNoCookieListenerTest extends TestCase
{
    // Whatever form was pasted - the address bar, a share link - what is stored is the player's address, the only one the site's security policy allows
    public function testWhateverWasPastedIsStoredAsThePlayersOwnAddress(): void
    {
        $video = new BookVideo()->setYoutubeUrl('https://www.youtube.com/watch?v=PbSR03g31vk');

        $this->listener()->prePersist(new PrePersistEventArgs($video, $this->createStub(ObjectManager::class)));

        $this->assertSame('https://www.youtube-nocookie.com/embed/PbSR03g31vk', $video->getYoutubeUrl());
    }

    // Unticked, the box leaves the address as it stands: an editor who made the choice upstream is not overruled
    public function testAnEditorHavingUncheckedTheBoxKeepsTheAddressTheyTyped(): void
    {
        $video = new BookVideo()->setYoutubeUrl('https://www.youtube.com/watch?v=PbSR03g31vk');
        $video->setNoCookie(false);

        $this->listener()->prePersist(new PrePersistEventArgs($video, $this->createStub(ObjectManager::class)));

        $this->assertSame('https://www.youtube.com/watch?v=PbSR03g31vk', $video->getYoutubeUrl());
    }

    // Une vidéo téléversée ne porte aucune adresse : rien à réécrire, et surtout rien à écrire
    public function testAnUploadedVideoIsLeftAlone(): void
    {
        $video = new BookVideo()->setName('trailer.mp4');

        $this->listener()->prePersist(new PrePersistEventArgs($video, $this->createStub(ObjectManager::class)));

        $this->assertNull($video->getYoutubeUrl());
    }

    // Doctrine has already taken the changeset on update: without the recomputation the rewritten address would be ignored
    public function testAnUpdateAsksDoctrineToLookAtTheRowAgain(): void
    {
        $video = new BookVideo()->setYoutubeUrl('https://youtu.be/PbSR03g31vk');

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())->method('recomputeSingleEntityChangeSet');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($this->createStub(ClassMetadata::class));

        $changeSet = [];
        $this->listener()->preUpdate(new PreUpdateEventArgs($video, $entityManager, $changeSet));

        $this->assertSame('https://www.youtube-nocookie.com/embed/PbSR03g31vk', $video->getYoutubeUrl());
    }

    // An already canonical address is not rewritten, so nothing is recomputed: an update changing nothing has no reason to ask for one
    public function testAnAddressAlreadyCanonicalAsksForNothing(): void
    {
        $video = new BookVideo()->setYoutubeUrl('https://www.youtube-nocookie.com/embed/PbSR03g31vk');

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->never())->method('recomputeSingleEntityChangeSet');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $changeSet = [];
        $this->listener()->preUpdate(new PreUpdateEventArgs($video, $entityManager, $changeSet));

        $this->assertSame('https://www.youtube-nocookie.com/embed/PbSR03g31vk', $video->getYoutubeUrl());
    }

    private function listener(): BookVideoNoCookieListener
    {
        return new BookVideoNoCookieListener(new VideoExtension());
    }
}
