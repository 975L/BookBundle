<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Form\BookPrintableType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The level is only asked of a site naming some, and never on a language screen, where the title alone is translated
class BookPrintableTypeTest extends TestCase
{
    /**
     * @param array<string, string> $kinds
     * @param array<string, mixed>  $options
     *
     * @return list<string>
     */
    private function fields(array $kinds, array $options = []): array
    {
        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getPrintableKinds')->willReturn($kinds);
        $added = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(static function (string $name) use (&$added, $builder): FormBuilderInterface {
            $added[] = $name;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(static fn (): FormBuilderInterface => $builder);

        new BookPrintableType(new BookCustomizationRegistry([$provider], $this->createStub(TranslatorInterface::class)))->buildForm($builder, $options);

        return $added;
    }

    public function testASiteNamingNoLevelIsNotAskedForOne(): void
    {
        $this->assertSame(['position', 'title', 'file', 'publishedOn'], $this->fields([]));
    }

    public function testASiteNamingLevelsChoosesOne(): void
    {
        $this->assertSame(['position', 'title', 'file', 'publishedOn', 'kind'], $this->fields(['small' => 'Small']));
    }

    // The day and the level are the same in every language
    public function testALanguageScreenAsksForNoLevel(): void
    {
        $this->assertNotContains('kind', $this->fields(['small' => 'Small'], ['translation_locale' => 'en']));
        $this->assertNotContains('publishedOn', $this->fields(['small' => 'Small'], ['translation_locale' => 'en']));
    }
}
