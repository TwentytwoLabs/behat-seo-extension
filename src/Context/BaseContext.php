<?php

declare(strict_types=1);

namespace TwentytwoLabs\BehatSeoExtension\Context;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use InvalidArgumentException;
use TwentytwoLabs\BehatSeoExtension\Exception\TimeoutException;

class BaseContext extends RawMinkContext
{
    protected string $webUrl;

    /**
     * @BeforeScenario
     */
    public function setupWebUrl(): void
    {
        $this->webUrl = $this->getMinkParameter('base_url');
    }

    protected function getOuterHtml(NodeElement $nodeElement): string
    {
        return $nodeElement->getOuterHtml();
    }

    protected function getResponseHeader(string $header): ?string
    {
        return $this->getSession()->getResponseHeader($header);
    }

    /**
     * @throws DriverException
     */
    protected function visit(string $url): void
    {
        $this->getSession()->getDriver()->visit($url);
    }

    protected function getStatusCode(): int
    {
        return $this->getSession()->getStatusCode();
    }

    /**
     * @throws TimeoutException
     */
    protected function spin(callable $closure, int $seconds = 5): bool
    {
        $iteration = 1;
        while ($iteration++ <= $seconds * 4) {
            if ($closure($this)) {
                return true;
            }
            $this->getSession()->wait(1000 / 4);
        }
        $backtrace = debug_backtrace();

        throw new TimeoutException(
            sprintf(
                "Timeout thrown by %s::%s()\n%s, line %s",
                $backtrace[0]['class'],
                $backtrace[0]['function'],
                $backtrace[0]['file'],
                $backtrace[0]['line']
            )
        );
    }

    protected function toAbsoluteUrl(string $url): string
    {
        if (!str_contains($url, '://')) {
            $url = sprintf('%s%s', $this->webUrl, $url);
        }

        return $url;
    }

    protected function getCurrentUrl(): string
    {
        return $this->getSession()->getCurrentUrl();
    }

    /**
     * @throws UnsupportedDriverActionException
     */
    protected function supportsDriver(string $driverClass): void
    {
        if (!is_a($this->getSession()->getDriver(), $driverClass)) {
            throw new UnsupportedDriverActionException(
                sprintf('This step is only supported by the %s driver', $driverClass),
                $this->getSession()->getDriver()
            );
        }
    }

    /**
     * @throws UnsupportedDriverActionException
     */
    protected function doesNotSupportDriver(string $driverClass): void
    {
        if (is_a($this->getSession()->getDriver(), $driverClass)) {
            throw new UnsupportedDriverActionException(
                sprintf('This step is not supported by the %s driver', $driverClass),
                $this->getSession()->getDriver()
            );
        }
    }

    protected function assertInverse(callable $callableStepDefinition, string $exceptionMessage = ''): void
    {
        try {
            $callableStepDefinition();
        } catch (InvalidArgumentException $e) {
            return;
        }

        throw new InvalidArgumentException($exceptionMessage);
    }
}
