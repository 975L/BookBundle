<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\SerieCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use c975L\BookBundle\Management\BookImportProvider;
use c975L\BookBundle\Management\SerieImportProvider;
use c975L\BookBundle\Management\StripImportProvider;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The trash, the exports and the copy are written once and used by the three screens (see Trait\TrashableCrudTrait): what each screen has to name of its own, and what it would silently lose by naming it wrong
class TrashableCrudTraitTest extends TestCase
{
    private const array CONTROLLERS = [BookCrudController::class, SerieCrudController::class, StripCrudController::class];

    private const array SHARED_ACTIONS = ['duplicate', 'restore', 'deletePermanently', 'exportSql', 'exportCsv', 'exportJson', 'exportSelection'];

    private const array NAMING_CONSTANTS = [
        'RESTORE_CSRF_TOKEN',
        'DELETE_PERMANENTLY_CSRF_TOKEN',
        'DUPLICATE_CSRF_TOKEN',
        'DISPLAY_ROUTE',
        'EXPORT_TABLE',
        'EXPORT_KIND',
        'TRASH_BACK_LABEL',
        'TRASH_BACK_ICON',
        'FLASH_DUPLICATED',
        'FLASH_RESTORED',
        'FLASH_DELETED_PERMANENTLY',
    ];

    /** @return iterable<string, array{class-string}> */
    public static function controllers(): iterable
    {
        foreach (self::CONTROLLERS as $controller) {
            yield new \ReflectionClass($controller)->getShortName() => [$controller];
        }
    }

    // An action of the trait reaches the admin only through the #[AdminRoute] it carries - EasyAdmin reads them off the controller, where a trait's methods stand as its own (see AdminRouteGenerator)
    #[DataProvider('controllers')]
    public function testEachScreenExposesTheSharedActionsAsAdminRoutes(string $controller): void
    {
        foreach (self::SHARED_ACTIONS as $action) {
            $method = new \ReflectionMethod($controller, $action);

            $this->assertNotSame([], $method->getAttributes(AdminRoute::class), sprintf('"%s::%s()" carries no #[AdminRoute], so nothing reaches it', $controller, $action));
            $this->assertSame($controller, $method->getDeclaringClass()->getName());
        }
    }

    // The words the shared code is given to name this very family
    #[DataProvider('controllers')]
    public function testEachScreenNamesItsOwnTableRouteAndWording(string $controller): void
    {
        foreach (self::NAMING_CONSTANTS as $constant) {
            $value = new \ReflectionClass($controller)->getConstant($constant);

            $this->assertIsString($value, sprintf('"%s" declares no %s, which the shared trash and exports read', $controller, $constant));
            $this->assertNotSame('', $value);
        }
    }

    // Two screens sharing one of these would act on the other's rows: the same table exported under both names, or a token minted on one screen accepted on the other
    #[DataProvider('namingConstants')]
    public function testNoTwoScreensNameTheSameThing(string $constant): void
    {
        $values = array_map(static fn (string $controller): mixed => new \ReflectionClass($controller)->getConstant($constant), self::CONTROLLERS);

        $this->assertCount(3, array_unique($values), sprintf('Two of the three screens declare the same %s', $constant));
    }

    /** @return iterable<string, array{string}> */
    public static function namingConstants(): iterable
    {
        foreach (self::NAMING_CONSTANTS as $constant) {
            yield $constant => [$constant];
        }
    }

    // The route each screen sends its "view on site" button and its redirects to, which is the public page of that family
    public function testTheRoutesNamedAreThePublicPagesOfTheThreeFamilies(): void
    {
        $routes = array_map(static fn (string $controller): mixed => new \ReflectionClass($controller)->getConstant('DISPLAY_ROUTE'), self::CONTROLLERS);

        $this->assertSame(['book_display', 'serie_display', 'strip_display'], $routes);
    }

    // The kind an archive is stamped with is what routes it back on import: a screen naming one no provider claims exports a zip ConfigBundle's "Import content" screen turns down
    public function testEachScreenExportsUnderTheKindItsImportProviderClaims(): void
    {
        $kinds = array_map(static fn (string $controller): mixed => new \ReflectionClass($controller)->getConstant('EXPORT_KIND'), self::CONTROLLERS);

        $this->assertSame([BookImportProvider::KIND, SerieImportProvider::KIND, StripImportProvider::KIND], $kinds);
    }

    // A flash key missing from a catalog shows as its raw key on the screen that just worked
    #[DataProvider('controllers')]
    public function testTheFlashKeysAreTranslated(string $controller): void
    {
        $catalog = self::catalog();

        foreach (['FLASH_DUPLICATED', 'FLASH_RESTORED', 'FLASH_DELETED_PERMANENTLY'] as $constant) {
            $key = (string) new \ReflectionClass($controller)->getConstant($constant);

            $this->assertContains($key, $catalog, sprintf('"%s" is missing from the french catalog', $key));
        }
    }

    /** @return list<string> */
    private static function catalog(): array
    {
        $xml = simplexml_load_file(\dirname(__DIR__, 3) . '/translations/book.fr.xlf');
        $keys = [];
        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $keys[] = (string) $unit->source;
        }

        return $keys;
    }
}
