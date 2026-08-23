<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Management;

use c975L\BookBundle\Entity\Media;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

// What the export and import providers of this bundle do alike with a file: the six subclasses of Media carry the same columns and the same stored name, whether they hang off a serie, a book, one of its versions or a strip
// The name is this hierarchy's own natural key - unique across the table, and the very path the file is served under - so an archive puts its files straight back where they were rather than re-uploading them: a catalog carried to another site answers at the same urls, and an import re-encodes nothing
class MediaArchiver
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    // Serializes one media and registers its physical file for the zip archive (&$files: archive-relative path => disk path). Returns null for a media whose file has left the disk, the caller dropping it rather than exporting a broken reference - a media standing for a YouTube url holds no file at all and travels all the same, that url being everything it is
    public function export(Media $media, array &$files): ?array
    {
        $name = $media->getName();
        $holdsFile = null !== $name && str_starts_with($name, Media::MEDIA_DIRECTORY . '/');

        $file = $holdsFile ? $this->registerFile($name, $files) : null;
        if ($holdsFile && null === $file) {
            return null;
        }

        return [
            'name' => $name,
            'kind' => $media->getKind(),
            'title' => $media->getTitle(),
            'position' => $media->getPosition(),
            'size' => $media->getSize(),
            'youtubeUrl' => $media->getYoutubeUrl(),
            // Vich never stamping an imported media, this is the only thing left to date it by
            'updatedAt' => $media->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'file' => $file,
        ];
    }

    /**
     * Writes the exported medias over an owner's collection, each row matched by the name its file is served under - what the archive no longer holds is detached, the rest is written over rather than dropped and built anew, so a file already on disk under that name keeps its row and its id.
     *
     * @template T of Media
     *
     * @param Collection<int, T> $medias  the owner's own collection, read to match and left to the callbacks to mutate
     * @param callable(): T      $factory builds a row of the owner's own subclass for a name the collection doesn't hold yet
     * @param callable(T): mixed $attach
     * @param callable(T): mixed $detach
     *
     * @return list<array{0: T, 1: array}> each written row paired with what the archive says of it, for restoreFiles()
     */
    public function sync(Collection $medias, array $mediasData, callable $factory, callable $attach, callable $detach): array
    {
        $existing = [];
        foreach ($medias as $media) {
            $existing[(string) $media->getName()] = $media;
        }

        $written = [];
        $kept = [];
        foreach ($mediasData as $mediaData) {
            $name = (string) ($mediaData['name'] ?? '');
            $media = $existing[$name] ?? $factory();
            $this->fill($media, $mediaData);
            $this->em->persist($media);
            $attach($media);
            $kept[$name] = true;
            $written[] = [$media, $mediaData];
        }

        foreach ($existing as $name => $media) {
            if (!isset($kept[$name])) {
                $detach($media);
            }
        }

        return $written;
    }

    /**
     * Copies every archived file back under the very name its media is served at, after the flush that made the rows real - nothing is done for an entry the archive doesn't hold, which is what a YouTube media and an archive predating the files both get.
     *
     * @param list<array{0: Media, 1: array}> $written
     */
    public function restoreFiles(array $written, ?string $filesDir): void
    {
        if (null === $filesDir) {
            return;
        }

        foreach ($written as [$media, $mediaData]) {
            $entry = $mediaData['file'] ?? null;
            $name = $media->getName();
            if (null === $entry || null === $name || !$this->isSafeName($name)) {
                continue;
            }

            $archivedPath = $filesDir . '/' . $entry;
            if (is_file($archivedPath)) {
                $this->filesystem->copy($archivedPath, $this->projectDir . '/public/' . $name, true);
            }
        }
    }

    // One row filled with what the archive says of it, its name kept as it is so the file it points at is the one restoreFiles() lays back
    private function fill(Media $media, array $mediaData): void
    {
        // First of the four: it names the media after the url it plays and stamps it, which the lines below then write over with what was actually exported
        if (!empty($mediaData['youtubeUrl'])) {
            $media->setYoutubeUrl($mediaData['youtubeUrl']);
        }

        $media
            ->setName($mediaData['name'] ?? null)
            ->setKind($mediaData['kind'] ?? null)
            ->setTitle($mediaData['title'] ?? null)
            ->setPosition($mediaData['position'] ?? 0)
            ->setSize($mediaData['size'] ?? null)
            // The column is not nullable, so an archive predating it dates the row from the import rather than leaving it unwritten
            ->setUpdatedAt(isset($mediaData['updatedAt']) ? new \DateTimeImmutable($mediaData['updatedAt']) : new \DateTimeImmutable());
    }

    // Registers one physical file for the zip archive and returns the reference the metadata carries, null for a file that has since left the disk so an archive never points at bytes it doesn't hold
    // The random prefix keeps the same-named files of two medias apart, an archive laying every file of every kind in one flat directory
    private function registerFile(string $name, array &$files): ?string
    {
        $path = $this->projectDir . '/public/' . $name;
        if (!is_file($path)) {
            return null;
        }

        $archivePath = 'files/' . bin2hex(random_bytes(8)) . '_' . basename($name);
        $files[$archivePath] = $path;

        return $archivePath;
    }

    // Whether a name out of an archive is one this bundle would write: it is only honoured under this bundle's own media directory, and only as a plain relative name - a "../" or an absolute path would have an import lay files anywhere the process can write, and a null byte would have PHP stop reading the name where C does
    private function isSafeName(string $name): bool
    {
        return str_starts_with($name, Media::MEDIA_DIRECTORY . '/')
            && !str_contains($name, "\0")
            && !\in_array('..', explode('/', $name), true);
    }
}
