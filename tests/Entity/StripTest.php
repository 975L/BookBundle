<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\Strip;
use PHPUnit\Framework\TestCase;

class StripTest extends TestCase
{
    public function testCharactersListPairsTheNameWithItsSlug(): void
    {
        $strip = new Strip()->setCharacters('Timothée, Papa Câlin , Zoé');

        $this->assertSame(
            [
                ['name' => 'Timothée', 'slug' => 'timothee'],
                ['name' => 'Papa Câlin', 'slug' => 'papa-calin'],
                ['name' => 'Zoé', 'slug' => 'zoe'],
            ],
            $strip->getCharactersList()
        );
    }

    public function testCharactersSlugFollowsTheCharacters(): void
    {
        $strip = new Strip()->setCharacters('Timothée, Zoé');
        $this->assertSame('timothee,zoe', $strip->getCharactersSlug());

        $strip->setCharacters(null);
        $this->assertNull($strip->getCharactersSlug());
        $this->assertSame([], $strip->getCharactersList());
    }

    public function testCharactersListIgnoresTheEmptyEntries(): void
    {
        $strip = new Strip()->setCharacters('Zoé,, ,');

        $this->assertSame([['name' => 'Zoé', 'slug' => 'zoe']], $strip->getCharactersList());
        $this->assertSame('zoe', $strip->getCharactersSlug());
    }

    // Static so a listing names the characters of a whole serie without loading every planche of it
    public function testCharactersAreSplitWithoutAPlancheToReadThemOff(): void
    {
        $this->assertSame(
            [['name' => 'Maman', 'slug' => 'maman'], ['name' => 'Zoé', 'slug' => 'zoe']],
            Strip::splitCharacters('Maman, Zoé')
        );
        $this->assertSame([], Strip::splitCharacters(null));
        $this->assertSame([], Strip::splitCharacters(' , '));
    }

    public function testCoversAlsoMatchTheLegacyMediasStoredWithNoKind(): void
    {
        $serie = new Serie();
        $legacy = new SerieMedia();
        $logo = new SerieMedia()->setKind('logo');
        $cover = new SerieMedia()->setKind('cover');
        $serie->addMedia($legacy)->addMedia($logo)->addMedia($cover);

        $this->assertSame([$legacy, $cover], array_values($serie->getCovers()->toArray()));
    }
}
