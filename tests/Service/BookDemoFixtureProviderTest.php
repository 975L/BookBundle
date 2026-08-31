<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookDemoFixtureProvider;
use c975L\BookBundle\Service\BookSampleCatalog;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookDemoFixtureProviderTest extends TestCase
{
    private const string IMAGE = 'showcase/photo.webp';
    private const string COVER = 'showcase/book/le-fil-rouge-1-1.webp';

    private string $projectDir;

    /** @var list<string> */
    private array $temporaryCopies = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/book-demo-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/showcase/book');
        file_put_contents($this->projectDir . '/public/' . self::IMAGE, 'image');
        file_put_contents($this->projectDir . '/public/' . self::COVER, 'cover');
    }

    // The copies handed to VichUploader live in the system's temp directory, where a real load has them moved away - nothing moves them here, so the test takes them back itself
    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->projectDir, ...$this->temporaryCopies]);
    }

    /**
     * @param list<string>                $images
     * @param array<string, list<string>> $keyed
     */
    private function createProvider(array $images = [self::IMAGE], array $keyed = []): BookDemoFixtureProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn($images);
        $registry->method('getImagesFor')->willReturnCallback(static fn (string $key): array => $keyed[$key] ?? []);

        return new BookDemoFixtureProvider(new BookSampleCatalog(), $translator, $registry, $this->projectDir);
    }

    /** @return list<object> */
    private function fixtures(BookDemoFixtureProvider $provider): array
    {
        $fixtures = iterator_to_array($provider->getDemoFixtures(), false);

        foreach ($fixtures as $entity) {
            if ($entity instanceof Book) {
                foreach ($entity->getMedias() as $media) {
                    $this->temporaryCopies[] = (string) $media->getFile()?->getPathname();
                }
            }
        }

        return $fixtures;
    }

    // Serie::$books is the inverse side and nothing cascades off it, so a book yielded before its serie would name one nothing has persisted yet
    public function testTheSeriesComeBeforeTheirBooks(): void
    {
        $seen = [];

        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Serie) {
                $seen[] = $entity->getSlug();

                continue;
            }

            if ($entity instanceof Book) {
                $this->assertContains($entity->getSerie()?->getSlug(), $seen, (string) $entity->getSlug());
            }
        }

        $this->assertCount(2, $seen);
    }

    // The relation is the owning side and nothing cascades off it, so a book or a serie yielded before the person it credits would name a row nothing has persisted yet
    public function testThePeopleComeBeforeAnythingCreditingThem(): void
    {
        $seen = [];

        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Contributor) {
                $seen[] = $entity->getSlug();

                continue;
            }

            if ($entity instanceof Book || $entity instanceof Serie) {
                $this->assertContains($entity->getAuthor()?->getSlug(), $seen, (string) $entity->getSlug());
            }
        }

        $this->assertSame(['camille-ferrand', 'noe-berthier'], $seen);
    }

    // The one illustrated book credits the second person, so the line a book's page prints for an illustrator has something to print
    public function testTheIllustratedBookCreditsTheOtherPerson(): void
    {
        $illustrated = array_filter(
            $this->fixtures($this->createProvider()),
            static fn (object $e): bool => $e instanceof Book && null !== $e->getIllustrator(),
        );

        $this->assertCount(1, $illustrated);
        $this->assertSame('noe-berthier', reset($illustrated)->getIllustrator()?->getSlug());
    }

    // The "to be published" rail reads a book with no date: without one, the block has nothing to list
    public function testOneBookIsStillToBePublished(): void
    {
        $books = array_filter($this->fixtures($this->createProvider()), static fn (object $e): bool => $e instanceof Book);

        $this->assertCount(4, $books);

        $toBePublished = array_filter($books, static fn (Book $book): bool => null === $book->getPublished());

        $this->assertCount(1, $toBePublished);
    }

    // Every other date is written down, so a demo reloaded between two takes of the same recorded sequence reads the same back
    public function testThePublishedBooksCarryAFrozenDateInThePast(): void
    {
        $now = new \DateTime();

        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Book && null !== $entity->getPublished()) {
                $this->assertLessThan($now, $entity->getPublished(), (string) $entity->getSlug());
            }
        }
    }

    // A site declaring its own cover for one book gets that one, and the generic pool never stands in for it
    public function testADeclaredCoverIsPreferredToThePool(): void
    {
        $provider = $this->createProvider([self::IMAGE], ['book/le-fil-rouge-1' => [self::COVER]]);

        foreach ($this->fixtures($provider) as $entity) {
            if ($entity instanceof Book && 'le-fil-rouge-1' === $entity->getSlug()) {
                $media = $entity->getMedias()->first();

                $this->assertSame('cover', $media->getKind());
                $this->assertStringContainsString('le-fil-rouge-1-1.webp', (string) $media->getFile()?->getPathname());

                return;
            }
        }

        $this->fail('no book "le-fil-rouge-1"');
    }

    // Three pictures are three different things on a book's page - its cover, its fourth cover, the backdrop it opens on - and a fourth has nothing left to be
    public function testTheDeclaredPicturesTakeTheThreeKindsInOrder(): void
    {
        $declared = [];

        foreach (['a', 'b', 'c', 'd'] as $index => $name) {
            $path = 'showcase/book/le-fil-rouge-1-' . ($index + 1) . '-' . $name . '.webp';
            file_put_contents($this->projectDir . '/public/' . $path, 'image');
            $declared[] = $path;
        }

        $provider = $this->createProvider([self::IMAGE], ['book/le-fil-rouge-1' => $declared]);

        foreach ($this->fixtures($provider) as $entity) {
            if ($entity instanceof Book && 'le-fil-rouge-1' === $entity->getSlug()) {
                $kinds = array_map(static fn (\c975L\BookBundle\Entity\Media $media): ?string => $media->getKind(), $entity->getMedias()->toArray());

                $this->assertSame(['cover', 'cover_back', 'background'], array_values($kinds));

                return;
            }
        }

        $this->fail('no book "le-fil-rouge-1"');
    }

    // A catalog with no picture to show is still a catalog: the card falls back on the bundle's own "no-cover.webp"
    public function testWithoutAPlaceholderTheBooksAreStillYielded(): void
    {
        $books = array_filter($this->fixtures($this->createProvider([])), static fn (object $e): bool => $e instanceof Book);

        $this->assertCount(4, $books);

        foreach ($books as $book) {
            $this->assertCount(0, $book->getMedias(), (string) $book->getSlug());
        }
    }
}
