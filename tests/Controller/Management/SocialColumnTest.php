<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\BookCrudController;
use c975L\BookBundle\Controller\Management\StripCrudController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// What the books and planches screens hand UiBundle's SocialStatusCrudTrait: a wrong type asks SocialBundle about other rows, a wrong domain or a missing key shows raw keys
class SocialColumnTest extends TestCase
{
    private const array LOCALES = ['fr', 'en', 'es'];

    /** @return iterable<string, array{class-string, string}> */
    public static function controllers(): iterable
    {
        yield 'books' => [BookCrudController::class, 'book'];
        yield 'planches' => [StripCrudController::class, 'strip'];
    }

    // The type SocialBundle files the rows under, and the domain the column's keys are read from
    #[DataProvider('controllers')]
    public function testEachScreenNamesItsTypeAndDomain(string $controller, string $type): void
    {
        $instance = $this->controller($controller, []);

        $this->assertSame($type, $this->call($instance, 'socialSourceType'));
        $this->assertSame('book', $this->call($instance, 'socialTranslationDomain'));
    }

    // The trash has no column, its rows being held by no post any more
    public function testTheTrashHasNoColumn(): void
    {
        foreach ([BookCrudController::class, StripCrudController::class] as $controller) {
            $this->assertTrue($this->call($this->controller($controller, []), 'socialStatusColumnShown'));
            $this->assertFalse($this->call($this->controller($controller, ['trash' => 1]), 'socialStatusColumnShown'));
        }
    }

    // Every key the column reads, in the three catalogs
    #[DataProvider('controllers')]
    public function testTheColumnKeysAreTranslated(string $controller, string $type): void
    {
        foreach (self::LOCALES as $locale) {
            $xml = simplexml_load_file(dirname(__DIR__, 3) . '/translations/book.' . $locale . '.xlf');
            $keys = [];
            foreach ($xml->file->body->{'trans-unit'} as $unit) {
                $keys[] = (string) $unit->source;
            }

            foreach (['', '_date_format', '_reserved', '_published'] as $suffix) {
                $this->assertContains('label.' . $type . '_social' . $suffix, $keys, sprintf('"label.%s_social%s" is missing from the %s catalog', $type, $suffix, $locale));
            }
        }
    }

    // A controller carrying only the request the trash is read from
    private function controller(string $controller, array $query): object
    {
        $instance = new \ReflectionClass($controller)->newInstanceWithoutConstructor();
        new \ReflectionProperty($controller, 'requestStack')->setValue($instance, new RequestStack([new Request($query)]));

        return $instance;
    }

    private function call(object $instance, string $method): mixed
    {
        return new \ReflectionMethod($instance, $method)->invoke($instance);
    }
}
