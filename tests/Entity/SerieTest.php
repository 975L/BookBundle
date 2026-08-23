<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Enum\SerieKind;
use PHPUnit\Framework\TestCase;

class SerieTest extends TestCase
{
    // Which index a serie answers to, the breadcrumb reading it to send a visitor back where the serie actually is listed
    public function testADeclaredKindDecidesWhichIndexHoldsTheSerie(): void
    {
        $serie = new Serie()->setKind(SerieKind::Strip->value);

        $this->assertTrue($serie->isStripSerie());
    }

    // What it declares wins over what it holds: a serie filed under books stays there even once a planche is added to it
    public function testADeclaredKindWinsOverWhatTheSerieHolds(): void
    {
        $serie = new Serie()->setKind(SerieKind::Book->value);
        $serie->addStrip(new Strip());

        $this->assertFalse($serie->isStripSerie());
    }

    // A site that never filled the field in keeps its series listed all the same, filed by what they hold - the very fallback SerieRepository writes in DQL
    public function testASerieDeclaringNothingIsFiledByWhatItHolds(): void
    {
        $serie = new Serie();
        $serie->addStrip(new Strip());

        $this->assertTrue($serie->isStripSerie());
    }

    public function testASerieDeclaringNothingAndHoldingNoPlancheIsNotOneOfPlanches(): void
    {
        $this->assertFalse(new Serie()->isStripSerie());
    }
}
