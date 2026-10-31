<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests;

use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

// Shared by the controller tests building a controller by hand - the container AbstractController reads its services from, answering only the ones given
trait ServicesContainerTestTrait
{
    // The csrf and access checks of AbstractController answering as told, next to whatever else the test needs
    /** @param array<string, object> $services */
    private function securityContainer(bool $csrfValid, bool $granted, array $services = []): ContainerInterface
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($csrfValid);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        return $this->servicesContainer([
            'security.csrf.token_manager' => $csrfTokenManager,
            'security.authorization_checker' => $authorizationChecker,
            ...$services,
        ]);
    }

    /** @param array<string, object> $services */
    private function servicesContainer(array $services): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $container->method('get')->willReturnCallback(static fn (string $id) => $services[$id] ?? null);

        return $container;
    }
}
