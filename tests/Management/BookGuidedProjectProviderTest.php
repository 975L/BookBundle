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
        $configService->method('get')->willReturn('ROLE_EDITOR');

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
            ['book-serie-creation', 'book-creation', 'book-composition', 'book-strip-creation', 'book-version-publication', 'book-trash'],
            array_column($projects, 'slug')
        );
        $this->assertSame([6010, 6020, 6030, 6040, 6050, 6060], array_column($projects, 'order'));
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

    // Every catalog screen sits behind the site's editor role, so a parcours walking them is dropped for anybody else
    public function testEveryProjectCarriesTheEditorRole(): void
    {
        foreach ($this->projects() as $project) {
            $this->assertSame('ROLE_EDITOR', $project['role']);
        }
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

    // The three sidebar entries of the catalog, each parcours opening on the one its task belongs to
    public function testEveryProjectOpensOnACatalogCrudIndex(): void
    {
        $controllers = [];
        $this->createProvider($controllers)->getGuidedProjects();

        $this->assertSame(
            ['SerieCrudController', 'BookCrudController', 'BookCrudController', 'StripCrudController', 'BookCrudController', 'BookCrudController'],
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

        $this->assertCount(5, $saveSteps, 'Only the trash parcours saves nothing, its gestures being buttons of the index');

        foreach ($saveSteps as $step) {
            $this->assertSame('.action-saveAndReturn', $step['highlight']);
        }
    }

    // A built-in action named by a step has to be one EasyAdmin still knows, the CRUD controllers' own ones being spelled out here
    public function testEveryBuiltInActionHighlightedIsAnEasyAdminOne(): void
    {
        // The two this bundle declares itself, next to EasyAdmin's (see BookCrudController and TrashableCrudTrait)
        $known = [...new \ReflectionClass(Action::class)->getConstants(), 'publishVersion', 'trash'];

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

    // Restoring a row and deleting it for good sit at the admin's role, so a step highlighting them would show nothing to the editors the parcours is offered to (see TrashableCrudTrait::configureActions)
    public function testNoStepHighlightsAnActionHeldAboveTheEditorRole(): void
    {
        $highlights = $this->highlights();

        $this->assertNotContains('.action-restore', $highlights);
        $this->assertNotContains('.action-deletePermanently', $highlights);
    }

    // A field is pointed at through the widget the user sees: EasyAdmin clips the select of a choice or an association out of sight, and TrixEditorType hides its textarea, so a bare "#Entity_property" on those outlines nothing
    public function testNoStepHighlightsAWidgetEasyAdminHidesFromSight(): void
    {
        $hidden = ['#Serie_kind', '#Serie_summary', '#Serie_covers', '#Book_serie', '#Book_previousVersion', '#Strip_serie', '#Strip_summary'];

        foreach ($this->highlights() as $highlight) {
            $this->assertNotContains($highlight, $hidden, sprintf('"%s" points at an element EasyAdmin renders out of sight', $highlight));
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

        $attributes = [];
        foreach ($this->highlights() as $highlight) {
            if (preg_match('/^\[(data-[a-z-]+)/', $highlight, $matches)) {
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
