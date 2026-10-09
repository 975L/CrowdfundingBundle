<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Entity\Traits;

use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\Mapping as ORM;

// The account a row was created or last changed by, filled by Listener\Traits\UserTrait
trait UserTrait
{
    // "SET NULL" and not the default: this records no author, and deleting that account must not be blocked by it
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?UserInterface $user = null;

    // Gets the user
    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    // Sets the user
    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }
}
