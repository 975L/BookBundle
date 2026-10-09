<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\StripServiceInterface;
use c975L\ConfigBundle\Management\FeedProviderInterface;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// The latest planches (/feed/strip.xml) - a strip published one page at a time being what a feed is made for
class StripFeedProvider implements FeedProviderInterface
{
    public function __construct(
        private readonly StripServiceInterface $stripService,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Served at /feed/strip.xml
    public function getFeedName(): string
    {
        return 'strip';
    }

    // Title shown by a feed reader, in the language of the page announcing it
    public function getFeedTitle(): string
    {
        return $this->translator->trans('label.feed_strips', [], 'book');
    }

    // Nothing when the site does not serve its planches, BookPublicUrlResolver then giving no url (see BookRoutePrefix)
    public function getEntries(int $limit): array
    {
        $siteUrl = $this->siteUrlResolver->siteUrl();
        if (null === $siteUrl) {
            return [];
        }

        $entries = [];
        foreach ($this->stripService->findAllPublished($limit) as $strip) {
            $url = $this->bookPublicUrlResolver->resolve('strip_display', ['slug' => $strip->getSlug()]);
            if (null === $url) {
                return [];
            }

            $image = $strip->getShareMedia()?->getName();
            $entries[] = [
                'url' => $url,
                'title' => (string) $strip->getTitle(),
                'updated' => $strip->getPublished() ?? $strip->getModification() ?? new \DateTimeImmutable(),
                'summary' => $strip->getSummary(),
                'image' => null === $image ? null : $siteUrl . '/' . ltrim($image, '/'),
            ];
        }

        return $entries;
    }
}
