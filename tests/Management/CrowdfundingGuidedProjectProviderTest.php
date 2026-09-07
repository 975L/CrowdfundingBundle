<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CrowdfundingBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\CrowdfundingBundle\Management\CrowdfundingGuidedProjectProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class CrowdfundingGuidedProjectProviderTest extends TestCase
{
    private function createProvider(array &$controllers = []): CrowdfundingGuidedProjectProvider
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator, &$controllers) {
            $controllers[] = $controller;

            return $generator;
        });
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/crowdfunding');

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return new CrowdfundingGuidedProjectProvider($generator, $configService);
    }

    // The 9000 block GuidedProjectProviderInterface reserves this bundle, at the step of 10 it states - and the order a campaign is actually built in, not an alphabetical one
    public function testGetGuidedProjectsRunsTheReservedBlockInBuildOrder(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(
            ['crowdfunding-campaign', 'crowdfunding-media', 'crowdfunding-counterpart', 'crowdfunding-blocks', 'crowdfunding-lottery', 'crowdfunding-draw-video'],
            array_column($projects, 'slug')
        );
        $this->assertSame([9010, 9020, 9030, 9040, 9050, 9060], array_column($projects, 'order'));
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertStringStartsWith('crowdfunding-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    public function testEveryProjectCarriesTheCrowdfundingDomainAndSteps(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('crowdfunding', $project['translation_domain']);
            $this->assertNotEmpty($project['steps']);
        }
    }

    // Every screen of this bundle sits behind the site's admin role, index to delete (see CrowdfundingCrudController::configureActions): a parcours offered below it walks a 403
    public function testEveryProjectCarriesTheAdminRole(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('ROLE_ADMIN', $project['role']);
        }
    }

    public function testNoStepSetsBothUrlAndHighlight(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                $this->assertFalse(
                    isset($step['url']) && isset($step['highlight']),
                    sprintf('Step %d of "%s" sets both url and highlight', $index, $project['slug'])
                );
            }
        }
    }

    // Only the opening step leaves the screen, everything after it walking the one the user has been sent to
    public function testOnlyTheFirstStepOfEachProjectCarriesAnUrl(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $steps = $project['steps'];

            $this->assertArrayHasKey('url', $steps[0], sprintf('Project "%s" does not open on a screen', $project['slug']));

            foreach (array_slice($steps, 1) as $index => $step) {
                $this->assertArrayNotHasKey('url', $step, sprintf('Step %d of "%s" leaves the screen again', $index + 1, $project['slug']));
            }
        }
    }

    // This bundle contributes a single CRUD, a campaign carrying its media, its counterparts, its lottery and its blocks on its own form: every parcours opens there and is told apart by the fieldset it walks to
    public function testEveryProjectOpensOnTheCampaignsScreen(): void
    {
        $controllers = [];
        $this->createProvider($controllers)->getGuidedProjects();

        $this->assertSame(array_fill(0, 6, 'CrowdfundingCrudController'), array_map(
            static fn (string $fqcn): string => basename(str_replace('\\', '/', $fqcn)),
            $controllers
        ));
    }

    // EasyAdmin renders the form's save button as action-saveAndReturn, .action-save matching nothing and leaving the step highlighting an empty selection
    public function testEverySaveStepHighlightsTheEasyAdminSaveButton(): void
    {
        $saveSteps = [];

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $step) {
                if (str_ends_with($step['label'], '_save')) {
                    $saveSteps[] = $step;
                }
            }
        }

        $this->assertCount(6, $saveSteps, 'Each of the six parcours walks the user to the save button once');

        foreach ($saveSteps as $step) {
            $this->assertSame('.action-saveAndReturn', $step['highlight']);
        }
    }

    // A CollectionField prints no id, so its row carries a marker instead - one laid in a controller or a form type here, not in a template, and renamed there it would leave its step highlighting nothing while the panel goes on showing itself
    public function testEveryDataAttributeHighlightedIsStillDeclared(): void
    {
        $sources = file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Management/CrowdfundingCrudController.php');
        foreach (glob(\dirname(__DIR__, 2) . '/src/Form/*.php') as $formType) {
            $sources .= file_get_contents($formType);
        }
        // "data-ui-sort-group" is UiBundle's own, laid on the blocks field by the row_attr builder this controller calls
        $sources .= file_get_contents(\dirname(__DIR__, 2) . '/vendor/c975l/core-bundle/UiBundle/src/Service/BlockMoveRowAttrBuilder.php');

        $attributes = [];
        foreach ($this->highlights() as $highlight) {
            if (preg_match('/\[(data-[a-z-]+)/', $highlight, $matches)) {
                $attributes[] = $matches[1];
            }
        }

        $this->assertNotEmpty($attributes);

        foreach ($attributes as $attribute) {
            $this->assertStringContainsString($attribute, (string) $sources, sprintf('Nothing lays "%s" any more', $attribute));
        }
    }

    // A rich-text field hides the element carrying its id: the step points at the widget EasyAdmin draws in its place, and a form theme dropping it would leave the step highlighting nothing
    public function testTheRichTextStepPointsAtTheWidgetEasyAdminDraws(): void
    {
        $this->assertContains('trix-editor[input="Crowdfunding_description"]', $this->highlights());
        $this->assertStringContainsString(
            '<trix-editor input=',
            (string) file_get_contents(\dirname(__DIR__, 2) . '/vendor/easycorp/easyadmin-bundle/templates/crud/form_theme.html.twig')
        );
    }

    // Every "#Entity_property" a step points at is a field the form still declares, a renamed property leaving its step highlighting nothing
    public function testEveryFieldHighlightedIsStillOnTheForm(): void
    {
        $controller = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Management/CrowdfundingCrudController.php');

        $fields = [];
        foreach ($this->highlights() as $highlight) {
            if (preg_match('/^#Crowdfunding_([A-Za-z]+)$/', $highlight, $matches)) {
                $fields[] = $matches[1];
            }
        }

        $this->assertNotEmpty($fields);

        foreach ($fields as $field) {
            $this->assertStringContainsString(sprintf("::new('%s')", $field), $controller, sprintf('The form declares no "%s" field any more', $field));
        }
    }

    // The filming itself is a procedure and not a parcours - it happens on the public page, outside any screen a step could highlight - so the two parcours touching the draw name it rather than trying to walk it
    public function testTheDrawIsHandedOverToTheProcedureThatCoversIt(): void
    {
        $slugs = array_column(json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/config/procedures.json'), true), 'slug');

        $this->assertContains('filmer-tirage-loterie', $slugs);

        $catalogue = (string) file_get_contents(\dirname(__DIR__, 2) . '/translations/crowdfunding.fr.xlf');
        $this->assertStringContainsString('Filmer et publier un tirage de loterie', $catalogue, 'The lottery parcours points at the procedure by its own title');
    }

    // EasyAdmin folds every entry of a collection into a Bootstrap accordion item (see its crud/form_theme.html.twig): a field nested in one is in the page and invisible, and highlighting it outlines nothing anybody can see. Whichever parcours points inside an entry opens it first, on the step before
    public function testNoStepPointsInsideACollectionEntryBeforeOpeningIt(): void
    {
        // The class the fold is opened by, EasyAdmin's own: renamed at a version bump, both steps opening an entry would highlight nothing
        $this->assertStringContainsString(
            'accordion-button',
            (string) file_get_contents(\dirname(__DIR__, 2) . '/vendor/easycorp/easyadmin-bundle/templates/crud/form_theme.html.twig')
        );

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $previous = null;

            foreach ($project['steps'] as $step) {
                $highlight = $step['highlight'] ?? '';

                foreach (['[data-lottery-' => '[data-crowdfunding-lotteries]', '[data-counterpart-' => '[data-crowdfunding-counterparts]'] as $prefix => $collection) {
                    if (str_starts_with($highlight, $prefix)) {
                        $this->assertSame(
                            $collection . ' .accordion-button',
                            $previous,
                            sprintf('"%s" points inside a folded entry with no step opening it first', $step['label'])
                        );
                    }
                }

                $previous = $highlight;
            }
        }
    }

    // A label or description with no translation reads as its own key in the panel, in whichever locale it is missing from
    public function testEveryLabelAndDescriptionIsTranslatedInEveryLocale(): void
    {
        foreach (['en', 'fr', 'es'] as $locale) {
            $translated = $this->translatedKeys('crowdfunding.' . $locale);

            foreach ($this->createProvider()->getGuidedProjects() as $project) {
                foreach ([$project, ...$project['steps']] as $item) {
                    $this->assertContains($item['label'], $translated, sprintf('"%s" is missing from the %s catalogue', $item['label'], $locale));
                    if (isset($item['description'])) {
                        $this->assertContains($item['description'], $translated, sprintf('"%s" is missing from the %s catalogue', $item['description'], $locale));
                    }
                }
            }
        }
    }

    // Every "highlight" the parcours declare, whichever fieldset they walk to
    private function highlights(): array
    {
        $highlights = [];
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $step) {
                if (isset($step['highlight'])) {
                    $highlights[] = $step['highlight'];
                }
            }
        }

        return $highlights;
    }

    private function translatedKeys(string $catalogue): array
    {
        $xliff = new \DOMDocument();
        $xliff->load(\dirname(__DIR__, 2) . '/translations/' . $catalogue . '.xlf');

        $keys = [];
        foreach ($xliff->getElementsByTagName('source') as $source) {
            $keys[] = $source->textContent;
        }

        return $keys;
    }
}
