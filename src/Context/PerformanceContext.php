<?php

declare(strict_types=1);

namespace TwentytwoLabs\BehatSeoExtension\Context;

use Behat\Mink\Element\NodeElement;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

final class PerformanceContext extends BaseContext
{
    public const RES_EXT = [
        'PNG' => 'png',
        'HTML' => 'html',
        'JPEG' => 'jpeg',
        'GIF' => 'gif',
        'ICO' => 'ico',
        'JAVASCRIPT' => 'js',
        'CSS' => 'css',
        'CSS_INLINE_HEAD' => 'css-inline-head',
        'CSS_LINK_HEAD' => 'css-link-head',
    ];

    /**
     * @Then CSS code should load deferred
     */
    public function cssFilesShouldLoadDeferred(): void
    {
        Assert::isEmpty(
            $this->getSelfHostedPageResources(self::RES_EXT['CSS_LINK_HEAD']),
            sprintf('Some self hosted css files are loading in head in %s', $this->getCurrentUrl())
        );
    }

    /**
     * @Then critical CSS code should exist in head
     */
    public function criticalCssShouldExistInHead(): void
    {
        Assert::notEmpty(
            $this->getSelfHostedPageResources(self::RES_EXT['CSS_INLINE_HEAD']),
            sprintf('No inline css is loading in head in %s', $this->getCurrentUrl())
        );
    }

    /**
     * @Then critical CSS code should not exist in head
     */
    public function criticalCssShouldNotExistInHead(): void
    {
        $this->assertInverse([$this, 'criticalCssShouldExistInHead'], 'Critical CSS exist in head.');
    }

    /**
     * @Then HTML code should be minified
     */
    public function htmlShouldBeMinified(): void
    {
        $content = $this->getSession()->getPage()->getContent();

        $this->assertContentIsMinified($content, $this->minimizeHtml($content));
    }

    /**
     * @Then HTML code should not be minified
     */
    public function htmlShouldNotBeMinified(): void
    {
        $this->assertInverse([$this, 'htmlShouldBeMinified'], 'HTML should not be minified.');
    }

    /**
     * @Then /^Javascript code should load (async|defer)$/
     */
    public function javascriptFilesShouldLoadAsync(): void
    {
        foreach ($this->getSelfHostedPageResources(self::RES_EXT['JAVASCRIPT']) as $scriptElement) {
            Assert::true(
                $scriptElement->hasAttribute('async') || $scriptElement->hasAttribute('defer'),
                sprintf(
                    'Javascript file %s is render blocking in %s',
                    $this->getResourceUrl($scriptElement, self::RES_EXT['JAVASCRIPT']),
                    $this->getCurrentUrl()
                )
            );
        }
    }

    /**
     * @Then /^Javascript code should not load (async|defer)$/
     */
    public function jsShouldNotLoadAsyncOr(): void
    {
        $this->assertInverse([$this, 'javascriptFilesShouldLoadAsync'], 'All JS files load async.');
    }

    /**
     * @Then /^(CSS|Javascript) code should be minified$/
     */
    public function cssOrJavascriptFilesShouldBeMinified(string $resourceType): void
    {
        $resourceType = 'Javascript' === $resourceType ? 'js' : 'css';

        foreach ($this->getSelfHostedPageResources($resourceType) as $element) {
            if ($url = $this->getResourceUrl($element, $resourceType)) {
                $this->getSession()->visit($url);
            }

            $content = $this->getSession()->getPage()->getContent();

            $this->assertContentIsMinified(
                $content,
                'js' === $resourceType ? $this->minimizeJs($content) : $this->minimizeCss($content)
            );

            $this->getSession()->back();
        }
    }

    /**
     * @Then /^(CSS|Javascript) code should not be minified$/
     */
    public function cssOrJavascriptFilesShouldNotBeMinified(string $resourceType): void
    {
        $this->assertInverse(
            fn() => $this->cssOrJavascriptFilesShouldBeMinified($resourceType),
            sprintf('%s should not be minified.', $resourceType)
        );
    }

    /**
     * @Then /^browser cache should not be enabled for (.+|external|internal) (png|jpeg|gif|ico|js|css) resources$/
     */
    public function browserCacheMustNotBeEnabledForResources(string $host, string $resourceType): void
    {
        $this->assertInverse(
            fn() => $this->browserCacheMustBeEnabledForResources($host, $resourceType),
            sprintf('Browser cache is enabled for %s resources.', $resourceType)
        );
    }

    /**
     * @Then /^browser cache should be enabled for (.+|external|internal) (png|jpeg|gif|ico|js|css) resources$/
     */
    public function browserCacheMustBeEnabledForResources(string $host, string $resourceType): void
    {
        $elements = match ($host) {
            'internal' => $this->getSelfHostedPageResources($resourceType),
            default => $this->getPageResources($resourceType, $host),
        };

        Assert::notEmpty($elements, sprintf('The are not %s resources in %s.', $host, $this->getCurrentUrl()));

        $this->checkResourceCache($elements[array_rand($elements)], $resourceType);
    }

    /**
     * @return NodeElement[]
     */
    private function getPageResources(string $resourceType, ?string $host = null): array
    {
        if (!$xpath = $this->getResourceXpath($resourceType)) {
            return [];
        }

        if ('external' === $host) {
            $xpath = preg_replace(
                '/\[contains\(@(.*),/',
                '[not(starts-with(@$1,"' . $this->webUrl . '") or starts-with(@$1,"/")) and contains(@$1,',
                $xpath
            );
        } elseif (null !== $host) {
            $xpath = preg_replace(
                '/\[contains\(@(.*),/',
                '[(starts-with(@$1,"' . $host . '") or starts-with(@$1,"/")) and contains(@$1,',
                $xpath
            );
        }

        if ($xpath) {
            return $this->getSession()->getPage()->findAll('xpath', $xpath);
        }

        return [];
    }

    /**
     * @return NodeElement[]
     */
    private function getSelfHostedPageResources(string $resourceType): array
    {
        if (!$xpath = $this->getResourceXpath($resourceType)) {
            return [];
        }

        $xpath = preg_replace(
            '/\[contains\(@(.*),/',
            sprintf('[(starts-with(@$1,"%s") or starts-with(@$1,"/")) and contains(@$1,', $this->webUrl),
            $xpath
        );

        if ($xpath) {
            return $this->getSession()->getPage()->findAll('xpath', $xpath);
        }

        return [];
    }

    private function getResourceXpath(string $resourceType): string
    {
        return match ($resourceType) {
            self::RES_EXT['JPEG'], self::RES_EXT['PNG'], self::RES_EXT['GIF'] => sprintf(
                '//img[contains(@src,".%s")]',
                $resourceType
            ),
            self::RES_EXT['ICO'], self::RES_EXT['CSS'] => sprintf('//link[contains(@href,".%s")]', $resourceType),
            self::RES_EXT['JAVASCRIPT'] => '//script[contains(@src,".js")]',
            self::RES_EXT['CSS_INLINE_HEAD'] => '//head//style',
            self::RES_EXT['CSS_LINK_HEAD'] => '//head//link[contains(@href,".css")]',
            default => '',
        };
    }

    private function getResourceUrl(NodeElement $element, string $resourceType): ?string
    {
        $this->assertResourceTypeIsValid($resourceType);

        if (
            in_array(
                $resourceType,
                [self::RES_EXT['PNG'], self::RES_EXT['JPEG'], self::RES_EXT['GIF'], self::RES_EXT['JAVASCRIPT']],
                true
            )
        ) {
            return $element->getAttribute('src');
        }

        if (in_array($resourceType, [self::RES_EXT['CSS'], self::RES_EXT['ICO']], true)) {
            return $element->getAttribute('href');
        }

        throw new InvalidArgumentException(
            sprintf('%s resource type url is not implemented', $resourceType)
        );
    }

    private function assertResourceTypeIsValid(string $resourceType): void
    {
        if (!in_array($resourceType, self::RES_EXT, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s resource type is not valid. Allowed types are: %s',
                    $resourceType,
                    implode(',', self::RES_EXT)
                )
            );
        }
    }

    private function assertContentIsMinified(string $content, string $contentMinified): void
    {
        Assert::same($content, $contentMinified, 'Code is not minified.');
    }

    private function minimizeHtml(string $html): string
    {
        return preg_replace('/(?<=>)\s+|\s+(?=<)/', '', $html) ?? $html;
    }

    private function minimizeJs(string $javascript): string
    {
        $minimized = preg_replace(
            [
                '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
            ],
            ['$1', '$1$2'],
            $javascript
        );

        return $minimized ?? $javascript;
    }

    private function minimizeCss(string $css): string
    {
        $minimized = preg_replace(
            [
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~+]|\s*+-(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            ],
            ['$1', '$1$2$3$4$5$6$7'],
            $css
        );

        return $minimized ?? $css;
    }

    private function checkResourceCache(NodeElement $element, string $resourceType): void
    {
        $url = $this->getResourceUrl($element, $resourceType);

        Assert::notNull($url);

        $this->getSession()->visit($url);
        $headers = array_change_key_case($this->getSession()->getResponseHeaders());
        $this->getSession()->back();

        Assert::keyExists(
            $headers,
            'cache-control',
            sprintf(
                'Browser cache is not enabled for %s resources. Cache-Control HTTP header was not received.',
                $resourceType
            )
        );

        Assert::notContains(
            $headers['cache-control'][0],
            'no-cache',
            sprintf(
                'Browser cache is not enabled for %s resources. Cache-Control HTTP header is "no-cache".',
                $resourceType
            )
        );

        Assert::notContains(
            $headers['cache-control'][0],
            'max-age=0',
            sprintf(
                'Browser cache is not enabled for %s resources. Cache-Control HTTP header is "max-age=0".',
                $resourceType
            )
        );
    }
}
