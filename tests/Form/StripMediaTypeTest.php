<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Enum\StripMediaKind;
use c975L\BookBundle\Form\StripMediaType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StripMediaTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    /** @param array<string, mixed> $options */
    private function build(array $options): void
    {
        $this->added = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $fieldOptions = []) use (&$builder) {
            $this->added[$name] = ['type' => $type, 'options' => $fieldOptions];

            return $builder;
        });

        new StripMediaType()->buildForm($builder, $this->resolve($options));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolve(array $options): array
    {
        $resolver = new OptionsResolver();
        new StripMediaType()->configureOptions($resolver);

        return $resolver->resolve($options);
    }

    // The three collections named after a role write it themselves (see Strip::addCaseMedia()), so offering the choice there would let a file filed under one heading claim another
    public function testTheRoleIsNotOfferedWhereTheCollectionAlreadySaysIt(): void
    {
        $this->build([]);

        $this->assertArrayNotHasKey('kind', $this->added);
        $this->assertArrayHasKey('position', $this->added);
        $this->assertArrayHasKey('file', $this->added);
    }

    // The view holding what no heading claims is the one place a role is picked by hand - how a media uploaded before the roles existed is filed
    public function testTheRoleIsOfferedOnTheViewWhoseHeadingDoesNotSayIt(): void
    {
        $this->build(['kind_editable' => true]);

        $this->assertSame(ChoiceType::class, $this->added['kind']['type']);
        $this->assertSame(StripMediaKind::choices(), $this->added['kind']['options']['choices']);
        // Left empty is a value of its own, the gallery a planche telling no role apart is drawn as
        $this->assertFalse($this->added['kind']['options']['required']);
        $this->assertSame('label.strip_media_gallery', $this->added['kind']['options']['placeholder']);
    }

    public function testTheOptionIsOffByDefaultAndOnlyTakesABoolean(): void
    {
        $resolved = $this->resolve([]);

        $this->assertFalse($resolved['kind_editable']);
        $this->assertSame(StripMedia::class, $resolved['data_class']);

        $this->expectException(InvalidOptionsException::class);
        $this->resolve(['kind_editable' => 'yes']);
    }
}
