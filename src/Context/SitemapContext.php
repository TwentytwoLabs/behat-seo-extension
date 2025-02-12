<?php

declare(strict_types=1);

namespace TwentytwoLabs\BehatSeoExtension\Context;

use Behat\Mink\Exception\DriverException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use InvalidArgumentException;
use TwentytwoLabs\BehatSeoExtension\Exception\InvalidOrderException;
use Webmozart\Assert\Assert;

final class SitemapContext extends BaseContext
{
    public const SITEMAP_SCHEMA_FILE = __DIR__ . '/../Resources/schemas/sitemap.xsd';
    public const SITEMAP_XHTML_SCHEMA_FILE = __DIR__ . '/../Resources/schemas/sitemap_xhtml.xsd';
    public const SITEMAP_INDEX_SCHEMA_FILE = __DIR__ . '/../Resources/schemas/sitemap_index.xsd';

    private DOMDocument $sitemapXml;

    /**
     * @Given the sitemap :sitemapUrl
     */
    public function theSitemap(string $sitemapUrl): void
    {
        $this->sitemapXml = $this->getSitemapXml($sitemapUrl);
    }

    /**
     * @Then /^the (index |multilanguage |)sitemap should not be valid$/
     */
    public function theSitemapShouldNotBeValid(string $sitemapType = ''): void
    {
        $this->assertInverse(
            function () use ($sitemapType) {
                $this->theSitemapShouldBeValid($sitemapType);
            },
            sprintf('The sitemap is a valid %s sitemap.', $sitemapType)
        );
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then /^the (index |multilanguage |)sitemap should be valid$/
     */
    public function theSitemapShouldBeValid(string $sitemapType = ''): void
    {
        $this->assertSitemapHasBeenRead();

        $sitemapSchemaFile = match (trim($sitemapType)) {
            'index' => self::SITEMAP_INDEX_SCHEMA_FILE,
            'multilanguage' => self::SITEMAP_XHTML_SCHEMA_FILE,
            default => self::SITEMAP_SCHEMA_FILE,
        };

        $this->assertValidSitemap(realpath($sitemapSchemaFile));
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then the index sitemap should have a child with URL :childSitemapUrl
     */
    public function theIndexSitemapShouldHaveAChildWithUrl(string $childSitemapUrl): void
    {
        $this->assertSitemapHasBeenRead();

        $xpathExpression = sprintf(
            '//sm:sitemapindex/sm:sitemap/sm:loc[substring(text(), string-length(text())- string-length("%s") + 1)  = "%s"]',
            $childSitemapUrl,
            $childSitemapUrl
        );

        $sitemapChildren = $this->getXpathInspector()->query($xpathExpression);

        Assert::notFalse($sitemapChildren);

        Assert::eq(
            $sitemapChildren->length,
            1,
            sprintf('Sitemap index %s has not child sitemap %s', $this->sitemapXml->documentURI, $childSitemapUrl)
        );
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then the index sitemap should not have a child with URL :childSitemapUrl
     */
    public function theIndexSitemapShouldNotHaveAChildWithUrl(string $childSitemapUrl): void
    {
        $this->assertSitemapHasBeenRead();

        $xpathExpression = sprintf(
            '//sm:sitemapindex/sm:sitemap/sm:loc[substring(text(), string-length(text())- string-length("%s") + 1)  = "%s"]',
            $childSitemapUrl,
            $childSitemapUrl
        );

        $sitemapChildren = $this->getXpathInspector()->query($xpathExpression);

        Assert::notFalse($sitemapChildren);

        Assert::eq(
            $sitemapChildren->length,
            0,
            sprintf(
                'Sitemap index %s has %d children sitemap %s',
                $this->sitemapXml->documentURI,
                $sitemapChildren->length,
                $childSitemapUrl
            )
        );
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then the sitemap should have a child with URL :childSitemapUrl
     */
    public function theSitemapShouldHaveAChildWithUrl(string $childSitemapUrl): void
    {
        $this->assertSitemapHasBeenRead();

        $xpathExpression = sprintf(
            '//sm:urlset/sm:url/sm:loc[substring(text(), string-length(text())- string-length("%s") + 1)  = "%s"]',
            $childSitemapUrl,
            $childSitemapUrl
        );

        $sitemapChildren = $this->getXpathInspector()->query($xpathExpression);

        Assert::notFalse($sitemapChildren);
        Assert::eq(
            $sitemapChildren->length,
            1,
            sprintf('Sitemap index %s has not child sitemap %s', $this->sitemapXml->documentURI, $childSitemapUrl)
        );
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then the sitemap should not have a child with URL :childSitemapUrl
     */
    public function theSitemapShouldNotHaveAChildWithUrl(string $childSitemapUrl): void
    {
        $this->assertSitemapHasBeenRead();

        $xpathExpression = sprintf(
            '//sm:urlset/sm:url/sm:loc[substring(text(), string-length(text())- string-length("%s") + 1)  = "%s"]',
            $childSitemapUrl,
            $childSitemapUrl
        );

        $sitemapChildren = $this->getXpathInspector()->query($xpathExpression);

        Assert::notFalse($sitemapChildren);

        Assert::eq(
            $sitemapChildren->length,
            0,
            sprintf(
                'Sitemap index %s has %d children sitemap %s',
                $this->sitemapXml->documentURI,
                $sitemapChildren->length,
                $childSitemapUrl
            )
        );
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then /^the sitemap should have ([0-9]+) children$/
     */
    public function theSitemapShouldHaveChildren(int $expectedChildrenCount): void
    {
        $this->assertSitemapHasBeenRead();

        $sitemapChildren = $this
            ->getXpathInspector()
            ->query('/*[self::sm:sitemapindex or self::sm:urlset]/*[self::sm:sitemap or self::sm:url]/sm:loc');

        Assert::notFalse($sitemapChildren);

        $sitemapChildrenCount = $sitemapChildren->length;

        Assert::eq(
            $expectedChildrenCount,
            $sitemapChildrenCount,
            sprintf(
                'Sitemap %s has %d children, expected value was: %d',
                $this->sitemapXml->documentURI,
                $sitemapChildrenCount,
                $expectedChildrenCount
            )
        );
    }

    /**
     * @throws InvalidOrderException
     * @throws DriverException
     *
     * @Then the sitemap URLs should be alive
     */
    public function theSitemapUrlsShouldBeAlive(): void
    {
        $this->assertSitemapHasBeenRead();

        $locNodes = $this->getXpathInspector()->query('//sm:urlset/sm:url/sm:loc');

        Assert::isInstanceOf($locNodes, DOMNodeList::class);

        foreach ($locNodes as $locNode) {
            $this->urlIsValid($locNode);
            $this->urlIsAlive($locNode);
        }
    }

    /**
     * @throws DriverException
     * @throws InvalidOrderException
     *
     * @Then /^(\d+) random sitemap URLs? should be alive$/
     */
    public function randomSitemapUrlsShouldBeAlive(int $randomUrlsCount): void
    {
        $this->assertSitemapHasBeenRead();

        $locNodes = $this->getXpathInspector()->query('//sm:urlset/sm:url/sm:loc');

        Assert::notFalse($locNodes);

        $locNodesArray = iterator_to_array($locNodes);

        $locNodesCount = count($locNodesArray);

        Assert::greaterThan(
            $locNodesCount,
            $randomUrlsCount,
            sprintf(
                'Sitemap %s only has %d children, minimum expected value was: %d',
                $this->sitemapXml->documentURI,
                $locNodesCount,
                $randomUrlsCount
            )
        );

        shuffle($locNodesArray);

        for ($i = 0; $i <= $randomUrlsCount - 1; $i++) {
            $this->urlIsValid($locNodesArray[$i]);
            $this->urlIsAlive($locNodesArray[$i]);
        }
    }

    /**
     * @throws InvalidOrderException
     *
     * @Then the multilanguage sitemap should pass Google validation
     */
    public function theMultilanguageSitemapShouldPassGoogleValidation(): void
    {
        $this->assertSitemapHasBeenRead();

        $this->assertValidSitemap(self::SITEMAP_XHTML_SCHEMA_FILE);

        $urlsNodes = $this->getXpathInspector()->query('//sm:urlset/sm:url');

        Assert::notFalse($urlsNodes);

        /** @var DOMElement $urlNode */
        foreach ($urlsNodes as $urlNode) {
            $urlElement = $urlNode->getElementsByTagName('loc')->item(0);

            Assert::notNull($urlElement);

            $urlLoc = $urlElement->nodeValue;

            /** @var DOMElement $alternateLink */
            foreach ($urlNode->getElementsByTagName('link') as $alternateLink) {
                $alternateLinkHref = $alternateLink->getAttribute('href');

                if ($alternateLinkHref !== $urlLoc) {
                    $alternateLinkNodes = $this->getXpathInspector()->query(
                        sprintf('//sm:urlset/sm:url/sm:loc[text()="%s"]', $alternateLinkHref)
                    );

                    Assert::notFalse($alternateLinkNodes);

                    Assert::eq(
                        $alternateLinkNodes->length,
                        1,
                        sprintf(
                            'Url %s has not reciprocous URL for alternative link %s in Sitemap %s',
                            $urlLoc,
                            $alternateLinkHref,
                            $this->sitemapXml->documentURI
                        )
                    );
                }
            }
        }
    }

    /**
     * @Then the multilanguage sitemap should not pass Google validation
     */
    public function theMultilanguageSitemapShouldNotPassGoogleValidation(): void
    {
        $this->assertInverse(
            [$this, 'theMultilanguageSitemapShouldPassGoogleValidation'],
            sprintf('The multilanguage sitemap passes Google validation.')
        );
    }

    private function getSitemapXml(string $sitemapUrl): DOMDocument
    {
        $this->getSession()->visit($sitemapUrl);

        $xml = new DOMDocument();
        $xml->strictErrorChecking = true;
        $xmlLoaded = @$xml->loadXML($this->getSession()->getPage()->getContent());

        Assert::true($xmlLoaded, 'Error loading %s Sitemap using DOMDocument');

        return $xml;
    }

    /**
     * @throws InvalidOrderException
     */
    private function assertSitemapHasBeenRead(): void
    {
        if (!isset($this->sitemapXml)) {
            throw new InvalidOrderException(
                'You should execute "Given the sitemap :sitemapUrl" step before executing this step.'
            );
        }
    }

    private function getXpathInspector(): DOMXPath
    {
        $xpath = new DOMXPath($this->sitemapXml);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        return $xpath;
    }

    private function assertValidSitemap(string $sitemapSchemaFile): void
    {
        Assert::fileExists(
            $sitemapSchemaFile,
            sprintf('Sitemap schema file %s does not exist', $sitemapSchemaFile)
        );

        Assert::true(
            @$this->sitemapXml->schemaValidate($sitemapSchemaFile),
            sprintf(
                'Sitemap %s does not pass validation using %s schema',
                $this->sitemapXml->documentURI,
                $sitemapSchemaFile
            )
        );
    }

    /**
     * @throws DriverException
     */
    private function urlIsValid(DOMNode $locNode): void
    {
        try {
            $this->visit($locNode->nodeValue);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                sprintf(
                    'Sitemap Url %s is not valid in Sitemap: %s. Exception: %s',
                    $locNode->nodeValue,
                    $this->sitemapXml->documentURI,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    private function urlIsAlive(DOMNode $locNode): void
    {
        Assert::eq(
            200,
            $this->getStatusCode(),
            sprintf(
                'Sitemap Url %s is not valid in Sitemap: %s. Response status code: %s',
                $locNode->nodeValue,
                $this->sitemapXml->documentURI,
                $this->getStatusCode()
            )
        );
    }
}
