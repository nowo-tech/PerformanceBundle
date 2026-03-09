<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class        => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class          => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class         => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                  => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class    => ['dev' => true, 'test' => true],
    Nowo\PerformanceBundle\NowoPerformanceBundle::class          => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class          => ['all' => true],
    Symfony\UX\Icons\UXIconsBundle::class                        => ['all' => true],
];
