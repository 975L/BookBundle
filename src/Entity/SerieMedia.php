<?php

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\Serie;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class SerieMedia extends Media implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'series', fileNameProperty: 'name', size: 'size')]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Serie::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Serie $serie = null;

    public function getMappingName(): string
    {
        return 'series';
    }

    public function getSerie(): ?Serie
    {
        return $this->serie;
    }

    public function setSerie(?Serie $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/books/series/' . $this->serie->getSlug();
    }
}
