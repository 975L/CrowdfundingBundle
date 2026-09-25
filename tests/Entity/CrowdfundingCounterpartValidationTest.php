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
use c975L\CrowdfundingBundle\Entity\CrowdfundingCounterpart;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// An empty row added to the counterparts of a campaign used to be saved as is, where the slug is written off the title and the title was not there
class CrowdfundingCounterpartValidationTest extends TestCase
{
    // Reads the constraints off the entity attributes, as the back-office form does
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    // The back-office submits a collection row without the browser's own check, so the refusal has to come from the entity
    public function testACounterpartWithoutATitleIsRefused(): void
    {
        $violations = $this->validator()->validate(new CrowdfundingCounterpart());

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('title', $violations->get(0)->getPropertyPath());
    }

    // The description fails through the same NOT NULL column as the title
    public function testACounterpartWithoutADescriptionIsRefused(): void
    {
        $counterpart = new CrowdfundingCounterpart()->setTitle('Merci !')->setPrice(1500);

        $violations = $this->validator()->validate($counterpart);

        $this->assertCount(1, $violations);
        $this->assertSame('description', $violations->get(0)->getPropertyPath());
    }

    // NotNull and not NotBlank: a counterpart offered for nothing is a thank-you the campaign is entitled to publish
    public function testACounterpartWithoutAPriceIsRefusedButAFreeOneIsNot(): void
    {
        $counterpart = new CrowdfundingCounterpart()->setTitle('Merci !')->setDescription('Un mot de remerciement');

        $violations = $this->validator()->validate($counterpart);

        $this->assertCount(1, $violations);
        $this->assertSame('price', $violations->get(0)->getPropertyPath());
        $this->assertCount(0, $this->validator()->validate($counterpart->setPrice(0)));
    }

    // Refusing the row is only worth something if the campaign it was added to looks inside its own collection
    public function testTheCampaignValidatesEachOfItsCounterparts(): void
    {
        $counterparts = $this->validator()->getMetadataFor(Crowdfunding::class)->getPropertyMetadata('counterparts');

        $this->assertNotEmpty($counterparts);
        $this->assertSame(CascadingStrategy::CASCADE, $counterparts[0]->getCascadingStrategy());
    }
}
