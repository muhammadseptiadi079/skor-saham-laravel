if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js').catch((err) => {
      console.error('SW register failed', err);
    });
  });
}

const form = document.getElementById('analyze-form');
const statusArea = document.getElementById('status-area');
const resultSection = document.getElementById('result');
const netStatus = document.getElementById('net-status');
const watchlistAddBtn = document.getElementById('watchlist-add-btn');

let currentAnalysis = null;

function updateNetStatus() {
  const online = navigator.onLine;
  netStatus.textContent = online ? 'online' : 'offline';
  netStatus.className = `badge ${online ? 'online' : 'offline'}`;
}
window.addEventListener('online', updateNetStatus);
window.addEventListener('offline', updateNetStatus);
updateNetStatus();

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const market = document.getElementById('market').value;
  const ticker = document.getElementById('ticker').value.trim();
  if (!ticker) return;

  setStatus(`Menganalisis ${ticker.toUpperCase()}...`);
  resultSection.hidden = true;

  try {
    const res = await fetch(`/api/analyze?ticker=${encodeURIComponent(ticker)}&market=${market}`);
    const data = await res.json();

    if (!res.ok) {
      if (data.error === 'offline') {
        const cached = await StockDB.getOne(`${market}:${ticker.toUpperCase()}`);
        if (cached) {
          setStatus('Offline — menampilkan hasil analisis terakhir yang tersimpan.', 'error');
          currentAnalysis = cached.analysis;
          renderResult(cached.analysis, cached.savedAt);
          updateWatchlistButton();
          return;
        }
      }
      setStatus(data.message || 'Terjadi kesalahan.', 'error');
      return;
    }

    setStatus('');
    await StockDB.save(data);
    currentAnalysis = data;
    renderResult(data, Date.now());
    renderHistory();
    updateWatchlistButton();
  } catch (err) {
    console.error(err);
    const cached = await StockDB.getOne(`${market}:${ticker.toUpperCase()}`);
    if (cached) {
      setStatus('Tidak bisa terhubung ke server — menampilkan hasil terakhir yang tersimpan.', 'error');
      currentAnalysis = cached.analysis;
      renderResult(cached.analysis, cached.savedAt);
      updateWatchlistButton();
    } else {
      setStatus('Tidak bisa terhubung ke server, dan belum ada riwayat tersimpan untuk saham ini.', 'error');
    }
  }
});

function setStatus(msg, kind) {
  if (!msg) {
    statusArea.innerHTML = '';
    return;
  }
  statusArea.innerHTML = `<div class="status-msg${kind === 'error' ? ' error' : ''}">${escapeHtml(msg)}</div>`;
}

function renderResult(data, savedAt) {
  document.getElementById('result-title').textContent = `${data.name} (${data.ticker})`;
  const when = new Date(savedAt || data.generatedAt);
  document.getElementById('result-generated').textContent = `Diperbarui: ${when.toLocaleString('id-ID')}`;

  setScoreCard('longterm', data.longterm);
  setScoreCard('trading', data.trading);

  setNotes('fund-notes', data.subScores.fundamentals.notes);
  setNotes('news-notes', data.subScores.news.notes);
  setNotes('momentum-notes', data.subScores.momentum.notes);
  setNotes('ownership-notes', data.subScores.ownership.notes);

  const articlesEl = document.getElementById('news-articles');
  articlesEl.innerHTML = '';
  (data.subScores.news.topArticles || []).forEach((a) => {
    const li = document.createElement('li');
    const link = document.createElement('a');
    link.href = a.url;
    link.textContent = a.title;
    link.target = '_blank';
    link.rel = 'noopener';
    li.appendChild(link);
    articlesEl.appendChild(li);
  });

  const txEl = document.getElementById('ownership-transactions');
  txEl.innerHTML = '';
  (data.subScores.ownership.transactions || []).forEach((t) => {
    const li = document.createElement('li');
    const arrow = t.type === 'buy' ? '↑ Beli' : '↓ Jual';
    li.textContent = `${arrow} — ${t.insiderName} (${t.role}), ${Number(t.shares).toLocaleString('id-ID')} lembar${t.date ? ' · ' + t.date : ''}`;
    txEl.appendChild(li);
  });

  document.getElementById('disclaimer').textContent = data.disclaimer;
  resultSection.hidden = false;
}

function setScoreCard(prefix, result) {
  const labelEl = document.getElementById(`${prefix}-label`);
  const fillEl = document.getElementById(`${prefix}-fill`);
  labelEl.textContent = result.label;
  const pct = result.score == null ? 50 : Math.round((result.score + 1) * 50);
  fillEl.style.width = `${pct}%`;
  fillEl.style.background = colorFor(result.score);
}

function colorFor(score) {
  if (score == null) return '#94a3b8';
  if (score >= 0.15) return '#22c55e';
  if (score <= -0.15) return '#ef4444';
  return '#eab308';
}

function setNotes(elId, notes) {
  const el = document.getElementById(elId);
  el.innerHTML = '';
  (notes || []).forEach((n) => {
    const li = document.createElement('li');
    li.textContent = n;
    el.appendChild(li);
  });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function renderHistory() {
  const listEl = document.getElementById('history-list');
  const items = await StockDB.getAll();
  listEl.innerHTML = '';
  items.forEach((item) => {
    const li = document.createElement('li');
    const left = document.createElement('span');
    left.textContent = `${item.analysis.name} (${item.analysis.ticker})`;
    const right = document.createElement('span');
    right.className = 'when';
    right.textContent = new Date(item.savedAt).toLocaleDateString('id-ID');
    li.appendChild(left);
    li.appendChild(right);
    li.addEventListener('click', () => {
      currentAnalysis = item.analysis;
      renderResult(item.analysis, item.savedAt);
      updateWatchlistButton();
    });
    listEl.appendChild(li);
  });
}

let watchlistCache = [];

async function fetchWatchlist() {
  try {
    const res = await fetch('/api/watchlist');
    watchlistCache = res.ok ? await res.json() : [];
  } catch (err) {
    watchlistCache = [];
  }
  renderWatchlist();
  updateWatchlistButton();
}

function renderWatchlist() {
  const listEl = document.getElementById('watchlist-list');
  const emptyEl = document.getElementById('watchlist-empty');
  listEl.innerHTML = '';
  emptyEl.hidden = watchlistCache.length > 0;

  watchlistCache.forEach((item) => {
    const li = document.createElement('li');
    const main = document.createElement('span');
    main.className = 'chip-main';
    main.textContent = `${item.name || item.ticker} (${item.ticker})`;
    main.addEventListener('click', () => runAnalyze(item.ticker, item.market));

    const removeBtn = document.createElement('button');
    removeBtn.className = 'chip-remove';
    removeBtn.textContent = '✕';
    removeBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      await fetch(`/api/watchlist/${item.id}`, { method: 'DELETE' });
      await fetchWatchlist();
    });

    li.appendChild(main);
    li.appendChild(removeBtn);
    listEl.appendChild(li);
  });
}

function updateWatchlistButton() {
  if (!currentAnalysis) {
    watchlistAddBtn.hidden = true;
    return;
  }
  watchlistAddBtn.hidden = false;
  const inWatchlist = watchlistCache.some(
    (w) => w.ticker === currentAnalysis.ticker && w.market === currentAnalysis.market
  );
  watchlistAddBtn.textContent = inWatchlist ? '★ Ada di watchlist' : '☆ Tambah ke watchlist';
  watchlistAddBtn.classList.toggle('active', inWatchlist);
  watchlistAddBtn.disabled = inWatchlist;
}

watchlistAddBtn.addEventListener('click', async () => {
  if (!currentAnalysis) return;
  await fetch('/api/watchlist', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      ticker: currentAnalysis.ticker,
      market: currentAnalysis.market,
      name: currentAnalysis.name,
    }),
  });
  await fetchWatchlist();
});

async function renderScreener() {
  const market = document.getElementById('screener-market').value;
  const listEl = document.getElementById('screener-list');
  const emptyEl = document.getElementById('screener-empty');
  listEl.innerHTML = '';

  try {
    const res = await fetch(`/api/screener?market=${market}`);
    const data = res.ok ? await res.json() : { items: [] };
    const items = data.items || [];
    emptyEl.hidden = items.length > 0;

    items.forEach((item) => {
      const li = document.createElement('li');
      const main = document.createElement('span');
      main.className = 'chip-main';
      main.textContent = `${item.name || item.ticker} (${item.ticker})`;
      main.addEventListener('click', () => runAnalyze(item.ticker, item.market));

      const scoreEl = document.createElement('span');
      scoreEl.className = 'chip-score';
      scoreEl.textContent = item.trading_label || item.longterm_label || '';
      scoreEl.style.color = colorFor(item.trading_score ?? item.longterm_score);

      li.appendChild(main);
      li.appendChild(scoreEl);
      listEl.appendChild(li);
    });
  } catch (err) {
    emptyEl.hidden = false;
  }
}

async function renderIpo() {
  const listEl = document.getElementById('ipo-list');
  const emptyEl = document.getElementById('ipo-empty');
  listEl.innerHTML = '';

  try {
    const res = await fetch('/api/ipo');
    const items = res.ok ? await res.json() : [];
    emptyEl.hidden = items.length > 0;

    items.forEach((item) => {
      const li = document.createElement('li');
      const main = document.createElement('span');
      main.className = 'chip-main';
      const marketLabel = item.market === 'idx' ? 'IDX' : 'Global';
      main.textContent = `${item.company_name}${item.ticker ? ' (' + item.ticker + ')' : ''} — ${marketLabel}`;

      const dateEl = document.createElement('span');
      dateEl.className = 'chip-score';
      dateEl.textContent = item.ipo_date || '';

      li.appendChild(main);
      li.appendChild(dateEl);
      listEl.appendChild(li);
    });
  } catch (err) {
    emptyEl.hidden = false;
  }
}

async function runAnalyze(ticker, market) {
  document.getElementById('ticker').value = ticker;
  document.getElementById('market').value = market;
  form.requestSubmit();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('screener-market').addEventListener('change', renderScreener);

renderHistory();
fetchWatchlist();
renderScreener();
renderIpo();
