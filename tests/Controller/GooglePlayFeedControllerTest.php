<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller;

use c975L\BookBundle\Controller\GooglePlayFeedController;
use c975L\BookBundle\Service\GooglePlayFeed;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// The folders are served to the one user the settings name, nothing at all while the feed is not set up, and the ONIX only under its dated name
class GooglePlayFeedControllerTest extends TestCase
{
    private const array SETTINGS = ['book-google-user' => 'google', 'book-google-password' => 'secret42'];

    public function testNothingIsServedWithoutAPassword(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(['book-google-user' => 'google'])->onix($this->request('google', ''), '337R84F-rights', 'Test_20260101.xml');
    }

    public function testNothingIsServedWithoutACollection(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(self::SETTINGS, '')->onix($this->request('google', 'secret42'), '-rights', 'Test_20260101.xml');
    }

    public function testAWrongPasswordIsAskedAgain(): void
    {
        $response = $this->createController(self::SETTINGS)->onix($this->request('google', 'wrong'), '337R84F-rights', 'Test_20260101.xml');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringStartsWith('Basic', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function testTheOnixIsServedPrivately(): void
    {
        $response = $this->createController(self::SETTINGS)->onix($this->request('google', 'secret42'), '337R84F-rights', 'Test_20260101.xml');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<ONIXMessage/>', $response->getContent());
        $this->assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertSame('Thu, 01 Jan 2026 00:00:00 GMT', $response->headers->get('Last-Modified'));
    }

    public function testAnotherNameOrFolderIsNotFound(): void
    {
        $controller = $this->createController(self::SETTINGS);

        foreach ([['337R84F-rights', 'Test_20251231.xml'], ['OTHER-rights', 'Test_20260101.xml']] as [$folder, $name]) {
            try {
                $controller->onix($this->request('google', 'secret42'), $folder, $name);
                $this->fail(sprintf('%s/%s should not be found.', $folder, $name));
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // An audiobook's recording is served from the audio folder, any other name is not found
    public function testAnAudiobookIsServedFromItsFolder(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'mp3');
        $controller = $this->createController(self::SETTINGS, audiobooks: ['9782488750073.mp3' => ['path' => $path, 'modified' => new \DateTimeImmutable('2026-01-01')]]);

        $this->assertSame(200, $controller->audiobook($this->request('google', 'secret42'), '337R84F', '9782488750073.mp3')->getStatusCode());

        $this->expectException(NotFoundHttpException::class);
        try {
            $controller->audiobook($this->request('google', 'secret42'), '337R84F', '9782488750073.m4a');
        } finally {
            unlink($path);
        }
    }

    private function request(string $user, string $password): Request
    {
        return Request::create('https://example.org/google-livres/onix/337R84F-rights/Test_20260101.xml', server: ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $password]);
    }

    /** @param array<string, string> $configs */
    /** @param array<string, array{path: string, modified: \DateTimeImmutable}> $audiobooks */
    private function createController(array $configs, string $collection = '337R84F', array $audiobooks = []): GooglePlayFeedController
    {
        $feed = $this->createStub(GooglePlayFeed::class);
        $feed->method('collection')->willReturn($collection);
        $feed->method('audiobooks')->willReturn($audiobooks);
        $feed->method('onix')->willReturn(['name' => 'Test_20260101.xml', 'content' => '<ONIXMessage/>', 'modified' => new \DateTimeImmutable('2026-01-01 00:00:00 UTC')]);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): ?string => $configs[$key] ?? null);

        return new GooglePlayFeedController($feed, $configService);
    }
}
