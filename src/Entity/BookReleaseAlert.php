<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Entity;

use c975L\BookBundle\Repository\BookReleaseAlertRepository;
use Doctrine\ORM\Mapping as ORM;

// One visitor waiting on one book coming out. Held against the book and not against an edition: a book is published once, in every format at once, where ShopBundle's stock alert waits on the item that ran out
#[ORM\Entity(repositoryClass: BookReleaseAlertRepository::class)]
#[ORM\Table(name: 'book_release_alert')]
#[ORM\UniqueConstraint(name: 'uniq_release_alert_book_email', columns: ['book_id', 'email'])]
class BookReleaseAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The book deleted takes its waiting list with it: what nobody will read is not worth being told about
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    // The same length ShopBundle's stock alert gives its own address column, the two holding addresses of the same visitors
    #[ORM\Column(length: 100)]
    private string $email;

    // The language the subscription was taken in, there being nothing else to read it from: both e-mails are composed in it rather than in whatever the nightly command happens to run under
    #[ORM\Column(length: 5)]
    private string $locale;

    // What the unsubscribe link carries, in place of the address itself - a link naming an email address is a link that leaks one
    #[ORM\Column(length: 16, unique: true)]
    private string $token;

    // Read by the purge: a book announced and never published leaves its waiting list behind, and an address kept for a parution that never comes is an address kept for nothing (see BookReleaseAlertService::PENDING_RETENTION_MONTHS)
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // How many times the parution e-mail was tried on this address and refused. An address refused for good sat at the head of the queue for ever, filling every run's batch and serving nobody behind it: counted here, the row goes last and is eventually let go (see BookReleaseAlertService::MAX_ATTEMPTS)
    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(8));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): self
    {
        $this->book = $book;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    // One more refusal on this address, which is what sends the row to the back of the queue and, past the ceiling, out of it
    public function failed(): self
    {
        ++$this->attempts;

        return $this;
    }

    // Somebody subscribing again, from another browser or in another language: the unique constraint on (book, email) leaves no second row to create, and the wait starts over rather than aging towards the purge
    public function renew(string $locale): self
    {
        $this->locale = $locale;
        $this->createdAt = new \DateTimeImmutable();

        return $this;
    }
}
