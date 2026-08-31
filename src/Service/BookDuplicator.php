<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookLink;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Repository\BookRepository;
use c975L\BookBundle\Repository\ContributorRepository;
use c975L\BookBundle\Repository\SerieRepository;
use c975L\BookBundle\Repository\StripRepository;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media as BlockMedia;
use c975L\UiBundle\Service\UniqueSlug;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Builds the copy of a serie, a book, a strip or a person with everything that belongs to it - its files, its platform links, its blocks - as an unsaved graph the caller persists in one go (see the duplicate() action of each CRUD controller). A serie's books and strips are deliberately left out: they belong to the serie they were published in, and duplicating one is its own decision, taken book by book
class BookDuplicator
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly ContributorRepository $contributorRepository,
        private readonly Security $security,
        private readonly SerieRepository $serieRepository,
        private readonly SluggerInterface $slugger,
        private readonly StripRepository $stripRepository,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Copies the person, their portrait, their background and their blocks - not what they signed, which stays credited to the original: a copy is a new person the editor then names, never a second author of the same books
    public function duplicateContributor(Contributor $source): Contributor
    {
        $now = new \DateTime();
        $copy = new Contributor()
            ->setName($this->copyTitle($source->getName(), 50))
            ->setSlug($this->copySlug((string) $source->getSlug(), 100, fn (string $candidate): bool => null !== $this->contributorRepository->findOneBy(['slug' => $candidate])))
            ->setSummary($source->getSummary())
            ->setWebsite($source->getWebsite())
            ->setPosition($source->getPosition())
            ->setCreation($now)
            ->setModification($now)
            ->setUser($this->currentUser());

        // Portrait and background alike, the kind carried by each row being what tells them apart (see Contributor::getPortraits())
        foreach ($source->getMedias() as $media) {
            $copy->addMedia($this->cloneMedia($media));
        }

        foreach ($source->getBlocks() as $block) {
            $copy->addBlock($this->cloneBlock($block));
        }

        return $copy;
    }

    // Copies the serie itself, its covers, its logos and its blocks - not its books nor its strips, which stay with the original
    public function duplicateSerie(Serie $source): Serie
    {
        $now = new \DateTime();
        $copy = new Serie()
            ->setTitle($this->copyTitle($source->getTitle(), 50))
            ->setSlug($this->copySlug((string) $source->getSlug(), 50, fn (string $candidate): bool => null !== $this->serieRepository->findOneBy(['slug' => $candidate])))
            ->setSummary($source->getSummary())
            ->setKind($source->getKind())
            ->setLanguage($source->getLanguage())
            ->setAuthor($source->getAuthor())
            ->setIllustrator($source->getIllustrator())
            ->setCreation($now)
            ->setModification($now)
            ->setUser($this->currentUser());

        // Covers and logos alike, the kind carried by each row being what tells them apart (see Serie::getCovers())
        foreach ($source->getMedias() as $media) {
            $copy->addMedia($this->cloneMedia($media));
        }

        foreach ($source->getBlocks() as $block) {
            $copy->addBlock($this->cloneBlock($block));
        }

        return $copy;
    }

    // Copies the book with its files, its platform links, its press, its videos, its marketing and its blocks - it stays in the same serie, and keeps pointing at the same original when it is a translation
    // The versions are not copied over: a duplicate is a new book, which the editor then qualifies - a new version is published by its own gesture, the only one chaining books one behind the other (see BookVersionPublisher)
    public function duplicateBook(Book $source): Book
    {
        $now = new \DateTime();
        $copy = new Book()
            ->setTitle($this->copyTitle($source->getTitle(), 100))
            ->setSlug($this->copySlug((string) $source->getSlug(), 100, fn (string $candidate): bool => null !== $this->bookRepository->findOneBy(['slug' => $candidate])))
            // The column is nullable, its setter is not - a book with no summary yet copies as one with an empty summary
            ->setSummary((string) $source->getSummary())
            ->setAuthor($source->getAuthor())
            ->setIllustrator($source->getIllustrator())
            ->setPublished($source->getPublished())
            ->setSerie($source->getSerie())
            ->setNumber($source->getNumber())
            ->setAge($source->getAge())
            ->setLanguage($source->getLanguage())
            ->setTranslationBook($source->getTranslationBook())
            ->setCrowdfunding($source->getCrowdfunding())
            ->setCrowdfundingEndDate($source->getCrowdfundingEndDate())
            // The whole payload rather than key by key: what a site adds to a book stays true of its copy, and a key added later needs nothing here
            ->setData($source->getData())
            ->setCreation($now)
            ->setModification($now)
            ->setUser($this->currentUser());

        // The editions, which say only what the book comes out under: their files and their platforms belong to the book and are copied with it, right below
        foreach ($source->getEditions() as $edition) {
            $copy->addEdition(new BookEdition()
                ->setKind($edition->getKind())
                ->setIsbn($edition->getIsbn())
                ->setPages($edition->getPages())
                ->setFormat($edition->getFormat())
                ->setPosition($edition->getPosition()));
        }

        foreach ($source->getMedias() as $media) {
            $copy->addMedia($this->cloneMedia($media));
        }

        foreach ($source->getLinks() as $link) {
            $copy->addLink(new BookLink()
                ->setKind($link->getKind())
                ->setUrl($link->getUrl())
                ->setPosition($link->getPosition()));
        }

        foreach ($source->getVideos() as $video) {
            $copy->addVideo($this->cloneMedia($video));
        }

        foreach ($source->getPresses() as $presse) {
            $copy->addPresse($this->cloneMedia($presse));
        }

        foreach ($source->getMarketings() as $marketing) {
            $copy->addMarketing($this->cloneMedia($marketing));
        }

        foreach ($source->getBlocks() as $block) {
            $copy->addBlock($this->cloneBlock($block));
        }

        return $copy;
    }

    // Copies the strip with its plates and its blocks - it stays in the same serie and keeps its number, the copy being made to be reworked, not to be published as the next one
    public function duplicateStrip(Strip $source): Strip
    {
        $now = new \DateTime();
        $copy = new Strip()
            ->setTitle($this->copyTitle($source->getTitle(), 100))
            ->setSlug($this->copySlug((string) $source->getSlug(), 100, fn (string $candidate): bool => null !== $this->stripRepository->findOneBy(['slug' => $candidate])))
            ->setNumber($source->getNumber())
            ->setCharacters($source->getCharacters())
            ->setSummary($source->getSummary())
            ->setSourceUrl($source->getSourceUrl())
            ->setPublished($source->getPublished())
            ->setSerie($source->getSerie())
            ->setCreation($now)
            ->setModification($now)
            ->setUser($this->currentUser());

        foreach ($source->getMedias() as $media) {
            $copy->addMedia($this->cloneMedia($media));
        }

        foreach ($source->getBlocks() as $block) {
            $copy->addBlock($this->cloneBlock($block));
        }

        return $copy;
    }

    // The file itself, copied into the class it belongs to: a page, a video, a press cutting and a promotional visual are the same table and the same copy, only the class changes (see Media and its children)
    /**
     * @template T of Media
     *
     * @param T $source
     *
     * @return T
     */
    private function cloneMedia(Media $source): Media
    {
        $copy = new ($source::class)()
            ->setKind($source->getKind())
            ->setTitle($source->getTitle())
            ->setPosition($source->getPosition())
            ->setNoCookie($source->isNoCookie())
            ->setYoutubeUrl($source->getYoutubeUrl());

        // The name is left to Vich to fill: it is the file's own path, and the namer appends a uniqid to it, so the copy ends up with a file of its own rather than sharing the source's (see UiMediaNamer). ReplacingFile and not a plain File - the upload only runs for an UploadedFile or a ReplacingFile, and would silently be skipped otherwise
        $name = $source->getName();
        if (null !== $name && is_file($path = $this->projectDir . '/public/' . $name)) {
            $copy->setFile(new ReplacingFile($path));
        }

        return $copy;
    }

    // Copies a block with its files and, recursively, the blocks it holds - a container (columns, accordion...) keeping its content in child blocks, themselves possibly containers
    private function cloneBlock(Block $source): Block
    {
        $copy = new Block()
            ->setKind($source->getKind())
            ->setPosition($source->getPosition())
            ->setData($source->getData())
            ->setAnimation($source->getAnimation())
            ->setUser($this->currentUser());

        foreach ($source->getMedias() as $media) {
            $copy->addMedia($this->cloneBlockMedia($media));
        }

        foreach ($source->getSlots() as $slot) {
            $copy->addSlot($this->cloneBlock($slot));
        }

        return $copy;
    }

    // Copies a media held by a block - UiBundle's own entity, which carries its display metadata in columns of its own where this bundle's medias have none
    private function cloneBlockMedia(BlockMedia $source): BlockMedia
    {
        $copy = new BlockMedia()
            ->setRole($source->getRole())
            ->setAlt($source->getAlt())
            ->setLabel($source->getLabel())
            ->setWidth($source->getWidth())
            ->setHeight($source->getHeight())
            ->setCssClasses($source->getCssClasses())
            ->setAbove($source->isAbove())
            ->setCredits($source->getCredits())
            ->setRightsReserved($source->isRightsReserved())
            ->setPosition($source->getPosition())
            ->setUser($this->currentUser());

        $filename = $source->getFilename();
        if (null !== $filename && is_file($path = $this->projectDir . '/public/' . $filename)) {
            $copy->setFile(new ReplacingFile($path));
        }

        return $copy;
    }

    // The copy's title, cut to what the column takes so the suffix always shows - it is what tells the two apart in an index listing them side by side
    private function copyTitle(?string $title, int $maxLength): string
    {
        $suffix = ' (' . $this->copySuffix() . ')';

        return mb_substr((string) $title, 0, $maxLength - mb_strlen($suffix)) . $suffix;
    }

    // The copy's slug, freed of any collision - three characters are left aside for the "-2", "-3"... UniqueSlug appends when the suffixed slug is already taken
    private function copySlug(string $base, int $maxLength, callable $collides): string
    {
        return UniqueSlug::build($this->slugger, mb_substr($base . '-' . $this->copySuffix(), 0, $maxLength - 3), $collides);
    }

    private function copySuffix(): string
    {
        return $this->translator->trans('label.copy_suffix', [], 'book');
    }

    // Security only guarantees its own UserInterface, the c975L entities relate to the one the application's User entity implements through the scaffold
    private function currentUser(): ?UserInterface
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
