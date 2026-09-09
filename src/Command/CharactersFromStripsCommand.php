<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\SerieRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

// The upgrade step of the release where who speaks in a planche stopped being text and became a row of its own (see Character): a site that had named its characters on every planche gets one character per distinct name, and the planches point at them
// Reads the former column in SQL rather than through the entity, which no longer carries it - so it has to run while that column is still there, between the two migrations (see UPGRADE.md)
#[AsCommand(
    name: 'c975l:book:characters:from-strips',
    description: 'Builds one character per name the planches used to carry as text, and points the planches at them'
)]
class CharactersFromStripsCommand extends Command
{
    private const string LEGACY_COLUMN = 'characters';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Says what would be written without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        // Nothing to read from means the step has already run, or the site never carried the column - either way there is nothing to do, and saying so is better than failing on a missing column
        if (!$this->hasLegacyColumn()) {
            $io->success('Nothing to do: the planches no longer carry their characters as text.');

            return Command::SUCCESS;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, serie_id, ' . self::LEGACY_COLUMN . ' AS names FROM book_strip WHERE ' . self::LEGACY_COLUMN . ' IS NOT NULL AND ' . self::LEGACY_COLUMN . " <> ''"
        );

        if ([] === $rows) {
            $io->success('Nothing to do: no planche names a character.');

            return Command::SUCCESS;
        }

        [$created, $linked] = $this->build($io, $rows, $dryRun);

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%d character(s) %s, %d link(s) %s.',
            $created,
            $dryRun ? 'would be created' : 'created',
            $linked,
            $dryRun ? 'would be written' : 'written'
        ));

        return Command::SUCCESS;
    }

    // Whether the planches still carry the column this reads: asked of the schema itself, the entity having stopped declaring it
    private function hasLegacyColumn(): bool
    {
        return array_any($this->connection->createSchemaManager()->listTableColumns('book_strip'), fn ($column) => self::LEGACY_COLUMN === $column->getName());
    }

    /**
     * One character per distinct name inside a serie, and one link per planche naming it.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array{0: int, 1: int}
     */
    private function build(SymfonyStyle $io, array $rows, bool $dryRun): array
    {
        $slugger = new AsciiSlugger();
        $stripRepository = $this->em->getRepository(Strip::class);
        $characters = [];
        $created = 0;
        $linked = 0;

        foreach ($rows as $row) {
            $serie = $this->serieRepository->find($row['serie_id']);
            $strip = $stripRepository->find($row['id']);

            if (null === $serie || null === $strip) {
                continue;
            }

            foreach (explode(',', (string) $row['names']) as $name) {
                $name = trim($name);

                if ('' === $name) {
                    continue;
                }

                $slug = $slugger->slug($name)->lower()->toString();
                $key = $serie->getId() . '/' . $slug;

                // Looked for among the ones this run has made before asking the serie: the same name is met once per planche it speaks in
                $character = $characters[$key] ?? $serie->getCharacter($slug);

                if (null === $character) {
                    $character = new Character()->setName($name)->setSlug($slug);
                    $serie->addCharacter($character);
                    ++$created;

                    if (!$dryRun) {
                        $this->em->persist($character);
                    }

                    $io->writeln(sprintf('  <info>%s</info> / %s', $serie->getSlug(), $name));
                }

                $characters[$key] = $character;
                $strip->addCharacter($character);
                ++$linked;
            }
        }

        return [$created, $linked];
    }
}
