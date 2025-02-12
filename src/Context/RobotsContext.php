<?php

declare(strict_types=1);

namespace TwentytwoLabs\BehatSeoExtension\Context;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use InvalidArgumentException;
use vipnytt\RobotsTxtParser\UriClient;
use Webmozart\Assert\Assert;

final class RobotsContext extends BaseContext
{
    private string $crawlerUserAgent = 'Googlebot';

    /**
     * @Given I am a :crawlerUserAgent crawler
     */
    public function iAmACrawler(string $crawlerUserAgent): void
    {
        $this->crawlerUserAgent = $crawlerUserAgent;
    }

    /**
     * @Then I should be able to crawl :resource
     */
    public function iShouldBeAbleToCrawl(string $resource): void
    {
        Assert::true(
            $this->getRobotsClient()->userAgent($this->crawlerUserAgent, '1.0')->isAllowed($resource),
            sprintf(
                'Crawler with User-Agent %s is not allowed to crawl %s',
                $this->crawlerUserAgent,
                $resource
            )
        );
    }

    /**
     * @Then I should not be able to crawl :resource
     */
    public function iShouldNotBeAbleToCrawl(string $resource): void
    {
        Assert::false(
            $this->getRobotsClient()->userAgent($this->crawlerUserAgent)->isAllowed($resource),
            sprintf(
                'Crawler with User-Agent %s is allowed to crawl %s',
                $this->crawlerUserAgent,
                $resource
            )
        );
    }

    /**
     * @Then I should be able to get the sitemap URL
     */
    public function iShouldBeAbleToGetTheSitemapUrl(): void
    {
        $sitemaps = $this->getRobotsClient()->sitemap()->export();

        Assert::false(
            empty($sitemaps),
            sprintf('Crawler with User-Agent %s can not find a sitemap url in robots file.', $this->crawlerUserAgent)
        );

        Assert::count(
            $sitemaps,
            1,
            sprintf(
                'Crawler with User-Agent %s has find more than 1 sitemap url in robots file.',
                $this->crawlerUserAgent
            )
        );

        try {
            $this->getSession()->visit($sitemaps[0]);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('Sitemap url %s is not valid. Exception: %s', $sitemaps[0], $e->getMessage()),
                0,
                $e
            );
        }

        Assert::eq(200, $this->getStatusCode(), sprintf('Sitemap url %s is not valid.', $sitemaps[0]));
    }

    private function getRobotsClient(): UriClient
    {
        return new UriClient($this->webUrl);
    }
}
