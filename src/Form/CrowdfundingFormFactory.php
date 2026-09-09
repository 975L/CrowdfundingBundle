<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class CrowdfundingFormFactory implements CrowdfundingFormFactoryInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function create(string $name, $object): FormInterface
    {
        $type = match ($name) {
            'news' => CrowdfundingNewsType::class,
            default => throw new \InvalidArgumentException(sprintf('Unknown form "%s"', $name)),
        };

        return $this->formFactory->create($type, $object);
    }
}
