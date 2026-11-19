<?php

namespace Lab;

use RuntimeException;

/**
 * Static embedding model (Model2Vec / Sentence Transformers StaticEmbedding) in plain PHP:
 * tokenize, look up one row per token straight from the .safetensors file, average, normalise.
 * No FFI, no native libraries, and the matrix is never loaded whole — rows are read on demand.
 */
class StaticEmbedder
{
    private WordPieceTokenizer $tokenizer;

    /** @var resource */
    private $file;

    private int $rowsOffset;

    private int $storedDimensions;

    private int $bytesPerValue;

    private string $dtype;

    /** @var array<int, list<float>> */
    private array $rowCache = [];

    /**
     * Optional per-token weights (token id => weight) for a weighted average, e.g. IDF from our own posts.
     * Tokens missing from the map get $defaultTokenWeight.
     *
     * @var array<int, float>|null
     */
    public ?array $tokenWeights = null;

    public float $defaultTokenWeight = 1.0;

    public float $loadMilliseconds = 0.0;

    public float $embedMilliseconds = 0.0;

    /**
     * @param  int|null  $dimensions  Keep only the first N dimensions (Matryoshka models allow this).
     * @param  bool  $dropUnknown  Model2Vec skips [UNK] tokens; Sentence Transformers keeps them.
     */
    public function __construct(
        string $safetensorsPath,
        string $tokenizerPath,
        private readonly ?int $dimensions = null,
        private readonly bool $dropUnknown = true,
        public readonly string $model = 'static',
    ) {
        $startedAt = hrtime(true);

        $this->tokenizer = new WordPieceTokenizer($tokenizerPath);
        $this->file = fopen($safetensorsPath, 'rb') ?: throw new RuntimeException("Cannot open {$safetensorsPath}.");

        $headerLength = unpack('P', fread($this->file, 8))[1];
        $header = json_decode(fread($this->file, $headerLength), true, flags: JSON_THROW_ON_ERROR);
        unset($header['__metadata__']);

        $tensor = $header['embeddings'] ?? $header['embedding.weight'] ?? throw new RuntimeException(
            'No embedding tensor found; tensors: '.implode(', ', array_keys($header))
        );

        $this->dtype = $tensor['dtype'];
        $this->bytesPerValue = match ($this->dtype) {
            'F32' => 4,
            'F16' => 2,
            default => throw new RuntimeException("Unsupported dtype {$this->dtype}."),
        };
        $this->storedDimensions = $tensor['shape'][1];
        $this->rowsOffset = 8 + $headerLength + $tensor['data_offsets'][0];

        $this->loadMilliseconds = (hrtime(true) - $startedAt) / 1e6;
    }

    public function dimensions(): int
    {
        return $this->dimensions ?? $this->storedDimensions;
    }

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $startedAt = hrtime(true);
        $dimensions = $this->dimensions();
        $sum = array_fill(0, $dimensions, 0.0);
        $totalWeight = 0.0;

        foreach ($this->tokenize($text) as $id) {
            $weight = $this->tokenWeights === null ? 1.0 : ($this->tokenWeights[$id] ?? $this->defaultTokenWeight);
            $row = $this->row($id);
            for ($i = 0; $i < $dimensions; $i++) {
                $sum[$i] += $weight * $row[$i];
            }
            $totalWeight += $weight;
        }

        $vector = Embedding::normalize($totalWeight > 0 ? array_map(fn (float $value): float => $value / $totalWeight, $sum) : $sum);
        $this->embedMilliseconds = (hrtime(true) - $startedAt) / 1e6;

        return $vector;
    }

    /**
     * Token ids that take part in the average (unknown tokens dropped when configured).
     *
     * @return list<int>
     */
    public function tokenize(string $text): array
    {
        $ids = $this->tokenizer->encode($text);

        if (! $this->dropUnknown) {
            return $ids;
        }

        return array_values(array_filter($ids, fn (int $id): bool => $id !== $this->tokenizer->unknownId()));
    }

    /**
     * @return list<float>
     */
    private function row(int $id): array
    {
        if (isset($this->rowCache[$id])) {
            return $this->rowCache[$id];
        }

        $rowBytes = $this->storedDimensions * $this->bytesPerValue;
        fseek($this->file, $this->rowsOffset + $id * $rowBytes);
        $bytes = fread($this->file, $this->dimensions() * $this->bytesPerValue);

        $row = $this->dtype === 'F32'
            ? array_values(unpack('g*', $bytes))
            : array_map(self::halfToFloat(...), array_values(unpack('v*', $bytes)));

        return $this->rowCache[$id] = $row;
    }

    private static function halfToFloat(int $half): float
    {
        $sign = ($half >> 15) ? -1.0 : 1.0;
        $exponent = ($half >> 10) & 0x1F;
        $fraction = $half & 0x3FF;

        return match (true) {
            $exponent === 0 => $sign * $fraction * 2 ** -24,
            $exponent === 31 => $fraction ? NAN : $sign * INF,
            default => $sign * (1 + $fraction / 1024) * 2 ** ($exponent - 15),
        };
    }
}
