<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Service;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\CrowdfundingContributor;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpartMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingMedia;
use c975L\CrowdfundingBundle\Entity\CrowdfundingNews;
use c975L\UiBundle\Contract\DemoFixtureLinkerInterface;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureTranslator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The one open campaign a demo site runs, its tiers, news and contributors, the amounts summed from them and every visitor-facing text staged for translation
class CrowdfundingDemoFixtureProvider implements DemoFixtureLinkerInterface, DemoFixtureProviderInterface
{
    private const string SLUG = 'four-atelier-ceramique';

    // Written down rather than taken from the clock, so a reloaded demo reads the same in every take of the same recorded sequence; the end is far enough ahead for the campaign to stay open
    private const string CREATION = '2026-02-01 10:00:00';
    private const string BEGIN = '2026-02-01';
    private const string END = '2027-12-31';
    private const string NEWS = '2026-03-15';

    // In cents, as every price of the ecosystem
    private const int GOAL = 300000;

    // slug => [title, description, price, limited quantity (0 = unlimited), shipped]
    private const array COUNTERPARTS = [
        'merci' => ['counterpart_thanks_title', 'counterpart_thanks_description', 1500, 0, false],
        'bol-emaille' => ['counterpart_bowl_title', 'counterpart_bowl_description', 4500, 50, true],
        'journee-atelier' => ['counterpart_day_title', 'counterpart_day_description', 12000, 10, false],
    ];

    // [name, e-mail, message key or null, tier slug, quantity, days after the opening]
    private const array CONTRIBUTORS = [
        ['Claire Moreau', 'claire.moreau@example.com', 'contributor_message_1', 'journee-atelier', 1, 2],
        ['Paul Rivière', 'paul.riviere@example.com', null, 'bol-emaille', 2, 3],
        ['Inès Duval', 'ines.duval@example.com', 'contributor_message_2', 'merci', 1, 5],
        ['Marc Lenoir', 'marc.lenoir@example.com', null, 'bol-emaille', 1, 9],
        ['Sophie Garnier', 'sophie.garnier@example.com', null, 'merci', 2, 14],
        ['Hugo Perrin', 'hugo.perrin@example.com', 'contributor_message_3', 'journee-atelier', 2, 21],
        ['Julie Bernard', 'julie.bernard@example.com', null, 'bol-emaille', 3, 30],
        ['Thomas Faure', 'thomas.faure@example.com', null, 'merci', 1, 42],
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DemoFixtureTranslator $demoFixtureTranslator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getDemoFixtures(): iterable
    {
        $creation = new \DateTime(self::CREATION);
        $crowdfunding = $this->crowdfunding($creation);

        $counterparts = [];
        $images = $this->placeholderMediaRegistry->getImages();
        foreach (self::COUNTERPARTS as $slug => [$title, $description, $price, $limited, $shipped]) {
            $counterparts[$slug] = $this->counterpart($slug, $title, $description, $price, $limited, $shipped, $creation);

            // A picture per tier, rotated through the generic pool so the three cards differ; a site declaring none gets the empty one the bundle's own listener adds
            $picture = [] === $images ? null : $images[\count($counterparts) % \count($images)];
            $file = null === $picture ? null : $this->temporaryCopy($picture);
            if (null !== $file) {
                $media = new CrowdfundingCounterpartMedia()->setCrowdfundingCounterpart($counterparts[$slug]);
                $media->setFile($file);
                $counterparts[$slug]->setMedia($media);
            }

            $crowdfunding->addCounterpart($counterparts[$slug]);
        }

        $news = new CrowdfundingNews()
            ->setTitle($this->text('news_title'))
            ->setContent('<div>' . $this->text('news_content') . '</div>')
            ->setPublishedDate(new \DateTime(self::NEWS))
            ->setCreation($creation)
            ->setModification($creation);
        $this->stage($news, CrowdfundingTranslator::OWNER_NEWS, ['title' => 'news_title'], ['content' => 'news_content']);
        $crowdfunding->addNews($news);

        $contributors = [];
        $achieved = 0;
        foreach (self::CONTRIBUTORS as [$name, $email, $message, $slug, $quantity, $days]) {
            $counterpart = $counterparts[$slug];
            $counterpart->setOrderedQuantity((int) $counterpart->getOrderedQuantity() + $quantity);
            $achieved += (int) $counterpart->getPrice() * $quantity;
            $contributors[] = $this->contributor($crowdfunding, $counterpart, $name, $email, $message, $quantity, $days);
        }

        $crowdfunding->setAmountAchieved($achieved);

        // The campaign carries its tiers, news and pictures through its own cascade; the contributors, then the tiers they chose, follow it
        yield $crowdfunding;

        foreach ($contributors as $contributor) {
            yield $contributor;
        }

        foreach ($contributors as $contributor) {
            yield from $contributor->getContributorCounterparts();
        }
    }

    private function crowdfunding(\DateTime $creation): Crowdfunding
    {
        $crowdfunding = new Crowdfunding()
            ->setTitle($this->text('crowdfunding_title'))
            ->setSlug(self::SLUG)
            ->setDescription('<div>' . $this->text('crowdfunding_description') . '</div>')
            ->setUseFor('<div>' . $this->text('crowdfunding_use_for') . '</div>')
            ->setAuthorName('Atelier Terre & Feu')
            ->setAuthorPresentation('<div>' . $this->text('crowdfunding_author') . '</div>')
            ->setAuthorWebsite(null)
            ->setAmountGoal(self::GOAL)
            ->setCurrency('EUR')
            ->setBeginDate(new \DateTime(self::BEGIN))
            ->setEndDate(new \DateTime(self::END))
            ->setPosition(1)
            ->setHidden(false)
            ->setCreation($creation)
            ->setModification($creation)
        ;
        $this->stage($crowdfunding, CrowdfundingTranslator::OWNER_CAMPAIGN, ['title' => 'crowdfunding_title'], ['description' => 'crowdfunding_description', 'authorPresentation' => 'crowdfunding_author']);

        // The pictures the site keys "crowdfunding/<slug>", failing those the generic pool: the first opens the page and stands for the campaign on its card, the rest run through the slider
        $pictures = $this->placeholderMediaRegistry->getImagesFor('crowdfunding/' . self::SLUG);
        if ([] === $pictures) {
            $pictures = $this->placeholderMediaRegistry->getImages();
        }

        foreach ($pictures as $index => $picture) {
            $file = $this->temporaryCopy($picture);
            if (null === $file) {
                continue;
            }

            $media = new CrowdfundingMedia()->setPosition($index + 1);
            $media->setFile($file);

            if (0 === $index) {
                $crowdfunding->addHero($media);

                // A cover of its own rather than the same row read twice, a file having one use (see Crowdfunding::getSlides())
                $cover = $this->temporaryCopy($picture);
                if (null !== $cover) {
                    $coverMedia = new CrowdfundingMedia()->setPosition(1);
                    $coverMedia->setFile($cover);
                    $crowdfunding->addCover($coverMedia);
                }

                continue;
            }

            $crowdfunding->addSlide($media);
        }

        return $crowdfunding;
    }

    private function counterpart(string $slug, string $title, string $description, int $price, int $limited, bool $shipped, \DateTime $creation): CrowdfundingCounterpart
    {
        $counterpart = new CrowdfundingCounterpart()
            ->setTitle($this->text($title))
            ->setSlug($slug)
            ->setDescription('<div>' . $this->text($description) . '</div>')
            ->setPrice($price)
            ->setCurrency('eur')
            ->setLimitedQuantity($limited)
            ->setOrderedQuantity(0)
            ->setRequiresShipping($shipped)
            ->setCreation($creation)
            ->setModification($creation)
        ;
        $this->stage($counterpart, CrowdfundingTranslator::OWNER_COUNTERPART, ['title' => $title], ['description' => $description]);

        return $counterpart;
    }

    private function contributor(Crowdfunding $crowdfunding, CrowdfundingCounterpart $counterpart, string $name, string $email, ?string $message, int $quantity, int $days): CrowdfundingContributor
    {
        $date = new \DateTime(self::BEGIN)->modify('+' . $days . ' days')->setTime(18, 30);

        return new CrowdfundingContributor()
            ->setName($name)
            ->setEmail($email)
            ->setMessage(null === $message ? null : $this->text($message))
            ->setLocale('fr')
            ->setCrowdfunding($crowdfunding)
            ->addCounterpart($counterpart, $quantity)
            ->setCreation($date)
            ->setModification($date)
        ;
    }

    // The rows staged above said in each of the other languages the site declares, now that they have identifiers
    public function getLinkedDemoFixtures(): iterable
    {
        return $this->demoFixtureTranslator->translations();
    }

    // Stages a row's fields for translation from their sample names, the rich ones wrapped in the box a rich-text field stores its prose in
    /**
     * @param array<string, string> $plain field => sample name
     * @param array<string, string> $rich  field => sample name
     */
    private function stage(object $owner, string $ownerType, array $plain, array $rich): void
    {
        $keys = static fn (array $names): array => array_map(static fn (string $name): string => 'label.sample_' . $name, $names);

        $this->demoFixtureTranslator->stage($owner, $ownerType, 'crowdfunding', $keys($plain));
        $this->demoFixtureTranslator->stage($owner, $ownerType, 'crowdfunding', $keys($rich), static fn (string $value): string => '<div>' . $value . '</div>');
    }

    // VichUploader moves the file it is handed, so it gets a copy - as a ReplacingFile, a plain File being what UploadHandler::hasUploadedFile() ignores in silence
    private function temporaryCopy(string $publicPath): ?ReplacingFile
    {
        $source = $this->projectDir . '/public/' . $publicPath;
        if (!is_file($source)) {
            return null;
        }

        $target = sys_get_temp_dir() . '/c975l-demo-' . uniqid() . '-' . basename($publicPath);

        return copy($source, $target) ? new ReplacingFile($target, true, true, true) : null;
    }

    // Every sample text, each spelled out as a call to the catalogue so the key is named where a reader - and the domain test - looks for it
    private function text(string $name): string
    {
        return match ($name) {
            'contributor_message_1' => $this->translator->trans('label.sample_contributor_message_1', [], 'crowdfunding'),
            'contributor_message_2' => $this->translator->trans('label.sample_contributor_message_2', [], 'crowdfunding'),
            'contributor_message_3' => $this->translator->trans('label.sample_contributor_message_3', [], 'crowdfunding'),
            'counterpart_bowl_description' => $this->translator->trans('label.sample_counterpart_bowl_description', [], 'crowdfunding'),
            'counterpart_bowl_title' => $this->translator->trans('label.sample_counterpart_bowl_title', [], 'crowdfunding'),
            'counterpart_day_description' => $this->translator->trans('label.sample_counterpart_day_description', [], 'crowdfunding'),
            'counterpart_day_title' => $this->translator->trans('label.sample_counterpart_day_title', [], 'crowdfunding'),
            'counterpart_thanks_description' => $this->translator->trans('label.sample_counterpart_thanks_description', [], 'crowdfunding'),
            'counterpart_thanks_title' => $this->translator->trans('label.sample_counterpart_thanks_title', [], 'crowdfunding'),
            'crowdfunding_author' => $this->translator->trans('label.sample_crowdfunding_author', [], 'crowdfunding'),
            'crowdfunding_description' => $this->translator->trans('label.sample_crowdfunding_description', [], 'crowdfunding'),
            'crowdfunding_title' => $this->translator->trans('label.sample_crowdfunding_title', [], 'crowdfunding'),
            'crowdfunding_use_for' => $this->translator->trans('label.sample_crowdfunding_use_for', [], 'crowdfunding'),
            'news_content' => $this->translator->trans('label.sample_news_content', [], 'crowdfunding'),
            'news_title' => $this->translator->trans('label.sample_news_title', [], 'crowdfunding'),
            default => throw new \LogicException(sprintf('No sample text named "%s".', $name)),
        };
    }
}
