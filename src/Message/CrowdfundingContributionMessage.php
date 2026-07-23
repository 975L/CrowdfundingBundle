<?php

namespace c975L\CrowdfundingBundle\Message;

class CrowdfundingContributionMessage
{
    public function __construct(
        private readonly int $basketId
    ) {
    }

    public function getBasketId(): int
    {
        return $this->basketId;
    }
}