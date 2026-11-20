<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\StripPublishCommand;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

// A planche written from a manifest: created in its serie or brought up to date by its number, visible from its day on, refused whole on a file it cannot find
class StripPublishCommandTest extends TestCase
{
    private string $directory;

    private Serie $serie;

    private bool $flushed = false;

    /** @var list<string> */
    private array $takenSlugs = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/book-strip-publish-' . uniqid();
        new Filesystem()->dumpFile($this->directory . '/011-page1.jpg', 'page');
        new Filesystem()->dumpFile($this->directory . '/011-Cases-page1.jpg', 'panel');
        $this->serie = new Serie()->setSlug('catalog');
        $this->serie->addCharacter(new Character()->setName('Item 2')->setSlug('item-2'));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    // A new planche takes the manifest's texts, day, characters and files, shown from its day and its address printed last
    public function testANewPlancheIsCreatedVisibleOnItsDay(): void
    {
        $tester = $this->publish(['summary' => '<p>item-3</p>', 'characters' => ['Item 2'], 'page' => '011-page1.jpg', 'panels' => ['011-Cases-page1.jpg']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertTrue($this->flushed);
        $strip = $this->serie->getStrips()->first();
        $this->assertInstanceOf(Strip::class, $strip);
        $this->assertSame('catalog-item-1', $strip->getSlug());
        $this->assertSame('2026-12-03', $strip->getPublished()?->format('Y-m-d'));
        $this->assertFalse($strip->isHidden());
        $this->assertSame('<p>item-3</p>', $strip->getSummary());
        $this->assertSame(['item-2'], array_column($strip->getCharactersList(), 'slug'));
        $this->assertSame(['page', 'case'], array_map(static fn (StripMedia $media): ?string => $media->getKind(), $strip->getMedias()->toArray()));
        $this->assertStringContainsString('https://example.com/item-1', $tester->getDisplay());
    }

    // The planche of that number is brought up to date, its slug and medias kept when the manifest names no file
    public function testTheSamePlancheIsUpdated(): void
    {
        $strip = new Strip()->setNumber(11)->setTitle('item-0')->setSlug('item-0')->setHidden(true);
        $this->serie->addStrip($strip);

        $this->assertSame(Command::SUCCESS, $this->publish([])->getStatusCode());
        $this->assertCount(1, $this->serie->getStrips());
        $this->assertSame('item-1', $strip->getTitle());
        $this->assertSame('item-0', $strip->getSlug());
        $this->assertFalse($strip->isHidden());
    }

    // A new planche whose slug another one already carries gets the next free one
    public function testANewPlancheSlugIsFreedOfACollision(): void
    {
        $this->takenSlugs = ['catalog-item-1'];

        $this->assertSame(Command::SUCCESS, $this->publish([])->getStatusCode());
        $this->assertSame('catalog-item-1-2', $this->serie->getStrips()->first()->getSlug());
    }

    // A character the serie does not hold is left out, never created
    public function testAnUnknownCharacterIsLeftOut(): void
    {
        $tester = $this->publish(['characters' => ['Item 2', 'item-9']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('item-9', $tester->getDisplay());
        $this->assertCount(1, $this->serie->getCharacters());
        $this->assertSame(['item-2'], array_column($this->serie->getStrips()->first()->getCharactersList(), 'slug'));
    }

    // A file that is not there or a malformed day refuses the manifest, nothing written
    public function testAMissingFileOrDayWritesNothing(): void
    {
        $this->assertSame(Command::FAILURE, $this->publish(['page' => '../item-9.jpg'])->getStatusCode());
        $this->assertSame(Command::FAILURE, $this->publish(['published' => '03/12/2026'])->getStatusCode());
        $this->assertFalse($this->flushed);
        $this->assertCount(0, $this->serie->getStrips());
    }

    // A planche of that number in the trash refuses the manifest, left as it was
    public function testAPlancheInTheTrashWritesNothing(): void
    {
        $strip = new Strip()->setNumber(11)->setTitle('item-0')->setSlug('item-0')->setIsDeleted(true);
        $this->serie->addStrip($strip);

        $this->assertSame(Command::FAILURE, $this->publish([])->getStatusCode());
        $this->assertFalse($this->flushed);
        $this->assertSame('item-0', $strip->getTitle());
    }

    // The command run on a manifest of planche 11 of the serie, the given keys laid over its own
    /** @param array<string, mixed> $manifest */
    private function publish(array $manifest): CommandTester
    {
        new Filesystem()->dumpFile($this->directory . '/strip.json', (string) json_encode([...['serie' => 'catalog', 'number' => 11, 'title' => 'item-1', 'published' => '2026-12-03'], ...$manifest]));
        $series = $this->createStub(SerieRepository::class);
        $series->method('findOneBy')->willReturn($this->serie);
        $strips = $this->createStub(StripRepository::class);
        $strips->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Strip => \in_array($criteria['slug'], $this->takenSlugs, true) ? new Strip() : null);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(function (): void {
            $this->flushed = true;
        });
        $urls = $this->createStub(BookPublicUrlResolver::class);
        $urls->method('resolve')->willReturn('https://example.com/item-1');
        $tester = new CommandTester(new StripPublishCommand($em, $series, $strips, $urls));
        $tester->execute(['manifest' => $this->directory . '/strip.json']);

        return $tester;
    }
}
