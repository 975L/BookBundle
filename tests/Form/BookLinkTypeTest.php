<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Form\BookLinkType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookLinkTypeTest extends TestCase
{
    // No provider declared, which is every site not naming its own editions - the form then offers the bundle's own
    private static function type(): BookLinkType
    {
        return new BookLinkType(new BookCustomizationRegistry([], self::registryTranslator()));
    }

    /** @return array<string, array{type: ?string, options: array<string, mixed>}> */
    private function build(array $options = []): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        self::type()->buildForm($builder, $options);

        return $added;
    }

    public function testBuildFormAsksForThePlatformAndItsAddress(): void
    {
        $added = $this->build();

        $this->assertSame(['position', 'kind', 'url'], array_keys($added));
        $this->assertSame(HiddenType::class, $added['position']['type']);
        $this->assertSame(ChoiceType::class, $added['kind']['type']);
        $this->assertSame(UrlType::class, $added['url']['type']);
    }

    // The platform is offered under its own brand and stored as the site's own word, never as a case of an enum
    public function testThePlatformIsOfferedUnderItsOwnName(): void
    {
        $choices = $this->build()['kind']['options']['choices'];

        $this->assertSame('epub_apple', $choices['Apple Books']);
        $this->assertSame('video_youtube', $choices['YouTube']);
    }

    public function testTheHiddenPositionCarriesTheClassTheSortingHangsOn(): void
    {
        $this->assertSame('ui-sort-position', $this->build()['position']['options']['attr']['class']);
    }

    public function testConfigureOptionsBindsTheLink(): void
    {
        $resolver = new OptionsResolver();
        self::type()->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame(BookLink::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
    }

    // The edition a link belongs to is not asked: the panel the form is opened in already names it (see BookEditionType)
    public function testTheEditionIsNeverAsked(): void
    {
        $this->assertArrayNotHasKey('edition', $this->build());
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
