<?php

namespace Lab;

/**
 * The "nightly job": rebuild corpus statistics from every post's text, save them,
 * and re-vectorise every post so stored vectors match the new statistics.
 */
class Reindexer
{
    /**
     * @param  array{config: array<string, mixed>, posts: PostRepository, vectorizer: PostVectorizer}  $app
     * @return array{posts: int, languages: array<string, int>, milliseconds: float, stats: CorpusStats}
     */
    public static function run(array $app): array
    {
        $startedAt = hrtime(true);
        $config = $app['config'];
        $embedder = $app['vectorizer']->embedder;
        $posts = $app['posts']->all();
        $texts = array_map(fn (array $post): string => Embedding::textFor($post['title'], $post['description']), $posts);

        $stats = CorpusStats::build($embedder, $texts, $config['tuning']['word_weights'], $config['tuning']['centre'], $config['tuning']['blend']);
        $stats->save($config['stats_path']);
        $vectorizer = new PostVectorizer($embedder, $stats);

        $languages = [];
        foreach ($posts as $index => $post) {
            ['vector' => $vector, 'language' => $language] = $vectorizer->vectorize($texts[$index]);
            $app['posts']->updateVector($post['id'], $config['model'], $vector, $language);
            $languages[$language] = ($languages[$language] ?? 0) + 1;
        }

        return [
            'posts' => count($posts),
            'languages' => $languages,
            'milliseconds' => (hrtime(true) - $startedAt) / 1e6,
            'stats' => $stats,
        ];
    }
}
