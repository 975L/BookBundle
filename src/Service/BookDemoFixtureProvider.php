<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Enum\SerieKind;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The catalog a demo site is seeded with, from the very data the block showcase renders (see BookSampleCatalog) - persisted here, where the showcase only ever builds entities it never writes
class BookDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // What each declared picture of a book is, in the order declared - "-1" the cover, "-2" the fourth cover, "-3" the backdrop its page opens on: three kinds and not three covers, a page reading them one by one (see book/display.html.twig)
    private const array KINDS = ['cover', 'cover_back', 'background'];

    // The same three read off a serie: its cover, the emblem above its title, the picture its page opens on (see Serie::addCover(), addLogo() and addBackground(), the kind telling them apart)
    private const array SERIE_KINDS = ['cover', 'logo', 'background'];

    // The day both series were opened, written down like the rest of the dataset rather than taken from the clock
    private const string CREATION = '2024-05-14';

    public function __construct(
        private readonly BookSampleCatalog $catalog,
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // The medias ride the ORM cascade off Book, so a reload fires VichUploader's removal listener on each and takes the file off the disk with the row; the serie is the inverse side and cascades nothing, so each book is yielded on its own, the serie first for the books to name it. A catalog with no picture is still a catalog - the card falls back on "no-cover.webp" - so nothing here needs the site to declare a media
    public function getDemoFixtures(): iterable
    {
        // The two people the demo credits, yielded before anything naming them: a book pointing at a row not yet recorded has nothing to point at
        $author = $this->contributor(BookSampleCatalog::AUTHOR, 'camille-ferrand', 1);
        $illustrator = $this->contributor(BookSampleCatalog::ILLUSTRATOR, 'noe-berthier', 2);

        yield $author;
        yield $illustrator;

        $series = [];

        foreach ($this->catalog->getSeries() as $position => $spec) {
            $serie = new Serie()
                ->setSlug($spec['slug'])
                ->setTitle($this->trans($spec['title']))
                ->setSummary($this->trans($spec['summary']))
                ->setKind(SerieKind::Book->value)
                ->setLanguage($this->language())
                ->setAuthor($author)
                ->setPosition($position + 1)
                ->setCreation(new \DateTime(self::CREATION))
                ->setModification(new \DateTime(self::CREATION));

            foreach ($this->pictures('serie', $spec['slug']) as $index => $image) {
                // Past the three kinds there is nothing left for a picture to be, so it is left aside rather than filed as a second backdrop
                if (!isset(self::SERIE_KINDS[$index])) {
                    break;
                }

                $media = $this->serieMedia($image, self::SERIE_KINDS[$index], $index + 1);

                // A picture the site declares and no longer has on disk: the serie is seeded without it rather than not at all
                if (null !== $media) {
                    $serie->addMedia($media);
                }
            }

            $series[$spec['slug']] = $serie;

            yield $serie;
        }

        foreach ($this->catalog->getBooks() as $spec) {
            yield $this->book($spec, $series[$spec['serie']], $author, $illustrator);
        }
    }

    // A made-up person, dated like the rest of the dataset rather than from the clock. No portrait: a demo catalog reads whole without one, and the card falls back on the bundle's "no-cover.webp" as a book's does
    private function contributor(string $name, string $slug, int $position): Contributor
    {
        return new Contributor()
            ->setName($name)
            ->setSlug($slug)
            ->setPosition($position)
            ->setCreation(new \DateTime(self::CREATION))
            ->setModification(new \DateTime(self::CREATION));
    }

    /**
     * @param array{slug: string, title: string, summary: string, serie: string, published: ?string, creation: string, number: int, illustrated: bool, age: string} $spec
     */
    private function book(array $spec, Serie $serie, Contributor $author, Contributor $illustrator): Book
    {
        $date = new \DateTime($spec['creation']);

        $book = new Book()
            ->setSlug($spec['slug'])
            ->setTitle($this->trans($spec['title']))
            ->setSummary($this->trans($spec['summary']))
            ->setAuthor($author)
            ->setLanguage($this->language())
            ->setSerie($serie)
            ->setNumber($spec['number'])
            ->setAge($spec['age'])
            ->setPublished(null === $spec['published'] ? null : new \DateTime($spec['published']))
            ->setCreation($date)
            ->setModification($date);

        // The one book credited to an illustrator, so the line a book's page prints for it has something to print
        if ($spec['illustrated']) {
            $book->setIllustrator($illustrator);
        }

        foreach ($this->pictures('book', $spec['slug']) as $position => $image) {
            // Past the three kinds there is nothing left for a picture to be, so it is left aside rather than filed as a second backdrop
            if (!isset(self::KINDS[$position])) {
                break;
            }

            $media = $this->media($image, self::KINDS[$position], $position + 1);

            // A picture the site declares and no longer has on disk: the book is seeded without it rather than not at all
            if (null !== $media) {
                $book->addMedia($media);
            }
        }

        return $book;
    }

    // The row's own pictures where the site declares them, keyed "book/<slug>" or "serie/<slug>" (see PlaceholderMediaProviderInterface), failing which one of the generic pool, rotated: a shelf of identical covers says less than a shelf of different ones
    /** @return list<string> */
    private function pictures(string $owner, string $slug): array
    {
        $declared = $this->placeholderMediaRegistry->getImagesFor($owner . '/' . $slug);

        if ([] !== $declared) {
            return $declared;
        }

        $pool = $this->placeholderMediaRegistry->getImages();

        if ([] === $pool) {
            return [];
        }

        // Read off the slug rather than off a counter, so the same book keeps the same picture whatever else is loaded beside it
        return [$pool[crc32($slug) % \count($pool)]];
    }

    // The kind is set before the media joins its serie, and both before anything is written: SerieMedia builds its stored path out of the two (see getVichMediaPath()), and the other way round lands in "cover-temp"
    private function serieMedia(string $image, string $kind, int $position): ?SerieMedia
    {
        $file = $this->temporaryCopy($image);

        if (null === $file) {
            return null;
        }

        $media = new SerieMedia();
        $media->setKind($kind);
        $media->setPosition($position);
        $media->setFile($file);

        return $media;
    }

    private function media(string $image, string $kind, int $position): ?BookMedia
    {
        $file = $this->temporaryCopy($image);

        if (null === $file) {
            return null;
        }

        $media = new BookMedia();
        $media->setKind($kind);
        $media->setPosition($position);
        $media->setFile($file);

        return $media;
    }

    // VichUploader moves the file it is handed, so it gets a copy - the declared picture is read by every other showcase and would be gone after the first load - and a ReplacingFile rather than a plain File, which UploadHandler::hasUploadedFile() ignores silently, writing the row with no file name
    private function temporaryCopy(string $publicPath): ?ReplacingFile
    {
        $source = $this->projectDir . '/public/' . $publicPath;

        if (!is_file($source)) {
            return null;
        }

        $target = sys_get_temp_dir() . '/c975l-demo-' . uniqid() . '-' . basename($publicPath);

        return copy($source, $target) ? new ReplacingFile($target, true, true, true) : null;
    }

    // The locale the made-up books are written in, a key like the rest so a Spanish demo holds Spanish books
    private function language(): string
    {
        return $this->trans(BookSampleCatalog::LANGUAGE_KEY);
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'book');
    }
}
