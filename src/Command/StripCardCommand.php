<?php

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

#[AsCommand(
    name: 'strip:card',
    description: 'Replaces each planche\'s media with the square card image captured for it, named after its slug'
)]
class StripCardCommand extends Command
{
    // What the capture script writes, and the only thing read here: the extension the stored file already carries, so nothing is converted on the way in (see UiMediaNamer, which names an image webp whatever it was sent as)
    private const string EXTENSION = 'webp';

    // The role the new file plays, written on the media so its own name says what it holds: the card, and no longer the reply drawn as a page (see StripMedia::getVichMediaPath, which builds the filename from it)
    private const string KIND = 'card';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
        private readonly StripRepository $stripRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory holding the captures, one "<slug>.' . self::EXTENSION . '" per planche'
            )
            ->addOption(
                'serie',
                null,
                InputOption::VALUE_OPTIONAL,
                'Slug of the serie to work on, all of them when left out'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Says what would be replaced without writing anything'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = rtrim((string) $input->getOption('dir'), '/');
        $dryRun = (bool) $input->getOption('dry-run');

        if ('' === $dir || !is_dir($dir)) {
            $io->error('Option --dir must name an existing directory');

            return Command::FAILURE;
        }

        $strips = $this->findStrips($input->getOption('serie'), $io);

        if (null === $strips) {
            return Command::FAILURE;
        }

        $replaced = 0;
        $missing = [];

        foreach ($strips as $strip) {
            $path = $dir . '/' . $strip->getSlug() . '.' . self::EXTENSION;

            // A planche the capture skipped is left exactly as it is: it keeps the media it already carries rather than losing it
            if (!is_file($path)) {
                $missing[] = $strip->getSlug();

                continue;
            }

            $media = $this->resolveMedia($strip);

            // Vich does the naming, the writing and the deleting of the file it replaces (mapping "block_media", delete_on_update), which is what keeps the media editable from the back-office exactly as an uploaded one
            if (!$dryRun) {
                $media
                    ->setKind(self::KIND)
                    ->setFile(new ReplacingFile($path))
                ;
            }

            ++$replaced;
            $io->writeln(sprintf('%s <- %s', $strip->getSlug(), basename($path)));
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        if ([] !== $missing) {
            $io->warning(sprintf('No capture found for %d planche(s): %s', count($missing), implode(', ', $missing)));
        }

        $io->success(sprintf('%d planche(s) %s', $replaced, $dryRun ? 'would be replaced' : 'replaced'));

        return Command::SUCCESS;
    }

    /**
     * The planches to work on, null when the serie asked for does not exist.
     *
     * @return Strip[]|null
     */
    private function findStrips(?string $serieSlug, SymfonyStyle $io): ?array
    {
        if (null === $serieSlug) {
            return $this->stripRepository->findAllPublished();
        }

        $serie = $this->serieRepository->findOneBy(['slug' => $serieSlug]);

        if (null === $serie) {
            $io->error(sprintf('No serie carries the slug "%s"', $serieSlug));

            return null;
        }

        return $this->stripRepository->findAllPublishedBySerie($serie);
    }

    // The media the capture takes the place of - the first one, the planches this is meant for carrying exactly one. A planche carrying none is given one rather than left without a share image
    private function resolveMedia(Strip $strip): StripMedia
    {
        $media = $strip->getMedias()->first();

        if ($media instanceof StripMedia) {
            return $media;
        }

        $media = new StripMedia()->setPosition(0);
        $strip->addMedia($media);
        $this->em->persist($media);

        return $media;
    }
}
