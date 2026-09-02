<?php

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\Serie;
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
                'Example: https://contes-du-soir.example/replique/{number}'
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

        $context = $this->importContext($io, $input);
        if (null === $context) {
            return Command::FAILURE;
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM `{$context['tableName']}` ORDER BY id ASC"
        );

        if (empty($rows)) {
            $io->warning("Table '{$context['tableName']}' returned no rows.");

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d rows.', count($rows)));

        [$created, $skipped] = $this->importRows($io, $rows, $context['serie'], $context);

        if (!$context['dryRun']) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(sprintf(
            '%d strips %s. %d skipped.',
            $created,
            $context['dryRun'] ? 'would be created' : 'created and flushed',
            $skipped
        ));

        return Command::SUCCESS;
    }

    // What the options describe, once they are known to name a serie and a directory that exists - null, the error already written, for anything the import cannot run on
    // @return array{serie: Serie, tableName: string, numberColumn: string, mediaRootFs: string, publicPrefix: string, urlTemplate: ?string, urlMax: int, dryRun: bool}|null
    private function importContext(SymfonyStyle $io, InputInterface $input): ?array
    {
        $serieSlug = $input->getOption('serie');
        $tableName = $input->getOption('table');
        $mediaDir = $input->getOption('media-dir');

        // Checked before anything is done with them: an option left out is null, which no string function is to be handed
        if (!$serieSlug || !$tableName || !$mediaDir) {
            $io->error('Options --serie, --table and --media-dir are all required.');

            return null;
        }

        $mediaDir = rtrim($mediaDir, '/');

        $serie = $this->serieRepository->findOneBy(['slug' => $serieSlug]);
        if (!$serie) {
            $io->error("No Serie found with slug '{$serieSlug}'.");

            return null;
        }

        $mediaRootFs = $this->projectDir . '/' . $mediaDir;
        if (!is_dir($mediaRootFs)) {
            $io->error("Directory not found: {$mediaRootFs}");

            return null;
        }

        $context = [
            'serie' => $serie,
            'tableName' => $tableName,
            'numberColumn' => $input->getOption('number-column'),
            'mediaRootFs' => $mediaRootFs,
            // Public URL prefix: strip leading "assets/" so asset() resolves correctly via AssetMapper
            'publicPrefix' => preg_replace('#^assets/#', '', $mediaDir),
            'urlTemplate' => $input->getOption('source-url-template'),
            'urlMax' => (int) $input->getOption('source-url-max'),
            'dryRun' => (bool) $input->getOption('dry-run'),
        ];

        $this->reportContext($io, $serieSlug, $context);

        return $context;
    }

    // The header printed before a single row is read: how the options were understood, and whether anything will be persisted
    private function reportContext(SymfonyStyle $io, string $serieSlug, array $context): void
    {
        $io->title(sprintf("Importing into serie '%s' (slug: %s)", $context['serie']->getTitle(), $serieSlug));
        $io->text([
            "Source table    : {$context['tableName']}",
            "Media dir (fs)  : {$context['mediaRootFs']}",
            "Public prefix   : {$context['publicPrefix']}",
            "Number column   : {$context['numberColumn']}",
            $context['dryRun'] ? '<comment>DRY-RUN — nothing will be persisted</comment>' : '<info>LIVE — changes will be flushed</info>',
        ]);
        $io->newLine();
    }

    // One strip per row of the source table, answering how many were written and how many the table could not name
    // @return array{0: int, 1: int}
    private function importRows(SymfonyStyle $io, array $rows, Serie $serie, array $options): array
    {
        $now = new \DateTime();
        $updatedAt = new \DateTimeImmutable();
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $slug = $row['slug'] ?? null;
            if (!$slug) {
                $io->warning("  Row id={$row['id']} has no slug — skipped.");
                ++$skipped;
                continue;
            }

            $number = isset($row[$options['numberColumn']]) ? (int) $row[$options['numberColumn']] : (int) $row['id'];
            $numFormatted = sprintf('%03d', $number);
            $stripDir = $options['mediaRootFs'] . '/' . $numFormatted;

            $strip = $this->buildStrip($row, $serie, $number, $now);
            $strip
                ->setSummary($this->transcription($stripDir, $numFormatted))
                ->setSourceUrl($this->sourceUrl($options['urlTemplate'], $number, $options['urlMax']));

            // Build media entries from directory scan
            $medias = $this->scanMediaDir($stripDir, $numFormatted, $options['publicPrefix'], $strip, $updatedAt);

            $io->writeln(sprintf(
                '  <info>[%s]</info> %s  published=%s  medias=%d%s',
                $numFormatted,
                $strip->getTitle(),
                $strip->getPublished()?->format('d/m/Y') ?? 'null',
                count($medias),
                $options['dryRun'] ? ' <comment>(dry-run)</comment>' : ''
            ));

            if (!$options['dryRun']) {
                $this->em->persist($strip);
                foreach ($medias as $media) {
                    $this->em->persist($media);
                }
            }

            ++$created;
        }

        return [$created, $skipped];
    }

    // The strip a source row describes, its summary and its source url written by the caller from the files beside it
    private function buildStrip(array $row, Serie $serie, int $number, \DateTime $now): Strip
    {
        $strip = new Strip();
        $strip->setSerie($serie);
        $strip->setTitle($row['title']);
        $strip->setSlug($row['slug']);
        $strip->setNumber($number);
        $strip->setCharacters($row['characters'] ?? null);
        $strip->setCreation($now);
        $strip->setModification($now);

        if (!empty($row['published'])) {
            $strip->setPublished(new \DateTime($row['published']));
        }

        return $strip;
    }

    // Transcription from .txt file → summary
    private function transcription(string $stripDir, string $numFormatted): ?string
    {
        $txtFile = $stripDir . '/' . $numFormatted . '.txt';

        return file_exists($txtFile) ? trim(file_get_contents($txtFile)) : null;
    }

    // Only the strips the site published up to a point carry one, the template naming each by its number
    private function sourceUrl(?string $urlTemplate, int $number, int $urlMax): ?string
    {
        if (!$urlTemplate || $number > $urlMax) {
            return null;
        }

        return str_replace('{number}', (string) $number, $urlTemplate);
    }

    // What a file's own name says it is, or null for anything the strip directory holds beside its pages
    // @return array{0: string, 1: int}|null the kind and the rank it is shown at
    private function recogniseMedia(string $file, string $numFormatted): ?array
    {
        $number = preg_quote($numFormatted, '/');

        // Full A4 page: NNN-page001.jpg
        if (preg_match('/^' . $number . '-page(\d+)\.jpg$/i', $file, $matches)) {
            return ['page', (int) $matches[1]];
        }

        // Individual panel: NNN-Cases-page001.jpg
        if (preg_match('/^' . $number . '-Cases-page(\d+)\.jpg$/i', $file, $matches)) {
            return ['case', (int) $matches[1]];
        }

        // PDF thumbnail
        return preg_match('/^' . $number . '\.pdf\.webp$/i', $file) ? ['thumbnail', 0] : null;
    }

    // Thumbnail first, then the full page, then the panels in their own order
    private function mediaOrder(StripMedia $a, StripMedia $b): int
    {
        $order = ['thumbnail' => 0, 'page' => 1, 'case' => 2];
        $first = $order[$a->getKind()] ?? 9;
        $second = $order[$b->getKind()] ?? 9;

        return $first !== $second ? $first - $second : $a->getPosition() <=> $b->getPosition();
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

            $recognised = $this->recogniseMedia($file, $numFormatted);
            if (null === $recognised) {
                continue;
            }

            [$kind, $position] = $recognised;

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
        usort($medias, $this->mediaOrder(...));

        // Renumbers positions sequentially so the sorted order is the stored one
        foreach ($medias as $index => $media) {
            $media->setPosition($index);
        }

        return $medias;
    }
}
