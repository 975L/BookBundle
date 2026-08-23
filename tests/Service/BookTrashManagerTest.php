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
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookTrashManager;
use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

// What a deletion does to the url a row used to answer - a 410 while it sits in the trash, a permanent one once it is removed for good, a 301 when it is merely renamed
class BookTrashManagerTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    /** @var list<object> */
    private array $removed = [];

    /** @var list<string> the owners whose ratings were dropped, as "type#id" */
    private array $ratingsDeletedFor = [];

    /** @var string[] */
    private array $reviewsDeletedFor = [];

    /** @var array<string, Redirect> */
    private array $redirectsByFromPath = [];

    /** @var list<Redirect> */
    private array $redirectsByToUrl = [];

    // A site serving its books under "/livre", its series under "/serie" and its strips under "/planche"
    private const array PREFIXES = [
        'book_display' => '/livre/',
        'serie_display' => '/serie/',
        'strip_display' => '/planche/',
    ];

    public function testMovingToTheTrashKeepsTheRowAndOnlyTakesItOffTheSite(): void
    {
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1');

        $this->manager()->moveToTrash($book);

        $this->assertTrue($book->isDeleted());
        $this->assertSame([], $this->persisted);
        $this->assertSame([], $this->removed);
    }

    public function testRestoringPutsTheRowBackOnTheSite(): void
    {
        $serie = new Serie()->setTitle('La Guilde')->setSlug('la-guilde')->setIsDeleted(true);

        $this->manager()->restore($serie);

        $this->assertFalse($serie->isDeleted());
    }

    public function testRemovingForGoodLeavesAGoneRedirectOnTheUrl(): void
    {
        $strip = new Strip()->setTitle('Le tournoi')->setSlug('le-tournoi');

        $this->manager()->deletePermanently($strip, 'strip_display');

        $this->assertSame([$strip], $this->removed);
        $this->assertCount(1, $this->persisted);
        $redirect = $this->persisted[0];
        $this->assertInstanceOf(Redirect::class, $redirect);
        $this->assertSame('/planche/le-tournoi', $redirect->getFromPath());
        $this->assertTrue($redirect->isGone());
        $this->assertNull($redirect->getToUrl());
    }

    public function testRemovingForGoodTurnsTheRedirectsPointingAtItIntoGoneOnes(): void
    {
        $dangling = new Redirect()->setFromPath('/livre/ancien-titre')->setToUrl('/livre/tome-1');
        $this->redirectsByToUrl = [$dangling];
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1');

        $this->manager()->deletePermanently($book, 'book_display');

        $this->assertTrue($dangling->isGone());
        $this->assertNull($dangling->getToUrl());
    }

    public function testATargetSetUpByHandIsLeftAloneWhenTheRowIsRemoved(): void
    {
        $this->redirectsByFromPath = ['/livre/tome-1' => new Redirect()->setFromPath('/livre/tome-1')->setToUrl('/livre/integrale')];
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1');

        $this->manager()->deletePermanently($book, 'book_display');

        // A destination an admin chose says more than a dead end, so no "gone" row is written over it
        $this->assertSame([], $this->persisted);
    }

    public function testRemovingABookForGoodDetachesTheTranslationsNamingIt(): void
    {
        $original = new Book()->setTitle('Tome 1')->setSlug('tome-1');
        $translation = new Book()->setTitle('Book 1')->setSlug('book-1');
        $original->addTranslation($translation);

        $this->manager()->deletePermanently($original, 'book_display');

        // The one column pointing at a book that Doctrine doesn't clear on its own - left as it was, the foreign key refuses the deletion
        $this->assertNull($translation->getTranslationBook());
        $this->assertSame([$original], $this->removed);
    }

    // The readers' votes hang off "book" + id rather than off a relation, so nothing cascades them: whoever deletes the row says so
    public function testRemovingABookForGoodDropsItsRatings(): void
    {
        $book = $this->bookWithId(42);

        $this->manager()->deletePermanently($book, 'book_display');

        $this->assertSame(['book#42'], $this->ratingsDeletedFor);
    }

    // What readers wrote hangs off the same pair and is cascaded by nothing either: a page nobody can reach any more must not keep its reviews reachable
    public function testRemovingABookForGoodDropsItsReviews(): void
    {
        $this->manager()->deletePermanently($this->bookWithId(42), 'book_display');

        $this->assertSame(['book#42'], $this->reviewsDeletedFor);
    }

    // Same reversibility as the votes': a book coming back finds its reviews where it left them
    public function testMovingABookToTheTrashKeepsItsReviews(): void
    {
        $this->manager()->moveToTrash($this->bookWithId(42));

        $this->assertSame([], $this->reviewsDeletedFor);
    }

    // The trash is reversible, and a book coming back has to find its notes where it left them
    public function testMovingABookToTheTrashKeepsItsRatings(): void
    {
        $this->manager()->moveToTrash($this->bookWithId(42));

        $this->assertSame([], $this->ratingsDeletedFor);
    }

    // The three families this bundle serves are rated the same way, so a row gone for good takes its votes with it whichever one it belongs to - a serie's own key, never another family's
    public function testRemovingASerieForGoodDropsItsOwnRatings(): void
    {
        $serie = new Serie()->setTitle('La Guilde')->setSlug('la-guilde');
        new \ReflectionProperty(Serie::class, 'id')->setValue($serie, 42);

        $this->manager()->deletePermanently($serie, 'serie_display');

        $this->assertSame(['serie#42'], $this->ratingsDeletedFor);
    }

    private function bookWithId(int $id): Book
    {
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1');
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);

        return $book;
    }

    public function testASiteReadingItsBooksElsewhereGetsNoRedirectAtAll(): void
    {
        $book = new Book()->setTitle('Tome 1')->setSlug('tome-1');

        // An empty prefix is how a site says this family is served elsewhere - there is no url of ours to leave a 410 on
        $this->manager(served: false)->deletePermanently($book, 'book_display');

        $this->assertSame([], $this->persisted);
        $this->assertSame([$book], $this->removed);
    }

    public function testARenamedRowKeepsItsOldUrlAnsweringWithA301(): void
    {
        $this->manager()->redirectSlugChange('book_display', 'tome-1', 'tome-premier');

        $this->assertCount(1, $this->persisted);
        $redirect = $this->persisted[0];
        $this->assertInstanceOf(Redirect::class, $redirect);
        $this->assertSame('/livre/tome-1', $redirect->getFromPath());
        $this->assertSame('/livre/tome-premier', $redirect->getToUrl());
        $this->assertTrue($redirect->isPermanent());
        $this->assertFalse($redirect->isGone());
    }

    public function testRenamingBackRemovesTheRedirectThatWouldLoop(): void
    {
        $reverse = new Redirect()->setFromPath('/livre/tome-1')->setToUrl('/livre/tome-premier');
        $this->redirectsByFromPath = ['/livre/tome-1' => $reverse];

        // Renaming "tome-premier" back to "tome-1": the row created by the first rename now points the other way
        $this->manager()->redirectSlugChange('book_display', 'tome-premier', 'tome-1');

        $this->assertSame([$reverse], $this->removed);
        $this->assertSame('/livre/tome-premier', $this->persisted[0]->getFromPath());
    }

    public function testASerieHoldingBooksOrStripsSaysSo(): void
    {
        $serie = new Serie()->setTitle('La Guilde')->setSlug('la-guilde');
        $this->assertFalse($serie->holdsContent());

        // A book already in the trash names the serie just as much as one on the site
        $serie->addBook(new Book()->setTitle('Tome 1')->setIsDeleted(true));
        $this->assertTrue($serie->holdsContent());
    }

    private function manager(bool $served = true): BookTrashManager
    {
        $publicUrlResolver = $this->createStub(BookPublicUrlResolver::class);
        $publicUrlResolver
            ->method('resolvePath')
            ->willReturnCallback(static fn (string $route, array $parameters = []): ?string => $served ? self::PREFIXES[$route] . $parameters['slug'] : null)
        ;

        $redirectRepository = $this->createStub(RedirectRepository::class);
        $redirectRepository
            ->method('findOneByFromPath')
            ->willReturnCallback(fn (string $fromPath): ?Redirect => $this->redirectsByFromPath[$fromPath] ?? null)
        ;
        $redirectRepository
            ->method('findByToUrl')
            ->willReturnCallback(fn (): array => $this->redirectsByToUrl)
        ;

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persisted[] = $entity;
            })
        ;
        $entityManager
            ->method('remove')
            ->willReturnCallback(function (object $entity): void {
                $this->removed[] = $entity;
            })
        ;

        $ratingRepository = $this->createStub(RatingRepository::class);
        $ratingRepository
            ->method('deleteForOwner')
            ->willReturnCallback(function (string $ownerType, int $ownerId): int {
                $this->ratingsDeletedFor[] = $ownerType . '#' . $ownerId;

                return 0;
            })
        ;

        // Reviews hang off the same name/id pair as the votes, and go the same way
        $reviewRepository = $this->createStub(ReviewRepository::class);
        $reviewRepository
            ->method('deleteForOwner')
            ->willReturnCallback(function (string $ownerType, int $ownerId): int {
                $this->reviewsDeletedFor[] = $ownerType . '#' . $ownerId;

                return 0;
            })
        ;

        return new BookTrashManager($publicUrlResolver, $entityManager, $redirectRepository, $ratingRepository, $reviewRepository);
    }
}
