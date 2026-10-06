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
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Service\BookShopPublisher;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Contract\ProductCatalogWriterInterface;
use c975L\UiBundle\Model\CatalogProduct;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// A book's whole family of versions goes into the shop as one product, keyed by its latest: the files of the editions ticked "Shop" alone, an earlier version's named as such
class BookShopPublisherTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/book-shop-publisher-' . uniqid();
        mkdir($this->projectDir . '/private/medias', 0o777, true);
        file_put_contents($this->projectDir . '/private/medias/new.epub', 'new');
        file_put_contents($this->projectDir . '/private/medias/old.epub', 'old');
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->projectDir . '/private/medias/*') ?: []);
        rmdir($this->projectDir . '/private/medias');
        rmdir($this->projectDir . '/private');
        rmdir($this->projectDir);
    }

    public function testTheFamilyIsOneProductOfTheShopTickedFiles(): void
    {
        $written = [];
        $old = $this->book(1, 'old.epub', ['shop']);
        $new = $this->book(2, 'new.epub', ['shop']);
        $new->addEdition(new BookEdition()->setKind('audio')->setChannels([])->setFileOf(BookEditionFileKind::Audio, new BookEditionFile()->setName('medias/new.epub')));
        $old->setNewerVersion($new);

        $this->publisher($written)->publish($old);

        $product = $written[0];
        $this->assertSame('book-2', $product->key);
        $this->assertSame(['book-2-digital-epub', 'book-1-digital-epub'], array_map(static fn ($item) => $item->key, $product->items));
        $this->assertSame(['label.edition_file_epub', 'label.edition_file_earlier_version'], array_map(static fn ($item) => $item->title, $product->items));
        $this->assertSame(299, $product->items[0]->price);
    }

    // An EPUB read aloud says so in its name and in its text, which the buyer reads before choosing
    public function testAReadAloudEpubSaysSo(): void
    {
        $written = [];
        $book = $this->book(1, 'new.epub', ['shop']);
        $book->getEditions()->first()->getFileOf(BookEditionFileKind::Epub)?->setReadAloud(true);

        $this->publisher($written)->publish($book);

        $this->assertSame('label.edition_file_epub_read_aloud', $written[0]->items[0]->title);
        $this->assertSame(['fr' => 'label.edition_file_read_aloud_description@fr', 'en' => 'label.edition_file_read_aloud_description@en'], $written[0]->items[0]->description);
    }

    // An edition ticked "Shop" whose files are not on disk yet is no reason to set the product's items aside: nothing is written
    public function testAShopEditionWithoutFilesWritesNothing(): void
    {
        $written = [];

        $this->publisher($written)->publish($this->book(1, 'missing.epub', ['shop']));

        $this->assertSame([], $written);
    }

    // Nothing ticked "Shop" any more is what sets the items aside: the product is written empty
    public function testABookNoLongerSoldIsWrittenEmpty(): void
    {
        $written = [];

        $this->publisher($written)->publish($this->book(1, 'new.epub', []));

        $this->assertSame([], $written[0]->items);
    }

    // The product takes the digital edition's front cover, an audiobook's square one coming first in the list notwithstanding
    public function testTheCoverIsTheDigitalEditionsOne(): void
    {
        $written = [];
        file_put_contents($this->projectDir . '/private/medias/square.jpg', 'square');
        file_put_contents($this->projectDir . '/private/medias/portrait.jpg', 'portrait');
        $book = new Book()->setTitle('Le Loup');
        new \ReflectionProperty(Book::class, 'id')->setValue($book, 1);
        $book->addEdition(new BookEdition()->setKind('audio')->setFileOf(BookEditionFileKind::CoverFront, new BookEditionFile()->setName('medias/square.jpg')));
        $book->addEdition(new BookEdition()->setKind('digital')->setFileOf(BookEditionFileKind::CoverFront, new BookEditionFile()->setName('medias/portrait.jpg')));

        $this->publisher($written)->publish($book);

        $this->assertStringEndsWith('/medias/portrait.jpg', (string) $written[0]->coverPath);
    }

    // A file of a kind the bundle no longer knows is left out like a missing one, rather than failing the whole product
    public function testAFileOfAnUnknownKindIsLeftOut(): void
    {
        $written = [];
        $book = $this->book(1, 'new.epub', ['shop']);
        $book->getEditions()->first()->getFileOf(BookEditionFileKind::Epub)?->setKind('obsolete');

        $this->publisher($written)->publish($book);

        $this->assertSame([], $written);
    }

    public function testNothingIsWrittenWithoutAShop(): void
    {
        $publisher = new BookShopPublisher($this->createStub(TranslatorInterface::class), new SiteLocales(['fr', 'en'], 'fr'), $this->projectDir);

        $this->assertFalse($publisher->isAvailable());
        $publisher->publish($this->book(1, 'new.epub', ['shop']));
    }

    /** @param list<string> $channels */
    private function book(int $id, string $file, array $channels): Book
    {
        $book = new Book()->setTitle('Le Loup');
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);
        $book->addEdition(new BookEdition()->setKind('digital')->setChannels($channels)
            ->setFileOf(BookEditionFileKind::Epub, new BookEditionFile()->setName('medias/' . $file)->setPrice(299)));

        return $book;
    }

    /** @param list<CatalogProduct> $written */
    private function publisher(array &$written): BookShopPublisher
    {
        $writer = $this->createStub(ProductCatalogWriterInterface::class);
        $writer->method('write')->willReturnCallback(static function (CatalogProduct $product) use (&$written): void {
            $written[] = $product;
        });
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => null === $locale ? $id : $id . '@' . $locale);

        return new BookShopPublisher($translator, new SiteLocales(['fr', 'en'], 'fr'), $this->projectDir, $writer);
    }
}
