<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>stag.io API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@4/swagger-ui.css">
  <style>
    html, body { height: 100%; margin: 0; }
    #swagger-ui { height: calc(100vh - 64px); }
    .top-bar { height: 64px; display:flex; align-items:center; gap:12px; padding:8px 16px; background:#20232a; color:#fff; }
    .token-input { margin-left: auto; display:flex; gap:8px; align-items:center; }
    .token-input input { padding:6px 8px; font-size:14px; width:360px; }
    .token-input button { padding:6px 10px; }
    @media (max-width:640px) { .token-input input { width:160px; } }
  </style>
</head>
<body>
  <div class="top-bar">
    <div style="font-weight:600">stag.io API Docs</div>
    <div style="opacity:.8">Swagger UI — loads /openapi.yaml</div>

    <div class="token-input" title="Set Bearer token for authenticated requests">
      <label style="font-size:13px; opacity:.9">Bearer Token</label>
      <input id="auth-token" placeholder="paste token here (no 'Bearer')" />
      <button id="set-token">Set</button>
      <button id="clear-token">Clear</button>
    </div>
  </div>

  <div id="swagger-ui"></div>

  <script src="https://unpkg.com/swagger-ui-dist@4/swagger-ui-bundle.js"></script>
  <script>
    // Use a relative path for the spec to avoid mixed-content issues
    const specUrl = '/openapi.yaml';

    // Build Swagger UI
    const ui = SwaggerUIBundle({
      url: specUrl,
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis],
      layout: 'BaseLayout',
      requestInterceptor: (req) => {
        // Attach Bearer token if saved in localStorage
        const token = localStorage.getItem('stagio_token');
        if (token) {
          req.headers['Authorization'] = 'Bearer ' + token;
        }
        return req;
      }
    });

    // Token UI handling
    const input = document.getElementById('auth-token');
    const setBtn = document.getElementById('set-token');
    const clearBtn = document.getElementById('clear-token');

    // Load stored token into input
    input.value = localStorage.getItem('stagio_token') || '';

    setBtn.addEventListener('click', () => {
      const v = input.value.trim();
      if (!v) return alert('Please paste the token string (do NOT include "Bearer ").');
      localStorage.setItem('stagio_token', v);
      alert('Token saved — requests will include Authorization: Bearer <token>');
      // Reload the UI to ensure requestInterceptor picks up the token
      ui.initOAuth && ui.initOAuth();
    });

    clearBtn.addEventListener('click', () => {
      localStorage.removeItem('stagio_token');
      input.value = '';
      alert('Token cleared.');
      ui.initOAuth && ui.initOAuth();
    });
  </script>
</body>
</html>
