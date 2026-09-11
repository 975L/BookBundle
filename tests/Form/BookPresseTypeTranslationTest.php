<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Form\BookPresseType;
use c975L\BookBundle\Service\BookTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

// A press cutting on the book's language screen: its title and nothing else, written to the translation table rather than over the file
class BookPresseTypeTranslationTest extends TestCase
{
    /** @var list<array{0: object, 1: string, 2: array<string, mixed>}> */
    private array $staged = [];

    private function createFormFactory(): FormFactoryInterface
    {
        $bookTranslator = $this->createStub(BookTranslator::class);
        $bookTranslator->method('promptValues')->willReturn(['title' => '[Le Dauphiné libéré]']);
        $bookTranslator->method('stage')->willReturnCallback(function (object $row, string $locale, array $values): void {
            $this->staged[] = [$row, $locale, $values];
        });

        return Forms::createFormFactoryBuilder()
            ->addType(new BookPresseType($bookTranslator))
            ->getFormFactory();
    }

    private function presse(): BookPresse
    {
        $presse = new BookPresse()->setTitle('Le Dauphiné libéré');
        new \ReflectionProperty(Media::class, 'id')->setValue($presse, 9);

        return $presse;
    }

    // The file, its rank and its address are not the language's to change
    public function testALanguageScreenOffersTheTitleAloneUnmapped(): void
    {
        $form = $this->createFormFactory()->create(BookPresseType::class, $this->presse(), ['translation_locale' => 'en']);

        $this->assertSame(['id', 'title'], array_keys($form->all()));
        $this->assertFalse($form->get('title')->getConfig()->getMapped());
        $this->assertSame('[Le Dauphiné libéré]', $form->get('title')->getData());
    }

    // What is typed goes to the translator, and the file keeps the title it was given
    public function testWhatIsTypedIsStagedAndLeavesTheFileAlone(): void
    {
        $presse = $this->presse();
        $form = $this->createFormFactory()->create(BookPresseType::class, $presse, ['translation_locale' => 'en']);

        $form->submit(['id' => '9', 'title' => 'Review in Le Dauphiné']);

        $this->assertSame('Le Dauphiné libéré', $presse->getTitle());
        $this->assertSame([[$presse, 'en', ['title' => 'Review in Le Dauphiné']]], $this->staged);
    }
}
