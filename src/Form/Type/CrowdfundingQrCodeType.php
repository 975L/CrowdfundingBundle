<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Not bound to any real Crowdfunding property - renders entirely through its own form theme block (c975l_crowdfunding_qrcode_widget in management/crowdfunding_crud_form_theme.html.twig), which reads the campaign through the form's own data. Under "Form/Type" and not beside the bundle's other types, which are each bound to an entity - a contract a test locks (see tests/Form/FormTypeContractTest.php) and that a panel bound to nothing cannot hold. Same pattern, and same place, as SiteBundle's PageQrCodeType
class CrowdfundingQrCodeType extends AbstractType
{
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'c975l_crowdfunding_qrcode';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
        ]);
    }
}
