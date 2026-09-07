<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form;

use Symfony\Component\Form\FormInterface;

interface CrowdfundingFormFactoryInterface
{
    /**
     * Builds one of the bundle's own forms by name, bound to the given entity.
     *
     * @param string $name   the form's short name
     * @param mixed  $object the entity the form is bound to
     */
    public function create(string $name, $object): FormInterface;
}
