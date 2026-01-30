<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Message;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use PHPUnit\Framework\TestCase;

final class RecordMetricsMessageTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'GET'
        );

        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('dev', $message->getEnv());
        $this->assertSame(0.5, $message->getRequestTime());
        $this->assertSame(10, $message->getTotalQueries());
        $this->assertSame(0.2, $message->getQueryTime());
        $this->assertSame(['id' => 123], $message->getParams());
        $this->assertSame(1048576, $message->getMemoryUsage());
        $this->assertSame('GET', $message->getHttpMethod());
        $this->assertNull($message->getRequestId());
    }

    public function testGettersReturnNullForOptionalParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev'
        );

        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('dev', $message->getEnv());
        $this->assertNull($message->getRequestTime());
        $this->assertNull($message->getTotalQueries());
        $this->assertNull($message->getQueryTime());
        $this->assertNull($message->getParams());
        $this->assertNull($message->getMemoryUsage());
        $this->assertNull($message->getHttpMethod());
        $this->assertNull($message->getRequestId());
    }

    public function testGetRequestIdReturnsValueWhenProvided(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            'req-abc123'
        );

        $this->assertSame('req-abc123', $message->getRequestId());
    }

    public function testGetRequestIdReturnsNullWhenNotProvided(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev', null, null, null, null, null, 'GET');

        $this->assertNull($message->getRequestId());
    }

    public function testGetRefererReturnsValueWhenProvided(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            'req-abc',
            'https://referer.example/page'
        );

        $this->assertSame('https://referer.example/page', $message->getReferer());
    }

    public function testGetRefererReturnsNullWhenNotProvided(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev', 0.5, 5);

        $this->assertNull($message->getReferer());
    }

    public function testGetRefererWithEmptyString(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            'req-1',
            ''
        );

        $this->assertSame('', $message->getReferer());
    }

    public function testConstructorWithMinimalArgs(): void
    {
        $message = new RecordMetricsMessage('other_route', 'prod');

        $this->assertSame('other_route', $message->getRouteName());
        $this->assertSame('prod', $message->getEnv());
        $this->assertNull($message->getRequestTime());
        $this->assertNull($message->getMemoryUsage());
        $this->assertNull($message->getHttpMethod());
    }

    public function testConstructorWithMemoryUsageOnly(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            null,
            null,
            null,
            null,
            2_097_152,
            null
        );

        $this->assertSame(2_097_152, $message->getMemoryUsage());
        $this->assertSame('app_home', $message->getRouteName());
        $this->assertNull($message->getRequestTime());
    }

    public function testGetUserIdentifierReturnsValueWhenProvided(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            null,
            null,
            null,
            null,
            'GET',
            null,
            null,
            'john@example.com',
            'uuid-42'
        );

        $this->assertSame('john@example.com', $message->getUserIdentifier());
        $this->assertSame('uuid-42', $message->getUserId());
    }

    public function testGetUserIdentifierAndUserIdReturnNullWhenNotProvided(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev');

        $this->assertNull($message->getUserIdentifier());
        $this->assertNull($message->getUserId());
    }

    public function testRouteNameAndEnvAreRequired(): void
    {
        $message = new RecordMetricsMessage('api_foo', 'stage');
        $this->assertSame('api_foo', $message->getRouteName());
        $this->assertSame('stage', $message->getEnv());
    }

    public function testGetRouteNameAndEnvWithEmptyStrings(): void
    {
        $message = new RecordMetricsMessage('', '');

        $this->assertSame('', $message->getRouteName());
        $this->assertSame('', $message->getEnv());
    }

    public function testHttpMethodOptional(): void
    {
        $m = new RecordMetricsMessage('r', 'dev', 0.1, 1, 0.01, null, null, 'POST');
        $this->assertSame('POST', $m->getHttpMethod());
    }

    public function testGetParamsWithEmptyArray(): void
    {
        $m = new RecordMetricsMessage('r', 'dev', null, null, null, [], null);
        $this->assertSame([], $m->getParams());
    }

    public function testGetParamsWithMultipleKeys(): void
    {
        $params = ['id' => 42, 'slug' => 'my-article'];
        $m = new RecordMetricsMessage('article_show', 'dev', null, null, null, $params, null);
        $this->assertSame($params, $m->getParams());
    }

    public function testGetMemoryUsageWithZero(): void
    {
        $m = new RecordMetricsMessage('r', 'dev', null, null, null, null, 0, null);
        $this->assertSame(0, $m->getMemoryUsage());
    }

    public function testConstructorWithAllOptionalUserFields(): void
    {
        $message = new RecordMetricsMessage(
            'api_foo',
            'prod',
            0.1,
            3,
            0.05,
            ['id' => 1],
            524288,
            'POST',
            'req-xyz',
            'https://example.com/from',
            'admin@example.com',
            '12345'
        );

        $this->assertSame('admin@example.com', $message->getUserIdentifier());
        $this->assertSame('12345', $message->getUserId());
        $this->assertSame('https://example.com/from', $message->getReferer());
        $this->assertSame('req-xyz', $message->getRequestId());
    }

    public function testGetRequestTimeWithZero(): void
    {
        $message = new RecordMetricsMessage('r', 'dev', 0.0, null, null, null, null, null);

        $this->assertSame(0.0, $message->getRequestTime());
    }

    public function testGetQueryTimeWithZero(): void
    {
        $message = new RecordMetricsMessage('r', 'dev', null, null, 0.0, null, null, null);

        $this->assertSame(0.0, $message->getQueryTime());
    }

    public function testGetTotalQueriesWithZero(): void
    {
        $message = new RecordMetricsMessage('r', 'dev', null, 0, null, null, null, null);

        $this->assertSame(0, $message->getTotalQueries());
    }

    public function testGetHttpMethodWithPUT(): void
    {
        $message = new RecordMetricsMessage('api_update', 'dev', null, null, null, null, null, 'PUT');

        $this->assertSame('PUT', $message->getHttpMethod());
    }

    public function testGetHttpMethodWithPATCH(): void
    {
        $message = new RecordMetricsMessage('api_patch', 'dev', null, null, null, null, null, 'PATCH');

        $this->assertSame('PATCH', $message->getHttpMethod());
    }

    public function testGetHttpMethodWithDELETE(): void
    {
        $message = new RecordMetricsMessage('api_delete', 'prod', null, null, null, null, null, 'DELETE');

        $this->assertSame('DELETE', $message->getHttpMethod());
    }

    public function testGetHttpMethodWithOPTIONS(): void
    {
        $message = new RecordMetricsMessage('api_cors', 'dev', null, null, null, null, null, 'OPTIONS');

        $this->assertSame('OPTIONS', $message->getHttpMethod());
    }

    public function testGetHttpMethodWithHEAD(): void
    {
        $message = new RecordMetricsMessage('api_resource', 'prod', null, null, null, null, null, 'HEAD');

        $this->assertSame('HEAD', $message->getHttpMethod());
    }

    public function testGetHttpMethodWithCONNECT(): void
    {
        $message = new RecordMetricsMessage('api_proxy', 'prod', null, null, null, null, null, 'CONNECT');

        $this->assertSame('CONNECT', $message->getHttpMethod());
    }
}
