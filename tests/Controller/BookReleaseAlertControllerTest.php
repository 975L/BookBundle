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
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookReleaseAlertServiceInterface;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class BookReleaseAlertControllerTest extends TestCase
{
    private ?string $bookPath = '/livre/le-livre';

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    // The real bot protection, a site-form-delay of 0 so a display then a submission in the same second is a human's
    private function createController(BookReleaseAlertServiceInterface $releaseAlertService, bool $validCsrfToken = true, ?FormInterface $form = null): BookReleaseAlertController
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn(0);

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolvePath')->willReturnCallback(fn (): ?string => $this->bookPath);
        $urlResolver->method('resolveLocalizedPath')->willReturnCallback(fn (): ?string => $this->bookPath);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $rateLimiterGuard = $this->createStub(RateLimiterGuard::class);
        $rateLimiterGuard->method('isAcceptedForIp')->willReturn(true);

        $controller = new BookReleaseAlertController(
            $releaseAlertService,
            $urlResolver,
            new FormBotProtection($config),
            $rateLimiterGuard,
            $translator,
            $this->createStub(BookTranslator::class),
        );

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form ?? $this->form(false));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<page>');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($validCsrfToken);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $container->set('security.csrf.token_manager', $csrfTokenManager);
        $container->set('request_stack', $this->requestStack);
        $controller->setContainer($container);

        return $controller;
    }

    // A form answering submitted and valid once a POST is handled, never before
    private function form(bool $submitsValid): FormInterface
    {
        $handled = false;
        $email = $this->createStub(FormInterface::class);
        $email->method('getData')->willReturn('waiting@example.org');

        $form = $this->createStub(FormInterface::class);
        $form->method('getName')->willReturn('book_release_alert');
        $form->method('handleRequest')->willReturnCallback(function (Request $request) use (&$handled, $form): FormInterface {
            $handled = $request->isMethod('POST');

            return $form;
        });
        $form->method('isSubmitted')->willReturnCallback(static function () use (&$handled, $submitsValid): bool {
            return $submitsValid && $handled;
        });
        $form->method('isValid')->willReturn($submitsValid);
        $form->method('get')->willReturn($email);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    // A request on the session given, carrying its cookie when the visitor already holds one
    private function request(string $method = 'GET', ?Session $session = null, bool $withCookie = false): Request
    {
        $session ??= $this->session();
        $request = Request::create('/book/release-alert/1', $method, 'POST' === $method ? ['book_release_alert' => ['email' => 'waiting@example.org']] : []);
        $request->setSession($session);
        if ($withCookie) {
            $request->cookies->set($session->getName(), 'session-id');
        }
        $this->requestStack->push($request);

        return $request;
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage(), flashes: new FlashBag());
    }

    // A visitor's first submission: the page displayed armed the timer, so the POST that follows subscribes
    public function testAPostAfterTheFormWasDisplayedSubscribes(): void
    {
        $releaseAlertService = $this->createMock(BookReleaseAlertServiceInterface::class);
        $releaseAlertService->expects($this->once())->method('subscribe')->willReturn(true);
        $controller = $this->createController($releaseAlertService, form: $this->form(true));
        $session = $this->session();

        $controller->new($this->request('GET', $session), $book = $this->book());
        $response = $controller->new($this->request('POST', $session, true), $book);

        $this->assertSame(302, $response->getStatusCode());
    }

    // A POST with a session but no display timed before it (a second tab, a session expired meanwhile) is shown back filled, with a word on why, and never run
    public function testAPostWithASessionButNoTimerIsShownBackWithoutSubscribing(): void
    {
        $releaseAlertService = $this->createMock(BookReleaseAlertServiceInterface::class);
        $releaseAlertService->expects($this->never())->method('subscribe');
        $session = $this->session();

        $response = $this->createController($releaseAlertService, form: $this->form(true))->new($this->request('POST', $session, true), $this->book());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['text.form_resend'], $session->getFlashBag()->get('warning'));
        // Armed by that display, so the visitor's next submission goes through
        $this->assertNotNull($session->get('book_release_alert_started_at'));
    }

    // A direct re-POST with neither session nor display never went through the form: dropped before the form even reads it
    public function testAPostWithNoSessionAndNoTimerIsDroppedUnread(): void
    {
        $releaseAlertService = $this->createMock(BookReleaseAlertServiceInterface::class);
        $releaseAlertService->expects($this->never())->method('subscribe');
        $form = $this->createMock(FormInterface::class);
        $form->method('getName')->willReturn('book_release_alert');
        $form->method('createView')->willReturn(new FormView());
        $form->expects($this->never())->method('handleRequest');

        $response = $this->createController($releaseAlertService, form: $form)->new($this->request('POST'), $this->book());

        $this->assertSame(200, $response->getStatusCode());
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
            ->new($this->request(), $this->book())
        ;

        $this->assertSame(200, $response->getStatusCode());
    }

    // A page composed against a stale card must not take an address for something nobody can reach any more
    public function testABookSetAsideIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new($this->request(), $this->book(hidden: true))
        ;
    }

    public function testABookOfASerieSetAsideIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new($this->request(), $this->book()->setSerie(new Serie()->setHidden(true)))
        ;
    }

    public function testABookInTheTrashIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new($this->request(), $this->book(deleted: true))
        ;
    }

    // A site reading its books elsewhere serves no page of the bundle's, so there is no parution to promise
    public function testABookOfAFamilyTheSiteDoesNotServeIsNotFound(): void
    {
        $this->bookPath = null;

        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(BookReleaseAlertServiceInterface::class))
            ->new($this->request(), $this->book())
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
