<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management\Trait;

use c975L\UiBundle\Model\SocialContentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

// Whether a social post holds the row - reserved by a draft, or published - on a site with SocialBundle only. The class using it carries $socialStatuses and $translator, and names the type SocialBundle files its rows under
trait SocialStatusCrudTrait
{
    // What marks the column among the fields of a row, its property being the id another column may show too
    private const string SOCIAL_STATUS_OPTION = 'socialStatus';

    // 'book' or 'strip': the source type SocialBundle is asked about, and the prefix of the column's translation keys
    abstract private function socialSourceType(): string;

    // The index column, left empty here: the page's rows are only known once EasyAdmin has formatted them (see index())
    /** @return list<FieldInterface> */
    private function socialStatusFields(): array
    {
        if (null === $this->socialStatuses) {
            return [];
        }

        return [
            TextField::new('id')
                ->setLabel(t('label.' . $this->socialSourceType() . '_social', [], 'book'))
                ->formatValue(static fn (): string => '')
                ->renderAsHtml()
                ->setSortable(false)
                ->onlyOnIndex()
                ->setCustomOption(self::SOCIAL_STATUS_OPTION, true),
        ];
    }

    // The badges of the page shown, SocialBundle being asked about its rows alone and in one call - the parameters being rendered only once returned
    #[\Override]
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $responseParameters = parent::index($context);
        $entities = $responseParameters instanceof KeyValueStore ? $responseParameters->get('entities') : null;
        if (null === $this->socialStatuses || !$entities instanceof EntityCollection || 0 === count($entities)) {
            return $responseParameters;
        }

        $ids = [];
        foreach ($entities as $entity) {
            $ids[] = (string) $entity->getPrimaryKeyValue();
        }
        $statuses = $this->socialStatuses->getStatuses($this->socialSourceType(), $ids);

        foreach ($entities as $entity) {
            foreach ($entity->getFields() ?? [] as $field) {
                if (true === $field->getCustomOption(self::SOCIAL_STATUS_OPTION)) {
                    $field->setFormattedValue($this->socialStatusBadge($statuses[(string) $entity->getPrimaryKeyValue()] ?? null));
                }
            }
        }

        return $responseParameters;
    }

    // The badge of the post holding the row, empty when none does
    private function socialStatusBadge(?SocialContentStatus $status): string
    {
        if (null === $status) {
            return '';
        }

        // The date's format is the language's own: "09/10" reads as the 10th of September in English
        $prefix = 'label.' . $this->socialSourceType() . '_social';
        $date = $status->at->format($this->translator->trans($prefix . '_date_format', [], 'book'));

        return sprintf(
            '<span class="badge %s">%s</span>',
            $status->isPublished() ? 'badge-success' : 'badge-warning',
            htmlspecialchars($this->translator->trans($prefix . '_' . $status->state, ['%date%' => $date], 'book')),
        );
    }
}
