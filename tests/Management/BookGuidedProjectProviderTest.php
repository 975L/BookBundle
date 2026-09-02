<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Management;

use c975L\BookBundle\Management\BookGuidedProjectProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class BookGuidedProjectProviderTest extends TestCase
{
    private function createAdminUrlGenerator(array &$controllers = []): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator, &$controllers) {
            $controllers[] = $controller;

            return $generator;
        });
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/book');

        return $generator;
    }

    private function createProvider(array &$controllers = []): BookGuidedProjectProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        // One bar per key rather than one for all: a project scoped to the admin is only told apart from the others if the two entries answer differently
        $configService->method('get')->willReturnCallback(
            static fn (string $key): string => 'site-role-admin' === $key ? 'ROLE_ADMIN' : 'ROLE_EDITOR'
        );

        return new BookGuidedProjectProvider($this->createAdminUrlGenerator($controllers), $configService);
    }

    private function projects(): array
    {
        return $this->createProvider()->getGuidedProjects();
    }

    // The 6000 block GuidedProjectProviderInterface reserves this bundle, at the step of 10 it states - an order shared with another provider's leaves their sequence to the order the providers happen to be registered in, which is what a block per bundle exists to prevent
    public function testGetGuidedProjectsContinuesTheOrderSequence(): void
    {
        $projects = $this->projects();

        $this->assertSame(
            ['book-contributor-creation', 'book-serie-creation', 'book-creation', 'book-media-move', 'book-composition', 'book-reader', 'book-sorting', 'book-strip-creation', 'book-duplication', 'book-version-publication', 'book-hidden', 'book-trash', 'book-export'],
            array_column($projects, 'slug')
        );
        $this->assertSame([6005, 6010, 6020, 6025, 6030, 6033, 6035, 6040, 6045, 6050, 6055, 6060, 6070], array_column($projects, 'order'));
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->projects() as $project) {
            $this->assertStringStartsWith('book-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    public function testEveryProjectCarriesTheBookTranslationDomainAndSteps(): void
    {
        foreach ($this->projects() as $project) {
            $this->assertSame('book', $project['translation_domain']);
            $this->assertNotEmpty($project['steps']);
        }
    }

    // Every catalog screen sits behind the site's editor role, so a parcours walking them is dropped for anybody else - the exports alone sit a role above, and a parcours highlighting buttons the user never sees is a broken one (see TrashableCrudTrait::configureActions)
    public function testEveryProjectCarriesTheRoleItsOwnScreensState(): void
    {
        $expected = array_fill_keys([
            'book-contributor-creation', 'book-serie-creation', 'book-creation', 'book-media-move', 'book-composition',
            'book-reader', 'book-sorting', 'book-strip-creation', 'book-duplication', 'book-version-publication', 'book-hidden', 'book-trash',
        ], 'ROLE_EDITOR') + ['book-export' => 'ROLE_ADMIN'];

        $roles = array_column($this->projects(), 'role', 'slug');

        $this->assertSame($expected, $roles);
    }

    public function testNoStepSetsBothUrlAndHighlight(): void
    {
        foreach ($this->projects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                $this->assertFalse(
                    isset($step['url']) && isset($step['highlight']),
                    sprintf('Step %d of "%s" sets both url and highlight', $index, $project['slug'])
                );
            }
        }
    }

    // Only the opening step leaves the screen, everything after it walking the one the user has been sent to
    public function testOnlyTheFirstStepOfEachProjectCarriesAnUrl(): void
    {
        foreach ($this->projects() as $project) {
            $steps = $project['steps'];

            $this->assertArrayHasKey('url', $steps[0], sprintf('Project "%s" does not open on a screen', $project['slug']));

            foreach (array_slice($steps, 1) as $index => $step) {
                $this->assertArrayNotHasKey('url', $step, sprintf('Step %d of "%s" leaves the screen again', $index + 1, $project['slug']));
            }
        }
    }

    // The catalog's own screens, each parcours opening on the one its task belongs to
    public function testEveryProjectOpensOnACatalogCrudIndex(): void
    {
        $controllers = [];
        $this->createProvider($controllers)->getGuidedProjects();

        $this->assertSame(
            ['ContributorCrudController', 'SerieCrudController', 'BookCrudController', 'BookCrudController', 'BookCrudController', 'BookCrudController', 'SerieCrudController', 'StripCrudController', 'BookCrudController', 'BookCrudController', 'BookCrudController', 'BookCrudController', 'BookCrudController'],
            array_map(static fn (string $fqcn): string => basename(str_replace('\\', '/', $fqcn)), $controllers)
        );
    }

    // EasyAdmin renders the form's save button as action-saveAndReturn, .action-save matching nothing and leaving the step highlighting an empty selection
    public function testEverySaveStepHighlightsTheEasyAdminSaveButton(): void
    {
        $saveSteps = [];

        foreach ($this->projects() as $project) {
            foreach ($project['steps'] as $step) {
                if (str_ends_with($step['label'], '_save')) {
                    $saveSteps[] = $step;
                }
            }
        }

        $this->assertCount(9, $saveSteps, 'The parcours saving nothing are those whose gestures are recorded on the spot: the trash, the sorting, the file move and the export');

        foreach ($saveSteps as $step) {
            $this->assertSame('.action-saveAndReturn', $step['highlight']);
        }
    }

    // A built-in action named by a step has to be one EasyAdmin still knows, the CRUD controllers' own ones being spelled out here
    public function testEveryBuiltInActionHighlightedIsAnEasyAdminOne(): void
    {
        // The ones this bundle declares itself, next to EasyAdmin's (see BookCrudController and TrashableCrudTrait) - "export" being the group the three formats sit in, and the only part of it a step can point at
        $known = [...new \ReflectionClass(Action::class)->getConstants(), 'publishVersion', 'trash', 'duplicate', 'export', 'exportSql', 'exportCsv', 'exportJson', 'exportSelection'];

        foreach ($this->highlights() as $highlight) {
            if (!preg_match('/^\.action-([A-Za-z]+)$/', $highlight, $matches)) {
                continue;
            }

            $this->assertContains(
                $matches[1],
                $known,
                sprintf('"%s" is neither an EasyAdmin action nor one of this bundle\'s own', $matches[1])
            );
        }
    }

    // Restoring a row and deleting it for good sit at the admin's role, so a step highlighting them would show nothing to the editors the trash parcours is offered to - and the one parcours running at the admin's role exports the catalog rather than emptying its trash (see TrashableCrudTrait::configureActions)
    public function testNoStepHighlightsAnActionHeldAboveTheEditorRole(): void
    {
        $highlights = $this->highlights();

        $this->assertNotContains('.action-restore', $highlights);
        $this->assertNotContains('.action-deletePermanently', $highlights);
    }

    // A field is pointed at through the widget the user sees: TrixEditorType hides its textarea, an autocompleted association has its select clipped by TomSelect, and a plain choice or association is a native select only below UiBundle's threshold of 10 options - so only the ones that can never reach it, "#Serie_kind" and its two values, are pointed at through their own id
    public function testNoStepHighlightsAWidgetEasyAdminHidesFromSight(): void
    {
        $hidden = ['#Serie_summary', '#Serie_covers', '#Book_serie', '#Strip_serie', '#Strip_summary', '#Contributor_summary'];

        foreach ($this->highlights() as $highlight) {
            $this->assertNotContains($highlight, $hidden, sprintf('"%s" points at an element EasyAdmin renders out of sight', $highlight));
        }
    }

    // An association calling autocomplete() is rendered by CrudAutocompleteType, which prints its select under an inner field named "autocomplete": the id gains that suffix, and a step naming the property alone outlines nothing (see AssociationConfigurator and CrudAutocompleteSubscriber)
    public function testEveryAutocompletedAssociationIsHighlightedUnderItsInnerFieldId(): void
    {
        $autocompleted = $this->autocompletedProperties();

        $this->assertNotEmpty($autocompleted, 'No controller declares an autocompleted association anymore');

        foreach ($this->highlights() as $highlight) {
            if (!preg_match('/^#([A-Za-z]+)_([A-Za-z]+)(_autocomplete)?(?: |$)/', $highlight, $matches)) {
                continue;
            }

            $property = $matches[1] . '::' . $matches[2];
            $isSuffixed = isset($matches[3]);

            if (\in_array($property, $autocompleted, true)) {
                $this->assertTrue($isSuffixed, sprintf('"%s" names an autocompleted association, whose select is printed as "%s_autocomplete"', $highlight, $matches[2]));
            } else {
                $this->assertFalse($isSuffixed, sprintf('"%s" is not autocompleted, and its select carries no such inner field', $highlight));
            }
        }
    }

    // A marker renamed in a controller would leave its step highlighting nothing at all, the panel going on showing itself - "data-ui-*" belongs to UiBundle, whose row_attr builder the blocks field takes its own from
    public function testEveryDataAttributeHighlightedIsStillDeclared(): void
    {
        $sources = '';
        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/Management/*.php') as $controller) {
            $sources .= file_get_contents($controller);
        }
        $sources .= file_get_contents(\dirname(__DIR__, 2) . '/vendor/c975l/core-bundle/UiBundle/src/Service/BlockMoveRowAttrBuilder.php');
        // The markers of the file move are laid by a service of this bundle, not by a controller
        $sources .= file_get_contents(\dirname(__DIR__, 2) . '/src/Service/BookMediaMoveRowAttrBuilder.php');
        // "data-column" is EasyAdmin's own, the index cell of a boolean carrying no field id to point at instead
        $sources .= file_get_contents(\dirname(__DIR__, 2) . '/vendor/easycorp/easyadmin-bundle/templates/crud/index.html.twig');
        // "data-kind" is laid on the tiles of the block palette by UiBundle's picker, which builds them from the select it hides
        $sources .= file_get_contents(\dirname(__DIR__, 2) . '/vendor/c975l/core-bundle/UiBundle/assets/js/block-picker.js');

        $attributes = [];
        foreach ($this->highlights() as $highlight) {
            if (preg_match('/\[(data-[a-z-]+)/', $highlight, $matches)) {
                $attributes[] = $matches[1];
            }
        }

        $this->assertNotEmpty($attributes);

        foreach ($attributes as $attribute) {
            $this->assertStringContainsString($attribute, $sources, sprintf('No management controller declares "%s" anymore', $attribute));
        }
    }

    // A label or description with no translation reads as its own key in the panel, in whichever locale it is missing from
    public function testEveryLabelAndDescriptionIsTranslatedInEveryLocale(): void
    {
        foreach (['en', 'fr', 'es'] as $locale) {
            $translated = $this->translatedKeys($locale);

            foreach ($this->projects() as $project) {
                foreach ([$project, ...$project['steps']] as $item) {
                    $this->assertContains($item['label'], $translated, sprintf('"%s" is missing from the %s catalogue', $item['label'], $locale));
                    if (isset($item['description'])) {
                        $this->assertContains($item['description'], $translated, sprintf('"%s" is missing from the %s catalogue', $item['description'], $locale));
                    }
                }
            }
        }
    }

    // "Entity::property" for every field a management controller declares with autocomplete() - one segment per field, cut at the next one, so an "->autocomplete()" inside belongs to the field opening it
    /** @return list<string> */
    private function autocompletedProperties(): array
    {
        $found = [];

        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/Management/*CrudController.php') as $path) {
            $entity = str_replace('CrudController.php', '', basename($path));

            foreach (preg_split('/(?=\w+Field::new\()/', file_get_contents($path)) as $segment) {
                if (preg_match("/^\w+Field::new\('(\w+)'/", $segment, $matches) && str_contains($segment, '->autocomplete(')) {
                    $found[] = $entity . '::' . $matches[1];
                }
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function highlights(): array
    {
        $highlights = [];

        foreach ($this->projects() as $project) {
            foreach ($project['steps'] as $step) {
                if (isset($step['highlight'])) {
                    $highlights[] = $step['highlight'];
                }
            }
        }

        return $highlights;
    }

    /** @return list<string> */
    private function translatedKeys(string $locale): array
    {
        $xliff = new \DOMDocument();
        $xliff->load(\dirname(__DIR__, 2) . '/translations/book.' . $locale . '.xlf');

        $keys = [];
        foreach ($xliff->getElementsByTagName('source') as $source) {
            $keys[] = $source->textContent;
        }

        return $keys;
    }
}
