<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Management\SerieResolver;
use c975L\BookBundle\Repository\SerieRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

// A serie an archive names before its own import is created set aside, and the import screen says which books wait for it
class SerieResolverTest extends TestCase
{
    public function testAShellSerieIsCreatedHiddenAndWhatWaitsOnItIsSaid(): void
    {
        [$resolver, $session] = $this->resolver(null);
        $series = [];

        $serie = $resolver->resolve('item-1', null, $series, 'Tome 1');
        $resolver->resolve('item-1', null, $series, 'Tome 2');
        $resolver->reportWaiting();

        $this->assertTrue($serie?->isHidden());
        $this->assertSame(['flash.import_waiting_serie|item-1 : Tome 1, Tome 2'], $session->getFlashBag()->get('warning'));

        // Said once: the next import starts from nothing
        $resolver->reportWaiting();
        $this->assertSame([], $session->getFlashBag()->get('warning'));
    }

    // A serie the site already holds waits for nothing
    public function testASerieAlreadyThereIsNotSaid(): void
    {
        $existing = new Serie()->setSlug('item-1')->setTitle('item-1');
        new \ReflectionProperty(Serie::class, 'id')->setValue($existing, 3);
        [$resolver, $session] = $this->resolver($existing);
        $series = [];

        $resolver->resolve('item-1', null, $series, 'Tome 1');
        $resolver->reportWaiting();

        $this->assertSame([], $session->getFlashBag()->get('warning'));
    }

    // The resolver over the serie given, on a request holding a session
    /** @return array{SerieResolver, Session} */
    private function resolver(?Serie $existing): array
    {
        $repository = $this->createStub(SerieRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters): string => $key . '|' . $parameters['%list%']);

        $session = new Session(new MockArraySessionStorage(), flashes: new FlashBag());
        $request = new Request();
        $request->setSession($session);

        return [new SerieResolver($this->createStub(EntityManagerInterface::class), $repository, new RequestStack([$request]), $translator), $session];
    }
}
