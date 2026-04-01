<?php

$config = new class extends Prooph\CS\Config\Prooph {
    public function getRules(): array
    {
        return \array_merge(parent::getRules(), ['header_comment' => false]);
    }
};
$config
    ->getFinder()
    ->in(__DIR__)
    ->exclude('GPBMetadata')
    ->exclude('src/Messages/ClientMessages');

$cacheDir = getenv('TRAVIS') ? getenv('HOME') . '/.php-cs-fixer' : __DIR__;

$config->setCacheFile($cacheDir . '/.php_cs.cache');

return $config;
