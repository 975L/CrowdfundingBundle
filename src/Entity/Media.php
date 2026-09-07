<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Entity;

use c975L\CrowdfundingBundle\Repository\MediaRepository;
use c975L\UiBundle\Entity\Trait\VichMediaTrait;
use Doctrine\ORM\Mapping as ORM;

// Its own SINGLE_TABLE hierarchy, sharing only the trait with the other bundles' Media, never the table
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'crowdfunding_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'owner_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'crowdfunding' => CrowdfundingMedia::class,
    'crowdfunding_counterpart' => CrowdfundingCounterpartMedia::class,
    'crowdfunding_video' => CrowdfundingVideo::class,
    'lottery_video' => LotteryVideo::class,
])]
abstract class Media
{
    use VichMediaTrait;
}
