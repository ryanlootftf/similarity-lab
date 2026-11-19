# Similarity Lab

A self-contained **multilingual semantic duplicate-detection** prototype that runs entirely in
**plain PHP** — no FFI, no native libraries, no external AI API, no vector database.

Given a new post, it embeds the text with a local static embedding model and finds the
most-similar existing posts by cosine similarity, so a "someone already posted this" check can
run on cheap shared hosting (cPanel, PHP-FPM) at posting time.

## How it works

1. **Static embeddings.** A `sentence-transformers/static-similarity-mrl-multilingual-v1`
   model (51 languages) trimmed to its first **256 of 1024 dimensions at float16** — a 416 MB
   download becomes a **54 MB** lookup table. Embedding a text is just a weighted average of
   token rows; there is no forward pass and the matrix is never loaded whole.
2. **Pure-PHP WordPiece tokenizer.** A port of the Hugging Face `BertNormalizer +
   BertPreTokenizer + WordPiece` pipeline, configured from the model's `tokenizer.json`.
3. **Corpus tuning, learned from post text only** (never from duplicate labels):
   - **IDF word weights** — rarer word-pieces count for more, so "video"/"episode" stop dominating.
   - **Language-mean subtraction** — each post's language average is subtracted, so two posts
     don't match merely because they're both Malay or both Chinese.
   - **App-area nudge** — +0.04 for the same app area, −0.04 for a different one.
4. **Scoring.** `score = cosine(new, existing) + area_adjustment`. A post is a *likely duplicate*
   when `score ≥ threshold` **and** it leads the runner-up by a margin (a near-tie means the
   system can't tell which is the original, so it stays silent — precision over recall).

## Results

Benchmark on 87 realistic posts across English, Malay and Chinese (`bin/compare.php`):

| Metric | Value | What it means |
|---|---|---|
| Top-1 duplicate found | **93.1%** | How often the #1 match is the correct duplicate. |
| Top-3 | 100% | The correct duplicate always ranks within the top 3 matches. |
| Cross-language top-1 | 91.7% | Still finds the duplicate when the new post is in a different language. |
| AUC vs all other posts | 0.996 | How well the score separates duplicates from unrelated posts (1.0 = perfect, 0.5 = coin flip). |
| Best F1 (threshold 0.38) | 0.877 | Best balance between finding all duplicates (recall 0.86) and not flagging false ones (precision 0.89). |
| Per-post embed | ~0.17 ms | Time to turn one post into a vector — negligible. |
| Model on disk | 54 MB | Storage footprint of the trimmed embedding model. |

## Requirements

- PHP **8.3+** with `pdo_sqlite`, `mbstring` and `intl`.
- No Composer packages required — the code ships with a PSR-4 fallback loader.

## Setup

```bash
# 1. Install dependencies (optional — only generates the autoloader)
composer install

# 2. Ensure the model is present (54 MB)
#    storage/static/multilingual-256-f16/model.safetensors
#    storage/static/multilingual-256-f16/tokenizer.json
#    Trim it from the full model with:
#    php bin/trim-static.php <full-model-dir> storage/static/multilingual-256-f16 256 --f16

# 3. Seed the demo posts + build corpus statistics
php bin/seed.php

# 4. Run the web UI
php -S 0.0.0.0:8000 -t public
```

Open `http://localhost:8000/` and type a title — similar posts appear as you finish the title,
change the area, or pause typing the description. Try English, Malay or Chinese.

## Commands

| Command | What it does |
|---|---|
| `php bin/seed.php [--fresh]` | Seed the demo posts and rebuild stats/vectors |
| `php bin/refresh.php` | The "nightly job": rebuild word weights + language averages, re-vectorise |
| `php bin/compare.php [--baselines] [--sweep] [--detail] [--thresholds]` | Run the benchmark; `--baselines` adds keyword + potion-8M references |
| `php bin/bench.php` | Feasibility numbers: load time, embed time, brute-force search scaling |
| `php bin/trim-static.php <src> <dst> 256 --f16` | Trim a Matryoshka static model to fewer dims |

## Project layout

```
similarity-lab/
├── public/            index.php (UI) + api.php (JSON API)
├── src/               the pipeline (Lab\ namespace)
│   ├── StaticEmbedder.php   static embedding model reader (pure PHP)
│   ├── WordPieceTokenizer.php
│   ├── CorpusStats.php      IDF weights + language averages
│   ├── PostVectorizer.php   text → vector
│   ├── DuplicateFinder.php  brute-force cosine + area nudge (swap for pgvector/Qdrant at scale)
│   ├── LanguageGuesser.php  en/ms/zh heuristic
│   ├── PostRepository.php   SQLite storage
│   ├── Reindexer.php        the "nightly job"
│   └── Embedding.php        shared normalize() + textFor() helpers
├── bin/               CLI tools (seed, refresh, bench, compare, trim)
├── bench/             benchmark data (posts.php) + results (results.json)
├── storage/           models, stats.json, lab.sqlite (git-ignored except the f16 model)
├── config.php         model path, tuning, thresholds, categories, areas
└── bootstrap.php      wires everything together
```

## Scaling

Plain-PHP brute-force comparison is fine to ~10k posts; beyond that, swap `DuplicateFinder` for
pgvector or Qdrant — it's the only class that changes.
