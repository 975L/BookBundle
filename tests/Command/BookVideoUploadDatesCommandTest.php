<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookVideoUploadDatesCommand;
use c975L\BookBundle\Entity\BookVideo;
use c975L\UiBundle\Service\VideoUploadDateFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// The videos of the books dated once from their platform, as UiBundle does for its video blocks
class BookVideoUploadDatesCommandTest extends TestCase
{
    // The rows the repository hands as having no date, and the platform's answer per address
    private function createTester(array $videos, array $dates, EntityManagerInterface $em): CommandTester
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($videos);
        $em->method('getRepository')->willReturn($repository);

        $fetcher = $this->createStub(VideoUploadDateFetcher::class);
        $fetcher->method('fetch')->willReturnCallback(static fn (?string $url): ?string => $dates[$url] ?? null);

        return new CommandTester(new BookVideoUploadDatesCommand($em, $fetcher));
    }

    public function testExecuteFillsTheDateAskedFromThePlatform(): void
    {
        $video = new BookVideo()->setYoutubeUrl('https://youtu.be/abc');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $tester = $this->createTester([$video], ['https://youtu.be/abc' => '2024-05-12'], $em);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('2024-05-12', $video->getUploadDate()?->format('Y-m-d'));
        $this->assertStringContainsString('1 vidéo(s) datée(s) sur 1', $tester->getDisplay());
    }

    // An uploaded file has no platform to ask
    public function testExecuteSkipsAnUploadedFile(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $tester = $this->createTester([new BookVideo()->setName('videos/tome-1.mp4')], [], $em);
        $tester->execute([]);

        $this->assertStringContainsString('0 vidéo(s) datée(s) sur 0', $tester->getDisplay());
    }

    // A platform giving no date leaves the row untouched and names it, for the editor to type the date
    public function testExecuteReportsAVideoItCouldNotDate(): void
    {
        $video = new BookVideo()->setYoutubeUrl('https://www.tiktok.com/@a/video/1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $tester = $this->createTester([$video], [], $em);
        $tester->execute([]);

        $this->assertNull($video->getUploadDate());
        $this->assertStringContainsString('https://www.tiktok.com/@a/video/1', $tester->getDisplay());
    }
}
