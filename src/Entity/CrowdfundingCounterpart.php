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
use c975L\CrowdfundingBundle\Repository\CrowdfundingCounterpartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrowdfundingCounterpartRepository::class)]
#[ORM\Table(name: 'crowdfunding_counterpart')]
class CrowdfundingCounterpart implements \Stringable
{
    public function __construct()
    {
        $this->contributorCounterparts = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column(nullable: true, type: 'smallint')]
    private ?int $limitedQuantity = 0;

    #[ORM\Column(nullable: true, type: 'smallint')]
    private ?int $orderedQuantity = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $expectedDelivery = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'eur';

    #[ORM\Column(type: 'boolean')]
    private bool $requiresShipping = false;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $lotteryTickets = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne(targetEntity: Crowdfunding::class, inversedBy: 'counterparts')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Crowdfunding $crowdfunding = null;

    #[ORM\OneToMany(targetEntity: CrowdfundingContributorCounterpart::class, mappedBy: 'counterpart')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $contributorCounterparts;

    #[ORM\OneToOne(inversedBy: 'crowdfundingCounterpart', cascade: ['persist', 'remove'])]
    private ?CrowdfundingCounterpartMedia $media = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }

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

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getLimitedQuantity(): ?int
    {
        return $this->limitedQuantity;
    }

    public function setLimitedQuantity(?int $limitedQuantity): static
    {
        $this->limitedQuantity = $limitedQuantity;

        return $this;
    }

    public function getOrderedQuantity(): ?int
    {
        return $this->orderedQuantity;
    }

    public function setOrderedQuantity(?int $orderedQuantity): static
    {
        $this->orderedQuantity = $orderedQuantity;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->translated['description'] ?? $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getExpectedDelivery(): ?string
    {
        return $this->translated['expectedDelivery'] ?? $this->expectedDelivery;
    }

    public function setExpectedDelivery(?string $expectedDelivery): static
    {
        $this->expectedDelivery = $expectedDelivery;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getRequiresShipping(): bool
    {
        return $this->requiresShipping;
    }

    public function setRequiresShipping(bool $requiresShipping): self
    {
        $this->requiresShipping = $requiresShipping;

        return $this;
    }

    public function getLotteryTickets(): int
    {
        return $this->lotteryTickets;
    }

    public function setLotteryTickets(int $lotteryTickets): self
    {
        $this->lotteryTickets = max(0, min(10, $lotteryTickets));

        return $this;
    }

    public function getCreation(): ?\DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(\DateTimeInterface $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?\DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(\DateTimeInterface $modification): static
    {
        $this->modification = $modification;

        return $this;
    }

    public function getCrowdfunding(): ?Crowdfunding
    {
        return $this->crowdfunding;
    }

    public function setCrowdfunding(?Crowdfunding $crowdfunding): static
    {
        $this->crowdfunding = $crowdfunding;

        return $this;
    }

    public function getContributors(): Collection
    {
        $contributors = new ArrayCollection();

        foreach ($this->contributorCounterparts as $relation) {
            $contributors->add($relation->getContributor());
        }

        return $contributors;
    }

    public function getMedia(): ?CrowdfundingCounterpartMedia
    {
        return $this->media;
    }

    public function setMedia(?CrowdfundingCounterpartMedia $media): static
    {
        $this->media = $media;

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
            'expectedDelivery' => $this->expectedDelivery,
            default => null,
        };
    }
}
