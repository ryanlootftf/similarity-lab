<?php

/**
 * Lab settings: multilingual static model in plain PHP (no FFI), with the tuning from bin/compare.php.
 *
 * @return array{model: string, static_dir: string, cache_dir: string, database: string, stats_path: string, tuning: array{word_weights: bool, centre: string, blend: int, area_strength: float}, min_score: float, likely_duplicate_score: float, max_results: int, categories: array<string, string>, areas: array<string, string>}
 */
return [
    'model' => 'static-similarity-mrl-multilingual-v1 (256d, f16)',
    'static_dir' => __DIR__.'/storage/static/multilingual-256-f16',
    'cache_dir' => __DIR__.'/storage/models',
    'database' => __DIR__.'/storage/lab.sqlite',
    'stats_path' => __DIR__.'/storage/stats.json',

    'tuning' => [
        'word_weights' => true,     // rarer words count for more (IDF over our posts)
        'centre' => 'language',     // subtract each language's average vector
        'blend' => 10,              // pull a language's average toward the all-posts one while it has few posts
        'area_strength' => 0.04,    // +/- for same / different app area ("other" is neutral)
    ],

    // Final score = similarity + area adjustment. Best-F1 threshold was 0.38 with stats from 87 posts
    // and 0.42 from 40 posts; 0.40 sits between until there's real data to tune on.
    'min_score' => 0.30,
    'likely_duplicate_score' => 0.40,
    'max_results' => 5,

    // Post categories.
    'categories' => [
        'bug' => 'Bug',
        'suggestion' => 'Suggestion',
        'new_feature' => 'New feature',
        'user_experience' => 'User experience',
    ],

    // Which part of the app a post is about.
    'areas' => [
        'video_player' => 'Video player',
        'coins_payment' => 'Coins & payment',
        'search' => 'Search',
        'recommendations' => 'Recommendations',
        'account' => 'Account & sign-in',
        'subtitles_language' => 'Subtitles & language',
        'other' => 'Other / not sure',
    ],
];
