<?php

/**
 * JSON endpoints for the posting page.
 *   POST ?action=check    {title, description, area?}                    -> similar posts + score breakdown + timings
 *   POST ?action=post     {category, area, title, description, force?}   -> 201 created, or 409 with likely duplicates
 *   GET  ?action=posts                                                    -> latest posts + tuning info
 *   POST ?action=refresh                                                  -> the "nightly job": rebuild stats, re-vectorise
 */
use Lab\Embedding;
use Lab\Reindexer;

header('Content-Type: application/json');

/**
 * @param  array<string, mixed>  $payload
 */
function respond(int $status, array $payload): never
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Without a length, php -S makes the browser wait ~2s for the socket to close.
    http_response_code($status);
    header('Content-Length: '.strlen($body));
    echo $body;
    exit;
}

try {
    $app = require __DIR__.'/../bootstrap.php';
} catch (Throwable $exception) {
    respond(500, ['error' => $exception->getMessage()]);
}

['config' => $config, 'embedder' => $embedder, 'vectorizer' => $vectorizer, 'posts' => $posts, 'finder' => $finder] = $app;

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$title = trim((string) ($input['title'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$area = array_key_exists($input['area'] ?? '', $config['areas']) ? $input['area'] : null;

/**
 * @return array{vector: list<float>, language: string, matches: list<array<string, mixed>>, timings: array<string, float|int>}
 */
$findSimilar = function () use ($embedder, $vectorizer, $posts, $finder, $config, $title, $description, $area): array {
    ['vector' => $vector, 'language' => $language] = $vectorizer->vectorize(Embedding::textFor($title, $description));
    $matches = $finder->find($vector, $area, $posts->allWithVectors($config['model']), $config['min_score'], $config['max_results'], $config['tuning']['area_strength']);

    foreach ($matches as &$match) {
        $match['likely_duplicate'] = $match['score'] >= $config['likely_duplicate_score'];
    }

    return [
        'vector' => $vector,
        'language' => $language,
        'matches' => $matches,
        'timings' => [
            'model_load_ms' => round($embedder->loadMilliseconds, 1),
            'embed_ms' => round($vectorizer->embedMilliseconds, 1),
            'search_ms' => round($finder->searchMilliseconds, 1),
            'compared' => $finder->comparedCount,
            'request_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1),
        ],
    ];
};

switch ($action) {
    case 'check':
        if (mb_strlen($title) < 2) {
            respond(200, ['matches' => [], 'skipped' => 'Title too short to compare.']);
        }

        $result = $findSimilar();
        unset($result['vector']);
        respond(200, $result);

    case 'post':
        if (! array_key_exists($input['category'] ?? '', $config['categories'])) {
            respond(422, ['error' => 'Pick a category.']);
        }
        if ($area === null) {
            respond(422, ['error' => 'Pick which part of the app this is about.']);
        }
        if (mb_strlen($title) < 2 || mb_strlen($description) < 5) {
            respond(422, ['error' => 'Please add a title and a short description.']);
        }

        $result = $findSimilar();
        $likelyDuplicates = array_values(array_filter($result['matches'], fn (array $match): bool => $match['likely_duplicate']));

        if ($likelyDuplicates !== [] && empty($input['force'])) {
            respond(409, ['matches' => $likelyDuplicates, 'language' => $result['language'], 'timings' => $result['timings']]);
        }

        $id = $posts->create([
            'category' => $input['category'],
            'area' => $area,
            'lang' => $result['language'],
            'title' => $title,
            'description' => $description,
        ], $config['model'], $result['vector']);
        respond(201, ['id' => $id, 'timings' => $result['timings']]);

    case 'posts':
        $stats = $vectorizer->stats;
        respond(200, [
            'posts' => $posts->latest(),
            'total' => $posts->count(),
            'model' => $config['model'],
            'tuning' => $config['tuning'] + ['likely' => $config['likely_duplicate_score'], 'min' => $config['min_score']],
            'stats' => ['documents' => $stats->documents, 'built_at' => $stats->builtAt, 'languages' => array_keys($stats->averages)],
        ]);

    case 'refresh':
        $result = Reindexer::run($app);
        respond(200, ['posts' => $result['posts'], 'languages' => $result['languages'], 'ms' => round($result['milliseconds'])]);

    default:
        respond(404, ['error' => 'Unknown action.']);
}
