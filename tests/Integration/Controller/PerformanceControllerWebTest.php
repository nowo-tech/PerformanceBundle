<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Controller;

use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Integration tests using WebTestCase (client with session/cookies) to submit forms with valid CSRF.
 */
final class PerformanceControllerWebTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testClearPostWithValidCsrfViaFormSubmit(): void
    {
        $client = self::createClient();
        $this->createTablesAndRecordMetric($client);

        $crawler = $client->request('GET', '/performance?env=test');
        if ($client->getResponse()->isRedirection()) {
            $crawler = $client->followRedirect();
        }
        self::assertResponseIsSuccessful();

        $form = $crawler->filterXpath('//form[@name="clear_performance_data"]')->form();
        $form['clear_performance_data[env]']->setValue('test');
        $client->submit($form);

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString('performance', $location);
    }

    public function testDashboardFormContainsClearForm(): void
    {
        $client = self::createClient();
        $this->createTablesAndRecordMetric($client);

        $crawler = $client->request('GET', '/performance');
        if ($client->getResponse()->isRedirection()) {
            $crawler = $client->followRedirect();
        }
        self::assertResponseIsSuccessful();

        $clearForm = $crawler->filterXpath('//form[@name="clear_performance_data"]');
        self::assertCount(1, $clearForm, 'Dashboard should contain clear performance data form');
        $tokenInput = $clearForm->filterXpath('.//input[@name="clear_performance_data[_token]"]');
        self::assertNotEmpty($tokenInput->attr('value'));
    }

    private function createTablesAndRecordMetric(KernelBrowser $client): void
    {
        $kernel      = $client->getKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
        $service = $kernel->getContainer()->get(PerformanceMetricsService::class);
        $service->recordMetrics('web_test_route', 'test', 0.1, 3, 0.02);
    }
}
