<?php

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Repository\SerieRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'strip:import',
    description: 'Import strips from an existing DB table and local media directory into book_strip/book_media'
)]
class StripImportCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'serie',
                null,
                InputOption::VALUE_REQUIRED,
                'Slug of the Serie to link strips to'
            )
            ->addOption(
                'table',
                null,
                InputOption::VALUE_REQUIRED,
                'Source DB table name (e.g. tribu, tocards_land)'
            )
            ->addOption(
                'media-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Filesystem path relative to project dir (e.g. assets/medias/la-tribu). ' .
                'The "assets/" prefix is stripped for the public URL stored in book_media.name.'
            )
            ->addOption(
                'number-column',
                null,
                InputOption::VALUE_OPTIONAL,
                'Column used as strip number AND directory name (zero-padded to 3 digits). Default: id',
                'id'
            )
            ->addOption(
                'source-url-template',
                null,
                InputOption::VALUE_OPTIONAL,
                'URL template for sourceUrl field. Use {number} as placeholder. ' .
                'Example: https://papa-calin.com/replique/{number}'
            )
            ->addOption(
                'source-url-max',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum strip number (inclusive) for which sourceUrl is applied. Default: unlimited',
                PHP_INT_MAX
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulate without persisting anything'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $serieSlug = $input->getOption('serie');
        $tableName = $input->getOption('table');
        $mediaDir = $input->getOption('media-dir');
        $numberColumn = $input->getOption('number-column');
        $urlTemplate = $input->getOption('source-url-template');
        $urlMax = (int) $input->getOption('source-url-max');
        $dryRun = $input->getOption('dry-run');

        // Checked before anything is done with them: an option left out is null, which no string function is to be handed
        if (!$serieSlug || !$tableName || !$mediaDir) {
            $io->error('Options --serie, --table and --media-dir are all required.');

            return Command::FAILURE;
        }

        $mediaDir = rtrim($mediaDir, '/');

        $serie = $this->serieRepository->findOneBy(['slug' => $serieSlug]);
        if (!$serie) {
            $io->error("No Serie found with slug '{$serieSlug}'.");

            return Command::FAILURE;
        }

        $mediaRootFs = $this->projectDir . '/' . $mediaDir;
        if (!is_dir($mediaRootFs)) {
            $io->error("Directory not found: {$mediaRootFs}");

            return Command::FAILURE;
        }

        // Public URL prefix: strip leading "assets/" so asset() resolves correctly via AssetMapper
        $publicPrefix = preg_replace('#^assets/#', '', $mediaDir);

        $io->title(sprintf("Importing into serie '%s' (slug: %s)", $serie->getTitle(), $serieSlug));
        $io->text([
            "Source table    : {$tableName}",
            "Media dir (fs)  : {$mediaRootFs}",
            "Public prefix   : {$publicPrefix}",
            "Number column   : {$numberColumn}",
            $dryRun ? '<comment>DRY-RUN — nothing will be persisted</comment>' : '<info>LIVE — changes will be flushed</info>',
        ]);
        $io->newLine();

        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM `{$tableName}` ORDER BY id ASC"
        );

        if (empty($rows)) {
            $io->warning("Table '{$tableName}' returned no rows.");

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d rows.', count($rows)));

        $now = new \DateTime();
        $updatedAt = new \DateTimeImmutable();
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $slug = $row['slug'] ?? null;
            $number = isset($row[$numberColumn]) ? (int) $row[$numberColumn] : (int) $row['id'];

            if (!$slug) {
                $io->warning("  Row id={$row['id']} has no slug — skipped.");
                ++$skipped;
                continue;
            }

            $numFormatted = sprintf('%03d', $number);
            $stripDir = $mediaRootFs . '/' . $numFormatted;

            // Transcription from .txt file → summary
            $summary = null;
            $txtFile = $stripDir . '/' . $numFormatted . '.txt';
            if (file_exists($txtFile)) {
                $summary = trim(file_get_contents($txtFile));
            }

            // sourceUrl
            $sourceUrl = null;
            if ($urlTemplate && $number <= $urlMax) {
                $sourceUrl = str_replace('{number}', (string) $number, $urlTemplate);
            }

            $strip = new Strip();
            $strip->setSerie($serie);
            $strip->setTitle($row['title']);
            $strip->setSlug($slug);
            $strip->setNumber($number);
            $strip->setCharacters($row['characters'] ?? null);
            $strip->setSummary($summary);
            $strip->setSourceUrl($sourceUrl);
            $strip->setCreation($now);
            $strip->setModification($now);

            if (!empty($row['published'])) {
                $strip->setPublished(new \DateTime($row['published']));
            }

            // Build media entries from directory scan
            $medias = $this->scanMediaDir($stripDir, $numFormatted, $publicPrefix, $strip, $updatedAt);

            $io->writeln(sprintf(
                '  <info>[%s]</info> %s  published=%s  medias=%d%s',
                $numFormatted,
                $strip->getTitle(),
                $strip->getPublished()?->format('d/m/Y') ?? 'null',
                count($medias),
                $dryRun ? ' <comment>(dry-run)</comment>' : ''
            ));

            if (!$dryRun) {
                $this->em->persist($strip);
                foreach ($medias as $media) {
                    $this->em->persist($media);
                }
            }

            ++$created;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(sprintf(
            '%d strips %s. %d skipped.',
            $created,
            $dryRun ? 'would be created' : 'created and flushed',
            $skipped
        ));

        return Command::SUCCESS;
    }

    private function scanMediaDir(
        string $stripDir,
        string $numFormatted,
        string $publicPrefix,
        Strip $strip,
        \DateTimeImmutable $updatedAt,
    ): array {
        if (!is_dir($stripDir)) {
            return [];
        }

        $medias = [];

        foreach (scandir($stripDir) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }

            $kind = null;
            $position = 0;

            if (preg_match('/^' . preg_quote($numFormatted, '/') . '-page(\d+)\.jpg$/i', $file, $m)) {
                // Full A4 page: NNN-page001.jpg
                $kind = 'page';
                $position = (int) $m[1];
            } elseif (preg_match('/^' . preg_quote($numFormatted, '/') . '-Cases-page(\d+)\.jpg$/i', $file, $m)) {
                // Individual panel: NNN-Cases-page001.jpg
                $kind = 'case';
                $position = (int) $m[1];
            } elseif (preg_match('/^' . preg_quote($numFormatted, '/') . '\.pdf\.webp$/i', $file)) {
                // PDF thumbnail
                $kind = 'thumbnail';
                $position = 0;
            }

            if (null === $kind) {
                continue;
            }

            $name = $publicPrefix . '/' . $numFormatted . '/' . $file;
            $fsize = filesize($stripDir . '/' . $file) ?: null;

            $media = new StripMedia();
            $media->setStrip($strip);
            $media->setKind($kind);
            $media->setPosition($position);
            $media->setName($name);
            $media->setSize($fsize);
            $media->setUpdatedAt($updatedAt);

            $medias[] = $media;
        }

        // Sort: thumbnail first, then full page, then panels in order
        usort($medias, static function (StripMedia $a, StripMedia $b): int {
            $order = ['thumbnail' => 0, 'page' => 1, 'case' => 2];
            $ak = $order[$a->getKind()] ?? 9;
            $bk = $order[$b->getKind()] ?? 9;

            return $ak !== $bk ? $ak - $bk : $a->getPosition() <=> $b->getPosition();
        });

        // Renumbers positions sequentially so the sorted order is the stored one
        foreach ($medias as $index => $media) {
            $media->setPosition($index);
        }

        return $medias;
    }
}
