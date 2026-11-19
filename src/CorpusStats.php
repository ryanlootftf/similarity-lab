<?php

namespace Lab;

/**
 * Statistics learned from the TEXT of all posts (never from duplicate decisions):
 *   - word weights: rarer word-pieces count for more (IDF), so "video" or "episode" stop dominating
 *   - language averages: the average vector of each language's posts, subtracted so that two posts
 *     don't look alike merely because they're both Malay (or both Chinese)
 * Recomputed periodically (the app would do it nightly) and stored as JSON.
 */
class CorpusStats
{
    /**
     * @param  array<int, float>  $tokenWeights  token id => weight
     * @param  array<string, list<float>>  $averages  language code (or "all") => average vector
     */
    public function __construct(
        public readonly array $tokenWeights = [],
        public readonly float $defaultTokenWeight = 1.0,
        public readonly array $averages = [],
        public readonly int $documents = 0,
        public readonly string $builtAt = '',
    ) {}

    /**
     * @param  list<string>  $texts
     * @param  'none'|'global'|'language'  $centre
     * @param  int  $shrink  With few posts in a language, its average still carries their topics. Blend it
     *                       toward the all-posts average: (n * language + shrink * all) / (n + shrink). 0 = off.
     */
    public static function build(StaticEmbedder $embedder, array $texts, bool $wordWeights = true, string $centre = 'language', int $shrink = 0): self
    {
        $documents = count($texts);
        $tokenWeights = [];
        $defaultTokenWeight = 1.0;

        if ($wordWeights && $documents > 0) {
            $documentFrequency = [];
            foreach ($texts as $text) {
                foreach (array_unique($embedder->tokenize($text)) as $id) {
                    $documentFrequency[$id] = ($documentFrequency[$id] ?? 0) + 1;
                }
            }
            $tokenWeights = array_map(fn (int $count): float => log(($documents + 1) / ($count + 1)) + 1, $documentFrequency);
            $defaultTokenWeight = log($documents + 1) + 1;
        }

        $averages = [];
        if ($centre !== 'none' && $documents > 0) {
            $embedder->tokenWeights = $wordWeights ? $tokenWeights : null;
            $embedder->defaultTokenWeight = $defaultTokenWeight;

            $sums = [];
            $counts = [];
            foreach ($texts as $text) {
                $group = $centre === 'language' ? LanguageGuesser::guess($text) : 'all';
                $vector = $embedder->embed($text);
                $sums[$group] ??= array_fill(0, count($vector), 0.0);
                foreach ($vector as $i => $value) {
                    $sums[$group][$i] += $value;
                }
                $counts[$group] = ($counts[$group] ?? 0) + 1;
            }
            foreach ($sums as $group => $sum) {
                $averages[$group] = array_map(fn (float $value): float => $value / $counts[$group], $sum);
            }

            if ($centre === 'language' && $shrink > 0) {
                $overall = array_fill(0, count(reset($sums)), 0.0);
                foreach ($sums as $sum) {
                    foreach ($sum as $i => $value) {
                        $overall[$i] += $value / $documents;
                    }
                }
                foreach ($averages as $group => $average) {
                    $n = $counts[$group];
                    $averages[$group] = array_map(fn (float $language, float $all): float => ($n * $language + $shrink * $all) / ($n + $shrink), $average, $overall);
                }
            }
        }

        return new self($tokenWeights, $defaultTokenWeight, $averages, $documents, date('c'));
    }

    public function applyTo(StaticEmbedder $embedder): void
    {
        $embedder->tokenWeights = $this->tokenWeights ?: null;
        $embedder->defaultTokenWeight = $this->defaultTokenWeight;
    }

    /**
     * The average to subtract for a post: its language's, or the global one when stats were built that way.
     *
     * @return list<float>|null
     */
    public function averageFor(string $language): ?array
    {
        return $this->averages[$language] ?? $this->averages['all'] ?? null;
    }

    public function save(string $path): void
    {
        file_put_contents($path, json_encode([
            'token_weights' => $this->tokenWeights,
            'default_token_weight' => $this->defaultTokenWeight,
            'averages' => $this->averages,
            'documents' => $this->documents,
            'built_at' => $this->builtAt,
        ]));
    }

    public static function load(string $path): self
    {
        if (! is_file($path)) {
            return new self;
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return new self(
            array_map('floatval', $data['token_weights']),
            (float) $data['default_token_weight'],
            $data['averages'],
            (int) $data['documents'],
            (string) $data['built_at'],
        );
    }
}
