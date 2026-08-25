<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\StripCardCommand;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// What the command makes of a directory of captures: the media a planche already carries is handed the new file, Vich doing the naming and the deleting of the one it replaces (see StripCardCommand)
class StripCardCommandTest extends TestCase
{
    private string $dir;

    /** @var list<object> */
    private array $persisted = [];

    private bool $flushed = false;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/book-strip-card-' . uniqid();
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    // The whole point of the command: the planche keeps its media, that media gets the capture
    public function testTheCaptureIsHandedToTheMediaThePlancheAlreadyCarries(): void
    {
        $this->capture('replique-160');
        $strip = $this->strip('replique-160', new StripMedia()->setKind('extract'));

        $tester = $this->attach([$strip]);
        $media = $strip->getMedias()->first();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertInstanceOf(StripMedia::class, $media);
        $this->assertInstanceOf(ReplacingFile::class, $media->getFile());
        $this->assertSame($this->dir . '/replique-160.webp', $media->getFile()->getPathname());
        $this->assertSame([], $this->persisted, 'A media already there is replaced, not doubled.');
        $this->assertTrue($this->flushed);
    }

    // The role written on the media is what its own filename is built from: it says the card, the reply drawn as a page being gone
    public function testTheMediaSaysItNowHoldsTheCard(): void
    {
        $this->capture('replique-160');
        $strip = $this->strip('replique-160', new StripMedia()->setKind('extract'));

        $this->attach([$strip]);
        $media = $strip->getMedias()->first();

        $this->assertInstanceOf(StripMedia::class, $media);
        $this->assertSame('card', $media->getKind());
    }

    // A planche carrying none is given one rather than left without a share image
    public function testAPlancheWithoutAMediaIsGivenOne(): void
    {
        $this->capture('replique-160');
        $strip = $this->strip('replique-160');

        $this->attach([$strip]);

        $this->assertCount(1, $this->persisted);
        $this->assertCount(1, $strip->getMedias());
    }

    // A planche the capture skipped keeps what it has: losing its media would be worse than not replacing it
    public function testAPlancheWithoutACaptureIsLeftAsItIs(): void
    {
        $strip = $this->strip('replique-160', new StripMedia()->setKind('extract'));

        $tester = $this->attach([$strip]);
        $media = $strip->getMedias()->first();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertInstanceOf(StripMedia::class, $media);
        $this->assertNull($media->getFile());
        $this->assertStringContainsString('No capture found for 1 planche(s): replique-160', $this->squash($tester->getDisplay()));
    }

    // Said, and nothing written: the run is there to be read before it is trusted
    public function testADryRunWritesNothing(): void
    {
        $this->capture('replique-160');
        $strip = $this->strip('replique-160', new StripMedia()->setKind('extract'));

        $tester = $this->attach([$strip], ['--dry-run' => true]);
        $media = $strip->getMedias()->first();

        $this->assertInstanceOf(StripMedia::class, $media);
        $this->assertNull($media->getFile());
        $this->assertFalse($this->flushed);
        $this->assertStringContainsString('1 planche(s) would be replaced', $this->squash($tester->getDisplay()));
    }

    // A directory typed wrong would report every planche as missing its capture, which says nothing about what went wrong
    public function testADirectoryThatIsNotThereStopsTheRun(): void
    {
        $tester = $this->attach([], ['--dir' => $this->dir . '/elsewhere']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Option --dir must name an existing directory', $this->squash($tester->getDisplay()));
    }

    // The planches are taken from a serie, which has to be there to take them from
    public function testAnUnknownSerieStopsTheRun(): void
    {
        $tester = $this->attach([], ['--serie' => 'nowhere'], serieFound: false);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No serie carries the slug "nowhere"', $this->squash($tester->getDisplay()));
    }

    /**
     * @param list<Strip>          $strips
     * @param array<string, mixed> $options
     */
    private function attach(array $strips, array $options = [], bool $serieFound = true): CommandTester
    {
        $serieRepository = $this->createStub(SerieRepository::class);
        $serieRepository->method('findOneBy')->willReturn($serieFound ? new Serie()->setTitle('Répliques') : null);

        $stripRepository = $this->createStub(StripRepository::class);
        $stripRepository->method('findAllPublished')->willReturn($strips);
        $stripRepository->method('findAllPublishedBySerie')->willReturn($strips);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persisted[] = $entity;
            })
        ;
        $entityManager
            ->method('flush')
            ->willReturnCallback(function (): void {
                $this->flushed = true;
            })
        ;

        // A null option stands for one left out of the command line, which is not the same as one given empty
        $given = array_filter(array_merge(['--dir' => $this->dir], $options), static fn (mixed $value): bool => null !== $value);

        $tester = new CommandTester(new StripCardCommand($entityManager, $serieRepository, $stripRepository));
        $tester->execute($given);

        return $tester;
    }

    private function capture(string $slug): void
    {
        file_put_contents($this->dir . '/' . $slug . '.webp', 'x');
    }

    private function strip(string $slug, ?StripMedia $media = null): Strip
    {
        $strip = new Strip()->setSlug($slug)->setTitle($slug);

        if (null !== $media) {
            $strip->addMedia($media);
        }

        return $strip;
    }

    // The console wraps its report to the width of the terminal, which says nothing about what it reports
    private function squash(string $display): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $display));
    }
}
