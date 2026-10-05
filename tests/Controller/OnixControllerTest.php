<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\OnixController;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookOnixBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

// The feed is served as XML, cached an hour, in the name of the publisher the settings give - the site's name for want of one
class OnixControllerTest extends TestCase
{
    /** @var array{sender?: string, baseUrl?: string} what the builder was handed */
    private array $built = [];

    public function testTheFeedIsPublicXml(): void
    {
        $response = $this->createController(['book-onix-publisher' => 'Éditions Test'])->feed(Request::create('https://example.org/onix.xml'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<ONIXMessage/>', $response->getContent());
        $this->assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame('Éditions Test', $this->built['sender']);
        $this->assertSame('https://example.org', $this->built['baseUrl']);
    }

    public function testThePublisherFallsBackOnTheSiteName(): void
    {
        $this->createController(['book-onix-publisher' => ' ', 'site-name' => 'Mon site'])->feed(Request::create('https://example.org/onix.xml'));

        $this->assertSame('Mon site', $this->built['sender']);
    }

    /** @param array<string, string> $configs */
    private function createController(array $configs): OnixController
    {
        $repository = $this->createStub(BookRepository::class);
        $repository->method('findAllForOnix')->willReturn([]);

        $builder = $this->createStub(BookOnixBuilder::class);
        $builder->method('build')->willReturnCallback(function (array $books, string $sender, string $baseUrl): string {
            $this->built = ['sender' => $sender, 'baseUrl' => $baseUrl];

            return '<ONIXMessage/>';
        });

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): ?string => $configs[$key] ?? null);

        return new OnixController($repository, $builder, $configService);
    }
}
