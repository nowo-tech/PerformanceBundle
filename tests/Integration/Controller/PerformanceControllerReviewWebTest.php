<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Controller;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A successful review through the real kernel (form from the review dialog, valid CSRF token in the client session):
 * the record is persisted as reviewed and the translated flash message is shown (regressions: the controller called
 * an undefined trans() method, and rejected the save_access_records field of the dialog as an extra field).
 */
final class PerformanceControllerReviewWebTest extends WebTestCase
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

    public function testReviewFromTheDialogPersistsAndFlashesTheTranslatedMessage(): void
    {
        $client = self::createClient();
        // one kernel (and in-memory database) for every request of the scenario
        $client->disableReboot();
        $kernel = $client->getKernel();
        $app    = new Application($kernel);
        $app->setAutoExit(false);
        (new CommandTester($app->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($app->find('nowo:performance:create-records-table')))->execute([]);
        $metrics = $kernel->getContainer()->get(PerformanceMetricsService::class);
        $metrics->recordMetrics('review_web_test_route', 'test', 0.1, 3, 0.02);
        $route = $metrics->getRepository()->findOneBy(['name' => 'review_web_test_route', 'env' => 'test']);
        self::assertInstanceOf(RouteData::class, $route);
        $id = (int) $route->getId();

        $crawler = $client->request('GET', '/performance/?env=test');
        self::assertResponseIsSuccessful();
        $form = $crawler->filterXPath('//form[@data-testid="performance-review-form-' . $id . '"]')->form();
        $form['review_route_data[queries_improved]']->setValue('1');
        $form['review_route_data[time_improved]']->setValue('0');
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('The record has been successfully marked as reviewed.', (string) $client->getResponse()->getContent());

        $client->getContainer()->get('doctrine')->getManager()->clear();
        $reviewed = $client->getContainer()->get(PerformanceMetricsService::class)->getRepository()->find($id);
        self::assertInstanceOf(RouteData::class, $reviewed);
        self::assertTrue($reviewed->isReviewed());
        self::assertTrue($reviewed->getQueriesImproved());
        self::assertFalse($reviewed->getTimeImproved());
        self::assertTrue($reviewed->getSaveAccessRecords());
    }
}
