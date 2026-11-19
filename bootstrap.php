<?php

use Lab\CorpusStats;
use Lab\DuplicateFinder;
use Lab\PostRepository;
use Lab\PostVectorizer;
use Lab\StaticEmbedder;

$autoload = __DIR__.'/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    // No Composer available: fall back to a plain PSR-4 loader for Lab\.
    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'Lab\\')) {
            $file = __DIR__.'/src/'.str_replace('\\', '/', substr($class, 4)).'.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

$config = require __DIR__.'/config.php';

$embedder = new StaticEmbedder(
    $config['static_dir'].'/model.safetensors',
    $config['static_dir'].'/tokenizer.json',
    dropUnknown: false,
    model: $config['model'],
);

return [
    'config' => $config,
    'embedder' => $embedder,
    'vectorizer' => new PostVectorizer($embedder, CorpusStats::load($config['stats_path'])),
    'posts' => new PostRepository($config['database']),
    'finder' => new DuplicateFinder,
];
