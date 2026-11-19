<?php

namespace Lab;

/**
 * Turns a post's text into the vector we compare: static embedding with our word weights,
 * minus its language's average, normalised.
 */
class PostVectorizer
{
    public float $embedMilliseconds = 0.0;

    public function __construct(
        public readonly StaticEmbedder $embedder,
        public readonly CorpusStats $stats,
    ) {
        $stats->applyTo($embedder);
    }

    /**
     * @return array{vector: list<float>, language: string}
     */
    public function vectorize(string $text): array
    {
        $startedAt = hrtime(true);
        $language = LanguageGuesser::guess($text);
        $vector = $this->embedder->embed($text);
        $average = $this->stats->averageFor($language);

        if ($average !== null) {
            $vector = Embedding::normalize(array_map(fn (float $value, float $mean): float => $value - $mean, $vector, $average));
        }

        $this->embedMilliseconds = (hrtime(true) - $startedAt) / 1e6;

        return ['vector' => $vector, 'language' => $language];
    }
}
