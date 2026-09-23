<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Service;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\BookBundle\Service\StripSocialContentSource;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;

class StripSocialContentSourceTest extends TestCase
{
    private function createStrip(int $id, string $published = '-1 month'): Strip
    {
        $strip = new Strip()->setTitle('Planche ' . $id)->setSlug('planche-' . $id)->setSummary('<p>Le loup</p>')->setPublished(new \DateTime($published));
        $strip->addMedia(new StripMedia()->setKind('card')->setName('medias/book/strips/card-' . $id . '.webp'));
        new \ReflectionProperty(Strip::class, 'id')->setValue($strip, $id);

        return $strip;
    }

    /**
     * @param list<Strip> $strips most recently published first, as the repository answers
     */
    private function createSource(array $strips, ?string $siteUrl = 'https://example.org'): StripSocialContentSource
    {
        $repository = $this->createStub(StripRepository::class);
        $repository->method('findAllPublished')->willReturn($strips);
        $repository->method('find')->willReturn($strips[0] ?? null);

        $urlResolver = $this->createStub(BookPublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(static fn (string $route, array $parameters): ?string => null === $siteUrl ? null : $siteUrl . '/strips/' . $parameters['slug']);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        return new StripSocialContentSource($repository, $urlResolver, $siteUrlResolver, '/var/www/site');
    }

    public function testTheOldestPlancheNotPostedYetIsHandedOverWithItsCard(): void
    {
        $content = $this->createSource([$this->createStrip(3), $this->createStrip(2), $this->createStrip(1)])->getNextContent(['1']);

        $this->assertSame('2', $content?->sourceId);
        $this->assertSame('https://example.org/strips/planche-2', $content->url);
        $this->assertSame('/var/www/site/public/medias/book/strips/card-2.webp', $content->imagePath);
    }

    public function testNothingIsHandedOverWithoutAPublicUrl(): void
    {
        $this->assertNull($this->createSource([$this->createStrip(1)], null)->getNextContent([]));
    }

    public function testAPlancheIsPostedOnce(): void
    {
        $this->assertNull($this->createSource([])->getRepeatAfterDays());
    }

    // A serie set aside takes its planches off the site, and off the networks with them
    public function testAPlancheOfASerieSetAsideIsNotReadAgain(): void
    {
        $strip = $this->createStrip(1);
        $this->assertSame('1', $this->createSource([$strip])->getContent('1')?->sourceId);

        $strip->setSerie(new Serie()->setHidden(true));
        $this->assertNull($this->createSource([$strip])->getContent('1'));
    }
}
