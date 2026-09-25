<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Entity;

use c975L\CrowdfundingBundle\Entity\Crowdfunding;
use c975L\CrowdfundingBundle\Entity\Lottery;
use c975L\CrowdfundingBundle\Entity\LotteryPrize;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// An empty row added to the prizes of a lottery used to reach the NOT NULL title column and fail the save with a 500
class LotteryPrizeValidationTest extends TestCase
{
    // Reads the constraints off the entity attributes, as the back-office form does
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    // The back-office submits a collection row without the browser's own check, so the refusal has to come from the entity
    public function testAPrizeWithoutATitleIsRefused(): void
    {
        $violations = $this->validator()->validate(new LotteryPrize()->setDescription('Un livre dédicacé'));

        $this->assertCount(1, $violations);
        $this->assertSame('title', $violations->get(0)->getPropertyPath());
    }

    // The description fails through the same NOT NULL column as the title
    public function testAPrizeWithoutADescriptionIsRefused(): void
    {
        $violations = $this->validator()->validate(new LotteryPrize()->setTitle('Premier lot'));

        $this->assertCount(1, $violations);
        $this->assertSame('description', $violations->get(0)->getPropertyPath());
    }

    // Refusing the row is only worth something if the campaign looks inside its lotteries, and each lottery inside its prizes
    public function testTheCampaignValidatesItsLotteriesAndTheirPrizes(): void
    {
        $lotteries = $this->validator()->getMetadataFor(Crowdfunding::class)->getPropertyMetadata('lotteries');
        $prizes = $this->validator()->getMetadataFor(Lottery::class)->getPropertyMetadata('prizes');

        $this->assertSame(CascadingStrategy::CASCADE, $lotteries[0]->getCascadingStrategy());
        $this->assertSame(CascadingStrategy::CASCADE, $prizes[0]->getCascadingStrategy());
    }
}
