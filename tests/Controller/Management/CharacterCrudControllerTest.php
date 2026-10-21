<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\CharacterCrudController;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The drag and drop of the characters' index: a position only means something inside a serie, so the order posted is saved for one serie and nothing else - and the row actions it sits beside
class CharacterCrudControllerTest extends TestCase
{
    private const int SERIE_ID = 3;

    // The order posted becomes the positions, and the answer says what was saved
    public function testTheOrderPostedBecomesThePositions(): void
    {
        $first = $this->character(10, self::SERIE_ID);
        $second = $this->character(11, self::SERIE_ID);

        $response = $this->reorder([$first, $second], [11, 10], self::SERIE_ID);

        $this->assertSame(['positions' => [11 => 0, 10 => 1]], json_decode((string) $response->getContent(), true));
        $this->assertSame(1, $first->getPosition());
        $this->assertSame(0, $second->getPosition());
    }

    // An id the database no longer holds keeps its rank in the order but is saved for nobody
    public function testAnIdThatIsGoneIsSkipped(): void
    {
        $character = $this->character(10, self::SERIE_ID);

        $response = $this->reorder([$character], [99, 10], self::SERIE_ID);

        $this->assertSame(['positions' => [10 => 1]], json_decode((string) $response->getContent(), true));
    }

    // A character of another serie is refused outright rather than renumbered among people it is not ranked with
    public function testACharacterOfAnotherSerieIsRefused(): void
    {
        $character = $this->character(10, self::SERIE_ID + 1);

        $this->expectException(AccessDeniedException::class);

        $this->reorder([$character], [10], self::SERIE_ID);
    }

    // The reorder is a POST that changes rows, so a token forged nowhere gets nothing done
    public function testAReorderWithoutAValidTokenIsRefused(): void
    {
        $character = $this->character(10, self::SERIE_ID);

        try {
            $this->reorder([$character], [10], self::SERIE_ID, csrfValid: false);
            $this->fail('A reorder without a valid token went through.');
        } catch (AccessDeniedException) {
            $this->assertSame(0, $character->getPosition());
        }
    }

    // Sorting the catalog is the editor's, whatever the browser posts
    public function testAVisitorWithoutTheEditorRoleIsTurnedAway(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->reorder([], [10], self::SERIE_ID, granted: false);
    }

    // Every row action icon-only with its title, in the order of the five other screens, and no detail screen
    public function testIndexRowActionsAreIconOnly(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters, string $domain): string => $domain . ':' . $id);

        $contentLocaleScreen = $this->createStub(ContentLocaleScreen::class);
        $contentLocaleScreen->method('action')->willReturn(Action::new('translate', 'Translate', 'fa fa-language')->linkToUrl('/translate'));

        $controller = new CharacterCrudController($this->createStub(ConfigServiceInterface::class));
        $controller->setContainer($this->servicesContainer([
            ContentLocaleScreen::class => $contentLocaleScreen,
            BookTranslator::class => $this->createStub(BookTranslator::class),
            TranslatorInterface::class => $translator,
        ]));

        $dto = $controller->configureActions(Actions::new()->add(Crud::PAGE_INDEX, Action::EDIT)->add(Crud::PAGE_INDEX, Action::DELETE))->getAsDto(null);
        $actions = $dto->getActions()[Crud::PAGE_INDEX];

        $this->assertSame([Action::EDIT, 'translate', Action::DELETE], array_keys($actions));
        $titles = ['EasyAdminBundle:action.edit', 'book:action.translate', 'EasyAdminBundle:action.delete'];
        foreach (array_values($actions) as $index => $action) {
            $this->assertFalse($action->getLabel());
            $this->assertSame($titles[$index], $action->getHtmlAttributes()['title']);
        }
        $this->assertContains(Action::DETAIL, $dto->getDisabledActions());
    }

    /** @param list<Character> $found @param list<int> $ids */
    private function reorder(array $found, array $ids, int $group, bool $csrfValid = true, bool $granted = true): JsonResponse
    {
        $request = new Request(content: (string) json_encode(['group' => (string) $group, 'ids' => $ids, '_token' => 'posted-token']));

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($found);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $controller = new CharacterCrudController($configService);
        $controller->setContainer($this->container($csrfValid, $granted));

        return $controller->reorder($request, $entityManager);
    }

    private function character(int $id, int $serieId): Character
    {
        $serie = new Serie();
        new \ReflectionProperty(Serie::class, 'id')->setValue($serie, $serieId);

        $character = new Character();
        new \ReflectionProperty(Character::class, 'id')->setValue($character, $id);
        $character->setSerie($serie);

        return $character;
    }

    private function container(bool $csrfValid, bool $granted): ContainerInterface
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($csrfValid);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        return $this->servicesContainer([
            'security.csrf.token_manager' => $csrfTokenManager,
            'security.authorization_checker' => $authorizationChecker,
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
