<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Service\BookShopPublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Writes every book into the shop at once - after the one-shot import, or to bring the shop back in line - what saving each book in the back-office does one at a time (see BookShopPublishSubscriber)
#[AsCommand(
    name: 'c975l:book:shop:publish',
    description: 'Writes every book whose editions are ticked "Shop" into the site\'s shop'
)]
class BookShopPublishCommand extends Command
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly BookShopPublisher $publisher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->publisher->isAvailable()) {
            $io->error('No shop is installed.');

            return Command::FAILURE;
        }

        // Each family of versions once, through its latest book
        $count = 0;
        foreach ($this->bookRepository->findBy(['newerVersion' => null, 'isDeleted' => false]) as $book) {
            $this->publisher->publish($book);
            ++$count;
        }

        $io->success(sprintf('%d book(s) written into the shop.', $count));

        return Command::SUCCESS;
    }
}
