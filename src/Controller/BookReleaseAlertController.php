<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookReleaseAlert;
use c975L\BookBundle\Form\BookReleaseAlertType;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookReleaseAlertServiceInterface;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// A page of its own rather than a form on the book's sheet, for the same reason ShopBundle's stock alert is one: the sheet's html is handed to a shared cache per fragment, where a form needs a session, a csrf token and a Set-Cookie - the three things that must never travel with a cached page
class BookReleaseAlertController extends AbstractController
{
    private const string SESSION_KEY = 'book_release_alert_started_at';

    public function __construct(
        private readonly BookReleaseAlertServiceInterface $releaseAlertService,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly FormBotProtection $botProtection,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly TranslatorInterface $translator,
        private readonly ?RateLimiterFactoryInterface $releaseAlertLimiterFactory = null,
    ) {
    }

    // SUBSCRIBE
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/book/release-alert/{id:book}',
        name: 'book_release_alert_new',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function new(Request $request, Book $book): Response
    {
        $bookPath = $this->bookPublicUrlResolver->resolvePath('book_display', ['slug' => $book->getSlug()]);

        // Nothing is offered on what the site does not show, nor on a book it does not serve at all: a stale link is answered with a 404 rather than with a page promising an e-mail nobody will send
        if ($book->isHidden() || $book->isDeleted() || null === $bookPath) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(BookReleaseAlertType::class);

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same answer and no hint - same reading as ShopBundle's stock alert
        $suspicious = $request->isMethod('POST')
            && $this->botProtection->isSuspicious($request, $form->getName(), self::SESSION_KEY);

        if (!$suspicious) {
            $form->handleRequest($request);
        }

        if (!$suspicious && $form->isSubmitted() && $form->isValid()) {
            // Counted per caller and not per address, an IPv6 subscriber holding a block far larger than any ceiling could count - see RateLimiterGuard::isAcceptedForIp()
            $clientIp = $request->getClientIp();

            if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->releaseAlertLimiterFactory, $clientIp)) {
                $this->addFlash('warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));
            } else {
                $subscribed = $this->releaseAlertService->subscribe($book, (string) $form->get('email')->getData(), $request->getLocale());

                // Two ways of being refused, and they do not say the same thing to the visitor: the book is already out, or the catalog no longer carries it - a serie set aside or a newer edition, neither of which the 404 above catches
                $message = match (true) {
                    $subscribed => 'text.release_alert_subscribed',
                    $book->isToBePublished() => 'text.release_alert_unavailable',
                    default => 'text.release_alert_already_published',
                };

                $this->addFlash(
                    $subscribed ? 'success' : 'warning',
                    $this->translator->trans($message, [], 'book')
                );

                return $this->redirect($bookPath);
            }
        }

        // Armed here rather than before isSuspicious(), which consumes the key on every submission: a page displayed again after a typo would otherwise re-arm the timer to now and read 0 second elapsed, turning every correction into a suspicious submission
        $this->botProtection->startTimer($request, self::SESSION_KEY);

        // A suspicious submission is answered exactly like a first display, and stored nowhere
        return $this->render('@c975LBook/release_alert/new.html.twig', [
            'form' => $form->createView(),
            'book' => $book,
        ]);
    }

    // UNSUBSCRIBE
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/book/release-alert/{token:releaseAlert}/unsubscribe',
        name: 'book_release_alert_unsubscribe',
        requirements: ['token' => '[a-zA-Z0-9]{16}'],
        methods: ['GET']
    )]
    public function unsubscribe(BookReleaseAlert $releaseAlert): Response
    {
        // A button rather than the link doing it on sight: the mail gateways that walk every address of a message before it is read - Outlook's Safe Links, an antivirus - would otherwise unsubscribe the visitor minutes after the acknowledgement carrying that very link reached them
        return $this->render('@c975LBook/release_alert/unsubscribe.html.twig', [
            'releaseAlert' => $releaseAlert,
        ]);
    }

    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/book/release-alert/{token:releaseAlert}/unsubscribe',
        name: 'book_release_alert_unsubscribe_confirm',
        requirements: ['token' => '[a-zA-Z0-9]{16}'],
        methods: ['POST']
    )]
    public function unsubscribeConfirm(Request $request, BookReleaseAlert $releaseAlert): Response
    {
        // Bound to the token of that one subscription: a form lifted from one page cannot unsubscribe anybody else
        if (!$this->isCsrfTokenValid('release_alert_unsubscribe' . $releaseAlert->getToken(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->releaseAlertService->unsubscribe($releaseAlert);

        return $this->render('@c975LBook/release_alert/unsubscribed.html.twig');
    }
}
