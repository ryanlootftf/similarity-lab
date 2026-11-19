<?php

/**
 * The "nightly job": rebuild word weights and language averages from every post, then re-vectorise all posts.
 * Usage: php bin/refresh.php
 */
$app = require __DIR__.'/../bootstrap.php';
$result = Lab\Reindexer::run($app);

printf(
    "Rebuilt from %d posts (%s) in %.0f ms.\n",
    $result['posts'],
    implode(', ', array_map(fn ($language, $count) => "{$count} {$language}", array_keys($result['languages']), $result['languages'])),
    $result['milliseconds'],
);
