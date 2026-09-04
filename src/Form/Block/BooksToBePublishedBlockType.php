<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Form\Block;

use Symfony\Component\Form\FormBuilderInterface;

// The data sub-form of the "book_to_be_published" kind - the head it shares with every other listing is in AbstractBookListingBlockType. No "display more" here: nothing is published yet, so there is no catalog page these books lead to
class BooksToBePublishedBlockType extends AbstractBookListingBlockType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $this->addSelectionFields($builder, 'label.block_max_books');
    }
}
