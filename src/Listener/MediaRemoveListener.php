<?php

namespace c975L\BookBundle\Listener;

use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\SerieMedia;
use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: SerieMedia::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: BookMedia::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: BookVideo::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: BookPresse::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: BookMarketing::class)]
class MediaRemoveListener
{
    public function __construct(private readonly KernelInterface $kernel) {}

    public function preRemove(object $entity): void
    {
        $name = $entity->getName();
        if (!$name) {
            return;
        }

        $path = $this->kernel->getProjectDir() . '/public/' . $name;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
