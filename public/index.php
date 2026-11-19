<?php $config = require __DIR__.'/../config.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Similarity Lab</title>
<style>
    :root {
        --bg: #f6f6f4; --surface: #ffffff; --text: #1d1d1b; --muted: #6b6b66; --border: #e2e2dc;
        --accent: #d9480f; --accent-soft: #fff1e8; --warn: #b54708; --warn-soft: #fffaeb; --ok: #067647;
        --radius: 10px;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #151514; --surface: #1f1f1d; --text: #ededea; --muted: #a3a39c; --border: #34342f;
            --accent: #ff7a45; --accent-soft: #3a2317; --warn: #fdb022; --warn-soft: #3a2c10; --ok: #47cd89;
        }
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
    main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    h2 { font-size: 15px; margin: 0 0 12px; }
    .lede { color: var(--muted); margin: 0 0 24px; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 20px; }
    label { display: block; font-weight: 600; font-size: 13px; margin: 0 0 6px; }
    input, select, textarea { width: 100%; font: inherit; color: inherit; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; }
    textarea { min-height: 120px; resize: vertical; }
    input:focus, select:focus, textarea:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
    .actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    button { font: inherit; font-weight: 600; border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; background: var(--accent); color: #fff; }
    button.secondary { background: transparent; color: var(--text); border: 1px solid var(--border); }
    button:disabled { opacity: .5; cursor: default; }
    .status { color: var(--muted); font-size: 13px; }
    .similar { border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; margin-bottom: 16px; }
    .similar.has-duplicate { border-color: var(--warn); background: var(--warn-soft); }
    .similar[hidden] { display: none; }
    .match { display: flex; gap: 12px; padding: 10px 0; border-top: 1px solid var(--border); }
    .match:first-of-type { border-top: 0; }
    .score { flex: none; font-variant-numeric: tabular-nums; font-weight: 700; font-size: 13px; padding: 2px 8px; border-radius: 6px; height: fit-content; background: var(--bg); }
    .match.likely .score { background: var(--warn); color: var(--surface); }
    .match p { margin: 2px 0 0; color: var(--muted); font-size: 13px; }
    .tag { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
    .timings { font: 12px/1.4 ui-monospace, Consolas, monospace; color: var(--muted); margin-top: 10px; }
    .breakdown { display: inline-block; margin-top: 4px; font: 12px/1.4 ui-monospace, Consolas, monospace; color: var(--muted); }
    .stats-line { justify-content: space-between; margin-bottom: 8px; }
    .stats-line .status { flex: 1 1 280px; }
    .notice { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .notice.ok { background: color-mix(in srgb, var(--ok) 14%, transparent); color: var(--ok); }
    .notice.error { background: var(--accent-soft); color: var(--accent); }
    .post { padding: 10px 0; border-top: 1px solid var(--border); }
    .post:first-child { border-top: 0; }
    .post strong { display: block; }
    .post span { color: var(--muted); font-size: 13px; }
</style>
</head>
<body>
<main>
    <h1>Similarity Lab</h1>
    <p class="lede">Multilingual semantic duplicate detection running in plain PHP: a static embedding model tuned with word weights, language averaging and an app-area nudge. Similar posts appear when you finish the title, change the area, or pause typing the description. Try English, Malay or Chinese.</p>

    <section class="card">
        <h2>New feedback</h2>
        <div id="notice"></div>
        <form id="post-form" novalidate>
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">Choose…</option>
                <?php foreach ($config['categories'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="area">Which part of the app?</label>
            <select id="area" name="area">
                <option value="">Choose one…</option>
                <?php foreach ($config['areas'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="title">Title</label>
            <input id="title" name="title" maxlength="150" autocomplete="off" placeholder="e.g. App crashes when I upload a video">

            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="What happened, and what did you expect?"></textarea>

            <div id="similar" class="similar" hidden aria-live="polite"></div>

            <div class="actions">
                <button type="submit" id="submit">Post</button>
                <button type="button" id="force" class="secondary" hidden>Post anyway</button>
                <span id="status" class="status"></span>
            </div>
        </form>
    </section>

    <section class="card">
        <h2 id="posts-heading">Existing posts</h2>
        <div class="actions stats-line">
            <span id="stats" class="status"></span>
            <button type="button" id="refresh" class="secondary">Run nightly refresh</button>
        </div>
        <div id="posts"></div>
    </section>
</main>

<script>
const form = document.getElementById('post-form');
const fields = { category: form.category, area: form.area, title: form.title, description: form.description };
const similarBox = document.getElementById('similar');
const statusLine = document.getElementById('status');
const notice = document.getElementById('notice');
const forceButton = document.getElementById('force');
const categories = <?= json_encode($config['categories']) ?>;
const areas = <?= json_encode($config['areas']) ?>;
const describe = (post) => [post.reference, categories[post.category] ?? post.category, areas[post.area] ?? 'No area'].filter(Boolean).map(escapeHtml).join(' · ');

let inFlight = null;
let lastCheckedText = '';
let descriptionTimer = null;

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const payload = () => ({ category: fields.category.value, area: fields.area.value, title: fields.title.value.trim(), description: fields.description.value.trim() });

async function api(action, body) {
    const response = await fetch(`api.php?action=${action}`, {
        method: body ? 'POST' : 'GET',
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
        signal: body?.signal,
    });
    return { status: response.status, data: await response.json() };
}

const languageNames = { en: 'English', ms: 'Malay', zh: 'Chinese' };
const percent = (value) => `${Math.round(value * 100)}%`;

function renderTimings(timings, language) {
    if (!timings) return '';
    return `<div class="timings">${language ? `detected ${languageNames[language] ?? language} · ` : ''}model load ${timings.model_load_ms} ms · embed ${timings.embed_ms} ms · search ${timings.search_ms} ms over ${timings.compared} posts · whole request ${timings.request_ms} ms</div>`;
}

function renderBreakdown(match) {
    const adjustment = match.area_adjustment;
    const area = adjustment > 0 ? ` + same area ${percent(adjustment)}` : adjustment < 0 ? ` − different area ${percent(-adjustment)}` : '';
    return `<span class="breakdown">meaning ${percent(match.similarity)}${area}</span>`;
}

function renderSimilar(matches, timings, heading, language) {
    similarBox.hidden = false;
    similarBox.classList.toggle('has-duplicate', matches.some((m) => m.likely_duplicate));

    if (matches.length === 0) {
        similarBox.innerHTML = `<strong>No similar posts found.</strong>${renderTimings(timings, language)}`;
        return;
    }

    similarBox.innerHTML = `<strong>${heading}</strong>` + matches.map((m) => `
        <div class="match ${m.likely_duplicate ? 'likely' : ''}">
            <span class="score">${percent(m.score)}</span>
            <div>
                <span class="tag">${describe(m)}${m.likely_duplicate ? ' · likely duplicate' : ' · possibly related'}</span>
                <div>${escapeHtml(m.title)}</div>
                <p>${escapeHtml(m.description)}</p>
                ${renderBreakdown(m)}
            </div>
        </div>`).join('') + renderTimings(timings, language);
}

async function checkSimilar() {
    const { title, description, area } = payload();
    const text = `${area}\n${title}\n${description}`;
    if (title.length < 2 || text === lastCheckedText) return;
    lastCheckedText = text;

    inFlight?.abort();
    inFlight = new AbortController();
    statusLine.textContent = 'Checking for similar posts…';

    try {
        const { data } = await api('check', { title, description, area, signal: inFlight.signal });
        renderSimilar(data.matches ?? [], data.timings, 'Similar posts already exist — is yours one of these?', data.language);
        statusLine.textContent = '';
    } catch (error) {
        if (error.name !== 'AbortError') statusLine.textContent = 'Similarity check failed.';
    }
}

fields.title.addEventListener('blur', checkSimilar);
fields.area.addEventListener('change', checkSimilar);
fields.description.addEventListener('input', () => {
    clearTimeout(descriptionTimer);
    descriptionTimer = setTimeout(checkSimilar, 1200);
});

async function submit(force) {
    notice.innerHTML = '';
    statusLine.textContent = 'Posting…';
    const { status, data } = await api('post', { ...payload(), force });
    statusLine.textContent = '';

    if (status === 409) {
        renderSimilar(data.matches, data.timings, 'This looks like a duplicate. Open one of these, or post anyway.', data.language);
        forceButton.hidden = false;
        return;
    }
    if (status !== 201) {
        notice.innerHTML = `<div class="notice error">${escapeHtml(data.error ?? 'Something went wrong.')}</div>`;
        return;
    }

    notice.innerHTML = `<div class="notice ok">Posted as #${data.id}.</div>`;
    form.reset();
    similarBox.hidden = true;
    forceButton.hidden = true;
    lastCheckedText = '';
    loadPosts();
}

form.addEventListener('submit', (event) => { event.preventDefault(); submit(false); });
forceButton.addEventListener('click', () => submit(true));

async function loadPosts() {
    const { data } = await api('posts');
    const { tuning, stats } = data;
    document.getElementById('posts-heading').textContent = `Existing posts (${data.total}) · ${data.model}`;
    document.getElementById('stats').textContent = `Likely duplicate ≥ ${percent(tuning.likely)} · shown from ${percent(tuning.min)} · area ±${percent(tuning.area_strength)} · `
        + (stats.documents ? `weights & language averages from ${stats.documents} posts (${stats.languages.map((l) => languageNames[l] ?? l).join(', ')}), built ${new Date(stats.built_at).toLocaleTimeString()}` : 'no weights built yet');
    document.getElementById('posts').innerHTML = data.posts.map((p) => `
        <div class="post"><strong>${escapeHtml(p.title)}</strong><span>${describe(p)} · ${escapeHtml(languageNames[p.lang] ?? p.lang ?? '')} — ${escapeHtml(p.description)}</span></div>`).join('');
}

document.getElementById('refresh').addEventListener('click', async (event) => {
    event.target.disabled = true;
    const { data } = await api('refresh', {});
    event.target.disabled = false;
    notice.innerHTML = `<div class="notice ok">Nightly refresh: rebuilt from ${data.posts} posts and re-vectorised them in ${data.ms} ms.</div>`;
    lastCheckedText = '';
    loadPosts();
});

loadPosts();
</script>
</body>
</html>
