<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/example')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PHP74Migration' => true,
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
