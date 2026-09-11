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
use c975L\CrowdfundingBundle\Repository\CrowdfundingRepository;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Contract\TrashableInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use c975L\UiBundle\Entity\Trait\TrashableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CrowdfundingRepository::class)]
#[ORM\Table(name: 'crowdfunding_crowdfunding')]
#[UniqueEntity('slug')]
class Crowdfunding implements HasBlocksInterface, TrashableInterface, \Stringable
{
    use HasBlocksTrait;
    use TrashableTrait;

    private string $type = 'crowdfunding';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // What an admin composes the rest of the campaign page with, on top of the fields above - the same kinds every other page of the site is built from
    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'crowdfunding_crowdfunding_block')]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $blocks;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    #[ORM\Column(length: 50)]
    private ?string $authorName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $authorPresentation = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorWebsite = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $amountGoal = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountAchieved = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $useFor = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $beginDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // The id breaks the tie, two rows being free to carry the same position an editor typed, and "orphanRemoval" is there for the reason $news carries it: the join column is nullable, so a media removed from the form only lost its campaign and stayed in the table with its file
    #[ORM\OneToMany(targetEntity: CrowdfundingMedia::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $medias;

    // Cascaded like everything else the campaign holds: without it, deleting a funded campaign for good was refused by the database, its contributors still pointing at the row being deleted. Only the second, deliberate deletion ever reaches here - the recycle bin removes nothing
    #[ORM\OneToMany(targetEntity: CrowdfundingContributor::class, mappedBy: 'crowdfunding', cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $contributors;

    // "persist" and "orphanRemoval" for the back office, where a news is written and corrected on the campaign's own form: without the first a news added there is never written, and without the second a deleted one only loses its campaign - the join column being nullable - and stays in the table forever
    #[ORM\OneToMany(targetEntity: CrowdfundingNews::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['publishedDate' => \SortDirection::Descending, 'id' => \SortDirection::Descending])]
    private Collection $news;

    // "orphanRemoval" like the collections around it - a counterpart being the one of them a contributor points at, CrowdfundingCrudController::updateEntity() refuses beforehand to remove one that was already subscribed, which the database would otherwise answer with a foreign key error
    #[ORM\OneToMany(targetEntity: CrowdfundingCounterpart::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['price' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $counterparts;

    // "orphanRemoval" like $medias, and for the same file left behind
    #[ORM\OneToMany(targetEntity: CrowdfundingVideo::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $videos;

    // "orphanRemoval" like the collections above - a lottery cascades to its own prizes, tickets and videos, so nothing of it is left pointing at a row that is gone
    #[ORM\OneToMany(targetEntity: Lottery::class, mappedBy: 'crowdfunding', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lotteries;

    // A campaign is written before it is opened: hidden, it stays out of the listing, out of the sitemap and out of every basket, its page answering 404 in the meantime - an editor reads it through the preview action. The column defaults to false so campaigns already online stay online the day it is created, the property to true so a campaign written from now on starts hidden
    #[ORM\Column(options: ['default' => false])]
    private bool $hidden = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
        $this->medias = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->contributors = new ArrayCollection();
        $this->news = new ArrayCollection();
        $this->counterparts = new ArrayCollection();
        $this->lotteries = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    // Trashing a campaign hides it too, the two never disagreeing: a row of the recycle bin is out of the listing whatever its own switch said before
    #[\Override]
    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        if ($isDeleted) {
            $this->hidden = true;
        }

        return $this;
    }

    // What this row says in the language being rendered, laid over the texts below and stored nowhere on the row: unmapped on purpose, Doctrine computing its changeset from the mapped properties and never from these getters, so a screen rendered in English cannot write English over the text the row was written in (see CrowdfundingTranslator, the only thing that sets it)
    /** @var array<string, string|null>|null */
    private ?array $translated = null;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

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

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorPresentation(): ?string
    {
        return $this->translated['authorPresentation'] ?? $this->authorPresentation;
    }

    public function setAuthorPresentation(string $authorPresentation): static
    {
        $this->authorPresentation = $authorPresentation;

        return $this;
    }

    public function getAuthorWebsite(): ?string
    {
        return $this->authorWebsite;
    }

    public function setAuthorWebsite(?string $authorWebsite): static
    {
        $this->authorWebsite = $authorWebsite;

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

    public function getAmountGoal(): ?int
    {
        return $this->amountGoal;
    }

    public function setAmountGoal(int $amountGoal): static
    {
        $this->amountGoal = $amountGoal;

        return $this;
    }

    public function getAmountAchieved(): ?int
    {
        return $this->amountAchieved;
    }

    public function setAmountAchieved(?int $amountAchieved): static
    {
        $this->amountAchieved = $amountAchieved;

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

    public function getUseFor(): ?string
    {
        return $this->useFor;
    }

    public function setUseFor(string $useFor): static
    {
        $this->useFor = $useFor;

        return $this;
    }

    public function getBeginDate(): ?\DateTimeInterface
    {
        return $this->beginDate;
    }

    public function setBeginDate(?\DateTimeInterface $beginDate): static
    {
        $this->beginDate = $beginDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(CrowdfundingMedia $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeMedia(CrowdfundingMedia $media): static
    {
        if ($this->medias->removeElement($media)) {
            // set the owning side to null (unless already changed)
            if ($media->getCrowdfunding() === $this) {
                $media->setCrowdfunding(null);
            }
        }

        return $this;
    }

    // The campaign's cover: the image that stands for it wherever the campaign is named but not opened - the listing card and the link a visitor shares. Shown whole and never cropped, so a banner an author framed themselves stays framed that way, where the opening image is printed over by the title and read full-bleed
    /** @return Collection<int, CrowdfundingMedia> */
    public function getCovers(): Collection
    {
        return $this->mediasOfKind(CrowdfundingMedia::KIND_COVER);
    }

    public function addCover(CrowdfundingMedia $media): static
    {
        $media->setKind(CrowdfundingMedia::KIND_COVER);

        return $this->addMedia($media);
    }

    public function removeCover(CrowdfundingMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The campaign's opening image, the one its page opens on, printed over by its name. Uploaded on a field of its own, which is what sets the kind: nothing here reads "the first media" any more, so a plate added at the top of the slider cannot become the opening by accident. A campaign holding no cover falls back on it (see components/Crowdfunding/Crowdfunding.html.twig), which is what campaigns written before the two were told apart still rely on
    /** @return Collection<int, CrowdfundingMedia> */
    public function getHeroes(): Collection
    {
        return $this->mediasOfKind(CrowdfundingMedia::KIND_HERO);
    }

    public function addHero(CrowdfundingMedia $media): static
    {
        $media->setKind(CrowdfundingMedia::KIND_HERO);

        return $this->addMedia($media);
    }

    public function removeHero(CrowdfundingMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // What the campaign's slider runs through. A file has one use: an image wanted both as the cover and in the slider is uploaded on each of the two fields, rather than one row being read twice
    /** @return Collection<int, CrowdfundingMedia> */
    public function getSlides(): Collection
    {
        return $this->mediasOfKind(CrowdfundingMedia::KIND_SLIDE);
    }

    public function addSlide(CrowdfundingMedia $media): static
    {
        $media->setKind(CrowdfundingMedia::KIND_SLIDE);

        return $this->addMedia($media);
    }

    public function removeSlide(CrowdfundingMedia $media): static
    {
        return $this->removeMedia($media);
    }

    // The campaign's own files of one kind, in the order they were sorted in
    /** @return Collection<int, CrowdfundingMedia> */
    public function mediasOfKind(string $kind): Collection
    {
        return $this->medias->filter(static fn (CrowdfundingMedia $media): bool => $kind === $media->getKind());
    }

    public function getContributors(): Collection
    {
        return $this->contributors;
    }

    public function addContributor(CrowdfundingContributor $contributor): static
    {
        if (!$this->contributors->contains($contributor)) {
            $this->contributors->add($contributor);
            $contributor->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeContributor(CrowdfundingContributor $contributor): static
    {
        if ($this->contributors->removeElement($contributor)) {
            // set the owning side to null (unless already changed)
            if ($contributor->getCrowdfunding() === $this) {
                $contributor->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getNews(): Collection
    {
        return $this->news;
    }

    public function addNews(CrowdfundingNews $news): static
    {
        if (!$this->news->contains($news)) {
            $this->news->add($news);
            $news->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeNews(CrowdfundingNews $news): static
    {
        if ($this->news->removeElement($news)) {
            // set the owning side to null (unless already changed)
            if ($news->getCrowdfunding() === $this) {
                $news->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getCounterparts(): Collection
    {
        return $this->counterparts;
    }

    public function addCounterpart(CrowdfundingCounterpart $counterpart): static
    {
        if (!$this->counterparts->contains($counterpart)) {
            $this->counterparts->add($counterpart);
            $counterpart->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeCounterpart(CrowdfundingCounterpart $counterpart): static
    {
        if ($this->counterparts->removeElement($counterpart)) {
            // set the owning side to null (unless already changed)
            if ($counterpart->getCrowdfunding() === $this) {
                $counterpart->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(CrowdfundingVideo $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeVideo(CrowdfundingVideo $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getCrowdfunding() === $this) {
                $video->setCrowdfunding(null);
            }
        }

        return $this;
    }

    public function getLotteries(): Collection
    {
        return $this->lotteries;
    }

    public function addLottery(Lottery $lottery): static
    {
        if (!$this->lotteries->contains($lottery)) {
            $this->lotteries->add($lottery);
            $lottery->setCrowdfunding($this);
        }

        return $this;
    }

    public function removeLottery(Lottery $lottery): static
    {
        if ($this->lotteries->removeElement($lottery)) {
            // set the owning side to null (unless already changed)
            if ($lottery->getCrowdfunding() === $this) {
                $lottery->setCrowdfunding(null);
            }
        }

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
            'authorPresentation' => $this->authorPresentation,
            default => null,
        };
    }
}
