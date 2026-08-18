<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PerformanceBundle\NowoPerformanceBundle;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class         => ['all' => true],
    SecurityBundle::class          => ['all' => true],
    DoctrineBundle::class          => ['all' => true],
    DoctrineFixturesBundle::class  => ['dev' => true, 'test' => true],
    TwigBundle::class              => ['all' => true],
    DebugBundle::class             => ['dev' => true, 'test' => true],
    WebProfilerBundle::class       => ['dev' => true, 'test' => true],
    NowoPerformanceBundle::class   => ['all' => true],
    NowoUiKitBundle::class         => ['all' => true],
    TwigComponentBundle::class     => ['all' => true],
    UXIconsBundle::class           => ['all' => true],
    NowoHotReloadBundle::class => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class => ['dev' => true, 'test' => true],
    TwigExtraBundle::class         => ['all' => true],
    NowoFormKitBundle::class       => ['all' => true],
];
