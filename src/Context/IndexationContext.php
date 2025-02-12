<?php

declare(strict_types=1);

namespace TwentytwoLabs\BehatSeoExtension\Context;

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Webmozart\Assert\Assert;

final class IndexationContext extends BaseContext
{
    private ?RobotsContext $robotsContext = null;
    private ?MetaContext $metaContext = null;

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        /** @var InitializedContextEnvironment $env */
        $env = $scope->getEnvironment();

        $this->robotsContext = $env->getContext(RobotsContext::class);
        $this->metaContext = $env->getContext(MetaContext::class);
    }

    /**
     * @Then the page should be indexable
     */
    public function thePageShouldBeIndexable(): void
    {
        $this->metaContext->thePageShouldNotBeNoindex();
        $this->robotsContext->iShouldBeAbleToCrawl($this->getCurrentUrl());

        if ($robotsHeaderTag = $this->getResponseHeader('X-Robots-Tag')) {
            Assert::notContains(
                strtolower($robotsHeaderTag),
                'noindex',
                sprintf(
                    'Url %s should not send X-Robots-Tag HTTP header with noindex value: %s',
                    $this->getCurrentUrl(),
                    $robotsHeaderTag
                )
            );
        }
    }

    /**
     * @Then the page should not be indexable
     */
    public function thePageShouldNotBeIndexable(): void
    {
        $this->assertInverse([$this, 'thePageShouldBeIndexable'], 'The page is indexable.');
    }
}
