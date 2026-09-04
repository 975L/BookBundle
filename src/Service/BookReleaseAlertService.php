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
use c975L\BookBundle\Repository\BookReleaseAlertRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The whole life of a "tell me when it comes out" subscription: taken on the book's page, acknowledged at once, told by the nightly command, and deleted the moment it is - a book is published once, so the row has nothing left to wait for
class BookReleaseAlertService implements BookReleaseAlertServiceInterface
{
    // How long a subscription survives a parution that never happens. A book announced and then abandoned would otherwise keep its waiting list for ever, which is an address kept for a purpose that no longer exists
    public const int PENDING_RETENTION_MONTHS = 24;

    // How many refusals an address is given before it is let go. Three nights of trying is enough to tell a mailbox momentarily full from one that no longer exists, and the row is dropped rather than left to be retried for two years
    public const int MAX_ATTEMPTS = 3;

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

        $releaseAlert = $this->releaseAlertRepository->findOneByBookAndEmail($book, $email);

        $subscribing = null === $releaseAlert;

        if ($subscribing) {
            $releaseAlert = new BookReleaseAlert()
                ->setBook($book)
                ->setEmail($email)
                ->setLocale($locale)
            ;
            $this->em->persist($releaseAlert);
        } else {
            // Somebody subscribing again, from another browser or in another language: the unique constraint leaves no second row to create
            $releaseAlert->renew($locale);
        }

        $this->em->flush();

        // Only the first time that address is written down. It is the only way out offered before the parution - anybody whose address was typed by somebody else unsubscribes from here - but sending it again on each submission would let the same form mail a stranger as often as the limiter allows
        if ($subscribing) {
            $this->sendConfirmation($releaseAlert);
        }

        return true;
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
            $book = $releaseAlert->getBook();
            $bookPath = null === $book ? null : $this->bookPublicUrlResolver->resolvePath('book_display', ['slug' => $book->getSlug()]);

            // A site that has stopped serving its book pages has nothing left to point at: the subscription is dropped rather than kept for a link that no longer exists
            if (null === $book || null === $bookPath) {
                $this->drop($releaseAlert);

                continue;
            }

            $bookUrl = $this->bookPublicUrlResolver->resolve('book_display', ['slug' => $book->getSlug()]);

            // The page is served and the site has no address of its own: raising says the installation is half-done, where dropping would take every waiting list away without a word (see BookPublicUrlResolver::resolve() - the two nulls do not mean the same thing)
            if (null === $bookUrl) {
                throw new \LogicException('No "site-url" is configured, so a release alert has no absolute address to send.');
            }

            // A send that failed leaves the row waiting rather than deleted: the next run tries again, which is the whole point of holding the queue in the database - but the refusal is counted, an address that never accepts anything being let go rather than retried for ever
            if (!$this->sendRelease($releaseAlert, $book, $bookUrl)) {
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

    // The acknowledgement, which carries the way out: the parution e-mail cannot: by then the row is gone and there is nothing left to unsubscribe from
    private function sendConfirmation(BookReleaseAlert $releaseAlert): bool
    {
        $book = $releaseAlert->getBook();

        if (null === $book) {
            return false;
        }

        return $this->send(
            $releaseAlert,
            BookEmailTemplateProvider::RELEASE_ALERT_CONFIRMATION,
            'label.release_alert_confirmation_subject',
            [
                'book_title' => (string) $book->getTitle(),
                'unsubscribe_url' => $this->urlGenerator->generate(
                    'book_release_alert_unsubscribe',
                    ['token' => $releaseAlert->getToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        );
    }

    // The one e-mail the whole waiting list exists for, and the last: no unsubscribe link, the row going with the send
    private function sendRelease(BookReleaseAlert $releaseAlert, Book $book, string $bookUrl): bool
    {
        return $this->send(
            $releaseAlert,
            BookEmailTemplateProvider::BOOK_RELEASED,
            'label.book_released_subject',
            [
                'book_title' => (string) $book->getTitle(),
                'book_url' => $bookUrl,
            ],
        );
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
