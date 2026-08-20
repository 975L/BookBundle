<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form\Block;

use c975L\BookBundle\Form\Block\ReaderBlockType;
use c975L\BookBundle\Form\Block\ReaderCueType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ReaderBlockTypeTest extends TestCase
{
    /** @return array<string, array{type: ?string, options: array<string, mixed>}> */
    private function build(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new ReaderBlockType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsACuesCollectionOfReaderCueType(): void
    {
        $added = $this->build();

        $this->assertSame(CollectionType::class, $added['cues']['type']);
        $this->assertSame(ReaderCueType::class, $added['cues']['options']['entry_type']);
        $this->assertTrue($added['cues']['options']['allow_add']);
        $this->assertTrue($added['cues']['options']['allow_delete']);
    }

    // The identifier is the slider's DOM id and the block's anchor, so it is the one field the reader cannot do without
    public function testOnlyTheIdentifierIsRequired(): void
    {
        $added = $this->build();

        $this->assertSame(TextType::class, $added['id']['type']);
        $this->assertTrue($added['id']['options']['required']);
        $this->assertFalse($added['title']['options']['required']);
        $this->assertFalse($added['cues']['options']['required']);
        $this->assertSame(CheckboxType::class, $added['autoAdvance']['type']);
        $this->assertFalse($added['autoAdvance']['options']['required']);
    }
}
