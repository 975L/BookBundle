<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Service\BookReleaseAlertServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Run nightly: a book is dated to the day, so it comes out at 00:00 and the run that follows tells everyone waiting. Nothing listens to the back-office save instead - a listener would fire from the editor's own request, without batching nor retry, and would fire again each time the date is edited
#[AsCommand(
    name: 'c975l:book:release-alerts:send',
    description: 'Tells the visitors waiting on a book that it has come out, and drops the lists of books that never did',
)]
class BookReleaseAlertSend extends Command
{
    // How many alerts one run sends at most. A long-awaited book can carry thousands of subscriptions, and sending them in one pass would hold the mailer for as long as it takes; the nightly run walks the queue instead
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly BookReleaseAlertServiceInterface $releaseAlertService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'How many alerts to send in this run', self::DEFAULT_LIMIT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        if ($limit < 1) {
            $io->error('The limit must be at least 1.');

            return Command::INVALID;
        }

        $sent = $this->releaseAlertService->notifyReleased($limit);

        // Purged in the same run rather than by a command of its own: one cron line, and the only two things that ever empty this table are here side by side
        $purged = $this->releaseAlertService->purgeStale();
        $pending = $this->releaseAlertService->countPending();

        // What is left waiting is said every run: a queue that stops going down is how a site finds out its mailer is refusing, where a bare success line would hide it
        $io->success(sprintf('%d release alert(s) sent, %d still waiting, %d expired subscription(s) deleted.', $sent, $pending, $purged));

        return Command::SUCCESS;
    }
}
