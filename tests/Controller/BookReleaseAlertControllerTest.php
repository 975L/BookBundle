<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\BookReleaseAlertController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookReleaseAlert;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookReleaseAlertServiceInterface;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class BookReleaseAlertControllerTest extends TestCase
{
    private ?string $bookPath = '/livre/le-livre';

    private function createController(BookReleaseAlertServiceInterface $releaseAlertService, bool $validCsrfToken = true): BookReleaseAlertController
    {
        $botProtection = $this->createStub(FormBotProtection::class);
        $botProtection->method('isSuspicious')->willReturn(false);

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolvePath')->willReturnCallback(fn (): ?string => $this->bookPath);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('a message');

        $controller = new BookReleaseAlertController(
            $releaseAlertService,
            $urlResolver,
            $botProtection,
            $this->createStub(RateLimiterGuard::class),
            $translator,
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('getName')->willReturn('book_release_alert');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<page>');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($validCsrfToken);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $container->set('security.csrf.token_manager', $csrfTokenManager);
        $controller->setContainer($container);

        return $controller;
    }

    private function book(bool $hidden = false, bool $deleted = false): Book
    {
        return new Book()
            ->setTitle('Le livre')
            ->setSlug('le-livre')
            ->setPublished(new \DateTime('+2 months'))
            ->setHidden($hidden)
            ->setIsDeleted($deleted)
        ;
    }

    public function testTheFormIsShownOnABookTheSiteStillShows(): void
    {
        $response = $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new(new Request(), $this->book())
        ;

        $this->assertSame(200, $response->getStatusCode());
    }

    // A page composed against a stale card must not take an address for something nobody can reach any more
    public function testABookSetAsideIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new(new Request(), $this->book(hidden: true))
        ;
    }

    public function testABookInTheTrashIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new(new Request(), $this->book(deleted: true))
        ;
    }

    // A site reading its books elsewhere serves no page of the bundle's, so there is no parution to promise
    public function testABookOfAFamilyTheSiteDoesNotServeIsNotFound(): void
    {
        $this->bookPath = null;

        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new(new Request(), $this->book())
        ;
    }

    // The link opens a page and takes nobody off the list: a mail gateway walking the addresses of the acknowledgement - Outlook's Safe Links, an antivirus - would otherwise unsubscribe the visitor minutes after they subscribed
    public function testTheTokenLinkOnlyAsksTheQuestion(): void
    {
        $releaseAlertService = $this->createMock(BookReleaseAlertServiceInterface::class);
        $releaseAlertService->expects($this->never())->method('unsubscribe');

        $response = $this->createController($releaseAlertService)->unsubscribe($this->releaseAlert());

        $this->assertSame(200, $response->getStatusCode());
    }

    // The button on that page is what drops the row, the submission being what no gateway makes on its own
    public function testTheButtonDropsTheSubscriptionAndSaysSo(): void
    {
        $releaseAlert = $this->releaseAlert();

        $releaseAlertService = $this->createMock(BookReleaseAlertServiceInterface::class);
        $releaseAlertService->expects($this->once())->method('unsubscribe')->with($releaseAlert);

        $response = $this->createController($releaseAlertService)->unsubscribeConfirm(new Request(), $releaseAlert);

        $this->assertSame(200, $response->getStatusCode());
    }

    // Bound to the token of that one subscription: a form lifted from one page unsubscribes nobody else
    public function testASubmissionCarryingNoValidTokenDropsNothing(): void
    {
        $releaseAlertService = $this->createMock(BookReleaseAlertServiceInterface::class);
        $releaseAlertService->expects($this->never())->method('unsubscribe');

        $this->expectException(AccessDeniedException::class);

        $this->createController($releaseAlertService, false)
            ->unsubscribeConfirm(new Request(), $this->releaseAlert())
        ;
    }

    private function releaseAlert(): BookReleaseAlert
    {
        return new BookReleaseAlert()
            ->setBook($this->book())
            ->setEmail('waiting@example.org')
            ->setLocale('fr')
        ;
    }
}
