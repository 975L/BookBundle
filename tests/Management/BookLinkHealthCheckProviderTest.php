<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Management\BookLinkHealthCheckProvider;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// What the Health check dashboard says about the platforms a catalog sends its readers to, the rows being persisted by ConfigBundle's HealthCheckRunner exactly as they are returned here
class BookLinkHealthCheckProviderTest extends TestCase
{
    // A store answering normally is what the vast majority of the rows are, and they carry the book and the platform the address was declared on
    public function testAReachableLinkIsReportedOk(): void
    {
        $book = $this->book('Contes du Soir', 'contes-du-soir', ['amazon' => 'https://amazon.fr/dp/1']);

        $rows = $this->provider([$book], ['https://amazon.fr/dp/1' => 200])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('https://amazon.fr/dp/1', $rows[0]['url']);
        $this->assertSame('Contes du Soir - amazon', $rows[0]['label']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame('label.health_check_link_ok', $rows[0]['summary']);
        $this->assertSame(['kind' => 'amazon', 'slug' => 'contes-du-soir', 'httpCode' => 200, 'books' => ['Contes du Soir - amazon']], $rows[0]['details']);
        $this->assertSame('BookCrudController/4/editions', $rows[0]['editUrl']);
    }

    // The whole point of the check: a store that closed, reported as an error the editor can act on
    public function testAnAddressAnsweringNotFoundIsAnError(): void
    {
        $rows = $this->provider([$this->book('Contes du Soir', 'contes-du-soir', ['fnac' => 'https://fnac.com/gone'])], ['https://fnac.com/gone' => 404])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('label.health_check_link_broken', $rows[0]['summary']);
    }

    // Most stores turn a HEAD carrying no browser behind it down - the link is fine and there is nothing to fix, so it stays out of the errors
    public function testAPlatformRefusingTheProbeIsSkippedRatherThanReportedBroken(): void
    {
        $rows = $this->provider([$this->book('Contes du Soir', 'contes-du-soir', ['amazon' => 'https://amazon.fr/dp/2'])], ['https://amazon.fr/dp/2' => 403])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $rows[0]['status']);
        $this->assertSame('label.health_check_link_refused', $rows[0]['summary']);
    }

    // A host that never answered at all is not a page answering 404, and the two read differently on the dashboard
    public function testAHostThatNeverAnsweredIsToldFromAPageAnswering(): void
    {
        $rows = $this->provider([$this->book('Contes du Soir', 'contes-du-soir', ['spotify' => 'https://gone.example/x'])], ['https://gone.example/x' => null])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('label.health_check_link_unreachable', $rows[0]['summary']);
        $this->assertNull($rows[0]['details']['httpCode']);
    }

    // A platform is declared with its whole address (see BookLink): anything else is a data error, named as one instead of being probed and reported as unreachable
    public function testAnAddressCarryingNoProtocolIsNeverProbed(): void
    {
        $checker = $this->createMock(UrlStatusChecker::class);
        $checker->expects($this->never())->method('status');

        $provider = new BookLinkHealthCheckProvider(
            $this->bookService([$this->book('Contes du Soir', 'contes-du-soir', ['fnac' => '/dp/1'])]),
            $this->registry(),
            $checker,
            $this->adminUrlGenerator(),
            $this->translator(),
        );

        $rows = $provider->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('label.health_check_link_invalid', $rows[0]['summary']);
    }

    // Results are kept per (url, kind), so two books sold on the same address are one row, naming both
    public function testTwoBooksSharingAnAddressAreOneRow(): void
    {
        $first = $this->book('Contes du Soir', 'contes-du-soir', ['amazon' => 'https://amazon.fr/dp/1']);
        $second = $this->book('Mamie ViteVite', 'mamie-vitevite', ['amazon' => 'https://amazon.fr/dp/1'], 5);

        $rows = $this->provider([$first, $second], ['https://amazon.fr/dp/1' => 200])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(['Contes du Soir - amazon', 'Mamie ViteVite - amazon'], $rows[0]['details']['books']);
    }

    // A book declaring no platform at all, and one whose address was left empty, have nothing to check
    public function testABookWithNoAddressIsSkippedEntirely(): void
    {
        $this->assertSame([], $this->provider([$this->book('Contes du Soir', 'contes-du-soir', ['fnac' => ''])], [])->runChecks());
    }

    /** @param array<string, ?int> $statuses url => the code the host answers, null for a host that never answered */
    private function provider(array $books, array $statuses): BookLinkHealthCheckProvider
    {
        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('status')->willReturnCallback(static fn (string $url): ?int => $statuses[$url] ?? null);

        return new BookLinkHealthCheckProvider(
            $this->bookService($books),
            $this->registry(),
            $checker,
            $this->adminUrlGenerator(),
            $this->translator(),
        );
    }

    private function bookService(array $books): BookServiceInterface
    {
        $bookService = $this->createStub(BookServiceInterface::class);
        $bookService->method('findAll')->willReturn($books);

        return $bookService;
    }

    // The platform prints under the name the site declares for it, the kind itself standing for one the vocabulary doesn't hold
    private function registry(): BookCustomizationRegistry
    {
        $registry = $this->createStub(BookCustomizationRegistry::class);
        $registry->method('getLinkLabel')->willReturnCallback(static fn (BookLink $link): string => (string) $link->getKind());

        return $registry;
    }

    // The key itself as its own translation, so what a row states can be told from how it is worded
    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    // Rebuilds the screen, the book and the tab the edit URL opens on, as BookBlockEditUrlProviderTest does for the blocks
    private function adminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $controller = null;
        $entityId = null;
        $focusField = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $fqcn) use (&$controller, $generator) {
            $controller = substr((string) strrchr($fqcn, '\\'), 1);

            return $generator;
        });
        $generator->method('setEntityId')->willReturnCallback(function ($id) use (&$entityId, $generator) {
            $entityId = $id;

            return $generator;
        });
        $generator->method('set')->willReturnCallback(function (string $name, $value) use (&$focusField, $generator) {
            $focusField = 'focusField' === $name ? $value : $focusField;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(static function () use (&$controller, &$entityId, &$focusField): string {
            return sprintf('%s/%s/%s', $controller, $entityId, $focusField);
        });

        return $generator;
    }

    /** @param array<string, string> $links kind => address */
    private function book(string $title, string $slug, array $links, int $id = 4): Book
    {
        $book = new Book();
        $book->setTitle($title);
        $book->setSlug($slug);
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);

        foreach ($links as $kind => $url) {
            $book->addLink(new BookLink()->setKind($kind)->setUrl($url));
        }

        return $book;
    }
}
