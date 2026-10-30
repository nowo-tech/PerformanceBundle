<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Security\AllowAllPerformanceAccessChecker;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Support\PageFixtures;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;
use function http_build_query;

/**
 * Builds a PerformanceController partial mock by named constructor arguments (unit tests of the HTTP layer): render,
 * redirectToRoute and addFlash are captured; createForm returns the form registered per type.
 *
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 */
trait ControllerTestHarness
{
    /** @var array<string, mixed> */
    private array $rendered = [];

    /** @var list<array{0: string, 1: string}> */
    private array $flashes = [];

    /** @var array<string, FormInterface> */
    private array $forms = [];

    /**
     * @param array<string, mixed> $args named constructor arguments (merged over the defaults)
     * @param list<string> $extraMethods extra methods to mock
     */
    private function controller(PerformanceMetricsService $metrics, array $args = [], array $extraMethods = []): PerformanceController&MockObject
    {
        $builder = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs(array_merge([
                'metricsService'  => $metrics,
                'analysisService' => null,
                'enabled'         => true,
                'accessChecker'   => new AllowAllPerformanceAccessChecker(),
                'pageFactory'     => PageFixtures::factory(),
            ], $args))
            ->onlyMethods(array_merge(['createForm', 'render', 'addFlash', 'redirectToRoute', 'getParameter'], $extraMethods));
        $controller = $builder->getMock();

        $controller->method('getParameter')->willReturn('test');
        $controller->method('render')->willReturnCallback(function (string $view, array $parameters = []): Response {
            $this->rendered = $parameters;

            return new Response($view);
        });
        $controller->method('redirectToRoute')->willReturnCallback(static fn (string $route, array $parameters = []): RedirectResponse => new RedirectResponse('/' . $route . '?' . http_build_query($parameters)));
        $controller->method('addFlash')->willReturnCallback(function (string $type, mixed $message): void {
            $this->flashes[] = [$type, (string) $message];
        });
        $controller->method('createForm')->willReturnCallback(fn (string $type): FormInterface => $this->forms[$type] ?? $this->form());

        return $controller;
    }

    /**
     * A submitted, valid (or invalid) form returning $data.
     */
    private function form(mixed $data = null, bool $valid = true): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn($valid);
        $form->method('getData')->willReturn($data);
        $form->method('createView')->willReturn(new FormView());
        $form->method('handleRequest')->willReturnSelf();
        $child = $this->createMock(FormInterface::class);
        $child->method('getData')->willReturn(null);
        $form->method('get')->willReturn($child);

        return $form;
    }

    private function flashed(string $type, string $needle): bool
    {
        foreach ($this->flashes as [$t, $message]) {
            if ($t === $type && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
