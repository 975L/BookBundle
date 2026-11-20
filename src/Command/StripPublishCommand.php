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
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Enum\StripMediaKind;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\BookBundle\Service\BookPublicUrlResolver;
use c975L\ConfigBundle\Management\ArchivePath;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// One planche written from a manifest, the way the back-office would: found in its serie by its number or created, its texts, day and characters set, its page and panels uploaded, visible from its day on, a character the serie does not hold left out. Run again, it brings the same planche up to date, its medias replaced only when the manifest names files. Manifest: {"serie": "slug", "number": 11, "title": "item-1", "published": "2026-12-03", "summary": "<p>…</p>", "characters": ["item-2"], "sourceUrl": "https://…", "page": "011-page1.jpg", "panels": ["011-Cases-page1.jpg"]}, file paths relative to it, all but "serie", "number", "title" and "published" optional; the last line printed is "STRIP <id> <address>"
#[AsCommand(name: 'c975l:book:strip:publish', description: 'Creates or updates the planche a JSON manifest describes, visible from its day on, and prints its address. Shows it without writing with --dry-run.')]
class StripPublishCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerieRepository $serieRepository,
        private readonly StripRepository $stripRepository,
        private readonly BookPublicUrlResolver $urlResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('manifest', InputArgument::REQUIRED, 'The JSON manifest describing the planche')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Shows what would be written, writing nothing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('manifest');
        $read = $this->read($path);
        if (null === $read) {
            $io->error('Manifeste illisible, série inconnue, ou numéro, titre ou date manquant : ' . $path);

            return Command::FAILURE;
        }
        ['manifest' => $manifest, 'serie' => $serie, 'published' => $published] = $read;

        // Every file checked before anything is written, a missing one leaving a planche without its page, and a planche in the trash refused rather than updated where nobody sees it; a character the serie does not hold is only left out, never created under a name that may be misspelt
        $characters = $this->characters($serie, $manifest['characters'] ?? []);
        $files = $this->files(\dirname($path), $manifest);
        if ([] !== $files['missing']) {
            $io->error(['Fichier introuvable :', ...$files['missing']]);

            return Command::FAILURE;
        }
        if ([] !== $characters['missing']) {
            $io->warning(['Personnage absent de la série, laissé de côté :', ...$characters['missing']]);
        }

        $strip = $serie->getStrips()->findFirst(static fn (int $key, Strip $known): bool => $manifest['number'] === $known->getNumber());
        if (true === $strip?->isDeleted()) {
            $io->error(\sprintf('Planche %s %03d à la corbeille, la restaurer d\'abord.', $serie->getSlug(), $manifest['number']));

            return Command::FAILURE;
        }
        $io->text(\sprintf('%s %s %03d « %s », %s, %d média(s)', null === $strip ? '+' : '=', $serie->getSlug(), $manifest['number'], trim($manifest['title']), $published->format('d/m/Y'), \count($files['medias'])));
        if ($input->getOption('dry-run')) {
            return Command::SUCCESS;
        }

        $strip ??= $this->created($serie, $manifest['number'], trim($manifest['title']));
        $this->fill($strip, $manifest, $published, $characters['found']);
        $this->lay($strip, $files['medias']);
        $this->em->flush();

        $output->writeln(\sprintf('STRIP %d %s', $strip->getId(), $this->urlResolver->resolve('strip_display', ['slug' => $strip->getSlug()]) ?? ''));

        return Command::SUCCESS;
    }

    // The manifest with its serie and its day, null when it is unreadable or lacks its serie, number, title or day
    /** @return array{manifest: array<mixed>, serie: Serie, published: \DateTimeImmutable}|null */
    private function read(string $path): ?array
    {
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!\is_array($manifest) || !\is_int($manifest['number'] ?? null) || !\is_string($manifest['title'] ?? null) || '' === trim($manifest['title'])) {
            return null;
        }
        $serie = \is_string($manifest['serie'] ?? null) ? $this->serieRepository->findOneBy(['slug' => $manifest['serie']]) : null;
        $published = $this->day($manifest['published'] ?? null);

        return null === $serie || null === $published ? null : ['manifest' => $manifest, 'serie' => $serie, 'published' => $published];
    }

    // A day written YYYY-MM-DD, null for anything else
    private function day(mixed $day): ?\DateTimeImmutable
    {
        return \is_string($day) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? (\DateTimeImmutable::createFromFormat('!Y-m-d', $day) ?: null) : null;
    }

    // The serie's own characters the manifest names, by their slug, those it does not hold listed apart
    /** @return array{found: list<Character>, missing: list<string>} */
    private function characters(Serie $serie, mixed $names): array
    {
        $slugger = new AsciiSlugger();
        $found = [];
        $missing = [];
        foreach (\is_array($names) ? $names : [] as $name) {
            $character = \is_string($name) ? $serie->getCharacter($slugger->slug($name)->lower()->toString()) : null;
            if (null === $character) {
                $missing[] = 'personnage ' . json_encode($name, \JSON_UNESCAPED_UNICODE);
                continue;
            }
            $found[] = $character;
        }

        return ['found' => $found, 'missing' => $missing];
    }

    // The page then the panels, read beside the manifest only (see ArchivePath), each with its kind, those not found listed apart
    /**
     * @param array<mixed> $manifest
     *
     * @return array{medias: list<array{kind: string, file: string}>, missing: list<string>}
     */
    private function files(string $directory, array $manifest): array
    {
        $named = [...(isset($manifest['page']) ? [[StripMediaKind::Page->value, $manifest['page']]] : []), ...array_map(static fn (mixed $panel): array => [StripMediaKind::Panel->value, $panel], \is_array($manifest['panels'] ?? null) ? $manifest['panels'] : [])];
        $medias = [];
        $missing = [];
        foreach ($named as [$kind, $relative]) {
            $file = ArchivePath::resolve($directory, $relative);
            if (null === $file) {
                $missing[] = 'fichier ' . json_encode($relative, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                continue;
            }
            $medias[] = ['kind' => $kind, 'file' => $file];
        }

        return ['medias' => $medias, 'missing' => $missing];
    }

    // A new planche of the serie, its slug the serie's followed by its title's, freed of any collision the way a duplicate's is (three characters left for UniqueSlug's "-2")
    private function created(Serie $serie, int $number, string $title): Strip
    {
        $now = new \DateTimeImmutable();
        $strip = new Strip()
            ->setSerie($serie)
            ->setNumber($number)
            ->setTitle($title)
            ->setSlug(UniqueSlug::build(new AsciiSlugger(), mb_substr($serie->getSlug() . '-' . $title, 0, 97), fn (string $candidate): bool => null !== $this->stripRepository->findOneBy(['slug' => $candidate])))
            ->setCreation($now);
        $serie->addStrip($strip);
        $this->em->persist($strip);

        return $strip;
    }

    // The texts, day and characters the manifest gives, the planche shown from its day on; a source left out of the manifest keeps the one it had
    /**
     * @param array<mixed>    $manifest
     * @param list<Character> $characters
     */
    private function fill(Strip $strip, array $manifest, \DateTimeImmutable $published, array $characters): void
    {
        $strip
            ->setTitle(trim($manifest['title']))
            ->setPublished($published)
            ->setHidden(false)
            ->setModification(new \DateTimeImmutable());
        if (\is_string($manifest['summary'] ?? null)) {
            $strip->setSummary($manifest['summary']);
        }
        if (\array_key_exists('sourceUrl', $manifest)) {
            $strip->setSourceUrl(\is_string($manifest['sourceUrl']) && '' !== $manifest['sourceUrl'] ? $manifest['sourceUrl'] : null);
        }
        if (\array_key_exists('characters', $manifest)) {
            foreach ($strip->getCharacters()->toArray() as $character) {
                $strip->removeCharacter($character);
            }
            foreach ($characters as $character) {
                $strip->addCharacter($character);
            }
        }
    }

    // The page and panels the manifest names, in its order, in place of those the planche had; none named leaves its medias as they were
    /** @param list<array{kind: string, file: string}> $medias */
    private function lay(Strip $strip, array $medias): void
    {
        if ([] === $medias) {
            return;
        }
        $kinds = [StripMediaKind::Page->value, StripMediaKind::Panel->value];
        foreach ($strip->getMedias()->toArray() as $media) {
            if (\in_array((string) $media->getKind(), $kinds, true)) {
                $strip->removeMedia($media);
            }
        }
        foreach ($medias as $position => $named) {
            $media = new StripMedia()
                ->setKind($named['kind'])
                ->setPosition($position)
                ->setFile(new ReplacingFile($named['file'], true, false, false));
            $strip->addMedia($media);
            $this->em->persist($media);
        }
    }
}
