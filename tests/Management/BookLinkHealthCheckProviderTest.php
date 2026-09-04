<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Contract\PlatformLinkInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Management\BookLinkHealthCheckProvider;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
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
        $this->assertSame(['kind' => 'amazon', 'slug' => 'contes-du-soir', 'httpCode' => 200, 'declaredOn' => ['Contes du Soir - amazon']], $rows[0]['details']);
        $this->assertSame('BookCrudController/4/buyLinks', $rows[0]['editUrl']);
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
            $this->bookService([$this->book('Contes du Soir', 'contes-du-soir', ['fnac' => 'dp/1'])]),
            $this->contributorService([]),
            $this->registry(),
            $checker,
            $this->configService('https://editions.example'),
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
        $this->assertSame(['Contes du Soir - amazon', 'Mamie ViteVite - amazon'], $rows[0]['details']['declaredOn']);
    }

    // A person's own page at a store rots exactly as a book's does, and the row leads to the collection it is written in
    public function testThePlatformsAPersonDeclaresAreCheckedToo(): void
    {
        $contributor = new Contributor()->setName('Ludmila Brazov')->setSlug('ludmila-brazov');
        new \ReflectionProperty(Contributor::class, 'id')->setValue($contributor, 3);
        $contributor->addLink(new ContributorLink()->setKind('fnac')->setUrl('https://fnac.com/ia1/Ludmila-Brazov'));

        $rows = $this->provider([], ['https://fnac.com/ia1/Ludmila-Brazov' => 404], [$contributor])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('Ludmila Brazov - fnac', $rows[0]['label']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('ludmila-brazov', $rows[0]['details']['slug']);
        $this->assertSame('ContributorCrudController/3/links', $rows[0]['editUrl']);
    }

    // A book and its author sending to the same store are one row, naming both: the dashboard lists an address once
    public function testABookAndAPersonSharingAnAddressAreOneRow(): void
    {
        $contributor = new Contributor()->setName('Ludmila Brazov')->setSlug('ludmila-brazov');
        new \ReflectionProperty(Contributor::class, 'id')->setValue($contributor, 3);
        $contributor->addLink(new ContributorLink()->setKind('amazon')->setUrl('https://amazon.fr/dp/1'));

        $rows = $this->provider([$this->book('Contes du Soir', 'contes-du-soir', ['amazon' => 'https://amazon.fr/dp/1'])], ['https://amazon.fr/dp/1' => 200], [$contributor])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(['Contes du Soir - amazon', 'Ludmila Brazov - amazon'], $rows[0]['details']['declaredOn']);
    }

    // The site's own shop is stored as the page writes it - "/shop/..." - so it is probed against the address the site declares for itself rather than reported as a malformed address
    public function testAnAddressOfTheSiteItselfIsProbedUnderTheSiteAddress(): void
    {
        $contributor = new Contributor()->setName('Ludmila Brazov')->setSlug('ludmila-brazov');
        new \ReflectionProperty(Contributor::class, 'id')->setValue($contributor, 3);
        $contributor->addLink(new ContributorLink()->setKind('shop')->setUrl('/shop/category/ludmila-brazov'));

        $rows = $this->provider([], ['https://editions.example/shop/category/ludmila-brazov' => 200], [$contributor])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        // Filed under the address as it is stored, which is the one the editor reads on the screen
        $this->assertSame('/shop/category/ludmila-brazov', $rows[0]['url']);
    }

    // A site that has not filled its own address in has nothing to probe such a row against, and no error to report either
    public function testAnAddressOfTheSiteItselfIsSkippedWhenTheSiteDeclaresNoAddress(): void
    {
        $contributor = new Contributor()->setName('Ludmila Brazov')->setSlug('ludmila-brazov');
        new \ReflectionProperty(Contributor::class, 'id')->setValue($contributor, 3);
        $contributor->addLink(new ContributorLink()->setKind('shop')->setUrl('/shop/category/ludmila-brazov'));

        $rows = $this->provider([], [], [$contributor], '')->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $rows[0]['status']);
        $this->assertSame('label.health_check_link_internal', $rows[0]['summary']);
    }

    // A book declaring no platform at all, and one whose address was left empty, have nothing to check
    public function testABookWithNoAddressIsSkippedEntirely(): void
    {
        $this->assertSame([], $this->provider([$this->book('Contes du Soir', 'contes-du-soir', ['fnac' => ''])], [])->runChecks());
    }

    /** @param array<string, ?int> $statuses url => the code the host answers, null for a host that never answered */
    private function provider(array $books, array $statuses, array $contributors = [], string $siteUrl = 'https://editions.example'): BookLinkHealthCheckProvider
    {
        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('status')->willReturnCallback(static fn (string $url): ?int => $statuses[$url] ?? null);

        return new BookLinkHealthCheckProvider(
            $this->bookService($books),
            $this->contributorService($contributors),
            $this->registry(),
            $checker,
            $this->configService($siteUrl),
            $this->adminUrlGenerator(),
            $this->translator(),
        );
    }

    // The address the site declares for itself, empty for a site that declares none
    private function configService(string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): ?string => 'site-url' === $slug ? $siteUrl : null);

        return $configService;
    }

    private function contributorService(array $contributors): ContributorServiceInterface
    {
        $contributorService = $this->createStub(ContributorServiceInterface::class);
        $contributorService->method('findAll')->willReturn($contributors);

        return $contributorService;
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
        $registry->method('getLinkLabel')->willReturnCallback(static fn (PlatformLinkInterface $link): string => (string) $link->getKind());
        // The stores sell the digital book, the rest is heard: enough for the edit link to be told from one gesture to the other
        $registry->method('getLinkGroup')->willReturnCallback(static fn (PlatformLinkInterface $link): string => \in_array($link->getKind(), ['amazon', 'fnac'], true) ? 'epub' : 'podcast');

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
