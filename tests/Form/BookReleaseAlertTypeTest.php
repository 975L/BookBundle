<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Form\BookReleaseAlertType;
use c975L\UiBundle\Service\FormBotProtection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// The form asks for one thing, an address, and carries the honeypot every public form of the ecosystem is served behind
class BookReleaseAlertTypeTest extends TestCase
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

        $botProtection = $this->createMock(FormBotProtection::class);
        $botProtection->expects($this->once())->method('addHoneypotField');

        $requestStack = new RequestStack([new Request()]);

        new BookReleaseAlertType($botProtection, $requestStack)->buildForm($builder, []);

        return $added;
    }

    // One field, and nothing else: the row stores the address, the locale it was taken in and a token
    public function testTheFormAsksForAnAddressAndNothingElse(): void
    {
        $added = $this->build();

        $this->assertSame(['email'], array_keys($added));
        $this->assertSame(EmailType::class, $added['email']['type']);
    }

    // The 100 characters BookReleaseAlert stores: a longer address would be refused by the column, not by the form
    public function testTheAddressIsCheckedAgainstWhatTheColumnHolds(): void
    {
        $constraints = $this->build()['email']['options']['constraints'];

        $this->assertInstanceOf(NotBlank::class, $constraints[0]);
        $this->assertInstanceOf(Email::class, $constraints[1]);
        $this->assertInstanceOf(Length::class, $constraints[2]);
        $this->assertSame(100, $constraints[2]->max);
    }
}
