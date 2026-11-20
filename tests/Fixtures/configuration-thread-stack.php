<?php

declare(strict_types=1);

/*
 * Run by ConfigurationThreadStackTest in a PHP process whose stack is capped like a FrankenPHP thread on Alpine
 * (-d zend.max_allowed_stack_size=512K): it autoloads, and so compiles, the configuration class the way a request does,
 * builds the tree, processes the defaults and dumps both references. Argument: the class name.
 */

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\XmlReferenceDumper;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$class = $argv[1];
/** @var ConfigurationInterface $configuration */
$configuration = new $class();

try {
    $defaults = var_export((new Processor())->processConfiguration($configuration, [[]]), true);
} catch (InvalidConfigurationException $e) {
    // A required key without a default: the message is part of the comparison.
    $defaults = $e->getMessage();
}

echo json_encode([
    'defaults' => $defaults,
    'yaml'     => (new YamlReferenceDumper())->dump($configuration),
    'xml'      => (new XmlReferenceDumper())->dump($configuration),
], \JSON_THROW_ON_ERROR);
