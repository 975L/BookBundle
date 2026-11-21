<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Email\BookEmailTemplateProvider;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookReleaseAlert;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\BookReleaseAlertRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The whole life of a "tell me when it comes out" subscription: taken on the page of a book or a planche, acknowledged at once, told by the nightly command, and deleted the moment it is - a book or a planche is published once, so the row has nothing left to wait for
class BookReleaseAlertService implements BookReleaseAlertServiceInterface
{
    // How long a subscription survives a parution that never happens. A book announced and then abandoned would otherwise keep its waiting list for ever, which is an address kept for a purpose that no longer exists
    public const int PENDING_RETENTION_MONTHS = 24;

    // How many refusals an address is given before it is let go. Three nights of trying is enough to tell a mailbox momentarily full from one that no longer exists, and the row is dropped rather than left to be retried for two years
    public const int MAX_ATTEMPTS = 3;

    // What each kind of alert points at and is written with: a book and a planche each have their own templates, an admin rewording one without touching the other
    private const array KINDS = [
        'book' => [
            'route' => 'book_display',
            'confirmation' => BookEmailTemplateProvider::RELEASE_ALERT_CONFIRMATION,
            'released' => BookEmailTemplateProvider::BOOK_RELEASED,
            'releasedSubject' => 'label.book_released_subject',
        ],
        'strip' => [
            'route' => 'strip_display',
            'confirmation' => BookEmailTemplateProvider::STRIP_RELEASE_ALERT_CONFIRMATION,
            'released' => BookEmailTemplateProvider::STRIP_RELEASED,
            'releasedSubject' => 'label.strip_released_subject',
        ],
    ];

    public function __construct(
        private readonly BookReleaseAlertRepository $releaseAlertRepository,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly EntityManagerInterface $em,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly EmailService $emailService,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function subscribe(Book $book, string $email, string $locale): bool
    {
        // Nothing to wait for on a book already out: taking the address would be taking it for an e-mail that is never sent. Nor on one the catalog has dropped - set aside, replaced, or held by a serie set aside: findReleased() reads the same conditions, so the parution e-mail would never go out and the row would be purged in silence two years later, the acknowledgement having promised otherwise
        if (!$book->isToBePublished() || !$book->isShownInCatalog()) {
            return false;
        }

        // Resolved before anything is read or written: raising once the row is flushed would leave an address subscribed with no acknowledgement and no way out, the next submission finding it already there and sending nothing
        $url = $this->url('book', $book);

        $existing = $this->releaseAlertRepository->findOneByBookAndEmail($book, $email);

        return $this->register($existing ?? new BookReleaseAlert()->setBook($book), null === $existing, $email, $locale, $url);
    }

    public function subscribeToStrip(Strip $strip, string $email, string $locale): bool
    {
        // The same two refusals as a book's, read on the planche's own rules: an undated planche is a draft, not one still to come
        if (!$strip->isToBePublished() || !$strip->isShownInCatalog()) {
            return false;
        }

        $url = $this->url('strip', $strip);

        $existing = $this->releaseAlertRepository->findOneByStripAndEmail($strip, $email);

        return $this->register($existing ?? new BookReleaseAlert()->setStrip($strip), null === $existing, $email, $locale, $url);
    }

    public function unsubscribe(BookReleaseAlert $releaseAlert): void
    {
        $this->em->remove($releaseAlert);
        $this->em->flush();
    }

    public function notifyReleased(int $limit): int
    {
        $sent = 0;

        foreach ($this->releaseAlertRepository->findReleased($limit) as $releaseAlert) {
            [$kind, $target] = $this->target($releaseAlert);
            $path = null === $target ? null : $this->bookPublicUrlResolver->resolvePath(self::KINDS[$kind]['route'], ['slug' => $target->getSlug()]);

            // A site that has stopped serving those pages has nothing left to point at: the subscription is dropped rather than kept for a link that no longer exists
            if (null === $target || null === $path) {
                $this->drop($releaseAlert);

                continue;
            }

            // A send that failed leaves the row waiting rather than deleted: the next run tries again, which is the whole point of holding the queue in the database - but the refusal is counted, an address that never accepts anything being let go rather than retried for ever
            if (!$this->sendRelease($releaseAlert, $kind, $target, $this->url($kind, $target))) {
                $this->recordFailure($releaseAlert);

                continue;
            }

            // Deleted one by one as they are sent, rather than in one go at the end: a row throwing halfway through would otherwise keep everyone already served on the list, and the next run would write to them a second time
            $this->drop($releaseAlert);
            ++$sent;
        }

        return $sent;
    }

    public function countPending(): int
    {
        return $this->releaseAlertRepository->countPending();
    }

    public function purgeStale(): int
    {
        return $this->releaseAlertRepository->deleteCreatedBefore(
            new \DateTimeImmutable('-' . self::PENDING_RETENTION_MONTHS . ' months')
        );
    }

    // Writes the address down, or renews the row already holding it, and acknowledges it the first time only. Sending it again on each submission would let the same form mail a stranger as often as the limiter allows, where the first one is the only way out offered before the parution
    private function register(BookReleaseAlert $releaseAlert, bool $subscribing, string $email, string $locale, string $url): bool
    {
        if ($subscribing) {
            $releaseAlert->setEmail($email)->setLocale($locale);
            $this->em->persist($releaseAlert);
        } else {
            // Somebody subscribing again, from another browser or in another language: the unique constraint leaves no second row to create
            $releaseAlert->renew($locale);
        }

        $this->em->flush();

        if ($subscribing) {
            $this->sendConfirmation($releaseAlert, $url);
        }

        return true;
    }

    // The acknowledgement, which carries the way out: the parution e-mail cannot: by then the row is gone and there is nothing left to unsubscribe from
    private function sendConfirmation(BookReleaseAlert $releaseAlert, string $url): bool
    {
        [$kind, $target] = $this->target($releaseAlert);

        if (null === $target) {
            return false;
        }

        // The page itself, the title being a link to it: somebody acknowledging a subscription reads it again far more often than they type its address
        return $this->send(
            $releaseAlert,
            self::KINDS[$kind]['confirmation'],
            'label.release_alert_confirmation_subject',
            $this->variables($kind, $target, $url) + [
                'unsubscribe_url' => $this->urlGenerator->generate(
                    'book_release_alert_unsubscribe',
                    ['token' => $releaseAlert->getToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        );
    }

    // The one e-mail the whole waiting list exists for, and the last: no unsubscribe link, the row going with the send
    private function sendRelease(BookReleaseAlert $releaseAlert, string $kind, Book | Strip $target, string $url): bool
    {
        return $this->send(
            $releaseAlert,
            self::KINDS[$kind]['released'],
            self::KINDS[$kind]['releasedSubject'],
            $this->variables($kind, $target, $url),
        );
    }

    // What an alert waits on, and its kind: a planche when it names one, a book otherwise
    /** @return array{0: 'book'|'strip', 1: Book|Strip|null} */
    private function target(BookReleaseAlert $releaseAlert): array
    {
        $strip = $releaseAlert->getStrip();

        return null !== $strip ? ['strip', $strip] : ['book', $releaseAlert->getBook()];
    }

    // "book_title" and "book_url", or their "strip_" twins: the names each kind's templates are written with
    /** @return array<string, string> */
    private function variables(string $kind, Book | Strip $target, string $url): array
    {
        return [
            $kind . '_title' => (string) $target->getTitle(),
            $kind . '_url' => $url,
        ];
    }

    // The page's public address, which both e-mails are written around: the page is served and the site has no address of its own, so raising says the installation is half-done, where a blank link would take every waiting list to nowhere without a word (see BookPublicUrlResolver::resolve())
    private function url(string $kind, Book | Strip $target): string
    {
        return $this->bookPublicUrlResolver->resolve(self::KINDS[$kind]['route'], ['slug' => $target->getSlug()])
            ?? throw new \LogicException('No "site-url" is configured, so a release alert has no absolute address to send.');
    }

    // Composes from the EmailTemplate of that name, in the language the subscription was taken in - there is no order nor account here to read a locale from, which is why the row carries its own
    private function send(BookReleaseAlert $releaseAlert, string $templateName, string $subjectKey, array $variables): bool
    {
        $html = $this->emailTemplateRenderer->renderNamed($templateName, $variables, $releaseAlert->getLocale());

        // Declared by this bundle and stored by no site is impossible on an installed BookBundle: raising says the installation is half-done, where sending a blank body would tell nobody
        if (null === $html) {
            throw new \LogicException(sprintf('No email template named "%s" is declared or stored, so this alert has no body to send.', $templateName));
        }

        return $this->emailService->send(new EmailSendRequest(
            subject: $this->buildSubject($subjectKey, $releaseAlert->getLocale()),
            context: [],
            html: $html,
            to: $releaseAlert->getEmail(),
            // renderNamed() has already wrapped the body through EmailLayoutRegistry - wrapping it again would put the site's layout inside itself
            wrapLayout: false,
        ));
    }

    // "<site name> - <what this e-mail is about>". The site's name and not a name of the bundle's own: BookBundle declares no address nor identity of its own, and the site-wide "email-*" keys are what the message goes out under
    private function buildSubject(string $subjectKey, string $locale): string
    {
        return trim((string) $this->configService->get('site-name'))
            . ' - ' . $this->translator->trans($subjectKey, [], 'book', $locale);
    }

    // One refusal more on that address: past the ceiling the row goes, the queue being for people the site can still reach
    private function recordFailure(BookReleaseAlert $releaseAlert): void
    {
        $releaseAlert->failed();

        if ($releaseAlert->getAttempts() >= self::MAX_ATTEMPTS) {
            $this->drop($releaseAlert);

            return;
        }

        $this->em->flush();
    }

    private function drop(BookReleaseAlert $releaseAlert): void
    {
        $this->em->remove($releaseAlert);
        $this->em->flush();
    }
}
