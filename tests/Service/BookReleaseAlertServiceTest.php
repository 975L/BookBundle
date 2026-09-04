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
use c975L\BookBundle\Entity\BookReleaseAlert;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Repository\BookReleaseAlertRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookReleaseAlertService;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Who gets told a book is out, and who does not: the waiting list only accepts what is still to come, tells each subscriber once, and keeps nothing once it has
class BookReleaseAlertServiceTest extends TestCase
{
    /** @var EmailSendRequest[] */
    private array $sent = [];

    /** @var object[] */
    private array $removed = [];

    private bool $mailerAccepts = true;

    private ?string $bookUrl = 'https://example.org/livre/le-livre';

    private ?string $bookPath = '/livre/le-livre';

    private function createService(BookReleaseAlertRepository $repository): BookReleaseAlertService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(fn (): ?string => $this->bookUrl);
        $urlResolver->method('resolvePath')->willReturnCallback(fn (): ?string => $this->bookPath);

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamed')->willReturn('<p>It is out</p>');

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request): bool {
            $this->sent[] = $request;

            return $this->mailerAccepts;
        });

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key): ?string => 'site-name' === $key ? 'Les Éditions' : null
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Le livre est paru');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.org/book/release-alert/abcd/unsubscribe');

        return new BookReleaseAlertService(
            $repository,
            $urlResolver,
            $em,
            $renderer,
            $emailService,
            $configService,
            $translator,
            $urlGenerator,
        );
    }

    private function book(?string $published): Book
    {
        return new Book()
            ->setTitle('Le livre')
            ->setSlug('le-livre')
            ->setPublished(null === $published ? null : new \DateTime($published))
        ;
    }

    private function alert(Book $book): BookReleaseAlert
    {
        return new BookReleaseAlert()
            ->setBook($book)
            ->setEmail('waiting@example.org')
            ->setLocale('fr')
        ;
    }

    /** @param BookReleaseAlert[] $released */
    private function repositoryReturning(array $released = [], ?BookReleaseAlert $existing = null): BookReleaseAlertRepository
    {
        $repository = $this->createStub(BookReleaseAlertRepository::class);
        $repository->method('findReleased')->willReturnCallback(
            static fn (int $limit): array => array_slice($released, 0, $limit)
        );
        $repository->method('findOneByBookAndEmail')->willReturn($existing);
        $repository->method('deleteCreatedBefore')->willReturn(3);

        return $repository;
    }

    public function testABookStillToComeAcceptsASubscription(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertTrue($service->subscribe($this->book('+2 months'), 'waiting@example.org', 'fr'));
        $this->assertTrue($service->subscribe($this->book(null), 'waiting@example.org', 'fr'));
    }

    // Nothing to wait for: taking the address would promise an email that is never sent
    public function testABookAlreadyOutRefusesASubscription(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertFalse($service->subscribe($this->book('-1 day'), 'waiting@example.org', 'fr'));
        $this->assertSame([], $this->sent);
    }

    // findReleased() reads the catalog's own conditions, so a book the catalog has dropped is one nobody will ever be written to about: taking the address would acknowledge a promise the nightly command cannot keep
    public function testABookTheCatalogNoLongerCarriesRefusesASubscription(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertFalse($service->subscribe($this->book('+2 months')->setHidden(true), 'waiting@example.org', 'fr'));
        $this->assertFalse($service->subscribe($this->book('+2 months')->setIsDeleted(true), 'waiting@example.org', 'fr'));
        $this->assertFalse($service->subscribe($this->book('+2 months')->setNewerVersion($this->book('+2 months')), 'waiting@example.org', 'fr'));
        $this->assertFalse($service->subscribe($this->book('+2 months')->setSerie(new Serie()->setHidden(true)), 'waiting@example.org', 'fr'));
        $this->assertSame([], $this->sent);
    }

    // The acknowledgement is the only way out offered before the parution: anybody whose address was typed by somebody else unsubscribes from there
    public function testSubscribingIsAcknowledgedByAnEmailCarryingTheUnsubscribeLink(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $service->subscribe($this->book('+2 months'), 'waiting@example.org', 'fr');

        $this->assertCount(1, $this->sent);
        $this->assertSame('waiting@example.org', $this->sent[0]->to);
        $this->assertStringContainsString('Les Éditions', (string) $this->sent[0]->subject);
        // renderNamed() has already wrapped the body: wrapping it again would nest the site's layout inside itself
        $this->assertFalse($this->sent[0]->wrapLayout);
    }

    // The unique constraint on (book, email) leaves no second row to create, so subscribing again has to renew the one that exists. Sending it again on each submission would let the same form mail a stranger as often as the limiter allows
    public function testSubscribingAgainIsNotAcknowledgedASecondTime(): void
    {
        $book = $this->book('+2 months');
        $service = $this->createService($this->repositoryReturning([], $this->alert($book)));

        $this->assertTrue($service->subscribe($book, 'waiting@example.org', 'fr'));
        $this->assertSame([], $this->sent);
    }

    public function testSubscribingAgainRenewsTheExistingRow(): void
    {
        $book = $this->book('+2 months');
        $existing = $this->alert($book);
        $aged = new \ReflectionProperty(BookReleaseAlert::class, 'createdAt');
        $aged->setValue($existing, new \DateTimeImmutable('-18 months'));

        $service = $this->createService($this->repositoryReturning([], $existing));

        $this->assertTrue($service->subscribe($book, 'waiting@example.org', 'en'));
        $this->assertSame('en', $existing->getLocale());
        $this->assertGreaterThan(new \DateTimeImmutable('-1 minute'), $existing->getCreatedAt());
    }

    // A book is published once: the row has nothing left to wait for, and an address kept past its purpose is an address kept for nothing
    public function testASubscriberIsToldOnceAndTheRowIsDeleted(): void
    {
        $alert = $this->alert($this->book('-1 day'));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(1, $service->notifyReleased(50));
        $this->assertCount(1, $this->sent);
        $this->assertSame('waiting@example.org', $this->sent[0]->to);
        $this->assertSame([$alert], $this->removed);
    }

    // The next run tries again, which is the whole point of holding the queue in the database
    public function testASendThatFailedLeavesTheSubscriptionWaiting(): void
    {
        $this->mailerAccepts = false;
        $alert = $this->alert($this->book('-1 day'));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyReleased(50));
        $this->assertSame([], $this->removed);
        // Counted so the row goes to the back of the queue rather than filling every run's batch from its head
        $this->assertSame(1, $alert->getAttempts());
    }

    // An address refused for good used to sit at the head of the queue for ever, served nobody behind it, and the command still announced a success
    public function testAnAddressRefusedOverAndOverIsLetGo(): void
    {
        $this->mailerAccepts = false;
        $alert = $this->alert($this->book('-1 day'));
        $service = $this->createService($this->repositoryReturning([$alert]));

        for ($run = 1; $run < BookReleaseAlertService::MAX_ATTEMPTS; ++$run) {
            $service->notifyReleased(50);
            $this->assertSame([], $this->removed);
        }

        $service->notifyReleased(50);

        $this->assertSame(BookReleaseAlertService::MAX_ATTEMPTS, $alert->getAttempts());
        $this->assertSame([$alert], $this->removed);
    }

    // A site that has stopped serving its book pages has nothing left to point at, and nothing left to keep an address for
    public function testASubscriptionOnABookTheSiteNoLongerServesIsDroppedWithoutBeingSent(): void
    {
        $this->bookPath = null;
        $alert = $this->alert($this->book('-1 day'));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->assertSame(0, $service->notifyReleased(50));
        $this->assertSame([], $this->sent);
        $this->assertSame([$alert], $this->removed);
    }

    // The other null BookPublicUrlResolver answers with: the page is served and the site has no address of its own - dropping there would take every waiting list away over a missing setting
    public function testASiteWithoutAnAddressOfItsOwnRaisesRatherThanDroppingTheList(): void
    {
        $this->bookUrl = null;
        $alert = $this->alert($this->book('-1 day'));
        $service = $this->createService($this->repositoryReturning([$alert]));

        $this->expectException(\LogicException::class);

        $service->notifyReleased(50);
    }

    public function testTheStaleSubscriptionsArePurged(): void
    {
        $service = $this->createService($this->repositoryReturning());

        $this->assertSame(3, $service->purgeStale());
    }
}
