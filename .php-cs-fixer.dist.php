<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('tests')
    ->exclude('demo')
    ->exclude('coverage')
    ->exclude('.phpunit.cache');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'phpdoc_order' => true,
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
