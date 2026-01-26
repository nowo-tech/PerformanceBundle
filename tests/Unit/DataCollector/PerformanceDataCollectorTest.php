<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DataCollector;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PerformanceDataCollectorTest extends TestCase
{
    private PerformanceDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new PerformanceDataCollector();
    }

    public function testGetName(): void
    {
        $this->assertSame('performance', $this->collector->getName());
    }

    public function testSetAndGetEnabled(): void
    {
        $this->collector->setEnabled(true);
        $this->assertTrue($this->collector->isEnabled());
        
        $this->collector->setEnabled(false);
        $this->assertFalse($this->collector->isEnabled());
    }

    public function testSetAndGetRouteName(): void
    {
        $this->collector->setRouteName('app_home');
        
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertSame('app_home', $this->collector->getRouteName());
    }

    public function testSetStartTimeAndCollect(): void
    {
        $startTime = microtime(true);
        $this->collector->setStartTime($startTime);
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $requestTime = $this->collector->getRequestTime();
        $this->assertNotNull($requestTime);
        $this->assertGreaterThan(0, $requestTime);
    }

    public function testSetQueryMetrics(): void
    {
        $this->collector->setQueryMetrics(10, 0.5);
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertSame(10, $this->collector->getQueryCount());
        $this->assertSame(500.0, $this->collector->getQueryTime()); // Converted to ms
    }

    public function testSetQueryCountAndTime(): void
    {
        $this->collector->setQueryCount(5);
        $this->collector->setQueryTime(0.25);
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertSame(5, $this->collector->getQueryCount());
        $this->assertSame(250.0, $this->collector->getQueryTime());
    }

    public function testGetFormattedRequestTime(): void
    {
        $this->collector->setStartTime(microtime(true) - 0.001); // 1ms ago
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedRequestTime();
        $this->assertStringContainsString('ms', $formatted);
    }

    public function testGetFormattedRequestTimeForSeconds(): void
    {
        $this->collector->setStartTime(microtime(true) - 1.5); // 1.5s ago
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedRequestTime();
        $this->assertStringContainsString('s', $formatted);
    }

    public function testGetFormattedRequestTimeWhenNull(): void
    {
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedRequestTime();
        $this->assertSame('N/A', $formatted);
    }

    public function testGetFormattedQueryTime(): void
    {
        $this->collector->setQueryTime(0.001); // 1ms
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedQueryTime();
        $this->assertStringContainsString('ms', $formatted);
    }

    public function testGetFormattedQueryTimeForSeconds(): void
    {
        $this->collector->setQueryTime(1.5); // 1.5s
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedQueryTime();
        $this->assertStringContainsString('s', $formatted);
    }

    public function testReset(): void
    {
        $this->collector->setStartTime(microtime(true));
        $this->collector->setQueryMetrics(10, 0.5);
        $this->collector->setRouteName('app_home');
        $this->collector->setEnabled(true);
        
        $this->collector->reset();
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertFalse($this->collector->isEnabled());
        $this->assertNull($this->collector->getRouteName());
        $this->assertNull($this->collector->getRequestTime());
        $this->assertSame(0, $this->collector->getQueryCount());
        $this->assertSame(0.0, $this->collector->getQueryTime());
    }

    public function testSetEnvironment(): void
    {
        // This method is currently a no-op, but we test it doesn't throw
        $this->collector->setEnvironment('dev');
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testSetRequestTime(): void
    {
        // This method is currently a no-op, but we test it doesn't throw
        $this->collector->setRequestTime(0.5);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }
}
