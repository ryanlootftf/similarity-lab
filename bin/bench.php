<?php

/**
 * Feasibility numbers: model load, per-post embedding, search at increasing corpus sizes,
 * and how the model scores some duplicate / non-duplicate pairs.
 * Usage: php bin/bench.php
 */
use Lab\DuplicateFinder;
use Lab\PostRepository;

$app = require __DIR__.'/../bootstrap.php';
$embedder = $app['embedder'];

$memoryBefore = memory_get_usage(true);
$coldStartedAt = hrtime(true);
$embedder->embed('warm up');
$coldMilliseconds = (hrtime(true) - $coldStartedAt) / 1e6;
printf("Model            %s\n", $embedder->model);
printf("Model load       %.0f ms (paid once per PHP process)\n", $embedder->loadMilliseconds);
printf("Cold first embed %.0f ms (load + first inference = cost of one check on plain PHP-FPM)\n", $coldMilliseconds);
printf("PHP heap growth  %.0f MB\n\n", (memory_get_usage(true) - $memoryBefore) / 1048576);

$embedTimes = [];
foreach (range(1, 10) as $_) {
    $embedder->embed('The app crashes whenever I upload a video longer than a minute and my draft is lost.');
    $embedTimes[] = $embedder->embedMilliseconds;
}
sort($embedTimes);
printf("Embed one post   %.1f ms median over 10 runs\n\n", $embedTimes[5]);

/** @var array<int, array{0: string, 1: string, 2: string}> $pairs [label, a, b] */
$pairs = [
    ['reworded duplicate', 'App crashes when uploading a video. Every time I upload a clip longer than a minute the app closes.', 'Everything closes when I post a clip. The app just shuts down halfway through posting my video.'],
    ['reworded duplicate', 'Add dark mode. Please add a dark theme, the white background hurts my eyes at night.', 'Black theme please. The screen is way too bright when I use the app in bed.'],
    ['reworded duplicate', 'Coins disappeared after the update. I had 450 coins before updating and now it shows zero.', 'Lost all my coins. After the latest version my balance went back to 0.'],
    ['same area, different issue', 'Video playback stutters on Wi-Fi. Shorts freeze every few seconds.', 'App crashes when uploading a video. The app closes when I upload a clip.'],
    ['unrelated', 'Add dark mode. Please add a dark theme for night use.', 'Coins disappeared after the update. My balance shows zero.'],
];
echo "Pair scores (cosine, 1.0 = identical)\n";
foreach ($pairs as [$label, $a, $b]) {
    [$vectorA, $vectorB] = [$embedder->embed($a), $embedder->embed($b)];
    $score = array_sum(array_map(fn (float $x, float $y): float => $x * $y, $vectorA, $vectorB));
    printf("  %.3f  %-26s %s\n", $score, $label, mb_strimwidth($a, 0, 40, '...').' | '.mb_strimwidth($b, 0, 40, '...'));
}

echo "\nBrute-force search (random vectors, same dimensions, includes unpacking from BLOB)\n";
$dimensions = count($embedder->embed('dimension probe'));
$query = $embedder->embed('App crashes when uploading a video');
$finder = new DuplicateFinder;

foreach ([1_000, 5_000, 10_000, 20_000] as $size) {
    $blobs = [];
    for ($i = 0; $i < $size; $i++) {
        $vector = [];
        for ($d = 0; $d < $dimensions; $d++) {
            $vector[] = mt_rand() / mt_getrandmax() - 0.5;
        }
        $blobs[] = PostRepository::pack($vector);
    }

    $startedAt = hrtime(true);
    $candidates = array_map(fn (string $blob, int $id): array => ['id' => $id, 'vector' => PostRepository::unpack($blob)], $blobs, array_keys($blobs));
    $unpackMilliseconds = (hrtime(true) - $startedAt) / 1e6;

    $finder->find($query, $candidates, 0.99, 5);
    printf("  %6d posts  unpack %6.0f ms  compare %6.0f ms  total %6.0f ms  memory %4.0f MB\n", $size, $unpackMilliseconds, $finder->searchMilliseconds, $unpackMilliseconds + $finder->searchMilliseconds, memory_get_usage(true) / 1048576);
    unset($blobs, $candidates);
}

printf("\nPeak memory %.0f MB\n", memory_get_peak_usage(true) / 1048576);
