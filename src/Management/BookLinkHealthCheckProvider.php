<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Where a catalog sends its readers away: the platform addresses each book declares (see BookLink), checked one by one so a store that closed, a podcast feed that moved or a mistyped address surfaces on the Health check dashboard instead of on a reader's screen. The bundle's own pages are checked elsewhere - ConfigBundle's DeclaredUrlsHealthCheckProvider already runs over what BookSitemapProvider declares
// No command of its own and no scheduler entry: c975l:health-check:run --frequency=weekly is already scheduled by ConfigBundle (see ConfigMaintenanceTaskProvider), and a provider is the whole of what a bundle contributes. One run on demand is c975l:health-check:run --kind=book-links
#[AsHealthCheck(AsHealthCheck::FREQUENCY_WEEKLY)]
class BookLinkHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    // What the label column holds, the row being persisted as it is returned here
    private const int LABEL_MAX_LENGTH = 255;

    // A platform answering one of these is up and simply refuses to be probed - most stores turn down a HEAD carrying no browser behind it. Reported as skipped rather than as an error: there is nothing for the editor to fix, and a red row nobody can act on is what makes a dashboard get ignored
    private const array REFUSED_CODES = [401, 403, 405, 429];

    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly BookCustomizationRegistry $bookCustomizationRegistry,
        private readonly UrlStatusChecker $urlStatusChecker,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'book-links';
    }

    // Every book, published or not: a book to be published shows its pre-order links on its page, and those are precisely the ones worth catching before its release day
    public function runChecks(): array
    {
        $rows = [];

        foreach ($this->bookService->findAll() as $book) {
            foreach ($book->getLinks() as $link) {
                $url = trim((string) $link->getUrl());
                if ('' === $url) {
                    continue;
                }

                // Results are kept per (url, kind), so two books selling on the same address are one row - the second would otherwise hide the first on the dashboard. Which books share it is kept in the details
                if (isset($rows[$url])) {
                    $rows[$url]['details']['books'][] = $this->labelOf($book, $link);
                    continue;
                }

                $rows[$url] = $this->check($url, $book, $link);
            }
        }

        return array_values($rows);
    }

    // One link's verdict, read from the status code alone (see UrlStatusChecker): the checker tells a page answering 404 from a host that never answered at all, and those are two different things to report
    private function check(string $url, Book $book, BookLink $link): array
    {
        $label = $this->labelOf($book, $link);

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_link_invalid', [], $book, $link, null);
        }

        $code = $this->urlStatusChecker->status($url);

        [$status, $message] = match (true) {
            null === $code => [HealthCheckResult::STATUS_ERROR, 'label.health_check_link_unreachable'],
            $code < 400 => [HealthCheckResult::STATUS_OK, 'label.health_check_link_ok'],
            \in_array($code, self::REFUSED_CODES, true) => [HealthCheckResult::STATUS_SKIPPED, 'label.health_check_link_refused'],
            default => [HealthCheckResult::STATUS_ERROR, 'label.health_check_link_broken'],
        };

        return $this->row($url, $label, $status, $message, ['%code%' => $code], $book, $link, $code);
    }

    /** @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string} */
    private function row(string $url, string $label, string $status, string $message, array $parameters, Book $book, BookLink $link, ?int $code): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => $status,
            'summary' => $this->translator->trans($message, $parameters, 'book'),
            'details' => [
                'kind' => $link->getKind(),
                'slug' => $book->getSlug(),
                'httpCode' => $code,
                'books' => [$label],
            ],
            'editUrl' => $this->editUrl($book),
        ];
    }

    // The book and the platform the address was declared on, which is what tells two rows apart on a dashboard listing nothing but urls
    private function labelOf(Book $book, BookLink $link): string
    {
        $label = sprintf('%s - %s', $book->getTitle(), $this->bookCustomizationRegistry->getLinkLabel($link));

        return mb_substr($label, 0, self::LABEL_MAX_LENGTH);
    }

    // The book's edit screen, opened on the editions its links are written in (see BookEditUrlExtension for the same query param). Swallowed like there, and for the same reason: this runs from a cron, and losing the dashboard's edit button beats losing the whole run
    private function editUrl(Book $book): ?string
    {
        if (null === $book->getId()) {
            return null;
        }

        try {
            return $this->adminUrlGenerator
                ->unsetAll()
                ->setController(BookCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($book->getId())
                ->set('focusField', 'editions')
                ->generateUrl()
            ;
        } catch (\Throwable) {
            return null;
        }
    }
}
