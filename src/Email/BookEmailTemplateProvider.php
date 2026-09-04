<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Email;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The e-mails this bundle sends on its own account, as templates an admin composes rather than Twig files nobody
 * but a developer can touch.
 *
 * Two, and they are the two halves of one promise: the first acknowledges the subscription and carries the way out
 * of it, the second says the book is out. Nothing else is ever sent to that address, which is what keeps the
 * waiting list a service the visitor asked for rather than a mailing list.
 *
 * Every sentence is read from the "book" catalogue, which is the one place the default wording lives: what
 * c975l:ui:email-templates:ensure seeds, what EmailTemplateRenderer falls back on if the row is deleted, and what a
 * translator writes for a language this bundle does not ship. An admin's rewriting happens after, on the row.
 */
class BookEmailTemplateProvider implements EmailTemplateProviderInterface
{
    // The acknowledgement, sent the moment somebody subscribes: it carries the unsubscribe link, which the parution e-mail cannot - by then the row is gone, and that message is the last one
    public const string RELEASE_ALERT_CONFIRMATION = 'book_release_alert_confirmation';

    // The one e-mail the whole waiting list exists for
    public const string BOOK_RELEASED = 'book_released';

    // The languages this bundle ships a book catalogue for. Listed rather than read from kernel.enabled_locales: the translator answers every locale by falling back on the default one, so iterating a site's languages would seed a Spanish row holding French sentences
    private const array LOCALES = ['fr', 'en', 'es'];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getEmailTemplates(): array
    {
        $templates = [];
        foreach (self::LOCALES as $locale) {
            foreach ($this->structure($locale) as $name => $blocks) {
                $templates[$name][$locale] = $blocks;
            }
        }

        return $templates;
    }

    /**
     * @return array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>
     */
    private function structure(string $locale): array
    {
        return [
            self::RELEASE_ALERT_CONFIRMATION => [
                $this->text('label.release_alert_confirmation_intro', $locale, ['%book%' => '{{ book_title }}']),
                $this->text('label.release_alert_confirmation_once', $locale),
                $this->text('label.release_alert_confirmation_unsubscribe', $locale, ['%url%' => '{{ unsubscribe_url }}']),
            ],
            self::BOOK_RELEASED => [
                $this->text('label.book_released_intro', $locale, ['%book%' => '{{ book_title }}']),
                ['button', null, null, null, $this->trans('label.book_released_discover', $locale), '{{ book_url }}'],
                $this->text('label.book_released_last', $locale),
            ],
        ];
    }

    /** @return array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string} */
    private function text(string $key, string $locale, array $parameters = []): array
    {
        return ['text', null, null, $this->trans($key, $locale, $parameters), null, null];
    }

    // A catalogue parameter becomes the "{{ name }}" an EmailTemplate block substitutes: the two placeholder syntaxes have to meet somewhere, and an admin editing that sentence in the back-office sees the one the editor documents
    private function trans(string $key, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, 'book', $locale);
    }
}
