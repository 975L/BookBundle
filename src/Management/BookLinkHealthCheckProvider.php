<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Contract\PlatformLinkInterface;
use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\ContributorCrudController;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Enum\BookLinkGroup;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use c975L\BookBundle\Service\BookServiceInterface;
use c975L\BookBundle\Service\ContributorServiceInterface;
use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Where a catalog sends its readers away: the platform addresses a book declares (see BookLink) and the ones a person declares (see ContributorLink), checked one by one so a store that closed, a podcast feed that moved or a mistyped address surfaces on the Health check dashboard instead of on a reader's screen. The bundle's own pages are checked elsewhere - ConfigBundle's DeclaredUrlsHealthCheckProvider already runs over what BookSitemapProvider declares. No command of its own and no scheduler entry: c975l:health-check:run --frequency=weekly is already scheduled by ConfigBundle (see ConfigMaintenanceTaskProvider), and a provider is the whole of what a bundle contributes. One run on demand is c975l:health-check:run --kind=book-links
#[AsHealthCheck(AsHealthCheck::FREQUENCY_WEEKLY)]
class BookLinkHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    // What the label column holds, the row being persisted as it is returned here
    private const int LABEL_MAX_LENGTH = 255;

    // A platform answering one of these is up and simply refuses to be probed - most stores turn down a HEAD carrying no browser behind it. Reported as skipped rather than as an error: there is nothing for the editor to fix, and a red row nobody can act on is what makes a dashboard get ignored
    private const array REFUSED_CODES = [401, 403, 405, 429];

    // Which collection of a book's screen the address is typed in, read from the platform's own group (see BookCrudController, where each gesture holds one): the pencil of the dashboard opens the very field to correct, as the pencils of the public page do (see Twig\BookEditUrlExtension)
    private const array BOOK_LINK_FIELDS = [
        BookLinkGroup::Epub->value => 'buyLinks',
        BookLinkGroup::Audio->value => 'listenLinks',
        BookLinkGroup::Podcast->value => 'listenLinks',
        BookLinkGroup::Video->value => 'watchLinks',
    ];

    // A platform the vocabulary files under no group of the three gestures is written in the collection the screen keeps for it
    private const string BOOK_OTHER_LINKS_FIELD = 'otherLinks';

    // A person's platforms are one collection, their page holding one card (see ContributorCrudController and Contributor:Shops)
    private const string CONTRIBUTOR_LINKS_FIELD = 'links';

    // The address the site declares for itself, which is what an address of its own - the shop's own basket, stored as "/shop/..." - is probed against
    private const string SITE_URL_CONFIG = 'site-url';

    public function __construct(
        private readonly BookServiceInterface $bookService,
        private readonly ContributorServiceInterface $contributorService,
        private readonly BookCustomizationRegistry $bookCustomizationRegistry,
        private readonly UrlStatusChecker $urlStatusChecker,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'book-links';
    }

    // Every book, published or not: a book to be published shows its pre-order links on its page, and those are precisely the ones worth catching before its release day. The people follow, their own store pages being read from their page the same way - someone set aside is left out, their page answering 404 for as long as the box is ticked
    public function runChecks(): array
    {
        /** @var array<string, array<string, mixed>> $rows the results by the address they report on */
        $rows = [];

        foreach ($this->bookService->findAll() as $book) {
            $this->collect($rows, $book);
        }

        foreach ($this->contributorService->findAll() as $contributor) {
            $this->collect($rows, $contributor);
        }

        return array_values($rows);
    }

    // One catalog row's platforms, added to what is already reported
    /** @param array<string, array<string, mixed>> $rows */
    private function collect(array &$rows, Book | Contributor $owner): void
    {
        foreach ($owner->getLinks() as $link) {
            $url = trim((string) $link->getUrl());
            if ('' === $url) {
                continue;
            }

            // Results are kept per url, so a book and its author sending to the same store are one row - the second would otherwise hide the first on the dashboard. Who declared it is kept in the details
            if (isset($rows[$url])) {
                $rows[$url]['details']['declaredOn'][] = $this->labelOf($owner, $link);
                continue;
            }

            $rows[$url] = $this->check($url, $owner, $link);
        }
    }

    // One link's verdict, read from the status code alone (see UrlStatusChecker): the checker tells a page answering 404 from a host that never answered at all, and those are two different things to report
    /** @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string} */
    private function check(string $url, Book | Contributor $owner, PlatformLinkInterface $link): array
    {
        $label = $this->labelOf($owner, $link);

        // The site's own shop is written as it is read on the page - "/shop/category/...", never the whole address - so it is probed against the address the site declares for itself, and reported as nothing to probe when it declares none
        if (str_starts_with($url, '/')) {
            $base = rtrim((string) $this->configService->get(self::SITE_URL_CONFIG), '/');

            if ('' === $base) {
                return $this->row($url, $label, HealthCheckResult::STATUS_SKIPPED, 'label.health_check_link_internal', [], $owner, $link, null);
            }

            return $this->verdict($url, $base . $url, $label, $owner, $link);
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_link_invalid', [], $owner, $link, null);
        }

        return $this->verdict($url, $url, $label, $owner, $link);
    }

    // What the host answered, read from the status code alone: the row is filed under the address as it is stored, whichever one was actually probed
    /** @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string} */
    private function verdict(string $url, string $probed, string $label, Book | Contributor $owner, PlatformLinkInterface $link): array
    {
        $code = $this->urlStatusChecker->status($probed);

        [$status, $message] = match (true) {
            null === $code => [HealthCheckResult::STATUS_ERROR, 'label.health_check_link_unreachable'],
            $code < 400 => [HealthCheckResult::STATUS_OK, 'label.health_check_link_ok'],
            \in_array($code, self::REFUSED_CODES, true) => [HealthCheckResult::STATUS_SKIPPED, 'label.health_check_link_refused'],
            default => [HealthCheckResult::STATUS_ERROR, 'label.health_check_link_broken'],
        };

        return $this->row($url, $label, $status, $message, ['%code%' => $code], $owner, $link, $code);
    }

    /** @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string} */
    private function row(string $url, string $label, string $status, string $message, array $parameters, Book | Contributor $owner, PlatformLinkInterface $link, ?int $code): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => $status,
            'summary' => $this->translator->trans($message, $parameters, 'book'),
            'details' => [
                'kind' => $link->getKind(),
                'slug' => $owner->getSlug(),
                'httpCode' => $code,
                'declaredOn' => [$label],
            ],
            'editUrl' => $this->editUrl($owner, $link),
        ];
    }

    // What the address was declared on - a book by its title, a person by their name - and the platform, which is what tells two rows apart on a dashboard listing nothing but urls
    private function labelOf(Book | Contributor $owner, PlatformLinkInterface $link): string
    {
        $name = $owner instanceof Book ? $owner->getTitle() : $owner->getName();
        $label = sprintf('%s - %s', $name, $this->bookCustomizationRegistry->getLinkLabel($link));

        return mb_substr($label, 0, self::LABEL_MAX_LENGTH);
    }

    // The screen the address is typed on, opened on the very collection holding it. Swallowed as BookEditUrlExtension does, and for the same reason: this runs from a cron, and losing the dashboard's edit button beats losing the whole run
    private function editUrl(Book | Contributor $owner, PlatformLinkInterface $link): ?string
    {
        if (null === $owner->getId()) {
            return null;
        }

        $isBook = $owner instanceof Book;
        $field = $isBook
            ? self::BOOK_LINK_FIELDS[(string) $this->bookCustomizationRegistry->getLinkGroup($link)] ?? self::BOOK_OTHER_LINKS_FIELD
            : self::CONTRIBUTOR_LINKS_FIELD;

        try {
            return $this->adminUrlGenerator
                ->unsetAll()
                ->setController($isBook ? BookCrudController::class : ContributorCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($owner->getId())
                ->set('focusField', $field)
                ->generateUrl()
            ;
        } catch (\Throwable) {
            return null;
        }
    }
}
