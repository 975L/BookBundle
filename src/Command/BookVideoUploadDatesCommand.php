<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\BookVideo;
use c975L\UiBundle\Service\VideoUploadDateFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// What UiBundle's c975l:ui:video-upload-dates does for its video blocks, for the videos of the books: the day each went online, asked once from its platform and stored, never on a page render
#[AsCommand(name: 'c975l:book:video-upload-dates', description: 'Fills the upload date of book videos that have none, asked from their platform')]
class BookVideoUploadDatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VideoUploadDateFetcher $videoUploadDateFetcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // A date typed by hand is never overwritten, and an uploaded file has no platform to ask
        $videos = array_filter(
            $this->em->getRepository(BookVideo::class)->findBy(['uploadDate' => null]),
            static fn (BookVideo $video): bool => !empty($video->getYoutubeUrl()),
        );

        $filled = 0;
        $missed = [];
        foreach ($videos as $video) {
            $date = $this->videoUploadDateFetcher->fetch($video->getYoutubeUrl());
            if (null === $date) {
                $missed[] = (string) $video->getYoutubeUrl();

                continue;
            }

            $video->setUploadDate(new \DateTimeImmutable($date));
            ++$filled;
        }

        if ($filled > 0) {
            $this->em->flush();
        }

        $io->success(sprintf('%d vidéo(s) datée(s) sur %d.', $filled, \count($videos)));

        // TikTok, a playlist or a platform that did not answer: the editor types the date on the book's video
        if ([] !== $missed) {
            $io->note(['Sans date, à saisir sur la vidéo du livre :', ...$missed]);
        }

        return Command::SUCCESS;
    }
}
