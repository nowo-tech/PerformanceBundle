<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DataCollector;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PerformanceDataCollectorTest extends TestCase
{
    public function testGetName(): void
    {
        $collector = new PerformanceDataCollector();
        $this->assertSame('performance', $collector->getName());
    }

    public function testIsEnabledFalseByDefault(): void
    {
        $collector = new PerformanceDataCollector();
        $this->assertFalse($collector->isEnabled());
    }

    public function testSetEnabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $this->assertTrue($collector->isEnabled());
        $collector->setEnabled(false);
        $this->assertFalse($collector->isEnabled());
    }

    public function testResetClearsDataAfterCollect(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $collector->setRouteName('app_home');

        $request = new Request();
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame('app_home', $collector->getRouteName());
        $this->assertSame(0, $collector->getQueryCount());

        $collector->reset();

        $this->assertNull($collector->getRouteName());
        $this->assertSame(0, $collector->getQueryCount());
        $this->assertFalse($collector->isEnabled());
    }

    public function testSetRouteName(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setRouteName('api_foo');
        $collector->setEnabled(false);

        $request = new Request();
        $request->attributes->set('_route', 'other');
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('api_foo', $collector->getRouteName());
    }

    public function testGetFormattedRequestTimeReturnsNaWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('N/A', $collector->getFormattedRequestTime());
    }

    public function testGetFormattedQueryTimeReturnsZeroMsWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertStringContainsString('ms', $collector->getFormattedQueryTime());
        $this->assertSame(0, $collector->getQueryCount());
    }

    public function testGetProcessingModeReturnsSyncWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame('sync', $collector->getProcessingMode());
        $this->assertFalse($collector->isAsync());
    }

    public function testSetRecordOperationAndGetRecordOperationStatus(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $collector->setRecordOperation(true, false);
        $this->assertTrue($collector->wasRecordNew());
        $this->assertFalse($collector->wasRecordUpdated());
        $this->assertSame($collector->wasRecordNew(), $collector->getWasRecordNew());
        $this->assertSame($collector->wasRecordUpdated(), $collector->getWasRecordUpdated());
        $this->assertSame('New record created', $collector->getRecordOperationStatus());

        $collector->setRecordOperation(false, true);
        $this->assertSame('Existing record updated', $collector->getRecordOperationStatus());

        $collector->setRecordOperation(false, false);
        $this->assertSame('No changes (metrics not worse than existing)', $collector->getRecordOperationStatus());

        $collector->setRecordOperation(true, true);
        $this->assertSame('New record created', $collector->getRecordOperationStatus());
    }

    public function testGetRecordOperationStatusUnknownWhenBothNull(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->wasRecordNew());
        $this->assertNull($collector->wasRecordUpdated());
        $this->assertSame('Unknown', $collector->getRecordOperationStatus());
    }

    public function testConfiguredEnvironmentsAndDisabledReasonStoredWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setConfiguredEnvironments(['dev', 'prod']);
        $collector->setCurrentEnvironment('dev');
        $collector->setDisabledReason('Route ignored');
        $collector->setEnabled(false);

        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(['dev', 'prod'], $collector->getConfiguredEnvironments());
        $this->assertSame('dev', $collector->getCurrentEnvironment());
        $this->assertSame('Route ignored', $collector->getDisabledReason());
    }

    public function testGetMissingDependenciesAndDependencyStatusWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame([], $collector->getMissingDependencies());
        $this->assertSame([], $collector->getDependencyStatus());
    }

    public function testTableExistsTableIsCompleteGetTableNameGetMissingColumnsWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertFalse($collector->tableExists());
        $this->assertFalse($collector->tableIsComplete());
        $this->assertNull($collector->getTableName());
        $this->assertSame([], $collector->getMissingColumns());
    }

    public function testGetRequestTimeAccessCountRankingAndTotalRoutesWhenDisabled(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);
        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertNull($collector->getRequestTime());
        $this->assertNull($collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
        $this->assertNull($collector->getRankingByQueryCount());
        $this->assertNull($collector->getTotalRoutes());
    }

    public function testCollectMustBeCalledBeforeReset(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(false);

        $collector->reset();

        $this->assertNull($collector->getRouteName());
        $this->assertSame(0, $collector->getQueryCount());
    }

    public function testSetStartTimeBeforeCollect(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setStartTime(microtime(true) - 0.5);

        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $requestTime = $collector->getRequestTime();
        $this->assertNotNull($requestTime);
        $this->assertGreaterThanOrEqual(0.0, $requestTime);
    }

    public function testSetQueryMetricsAndGetQueryCountQueryTime(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setQueryMetrics(7, 0.15);

        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(7, $collector->getQueryCount());
        $this->assertStringContainsString('ms', $collector->getFormattedQueryTime());
    }

    public function testSetAsyncAndIsAsync(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setAsync(true);

        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        // isAsync() returns true only when both setAsync(true) AND Symfony Messenger is available
        $messengerAvailable = interface_exists('Symfony\Component\Messenger\MessageBusInterface')
            || class_exists('Symfony\Component\Messenger\MessageBusInterface');
        if ($messengerAvailable) {
            $this->assertTrue($collector->isAsync());
            $this->assertSame('async', $collector->getProcessingMode());
        } else {
            $this->assertFalse($collector->isAsync());
            $this->assertSame('sync', $collector->getProcessingMode());
        }
    }

    public function testSetQueryCountAndSetQueryTimeBeforeCollect(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setQueryCount(3);
        $collector->setQueryTime(0.12);

        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->assertSame(3, $collector->getQueryCount());
        $this->assertStringContainsString('ms', $collector->getFormattedQueryTime());
    }

    public function testSetRequestTimeCalledDoesNotThrow(): void
    {
        $collector = new PerformanceDataCollector();
        $collector->setEnabled(true);
        $collector->setRequestTime(0.5);

        $request = new Request();
        $response = new Response();
        $collector->collect($request, $response);

        $this->addToAssertionCount(1);
    }
}
