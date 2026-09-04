<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// The sheet hands the fragment the two things only it knows - the age the book declares and the language it is read in - and the fragment decides whether there is anything to say. Nothing renders here, so the call is read where it is written
class BookAgeWarningTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/book/display.html.twig';

    // A catalog of children's books installs the very same bundle: the age is what tells a sheet that says nothing from one that opens on a warning, and it is the fragment's own guard
    public function testTheSheetHandsTheBooksAgeToTheFragment(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Alert:AgeWarning age="{{ book.age }}"', $this->contents());
    }

    // The sentence is asked in the language the book is written in, as every other word of this page is: a book translated into English would otherwise be read the site's French
    public function testTheSentenceIsAskedInTheBooksOwnLanguage(): void
    {
        $this->assertStringContainsString('locale="{{ book.language }}"/>', $this->contents());
    }

    // The sentence and the "has the site written one" question are the fragment's own business - reading the setting here too would be the same guard written in two places, and the shop sheet would then owe the third
    public function testThePageDoesNotReadTheSettingItself(): void
    {
        $this->assertStringNotContainsString("config('site-age-warning')", $this->contents());
    }

    private function contents(): string
    {
        $this->assertFileExists(self::DISPLAY);
        $contents = file_get_contents(self::DISPLAY);
        $this->assertIsString($contents);

        return $contents;
    }
}
