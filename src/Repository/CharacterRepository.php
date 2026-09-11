<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Repository;

use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Serie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Character> */
class CharacterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Character::class);
    }

    /**
     * Who peoples one serie, in the order their cards are presented - what the serie's page reads, and what a planche's screen offers to pick from.
     *
     * @return Character[]
     */
    public function findBySerie(Serie $serie): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.serie = :serie')
            ->setParameter('serie', $serie)
            ->orderBy('c.position', \SortDirection::Ascending)
            ->addOrderBy('c.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult()
        ;
    }

    // The one a slug names inside a serie, or null - two series may each people themselves with a "Papa", so the serie is part of the question
    public function findOneBySlug(Serie $serie, string $slug): ?Character
    {
        return $this->findOneBy(['serie' => $serie, 'slug' => $slug]);
    }
}
