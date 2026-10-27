<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when the dashboard is enabled without form CSRF protection (REQ-SEC-005).
 *
 * Dashboard mutations (clear data, delete record, review, purge access records, delete by filter) are validated through
 * Symfony forms whose types define their own CSRF options. A host that disables `framework.form.csrf_protection` (or
 * has no CSRF token manager) would silently accept forged POSTs, so the container refuses to compile instead.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    /**
     * Throws when `dashboard.enabled` is true and form CSRF protection or the CSRF token manager is unavailable.
     */
    public function process(ContainerBuilder $container): void
    {
        if (
            !$container->hasParameter('nowo_performance.dashboard.enabled')
            || $container->getParameter('nowo_performance.dashboard.enabled') !== true
        ) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoPerformanceBundle: the dashboard (dashboard.enabled: true) requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf), or set nowo_performance.dashboard.enabled: false.');
        }
    }
}
