const CACHE_VERSION = 'sinporg-v5';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;

// Assets estáticos — Cache First (não mudam com frequência)
const STATIC_ASSETS = [
  './assets/css/style.css',
  './assets/css/agricultura-familiar.css',
  './manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css',
  'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css',
];

// Página de fallback offline
const OFFLINE_FALLBACK_URL = './index.php';

// Padrões de API — nunca fazer cache (sempre Network)
const API_PATTERNS = [/\/api\//, /\/modulos\/.*validar/, /\.php\?.*csrf/];

// ---- Install: pré-cachear assets estáticos ----
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// ---- Activate: limpar caches de versões anteriores ----
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
          .map(key => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

// ---- Background Sync: enviar formulários salvos offline ----
self.addEventListener('sync', event => {
  if (event.tag === 'sync-forms') {
    event.waitUntil(
      self.clients.matchAll().then(clients => {
        // Notificar todos os clientes abertos para executar a sincronização
        clients.forEach(client => {
          client.postMessage({ type: 'TRIGGER_SYNC' });
        });
      })
    );
  }
});

// ---- Fetch: estratégias diferenciadas por tipo de recurso ----
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorar requisições não-GET
  if (request.method !== 'GET') return;

  // Ignorar esquemas não-http (chrome-extension, data:, etc.)
  if (!url.protocol.startsWith('http')) return;

  // API calls — sempre Network, sem cache
  if (API_PATTERNS.some(p => p.test(url.pathname + url.search))) {
    event.respondWith(fetch(request));
    return;
  }

  // Assets estáticos (CSS, JS, imagens, fontes) — Cache First
  if (isStaticAsset(request)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Páginas e conteúdo dinâmico — Network First com fallback de cache
  event.respondWith(networkFirst(request));
});

// ---- Helpers ----

function isStaticAsset(request) {
  const url = request.url;
  return (
    url.includes('/assets/') ||
    url.includes('bootstrap') ||
    url.includes('datatables') ||
    url.includes('jquery') ||
    url.includes('bootstrap-icons') ||
    /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)(\?|$)/.test(url)
  );
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('Recurso não disponível offline.', { status: 503 });
  }
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    // Rede falhou — tentar retornar do cache dinâmico
    const cached = await caches.match(request);
    if (cached) return cached;

    // Para navegação de página, retornar página offline personalizada
    if (request.mode === 'navigate') {
      const fallback = await caches.match(OFFLINE_FALLBACK_URL);
      if (fallback) return fallback;
    }

    // Página offline embutida como fallback final
    return new Response(
      `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Offline — SINPORG</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; background: #0f1923; color: #e9ecef;
    }
    .card {
      background: #1a2b3c; border-radius: 20px; padding: 2.5rem 2rem;
      max-width: 420px; width: 90%; text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
      border: 1px solid rgba(255,255,255,0.08);
    }
    .icon { font-size: 3.5rem; margin-bottom: 1rem; display: block; }
    h1 { font-size: 1.6rem; color: #20c997; margin-bottom: .5rem; }
    p { color: #8fa3b3; line-height: 1.6; margin: 1rem 0 1.8rem; font-size: .95rem; }
    .badge {
      display: inline-block; background: rgba(32,201,151,.15); color: #20c997;
      border: 1px solid rgba(32,201,151,.3); border-radius: 20px;
      padding: .3rem 1rem; font-size: .8rem; margin-bottom: 1.5rem;
    }
    .btn {
      display: inline-block; padding: .75rem 2rem; background: #20c997; color: #000;
      border-radius: 10px; text-decoration: none; font-weight: 700;
      font-size: .95rem; transition: opacity .2s;
    }
    .btn:hover { opacity: .85; }
  </style>
</head>
<body>
  <div class="card">
    <span class="icon">📡</span>
    <h1>Sem conexão</h1>
    <span class="badge">⚡ Modo Offline Ativo</span>
    <p>Você está sem conexão com a internet. Seus dados registrados neste dispositivo estão <strong>seguros e protegidos</strong> e serão sincronizados automaticamente assim que a rede for restabelecida.</p>
    <a href="javascript:location.reload()" class="btn">↻ Tentar novamente</a>
  </div>
</body>
</html>`,
      { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}
