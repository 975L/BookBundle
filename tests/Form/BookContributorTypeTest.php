<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookContributor;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Form\BookContributorType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// One row of the "Participants" fieldset: the person, and the part they take in this one book
class BookContributorTypeTest extends TestCase
{
    // No provider declared, which is every site not naming its own parts - the form then offers the bundle's own
    private static function type(): BookContributorType
    {
        return new BookContributorType(new BookCustomizationRegistry([], self::registryTranslator()));
    }

    /** @return array<string, array{type: ?string, options: array<string, mixed>}> */
    private function build(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        self::type()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAsksForThePersonAndTheirPart(): void
    {
        $added = $this->build();

        $this->assertSame(['position', 'contributor', 'role'], array_keys($added));
        $this->assertSame(HiddenType::class, $added['position']['type']);
        $this->assertSame(EntityType::class, $added['contributor']['type']);
        $this->assertSame(Contributor::class, $added['contributor']['options']['class']);
        $this->assertSame(ChoiceType::class, $added['role']['type']);
    }

    // Neither author nor illustrator: the book holds those two in columns of its own, and offering them here would credit the same person twice
    public function testThePartsOfferedAreTheOnesTheBookHoldsInNoColumn(): void
    {
        $choices = $this->build()['role']['options']['choices'];

        $this->assertSame(['narrator', 'translator'], array_values($choices));
        $this->assertArrayNotHasKey('author', array_flip($choices));
        $this->assertArrayNotHasKey('illustrator', array_flip($choices));
    }

    public function testTheHiddenPositionCarriesTheClassTheSortingHangsOn(): void
    {
        $this->assertSame('ui-sort-position', $this->build()['position']['options']['attr']['class']);
    }

    public function testConfigureOptionsBindsTheCredit(): void
    {
        $resolver = new OptionsResolver();
        self::type()->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame(BookContributor::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
    }

    // The translator the registry asks for: it returns the key as is
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
