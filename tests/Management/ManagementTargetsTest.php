<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Management\BookGuidedProjectProvider;
use c975L\BookBundle\Management\LinkableRouteProvider;
use c975L\BookBundle\Management\MenuProvider;
use c975L\BookBundle\Repository\BookCategoryRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Tests\BookPublicUrlGeneratorTestTrait;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// Every CRUD controller and route this bundle's management providers name, checked against what its controllers actually declare - see ConfigBundle's ManagementTargetsTestCase
class ManagementTargetsTest extends ManagementTargetsTestCase
{
    use BookPublicUrlGeneratorTestTrait;

    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider($this->createStub(ConfigServiceInterface::class)),
            new LinkableRouteProvider($this->createRoutePrefix(self::CATEGORY_PREFIX_ENABLED), $this->categoryRepository(), $this->contributorRepository(), $this->serieRepository(), $this->createStub(TranslatorInterface::class)),
            // The recording generator, so the CRUD controllers each project opens on are captured on their way through
            new BookGuidedProjectProvider($this->adminUrlGenerator(), $this->createStub(ConfigServiceInterface::class)),
        ];
    }

    // The categories are the one family a fresh install serves no page for (see config/configs.json): the entry is filled in here, so the route their entries name is checked like every other
    private const array CATEGORY_PREFIX_ENABLED = ['book-route-categories' => 'categories'];

    // One category, for the same reason as the serie below: an empty repository would leave its index as the only linkable target
    private function categoryRepository(): BookCategoryRepository
    {
        $repository = $this->createStub(BookCategoryRepository::class);
        $repository->method('findAll')->willReturn([new BookCategory()->setSlug('romans')->setTitle('Romans')]);

        return $repository;
    }

    // One serie is enough to have the route its entries name checked too - an empty repository would leave the three indexes as the only linkable targets
    private function serieRepository(): SerieRepository
    {
        $repository = $this->createStub(SerieRepository::class);
        $repository->method('findAll')->willReturn([new Serie()->setSlug('la-compagnie-des-ombres')->setTitle('La Compagnie des Ombres')]);

        return $repository;
    }

    // One person, for the same reason: an empty repository would leave the indexes as the only linkable targets, and the route their entries name unchecked
    private function contributorRepository(): ContributorRepository
    {
        $repository = $this->createStub(ContributorRepository::class);
        $repository->method('findAll')->willReturn([new Contributor()->setSlug('tim-loval')->setName('Tim Loval')]);

        return $repository;
    }

    // This bundle's own controllers on top of ConfigBundle's: the public ones carry the routes its linkable entries name, the management ones those of its menus
    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller', __DIR__ . '/../../src/Controller/Management'];
    }
}
