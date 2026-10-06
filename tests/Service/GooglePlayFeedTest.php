<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookOnixBuilder;
use c975L\BookBundle\Service\GooglePlayFeed;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Filesystem\Filesystem;

// What the crawler finds: the ONIX named after the publisher and the catalog's last change, the ebooks' files named after their ISBN - and nothing for a printed edition or a file missing on disk
class GooglePlayFeedTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/google-play-feed-' . uniqid();
        mkdir($this->projectDir . '/private/medias/book/editions', 0o777, true);
        file_put_contents($this->projectDir . '/private/medias/book/editions/loup-digital.epub', 'epub');
        file_put_contents($this->projectDir . '/private/medias/book/editions/loup-digital.pdf', 'pdf');
        mkdir($this->projectDir . '/public/medias/book', 0o777, true);
        $image = imagecreatetruecolor(4, 6);
        imagepng($image, $this->projectDir . '/public/medias/book/loup.png');
        mkdir($this->projectDir . '/cache');
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->projectDir . '/private/medias/book/editions/*') ?: []);
        rmdir($this->projectDir . '/private/medias/book/editions');
        rmdir($this->projectDir . '/private/medias/book');
        rmdir($this->projectDir . '/private/medias');
        rmdir($this->projectDir . '/private');
        new Filesystem()->remove([$this->projectDir . '/public', $this->projectDir . '/cache']);
        rmdir($this->projectDir);
    }

    public function testTheOnixIsNamedAfterThePublisherAndTheLastChange(): void
    {
        $onix = $this->feed()->onix('https://example.org');

        $this->assertSame('EditionsTest_20260301.xml', $onix['name']);
        $this->assertSame('2026-03-01', $onix['modified']->format('Y-m-d'));
    }

    public function testTheCollectionKeepsLettersAndDigitsOnly(): void
    {
        $this->assertSame('337R84F', $this->feed()->collection());
    }

    // A book sent nowhere near Google does not rename the file, a new EPUB on an ebook sent does
    public function testOnlyTheBooksSentAndTheirFilesDateTheOnix(): void
    {
        $paper = new Book()->setTitle('Papier')->setModification(new \DateTime('2026-06-01'));
        $paper->addEdition(new BookEdition()->setKind('paper')->setIsbn('9782488750059')->setChannels(['google']));
        $book = $this->book();
        $book->getEditions()->first()->getFileOf(BookEditionFileKind::Epub)->setUpdatedAt(new \DateTimeImmutable('2026-04-01'));

        $this->assertSame('2026-04-01', $this->feed([$book, $paper])->onix('https://example.org')['modified']->format('Y-m-d'));
    }

    // Neither a booklet, nor a file missing on disk, nor an edition not ticked "Google", nor a printed one
    public function testOnlyTheEbooksFilesOnDiskAreListed(): void
    {
        $this->assertSame(['9782488750011.epub'], array_keys($this->feed()->ebooks()));
    }

    // Google takes no PNG nor WebP: the cover is handed over as a JPEG made once in the cache directory
    public function testACoverInAnotherFormatIsServedAsAJpeg(): void
    {
        $book = $this->book()->addMedia(new BookMedia()->setKind('cover')->setName('medias/book/loup.png'));

        $cover = $this->feed([$book])->ebooks()['9782488750011_frontcover.jpg'];

        $this->assertSame($this->projectDir . '/cache/book-google/9782488750011_frontcover.jpg', $cover['path']);
        $this->assertSame('image/jpeg', mime_content_type($cover['path']));
    }

    /** @param list<Book>|null $books */
    private function feed(?array $books = null): GooglePlayFeed
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findAllForOnix')->willReturn($books ?? [$this->book()]);

        $builder = $this->createStub(BookOnixBuilder::class);
        $builder->method('build')->willReturn('<ONIXMessage/>');

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): ?string => ['book-onix-publisher' => 'Éditions Test', 'book-google-collection' => ' 337R84F '][$key] ?? null);

        return new GooglePlayFeed($repository, $builder, $configService, new TagAwareAdapter(new ArrayAdapter()), $this->projectDir, $this->projectDir . '/cache');
    }

    private function book(): Book
    {
        $book = new Book()->setTitle('Le Loup')->setPublished(new \DateTime('2026-01-01'))->setModification(new \DateTime('2026-03-01'));
        $book->addEdition(new BookEdition()->setKind('digital')->setIsbn('9782488750011')->setChannels(['google'])
            ->setFileOf(BookEditionFileKind::Epub, self::file('loup-digital.epub'))
            ->setFileOf(BookEditionFileKind::Booklet, self::file('loup-digital.pdf')));
        $book->addEdition(new BookEdition()->setKind('digital')->setIsbn('9782488750035')->setChannels(['google'])->setFileOf(BookEditionFileKind::Epub, self::file('missing.epub')));
        $book->addEdition(new BookEdition()->setKind('digital')->setIsbn('9782488750042')->setFileOf(BookEditionFileKind::Epub, self::file('loup-digital.epub')));
        $book->addEdition(new BookEdition()->setKind('paper')->setIsbn('9782488750028')->setChannels(['google']));

        return $book;
    }

    // A file uploaded before the book's last change
    private static function file(string $name): BookEditionFile
    {
        return new BookEditionFile()->setName('medias/book/editions/' . $name)->setUpdatedAt(new \DateTimeImmutable('2026-02-01'));
    }
}
