<?php

/**
 * Duplicate-detection benchmark on bench/posts.php, the way the app will use it:
 * every query post is scored against every existing post.
 *
 * Usage: php bin/compare.php [--detail] [--baselines] [--sweep] [--only=name,name]
 *   --detail     per-query table: rank of the true duplicate and the top match
 *   --thresholds what the reviewer would see at a range of thresholds
 *   --baselines  also run keyword and potion-8M for reference
 *   --sweep      compare area-adjustment strengths on top of language centring + word weights
 *   --only       run only the named variants (comma-separated, case-insensitive substring)
 */
use Lab\CorpusStats;
use Lab\DuplicateFinder;
use Lab\Embedding;
use Lab\LanguageGuesser;
use Lab\PostVectorizer;
use Lab\StaticEmbedder;

putenv('OPENBLAS_NUM_THREADS=1');
require __DIR__.'/../vendor/autoload.php';
ini_set('memory_limit', '-1');

$data = require __DIR__.'/../bench/posts.php';
$existing = $data['existing'];
$queries = $data['queries'];
$static = __DIR__.'/../storage/static';
$text = fn (array $post): string => Embedding::textFor($post['title'], $post['description']);

$dot = function (array $a, array $b): float {
    $sum = 0.0;
    foreach ($a as $i => $value) {
        $sum += $value * $b[$i];
    }

    return $sum;
};

/**
 * A variant turns posts into vectors (`vectorize`, built once and timed as cold start)
 * and scores a query post against an existing post (`score`). Fine-tuning ideas become new variants.
 *
 * @var array<string, array{build: callable(): callable, score: callable, disk_mb: int|float}>
 */
$variants = [];

$variants['Keyword'] = [
    'build' => fn () => function (array $post) use ($text): array {
        $stopWords = array_flip(['the', 'and', 'for', 'with', 'when', 'not', 'can', 'cant', 'does', 'this', 'that', 'there', 'after', 'from', 'have', 'has', 'please', 'add', 'make', 'should', 'would', 'could', 'will', 'app', 'some', 'very', 'too', 'are', 'was', 'every', 'just', 'let', 'want', 'like', 'its', 'now', 'all', 'get', 'see', 'one', 'they', 'them', 'more', 'than', 'out', 'what', 'how', 'where', 'but', 'even', 'only', 'then', 'into']);
        $counts = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text($post)), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strlen($word) < 3 || isset($stopWords[$word])) {
                continue;
            }
            $stem = preg_replace('/(ing|es|ed|s)$/u', '', $word);
            $stem = mb_strlen($stem) >= 3 ? $stem : $word;
            $counts[$stem] = ($counts[$stem] ?? 0) + 1;
        }

        return $counts;
    },
    'score' => function (array $queryPost, array $existingPost, array $a, array $b): float {
        $dot = 0.0;
        foreach ($a as $word => $count) {
            $dot += $count * ($b[$word] ?? 0);
        }
        $norm = sqrt(array_sum(array_map(fn ($c) => $c * $c, $a))) * sqrt(array_sum(array_map(fn ($c) => $c * $c, $b)));

        return $norm > 0 ? $dot / $norm : 0.0;
    },
    'disk_mb' => 0,
];

$staticVariant = fn (string $directory, bool $dropUnknown, ?int $dimensions = null) => [
    'build' => function () use ($static, $directory, $dropUnknown, $dimensions, $text) {
        $embedder = new StaticEmbedder("{$static}/{$directory}/model.safetensors", "{$static}/{$directory}/tokenizer.json", $dimensions, $dropUnknown);
        $embedder->embed('warm up');

        return fn (array $post) => $embedder->embed($text($post));
    },
    'score' => fn ($q, $e, array $a, array $b) => $dot($a, $b),
    'disk_mb' => round((filesize("{$static}/{$directory}/model.safetensors") + filesize("{$static}/{$directory}/tokenizer.json")) / 1048576),
];

$variants['potion-8M (EN)'] = $staticVariant('potion-base-8M', true);

if (! in_array('--baselines', $argv, true)) {
    $variants = [];
}

/**
 * Multilingual static model plus the fine-tuning adjustments. Corpus statistics (word weights, language
 * averages) use the TEXT of every post only — never the duplicate labels — as the app would over its posts.
 *
 * @param  'none'|'global'|'language'  $centre  subtract the average vector of all posts, or of the post's language
 * @param  bool  $idf  weight word-pieces by how rare they are across our posts
 * @param  float  $area  added when both posts name the same area, subtracted when they name different ones ("other" = neutral)
 */
$multi = function (string $centre = 'none', bool $idf = false, float $area = 0.0, string $statsFrom = 'all', int $shrink = 0) use ($static, $text, $dot, $existing, $queries): array {
    $directory = "{$static}/multilingual-256-f16";

    // Same classes the lab page uses (CorpusStats, PostVectorizer, DuplicateFinder::areaAdjustment).
    // $statsFrom: 'all' = statistics from all 87 posts' text, 'existing' = only the 40 existing posts (like the lab page).
    return [
        'build' => function () use ($directory, $centre, $idf, $text, $existing, $queries, $statsFrom, $shrink) {
            $embedder = new StaticEmbedder("{$directory}/model.safetensors", "{$directory}/tokenizer.json", dropUnknown: false);
            $corpus = $statsFrom === 'existing' ? $existing : $existing + $queries;
            $stats = CorpusStats::build($embedder, array_values(array_map($text, $corpus)), $idf, $centre, $shrink);
            $vectorizer = new PostVectorizer($embedder, $stats);

            return fn (array $post) => $vectorizer->vectorize($text($post))['vector'];
        },
        'score' => fn (array $queryPost, array $existingPost, array $a, array $b): float => $dot($a, $b) + DuplicateFinder::areaAdjustment($queryPost['area'], $existingPost['area'], $area),
        'disk_mb' => 54,
    ];
};

if (in_array('--small', $argv, true)) {
    $variants['87 posts'] = $multi('language', true, 0.04);
    $variants['87 + blend 10'] = $multi('language', true, 0.04, 'all', 10);
    $variants['40 posts'] = $multi('language', true, 0.04, 'existing');
    $variants['40 + blend 5'] = $multi('language', true, 0.04, 'existing', 5);
    $variants['40 + blend 10'] = $multi('language', true, 0.04, 'existing', 10);
    $variants['40 + blend 20'] = $multi('language', true, 0.04, 'existing', 20);
    $variants['40, no lang avg'] = $multi('global', true, 0.04, 'existing');
} elseif (in_array('--sweep', $argv, true)) {
    $variants['Lang+words'] = $multi('language', true);
    foreach ([0.02, 0.04, 0.06, 0.08, 0.10, 0.15] as $strength) {
        $variants[sprintf('L+W+area %.2f', $strength)] = $multi('language', true, $strength);
    }
} else {
    $variants['Multi (base)'] = $multi();
    $variants['Global centre'] = $multi('global');
    $variants['Lang centre'] = $multi('language');
    $variants['Word weights'] = $multi('none', true);
    $variants['Area ±0.05'] = $multi('none', false, 0.05);
    $variants['Lang+area'] = $multi('language', false, 0.05);
    $variants['Lang+words+area'] = $multi('language', true, 0.05);
    $variants['Lab page config'] = $multi('language', true, 0.04);
}

foreach (array_filter(array_map(fn ($a) => str_starts_with($a, '--only=') ? substr($a, 7) : null, $argv)) as $only) {
    $wanted = array_map('strtolower', explode(',', $only));
    $variants = array_filter($variants, fn ($name) => (bool) array_filter($wanted, fn ($w) => str_contains(strtolower($name), $w)), ARRAY_FILTER_USE_KEY);
}

/** Fraction of (positive, negative) pairs where the positive scores higher; ties count half. */
$auc = function (array $positives, array $negatives): float {
    if (! $positives || ! $negatives) {
        return NAN;
    }
    $wins = 0.0;
    foreach ($positives as $p) {
        foreach ($negatives as $n) {
            $wins += $p > $n ? 1 : ($p == $n ? 0.5 : 0);
        }
    }

    return $wins / (count($positives) * count($negatives));
};

$queryKind = function (string $id, array $query) use ($existing): string {
    $tags = $query['tags'] ?? [];

    return match (true) {
        in_array('short', $tags, true) => 'short',
        in_array('wrong_area', $tags, true) => 'wrong area',
        $query['lang'] !== $existing[$query['duplicate_of']]['lang'] => 'cross-language',
        default => 'same language',
    };
};

$results = [];

foreach ($variants as $name => $variant) {
    $startedAt = hrtime(true);
    $vectorize = $variant['build']();
    $coldMs = (hrtime(true) - $startedAt) / 1e6;

    $embedTimes = [];
    $vectors = [];
    foreach ($existing + $queries as $id => $post) {
        $startedAt = hrtime(true);
        $vectors[$id] = $vectorize($post);
        $embedTimes[] = (hrtime(true) - $startedAt) / 1e6;
    }
    sort($embedTimes);

    // Score every query against every existing post.
    $positives = [];          // (query, its duplicate)
    $negatives = [];          // (query, any other existing post)
    $hardNegatives = [];      // (query, other existing post in the same area as the true duplicate)
    $pairs = [];              // [score, isDuplicate] for threshold sweeps
    $perQuery = [];

    foreach ($queries as $queryId => $query) {
        $scores = [];
        foreach ($existing as $existingId => $existingPost) {
            $scores[$existingId] = $variant['score']($query, $existingPost, $vectors[$queryId], $vectors[$existingId]);
        }
        arsort($scores);

        $duplicateOf = $query['duplicate_of'];
        foreach ($scores as $existingId => $score) {
            $isDuplicate = $existingId === $duplicateOf;
            $pairs[] = [$score, $isDuplicate];
            if ($isDuplicate) {
                $positives[] = $score;
            } else {
                $negatives[] = $score;
                if ($duplicateOf && $existing[$existingId]['area'] === $existing[$duplicateOf]['area']) {
                    $hardNegatives[] = $score;
                }
            }
        }

        $ranked = array_keys($scores);
        $perQuery[$queryId] = [
            'kind' => $duplicateOf ? $queryKind($queryId, $query) : 'no duplicate',
            'rank' => $duplicateOf ? array_search($duplicateOf, $ranked, true) + 1 : null,
            'duplicate_score' => $duplicateOf ? $scores[$duplicateOf] : null,
            'top' => $ranked[0],
            'top_score' => $scores[$ranked[0]],
            'second_score' => $scores[$ranked[1]],
        ];
    }

    // Best-F1 threshold: flag every pair scoring at or above it.
    $best = ['f1' => 0.0];
    foreach (array_unique(array_column($pairs, 0)) as $threshold) {
        $flagged = array_filter($pairs, fn ($pair) => $pair[0] >= $threshold);
        $truePositives = count(array_filter($flagged, fn ($pair) => $pair[1]));
        if ($truePositives === 0) {
            continue;
        }
        $precision = $truePositives / count($flagged);
        $recall = $truePositives / count($positives);
        $f1 = 2 * $precision * $recall / ($precision + $recall);
        if ($f1 > $best['f1']) {
            $best = compact('f1', 'precision', 'recall', 'threshold');
        }
    }

    $withDuplicate = array_filter($perQuery, fn ($q) => $q['rank'] !== null);
    $top1 = fn (array $subset) => $subset ? count(array_filter($subset, fn ($q) => $q['rank'] === 1)) / count($subset) : NAN;
    $byKind = [];
    foreach (['same language', 'cross-language', 'wrong area', 'short'] as $kind) {
        $byKind[$kind] = $top1(array_filter($withDuplicate, fn ($q) => $q['kind'] === $kind));
    }
    $noDuplicate = array_filter($perQuery, fn ($q) => $q['rank'] === null);
    $falseAlarms = count(array_filter($noDuplicate, fn ($q) => $q['top_score'] >= $best['threshold'])) / max(1, count($noDuplicate));

    $results[$name] = [
        'top1' => $top1($withDuplicate),
        'top3' => count(array_filter($withDuplicate, fn ($q) => $q['rank'] <= 3)) / count($withDuplicate),
        'by_kind' => $byKind,
        'auc' => $auc($positives, $negatives),
        'auc_hard' => $auc($positives, $hardNegatives),
        'best' => $best,
        'false_alarms' => $falseAlarms,
        'cold_ms' => $coldMs,
        'embed_ms' => $embedTimes[intdiv(count($embedTimes), 2)],
        'disk_mb' => $variant['disk_mb'],
        'per_query' => $perQuery,
        'pairs' => $pairs,
        'duplicate_count' => count($positives),
    ];

    fprintf(STDERR, "done: %s\n", $name);
}

file_put_contents(__DIR__.'/../bench/results.json', json_encode($results, JSON_PRETTY_PRINT));

$counts = array_count_values(array_column(reset($results)['per_query'], 'kind'));
printf("%d existing posts, %d query posts (%s)\n", count($existing), count($queries), implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($counts), $counts)));
$misguessed = array_filter($existing + $queries, fn ($post) => LanguageGuesser::guess($text($post)) !== $post['lang']);
printf("Language guess: %d/%d correct%s\n\n", count($existing + $queries) - count($misguessed), count($existing + $queries), $misguessed ? ' (wrong: '.implode(', ', array_keys($misguessed)).')' : '');

$width = 15;
$line = fn (string $label, callable $format) => printf("%-30s%s\n", $label, implode('', array_map(fn ($r) => str_pad($format($r), $width, ' ', STR_PAD_LEFT), $results)));
$percent = fn (float $value) => is_nan($value) ? '—' : sprintf('%.0f%%', $value * 100);

printf("%-30s%s\n", '', implode('', array_map(fn ($n) => str_pad(mb_strimwidth($n, 0, $width - 1), $width, ' ', STR_PAD_LEFT), array_keys($results))));
echo "FINDS THE RIGHT ORIGINAL\n";
$line('  Top-1, all duplicates', fn ($r) => $percent($r['top1']));
$line('    same language', fn ($r) => $percent($r['by_kind']['same language']));
$line('    cross-language', fn ($r) => $percent($r['by_kind']['cross-language']));
$line('    wrong area picked', fn ($r) => $percent($r['by_kind']['wrong area']));
$line('    very short', fn ($r) => $percent($r['by_kind']['short']));
$line('  In top 3', fn ($r) => $percent($r['top3']));
echo "SEPARATES DUPLICATES FROM THE REST\n";
$line('  AUC vs all other posts', fn ($r) => sprintf('%.3f', $r['auc']));
$line('  AUC vs same-area posts', fn ($r) => sprintf('%.3f', $r['auc_hard']));
echo "AT THE BEST THRESHOLD (max F1)\n";
$line('  Threshold', fn ($r) => sprintf('%.2f', $r['best']['threshold']));
$line('  Duplicates caught (recall)', fn ($r) => $percent($r['best']['recall']));
$line('  Flags that are right (prec.)', fn ($r) => $percent($r['best']['precision']));
$line('  Non-dup posts falsely flagged', fn ($r) => $percent($r['false_alarms']));
echo "COST\n";
$line('  Cold start', fn ($r) => sprintf('%.0f ms', $r['cold_ms']));
$line('  Per post', fn ($r) => sprintf('%.2f ms', $r['embed_ms']));
$line('  Disk', fn ($r) => sprintf('%d MB', $r['disk_mb']));

if (in_array('--thresholds', $argv, true)) {
    echo "\nTHRESHOLD TABLE: every pair scoring >= threshold is shown to the admin\n";
    foreach ($results as $name => $r) {
        echo "  {$name}\n";
        printf("    %-9s %10s %14s %16s %18s\n", 'threshold', 'dups found', 'dups missed', 'wrong flags', 'non-dup posts hit');
        foreach ([0.25, 0.30, 0.32, 0.35, 0.38, 0.40, 0.42, 0.45, 0.50] as $threshold) {
            $flagged = array_filter($r['pairs'], fn ($pair) => $pair[0] >= $threshold);
            $found = count(array_filter($flagged, fn ($pair) => $pair[1]));
            $noDuplicateHit = count(array_filter($r['per_query'], fn ($q) => $q['rank'] === null && $q['top_score'] >= $threshold));
            $noDuplicateTotal = count(array_filter($r['per_query'], fn ($q) => $q['rank'] === null));
            printf("    %-9.2f %5d/%-4d %14d %16d %13d/%-4d\n", $threshold, $found, $r['duplicate_count'], $r['duplicate_count'] - $found, count($flagged) - $found, $noDuplicateHit, $noDuplicateTotal);
        }
    }
}

if (in_array('--autoflag', $argv, true)) {
    echo "\nAUTO-FLAG SIMULATION: mark a new post as a duplicate of its TOP match when score >= threshold\n";
    echo "and the top match leads the runner-up by >= margin. Right = flagged against the true original.\n";
    foreach ($results as $name => $r) {
        echo "  {$name}\n";
        printf("    %-9s %-7s %8s %8s %10s %12s\n", 'threshold', 'margin', 'flagged', 'right', 'wrong', 'dups caught');
        foreach ([0.40, 0.42, 0.45, 0.48, 0.50, 0.55] as $threshold) {
            foreach ([0.0, 0.03, 0.05] as $margin) {
                $flagged = array_filter($r['per_query'], fn ($q) => $q['top_score'] >= $threshold && $q['top_score'] - $q['second_score'] >= $margin);
                $right = count(array_filter($flagged, fn ($q) => $q['rank'] === 1));
                printf("    %-9.2f %-7.2f %8d %8d %10d %8d/%-3d\n", $threshold, $margin, count($flagged), $right, count($flagged) - $right, $right, $r['duplicate_count']);
            }
        }
    }
}

if (in_array('--detail', $argv, true)) {
    echo "\nPER QUERY: rank of the true duplicate (score) | top match if wrong\n";
    foreach ($queries as $queryId => $query) {
        $cells = array_map(function ($r) use ($queryId) {
            $q = $r['per_query'][$queryId];
            if ($q['rank'] === null) {
                $cell = sprintf('top %s %.2f', $q['top'], $q['top_score']);
            } else {
                $cell = $q['rank'] === 1 ? sprintf('#1 (%.2f)', $q['duplicate_score']) : sprintf('#%d→%s', $q['rank'], $q['top']);
            }

            return str_pad($cell, 15, ' ', STR_PAD_LEFT);
        }, $results);
        printf("  %-4s %-26s%s\n", $queryId, mb_strimwidth($query['title'], 0, 26, '…'), implode('', $cells));
    }
}
