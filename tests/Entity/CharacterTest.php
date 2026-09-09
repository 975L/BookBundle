<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\CharacterMedia;
use PHPUnit\Framework\TestCase;

class CharacterTest extends TestCase
{
    // What a template printing "{{ character }}" gets, and what a chip wears
    public function testACharacterPrintsAsTheirName(): void
    {
        $this->assertSame('Papa Câlin', (string) new Character()->setName('Papa Câlin'));
    }

    // The adder is what owns the relation: a media added through it knows the character it hangs off
    public function testAddingAPortraitWritesTheOwningSide(): void
    {
        $character = new Character();
        $portrait = new CharacterMedia();

        $character->addMedia($portrait);

        $this->assertSame([$portrait], array_values($character->getMedias()->toArray()));
        $this->assertSame($character, $portrait->getCharacter());

        $character->addMedia($portrait);

        $this->assertCount(1, $character->getMedias());
    }

    public function testRemovingAPortraitTakesItOffTheCharacter(): void
    {
        $character = new Character();
        $portrait = new CharacterMedia();
        $character->addMedia($portrait);

        $character->removeMedia($portrait);

        $this->assertCount(0, $character->getMedias());
        $this->assertNull($portrait->getCharacter());
    }

    // A row carrying no file is a media all the same, and it is not the face: a character with only such rows reads as their name alone
    public function testTheFaceIsTheFirstPortraitActuallyCarryingAFile(): void
    {
        $character = new Character();
        $character->addMedia(new CharacterMedia());
        $drawn = new CharacterMedia()->setName('medias/book/characters/portrait-papa/p.webp');
        $character->addMedia($drawn);

        $this->assertSame($drawn, $character->getMedia());
        $this->assertNull(new Character()->getMedia());
    }
}
