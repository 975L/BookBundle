<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Command;

use c975L\BookBundle\Entity\BookPrintable;
use c975L\BookBundle\Service\BookPrintableNamer;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

// The documents to print uploaded before the site named them, or since their book changed its slug, brought to the name an upload gives them now (see BookPrintableNamer), their thumbnail with them
#[AsCommand(name: 'c975l:book:printables:rename', description: 'Renames the stored documents to print to the name an upload gives them now. Lists them without renaming unless --force is given.')]
class BookPrintablesRenameCommand extends Command
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookPrintableNamer $printableNamer,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Renames the files and their rows instead of listing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $renamed = [];
        $missing = [];
        foreach ($this->em->getRepository(BookPrintable::class)->findAll() as $printable) {
            $name = (string) $printable->getName();
            $target = $this->printableNamer->renamed($printable);
            if (null === $target) {
                continue;
            }

            // A row whose file is gone has nothing to rename: it is named for the editor to upload it again
            if (!$this->filesystem->exists($this->path($name))) {
                $missing[] = $name;

                continue;
            }

            // Flushed row by row, so a rename failing halfway leaves no row naming a file already moved
            if ($force) {
                $this->move($name, $target);
                $printable->setName($target);
                $this->em->flush();
            }

            $renamed[] = $name . ' → ' . $target;
        }

        if ([] !== $renamed) {
            $io->listing($renamed);
        }

        $io->success(sprintf($force ? '%d document(s) renommé(s).' : '%d document(s) à renommer, relancer avec --force pour le faire.', \count($renamed)));

        if ([] !== $missing) {
            $io->warning(['Fichier absent, à envoyer de nouveau :', ...$missing]);
        }

        return Command::SUCCESS;
    }

    // The PDF and the thumbnail cut from it, which a host without Ghostscript never produced
    private function move(string $name, string $target): void
    {
        $this->filesystem->rename($this->path($name), $this->path($target));

        $thumbnail = $this->path(VichPdfThumbnailListener::toWebpPath($name));
        if ($this->filesystem->exists($thumbnail)) {
            $this->filesystem->rename($thumbnail, $this->path(VichPdfThumbnailListener::toWebpPath($target)));
        }
    }

    private function path(string $name): string
    {
        return $this->projectDir . '/public/' . $name;
    }
}
