<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\CrowdfundingBundle\Repository\LotteryPrizeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotteryPrizeRepository::class)]
#[ORM\Table(name: 'crowdfunding_lottery_prize')]
class LotteryPrize
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: 'smallint')]
    private int $rank;

    #[ORM\ManyToOne(targetEntity: Lottery::class, inversedBy: 'prizes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lottery $lottery = null;

    #[ORM\OneToOne(targetEntity: LotteryTicket::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?LotteryTicket $winningTicket = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $drawDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    // What this row says in the language being rendered, laid over the texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a screen rendered in English cannot write English over the text the row was written in (see CrowdfundingTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->translated['title'] ?? $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->translated['description'] ?? $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): self
    {
        // Ensure rank is between 1-5
        $this->rank = max(1, min(5, $rank));

        return $this;
    }

    public function getLottery(): ?Lottery
    {
        return $this->lottery;
    }

    public function setLottery(?Lottery $lottery): self
    {
        $this->lottery = $lottery;

        return $this;
    }

    public function getWinningTicket(): ?LotteryTicket
    {
        return $this->winningTicket;
    }

    public function setWinningTicket(?LotteryTicket $ticket): self
    {
        $this->winningTicket = $ticket;

        return $this;
    }

    public function getDrawDate(): ?\DateTimeInterface
    {
        return $this->drawDate;
    }

    public function setDrawDate(?\DateTimeInterface $drawDate): self
    {
        $this->drawDate = $drawDate;

        return $this;
    }

    public function getCreation(): ?\DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(?\DateTimeInterface $creation): self
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?\DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(?\DateTimeInterface $modification): self
    {
        $this->modification = $modification;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    // Lays what a language says over the texts this row was written with, for the render being built and no longer than that - only CrowdfundingTranslator calls it, and only on the front, a form screen having to go on reading the row
    /** @param array<string, string|null> $values field => value */
    public function setTranslated(array $values): void
    {
        $this->translated = $values;
    }

    // The text the row itself carries, whatever language is being rendered - what a language screen offers as the thing to translate, and what tells an untouched field from a written one (see CrowdfundingTranslator)
    public function getUntranslated(string $field): ?string
    {
        return match ($field) {
            'title' => $this->title,
            'description' => $this->description,
            default => null,
        };
    }
}
