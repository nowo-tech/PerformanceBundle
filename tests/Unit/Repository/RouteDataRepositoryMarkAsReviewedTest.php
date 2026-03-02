<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteDataRepository::markAsReviewed() method.
 */
final class RouteDataRepositoryMarkAsReviewedTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->registry      = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry->method('getManager')->willReturn($this->entityManager);
        $this->registry->method('getManagerForClass')
            ->with(RouteData::class)
            ->willReturn($this->entityManager);

        $classMetadata = new ClassMetadata(RouteData::class);
        $this->entityManager->method('getClassMetadata')
            ->with(RouteData::class)
            ->willReturn($classMetadata);
    }

    public function testMarkAsReviewedReturnsFalseWhenRouteNotFound(): void
    {
        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find'])
            ->getMock();

        $repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $repository->markAsReviewed(999, true, false, 'admin');

        $this->assertFalse($result);
    }

    public function testMarkAsReviewedReturnsTrueAndFlushesWhenRouteFound(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find'])
            ->getMock();

        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($routeData);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $repository->markAsReviewed(1, true, false, 'reviewer');

        $this->assertTrue($result);
        $this->assertTrue($routeData->isReviewed());
    }

    public function testMarkAsReviewedWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find'])
            ->getMock();

        $repository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($routeData);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $repository->markAsReviewed(5, false, true, 'admin');

        $this->assertTrue($result);
        $this->assertTrue($routeData->isReviewed());
        $this->assertSame('stage', $routeData->getEnv());
    }

    public function testMarkAsReviewedWithNullReviewedBy(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $repository = $this->getMockBuilder(RouteDataRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['find'])
            ->getMock();

        $repository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($routeData);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $repository->markAsReviewed(2, null, null, null);

        $this->assertTrue($result);
        $this->assertTrue($routeData->isReviewed());
        $this->assertNull($routeData->getReviewedBy());
    }
}
