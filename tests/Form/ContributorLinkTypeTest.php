<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\ContributorLink;
use c975L\BookBundle\Form\ContributorLinkType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Where a person is sold: their own page at each store, which is not one book's (see Contributor:Shops)
class ContributorLinkTypeTest extends TestCase
{
    private static function type(): ContributorLinkType
    {
        return new ContributorLinkType(new BookCustomizationRegistry([], self::registryTranslator()));
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

    public function testBuildFormAsksForThePlatformAndItsAddress(): void
    {
        $added = $this->build();

        $this->assertSame(['position', 'kind', 'url'], array_keys($added));
        $this->assertSame(HiddenType::class, $added['position']['type']);
        $this->assertSame(ChoiceType::class, $added['kind']['type']);
        $this->assertSame(UrlType::class, $added['url']['type']);
    }

    // The same platforms a book is sold on, offered under their own brand and stored as the site's own word
    public function testThePlatformsAreTheOnesTheCatalogAlreadyKnows(): void
    {
        $choices = $this->build()['kind']['options']['choices'];

        $this->assertSame('epub_apple', $choices['Apple Books']);
        $this->assertSame('video_youtube', $choices['YouTube']);
    }

    public function testTheHiddenPositionCarriesTheClassTheSortingHangsOn(): void
    {
        $this->assertSame('ui-sort-position', $this->build()['position']['options']['attr']['class']);
    }

    public function testConfigureOptionsBindsThePersonsLink(): void
    {
        $resolver = new OptionsResolver();
        self::type()->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame(ContributorLink::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
    }

    // The help says what the address must be - their page, not a book's - so it is part of the contract
    public function testTheAddressIsExplainedAsTheirOwnPage(): void
    {
        $this->assertSame('label.contributor_link_url-help', $this->build()['url']['options']['help']);
    }

    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
