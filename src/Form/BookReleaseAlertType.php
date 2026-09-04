<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form;

use c975L\UiBundle\Service\FormBotProtection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

// The one field somebody waiting on a book fills in. Not bound to the entity: the book and the locale are the page's own business, and nothing a form posts should be able to name a row
class BookReleaseAlertType extends AbstractType
{
    public function __construct(
        private readonly FormBotProtection $formBotProtection,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => t('label.release_alert_email', [], 'book'),
                'help' => t('label.release_alert_email_help', [], 'book'),
                // The 100 characters BookReleaseAlert stores, which is what ShopBundle gives its own address column
                'constraints' => [new NotBlank(), new Email(), new Length(max: 100)],
            ])
        ;

        // The same honeypot the other public forms of the ecosystem carry, rather than one of its own
        $this->formBotProtection->addHoneypotField($builder, $this->requestStack->getCurrentRequest());
    }
}
