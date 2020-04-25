<?php

$config = new Prooph\CS\Config\Prooph();
$config
    ->getFinder()
    ->in(__DIR__)
    ->exclude('GPBMetadata')
    ->exclude('src/Messages/ClientMessages');

$cacheDir = getenv('TRAVIS') ? getenv('HOME') . '/.php-cs-fixer' : __DIR__;

$config->setCacheFile($cacheDir . '/.php_cs.cache');

return $config;
