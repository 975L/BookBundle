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
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Form\BookReleaseAlertType;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\BookReleaseAlertServiceInterface;
use c975L\BookBundle\Service\BookTranslator;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// A page of its own rather than a form on the page of the book or the planche, for the same reason ShopBundle's stock alert is one: the sheet's html is handed to a shared cache per fragment, where a form needs a session, a csrf token and a Set-Cookie - the three things that must never travel with a cached page
class BookReleaseAlertController extends AbstractController
{
    private const string SESSION_KEY = 'book_release_alert_started_at';

    public function __construct(
        private readonly BookReleaseAlertServiceInterface $releaseAlertService,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly FormBotProtection $botProtection,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly TranslatorInterface $translator,
        private readonly BookTranslator $bookTranslator,
        private readonly ?RateLimiterFactoryInterface $releaseAlertLimiterFactory = null,
    ) {
    }

    // SUBSCRIBE
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    // Twinned like the book's own page: the language the book is read in carries over to this page, to the message it answers with and to the language the subscription is stored in (see BookReleaseAlertService::subscribe)
    #[Route(
        '/{_locale}/book/release-alert/{id:book}',
        name: 'book_release_alert_new_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    #[Route(
        '/book/release-alert/{id:book}',
        name: 'book_release_alert_new',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function new(Request $request, Book $book): Response
    {
        // The book's page in the language this one is read in, which is where the visitor is sent back to
        $bookPath = $this->bookPublicUrlResolver->resolveLocalizedPath('book_display', ['slug' => $book->getSlug()]);

        // Nothing is offered on what the site does not show, nor on a book it does not serve at all: a stale link is answered with a 404 rather than with a page promising an e-mail nobody will send
        if ($book->isHidden() || ($book->getSerie()?->isHidden() ?? false) || $book->isDeleted() || null === $bookPath) {
            throw $this->createNotFoundException();
        }

        return $this->handle(
            $request,
            $bookPath,
            // Two ways of being refused, and they do not say the same thing to the visitor: the book is already out, or the catalog no longer carries it - a serie set aside or a newer edition, neither of which the 404 above catches
            fn (string $email): string => $this->outcome($this->releaseAlertService->subscribe($book, $email, $request->getLocale()), $book->isToBePublished(), ''),
            $book,
            ['book' => $book],
        );
    }

    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    // The same page for a planche dated ahead, its own page offering it in place of the planche it does not show yet
    #[Route(
        '/{_locale}/book/release-alert/strip/{id:strip}',
        name: 'book_release_alert_strip_new_localized',
        requirements: [
            '_locale' => '%c975l_config.locales_pattern%',
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    #[Route(
        '/book/release-alert/strip/{id:strip}',
        name: 'book_release_alert_strip_new',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function newStrip(Request $request, Strip $strip): Response
    {
        $stripPath = $this->bookPublicUrlResolver->resolveLocalizedPath('strip_display', ['slug' => $strip->getSlug()]);

        // An undated planche is a draft its own page answers 404 for, and so does this one
        if (!$strip->isShownInCatalog() || null === $strip->getPublished() || null === $stripPath) {
            throw $this->createNotFoundException();
        }

        return $this->handle(
            $request,
            $stripPath,
            fn (string $email): string => $this->outcome($this->releaseAlertService->subscribeToStrip($strip, $email, $request->getLocale()), $strip->isToBePublished(), 'strip_'),
            $strip,
            ['strip' => $strip],
        );
    }

    // The form shared by both pages: checked against bots, subscribed through $subscribe - which answers the message to flash - and sent back to $backPath once done
    /** @param \Closure(string): string $subscribe */
    private function handle(Request $request, string $backPath, \Closure $subscribe, Book | Strip $target, array $parameters): Response
    {
        $form = $this->createForm(BookReleaseAlertType::class);

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same answer and no hint; a submission with no timed display (a second tab, a session expired while the visitor typed) is shown back filled and never run - the same reading as ShopBundle's stock alert
        [$untimed, $suspicious] = $this->classifySubmission($request, $form->getName());

        if (!$suspicious) {
            $form->handleRequest($request);
        }

        if ($untimed) {
            $this->addFlash('warning', $this->translator->trans('text.form_resend', [], 'ui'));
        }

        if (!$untimed && !$suspicious && $form->isSubmitted() && $form->isValid()) {
            $subscribed = $this->subscribe($request, $subscribe, (string) $form->get('email')->getData());
            if ($subscribed) {
                return $this->redirect($backPath);
            }
        }

        // Armed at display, once the submission is handled and never before isSuspicious() on the same request: a page shown again after a typo is timed from that very display
        $this->botProtection->startTimer($request, self::SESSION_KEY);

        // Its title in the language the page is read in, which is what the heading and the introduction quote - applied once the subscription is flushed, never before
        $this->bookTranslator->apply([$target]);

        // A suspicious submission is answered exactly like a first display, and stored nowhere
        return $this->render('@c975LBook/release_alert/new.html.twig', ['form' => $form->createView()] + $parameters);
    }

    // The message a subscription answers with: taken, refused on what is no longer shown, or refused on what is already out - "$prefix" picking a planche's wording over a book's
    private function outcome(bool $subscribed, bool $toBePublished, string $prefix): string
    {
        return match (true) {
            $subscribed => 'text.release_alert_subscribed',
            $toBePublished => 'text.' . $prefix . 'release_alert_unavailable',
            default => 'text.' . $prefix . 'release_alert_already_published',
        };
    }

    // Whether a POST comes with no timed display (shown back filled) or looks like a bot (dropped), as [untimed, suspicious] - isUntimed() asked first, isSuspicious() consuming the timer
    /** @return array{bool, bool} */
    private function classifySubmission(Request $request, string $formName): array
    {
        if (!$request->isMethod('POST')) {
            return [false, false];
        }

        if ($this->botProtection->isUntimed($request, self::SESSION_KEY)) {
            return [true, false];
        }

        return [false, $this->botProtection->isSuspicious($request, $formName, self::SESSION_KEY)];
    }

    // Records the alert and says so, true once it is done; false when the caller hit the ceiling, the form then shown again with the warning
    /** @param \Closure(string): string $subscribe */
    private function subscribe(Request $request, \Closure $subscribe, string $email): bool
    {
        // Counted per caller and not per address, an IPv6 subscriber holding a block far larger than any ceiling could count - see RateLimiterGuard::isAcceptedForIp()
        $clientIp = $request->getClientIp();

        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->releaseAlertLimiterFactory, $clientIp)) {
            $this->addFlash('warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));

            return false;
        }

        $message = $subscribe($email);

        $this->addFlash(
            'text.release_alert_subscribed' === $message ? 'success' : 'warning',
            $this->translator->trans($message, [], 'book')
        );

        return true;
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
