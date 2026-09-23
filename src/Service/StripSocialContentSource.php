<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\StripRepository;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Hands SocialBundle's publication the published planches, oldest first - a site without SocialBundle simply never asks. What went out where is SocialBundle's to record
class StripSocialContentSource implements SocialContentSourceInterface
{
    public function __construct(
        private readonly StripRepository $stripRepository,
        private readonly BookPublicUrlResolver $bookPublicUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getSourceType(): string
    {
        return 'strip';
    }

    // Never: a planche read twice is a rerun, a serie keeps growing with new ones to post
    public function getRepeatAfterDays(): ?int
    {
        return null;
    }

    public function getNextContent(array $excludedIds): ?SocialContent
    {
        foreach (array_reverse($this->stripRepository->findAllPublished()) as $strip) {
            if (!\in_array((string) $strip->getId(), $excludedIds, true)) {
                return $this->toContent($strip);
            }
        }

        return null;
    }

    // Null for a planche taken off the site since its post was prepared, or whose serie was
    public function getContent(string $sourceId): ?SocialContent
    {
        $strip = $this->stripRepository->find((int) $sourceId);
        $published = $strip?->getPublished();

        return $strip instanceof Strip && !$strip->isDeleted() && !$strip->isHidden() && !($strip->getSerie()?->isHidden() ?? false) && null !== $published && $published <= new \DateTime()
            ? $this->toContent($strip)
            : null;
    }

    // Null while the planche has no public url - "site-url" unset, or the planches not served on this site
    private function toContent(Strip $strip): ?SocialContent
    {
        $url = $this->bookPublicUrlResolver->resolve('strip_display', ['slug' => $strip->getSlug()]);
        if (null === $url) {
            return null;
        }

        // What the planche's own page shares: the whole page where it is a drawing, the card where it is words
        $image = $strip->getMediasByKind('page')->first() ?: $strip->getMediasByKind('card')->first() ?: $strip->getMedias()->first() ?: null;
        $name = $image?->getName();

        return new SocialContent(
            sourceId: (string) $strip->getId(),
            title: (string) $strip->getTitle(),
            url: $url,
            imagePath: null === $name ? null : $this->projectDir . '/public/' . $name,
            imageUrl: null === $name ? null : $this->siteUrlResolver->siteUrl() . '/' . $name,
            imageAlt: (string) $strip->getTitle(),
            variables: array_filter([
                'description' => trim(html_entity_decode(strip_tags((string) $strip->getSummary()))),
                'serie' => (string) $strip->getSerie()?->getTitle(),
            ]),
        );
    }
}
