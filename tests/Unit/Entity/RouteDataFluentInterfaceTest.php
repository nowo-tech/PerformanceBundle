<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteData fluent interface (setters return self).
 */
final class RouteDataFluentInterfaceTest extends TestCase
{
    public function testSetEnvReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setEnv('dev');

        $this->assertSame($routeData, $result);
    }

    public function testSetEnvWithEmptyStringReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setEnv('');

        $this->assertSame($routeData, $result);
        $this->assertSame('', $routeData->getEnv());
    }

    public function testSetEnvWithStageReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setEnv('stage');

        $this->assertSame($routeData, $result);
        $this->assertSame('stage', $routeData->getEnv());
    }

    public function testSetNameReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setName('app_home');

        $this->assertSame($routeData, $result);
    }

    public function testSetNameWithEmptyStringReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setName('');

        $this->assertSame($routeData, $result);
        $this->assertSame('', $routeData->getName());
    }

    public function testSetHttpMethodReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setHttpMethod('GET');

        $this->assertSame($routeData, $result);
    }

    public function testSetHttpMethodWithOPTIONSReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setHttpMethod('OPTIONS');

        $this->assertSame($routeData, $result);
        $this->assertSame('OPTIONS', $routeData->getHttpMethod());
    }

    public function testSetHttpMethodWithPUTReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setHttpMethod('PUT');

        $this->assertSame($routeData, $result);
        $this->assertSame('PUT', $routeData->getHttpMethod());
    }

    public function testSetParamsReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setParams(['id' => 123]);

        $this->assertSame($routeData, $result);
    }

    public function testSetParamsWithEmptyArrayReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setParams([]);

        $this->assertSame($routeData, $result);
        $this->assertSame([], $routeData->getParams());
    }

    public function testSetCreatedAtReturnsSelf(): void
    {
        $routeData = new RouteData();
        $date      = new DateTimeImmutable();
        $result    = $routeData->setCreatedAt($date);

        $this->assertSame($routeData, $result);
    }

    public function testSetLastAccessedAtReturnsSelf(): void
    {
        $routeData = new RouteData();
        $date      = new DateTimeImmutable();
        $result    = $routeData->setLastAccessedAt($date);

        $this->assertSame($routeData, $result);
    }

    public function testChainedSetters(): void
    {
        $routeData = new RouteData();
        $date      = new DateTimeImmutable();

        $result = $routeData
            ->setEnv('prod')
            ->setName('api_endpoint')
            ->setHttpMethod('POST')
            ->setParams(['id' => 456])
            ->setCreatedAt($date)
            ->setLastAccessedAt($date)
            ->setSaveAccessRecords(false);

        $this->assertSame($routeData, $result);
        $this->assertSame('prod', $routeData->getEnv());
        $this->assertSame('api_endpoint', $routeData->getName());
        $this->assertSame('POST', $routeData->getHttpMethod());
        $this->assertSame(['id' => 456], $routeData->getParams());
        $this->assertSame($date, $routeData->getCreatedAt());
        $this->assertSame($date, $routeData->getLastAccessedAt());
        $this->assertFalse($routeData->getSaveAccessRecords());
    }

    public function testSetSaveAccessRecordsReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setSaveAccessRecords(true);
        $this->assertSame($routeData, $result);
    }

    public function testSetReviewedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setReviewed(true);
        $this->assertSame($routeData, $result);
    }

    public function testSetReviewedAtReturnsSelf(): void
    {
        $routeData = new RouteData();
        $date      = new DateTimeImmutable();
        $result    = $routeData->setReviewedAt($date);
        $this->assertSame($routeData, $result);
    }

    public function testSetQueriesImprovedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setQueriesImproved(true);
        $this->assertSame($routeData, $result);
    }

    public function testSetTimeImprovedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setTimeImproved(false);
        $this->assertSame($routeData, $result);
    }

    public function testSetReviewedByReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result    = $routeData->setReviewedBy('admin@example.com');
        $this->assertSame($routeData, $result);
    }
}
