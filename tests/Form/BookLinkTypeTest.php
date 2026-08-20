<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Enum\BookLinkKind;
use c975L\BookBundle\Form\BookLinkType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookLinkTypeTest extends TestCase
{
    // No provider declared, which is every site not naming its own editions - the form then offers the bundle's own
    private static function type(): BookLinkType
    {
        return new BookLinkType(new BookCustomizationRegistry([]));
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

        self::type()->buildForm($builder, $options + ['book' => null]);

        return $added;
    }

    public function testBuildFormAsksForThePlatformAndItsAddress(): void
    {
        $added = $this->build();

        $this->assertSame(['position', 'kind', 'url'], array_keys($added));
        $this->assertSame(HiddenType::class, $added['position']['type']);
        $this->assertSame(EnumType::class, $added['kind']['type']);
        $this->assertSame(BookLinkKind::class, $added['kind']['options']['class']);
        $this->assertSame(UrlType::class, $added['url']['type']);
    }

    public function testThePlatformIsOfferedUnderItsOwnName(): void
    {
        $choiceLabel = $this->build()['kind']['options']['choice_label'];

        $this->assertSame('Apple Books', $choiceLabel(BookLinkKind::EpubApple));
    }

    public function testTheHiddenPositionCarriesTheClassTheSortingHangsOn(): void
    {
        $this->assertSame('ui-sort-position', $this->build()['position']['options']['attr']['class']);
    }

    public function testConfigureOptionsBindsTheLinkAndItsCatalog(): void
    {
        $resolver = new OptionsResolver();
        self::type()->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame(BookLink::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
        $this->assertNull($options['book']);
    }

    // A book with no edition yet - a new one, or a site not publishing any - gets no edition field rather than an empty list
    public function testTheEditionIsOnlyAskedForWhenTheBookHasOne(): void
    {
        $this->assertArrayNotHasKey('edition', $this->build());
    }
}
