<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\BookSettings;
use PHPUnit\Framework\TestCase;

// What the catalog's own line reads once a language is laid over it
class BookSettingsTest extends TestCase
{
    // The translated intro is read, the text it was written with staying behind getUntranslated()
    public function testATranslationIsLaidOverTheIntro(): void
    {
        $settings = new BookSettings()->setIntro('Bienvenue');
        $settings->setTranslated(['intro' => 'Welcome']);

        $this->assertSame('Welcome', $settings->getIntro());
        $this->assertSame('Bienvenue', $settings->getUntranslated('intro'));
        $this->assertNull($settings->getUntranslated('title'));
    }

    // Nothing laid over it, the intro is the one it was written with
    public function testAnUntranslatedIntroIsTheOriginal(): void
    {
        $settings = new BookSettings()->setIntro('Bienvenue');
        $settings->setTranslated([]);

        $this->assertSame('Bienvenue', $settings->getIntro());
    }
}
