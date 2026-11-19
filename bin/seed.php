<?php

/**
 * Seeds the lab with the "existing" posts from bench/posts.php, then rebuilds the corpus statistics
 * and re-vectorises everything (the same step as bin/refresh.php). Usage: php bin/seed.php [--fresh]
 */
use Lab\Embedding;
use Lab\Reindexer;

$app = require __DIR__.'/../bootstrap.php';
$posts = (require __DIR__.'/../bench/posts.php')['existing'];

if (in_array('--fresh', $argv, true)) {
    $app['posts']->reset();
}

foreach ($posts as $reference => $post) {
    ['vector' => $vector, 'language' => $language] = $app['vectorizer']->vectorize(Embedding::textFor($post['title'], $post['description']));
    $app['posts']->create(['reference' => $reference, 'lang' => $language] + $post, $app['config']['model'], $vector);
}

$result = Reindexer::run($app);

printf(
    "Seeded %d posts. Rebuilt word weights and language averages from %d posts (%s) and re-vectorised them in %.0f ms.\n",
    count($posts),
    $result['posts'],
    implode(', ', array_map(fn ($language, $count) => "{$count} {$language}", array_keys($result['languages']), $result['languages'])),
    $result['milliseconds'],
);
