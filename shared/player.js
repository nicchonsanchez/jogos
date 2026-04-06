/**
 * shared/player.js — Sistema de perfil compartilhado entre todos os jogos
 *
 * Uso:
 *   <script src="../shared/player.js"></script>
 *   PlayerLogin.init(accentColor, function(player) {
 *     // player = {username, name}
 *   });
 *   PlayerLogin.getPlayer() — retorna o jogador atual ou null
 *   PlayerLogin.showSwitch(accentColor, cb) — abre o modal para trocar conta
 *
 * Armazenamento:
 *   - localStorage: nicchon_user, nicchon_accounts (cache local, rápido)
 *   - Servidor: shared/users.php → shared/users.json (base única, cross-device)
 */
(function(global) {
  const STORAGE_USER     = 'nicchon_user';
  const STORAGE_ACCOUNTS = 'nicchon_accounts';
  const MAX_ACCOUNTS     = 6;

  // Detecta o caminho base até shared/ independente do jogo atual
  function usersApiUrl() {
    const scripts = document.querySelectorAll('script[src]');
    for (const s of scripts) {
      const m = s.src.match(/^(.*shared\/)player\.js/);
      if (m) return m[1] + 'users.php';
    }
    return '../shared/users.php';
  }

  // Migra dados das chaves antigas (snake_*) para as novas (nicchon_*)
  (function migrate() {
    try {
      if (!localStorage.getItem(STORAGE_USER) && localStorage.getItem('snake_user')) {
        localStorage.setItem(STORAGE_USER, localStorage.getItem('snake_user'));
      }
      if (!localStorage.getItem(STORAGE_ACCOUNTS) && localStorage.getItem('snake_accounts')) {
        localStorage.setItem(STORAGE_ACCOUNTS, localStorage.getItem('snake_accounts'));
      }
    } catch(e) {}
  })();

  // ── localStorage ───────────────────────────────────────
  function loadAccounts() {
    try { return JSON.parse(localStorage.getItem(STORAGE_ACCOUNTS)) || []; } catch(e) { return []; }
  }
  function saveAccounts(list) {
    localStorage.setItem(STORAGE_ACCOUNTS, JSON.stringify(list.slice(0, MAX_ACCOUNTS)));
  }
  function loadUser() {
    try { return JSON.parse(localStorage.getItem(STORAGE_USER)) || null; } catch(e) { return null; }
  }
  function saveUserLocal(player) {
    localStorage.setItem(STORAGE_USER, JSON.stringify(player));
    let list = loadAccounts().filter(a => a.username !== player.username);
    list.unshift(player);
    saveAccounts(list);
  }

  // ── API do servidor ────────────────────────────────────
  async function registerOnServer(player) {
    try {
      await fetch(usersApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'register', username: player.username, name: player.name })
      });
    } catch(e) { /* offline — tudo bem, localStorage basta */ }
  }

  // Busca contas do servidor para popular o seletor no novo dispositivo
  async function fetchServerAccounts() {
    try {
      const r = await fetch(usersApiUrl() + '?action=list');
      if (!r.ok) return [];
      return await r.json();
    } catch(e) { return []; }
  }

  // ── Modal HTML ──────────────────────────────────────────
  function createModal(accentColor) {
    const c = accentColor || '#4ade80';
    const el = document.createElement('div');
    el.id = 'player-login-modal';
    el.innerHTML = `
      <style>
        #player-login-modal {
          position: fixed; inset: 0; z-index: 9999;
          background: rgba(6,7,18,.92); backdrop-filter: blur(4px);
          display: flex; align-items: center; justify-content: center;
          padding: 16px;
        }
        #player-login-modal .plm-box {
          background: #0b0d1e; border: 1px solid #181b30; border-radius: 12px;
          padding: 24px; width: 100%; max-width: 360px;
          font-family: 'Open Sans','Segoe UI', sans-serif; color: #fff;
        }
        #player-login-modal .plm-title {
          font-size: .6rem; letter-spacing: 2px; text-transform: uppercase;
          color: #2e3255; margin-bottom: 16px;
        }
        #player-login-modal .plm-tabs {
          display: flex; gap: 0; margin-bottom: 16px;
          border-bottom: 1px solid #181b30;
        }
        #player-login-modal .plm-tab {
          padding: 7px 16px; font-size: .78rem; cursor: pointer;
          border-bottom: 2px solid transparent; margin-bottom: -1px;
          color: #444; background: none; border-top: none;
          border-left: none; border-right: none;
          font-family: inherit; transition: color .15s;
        }
        #player-login-modal .plm-tab.active {
          color: ${c}; border-bottom-color: ${c};
        }
        #player-login-modal .plm-panel { display: none; flex-direction: column; gap: 10px; }
        #player-login-modal .plm-panel.active { display: flex; }
        #player-login-modal .plm-chips {
          display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;
        }
        #player-login-modal .plm-chip {
          padding: 5px 12px; border-radius: 20px; font-size: .78rem;
          background: #060712; border: 1px solid #1c2035;
          color: #888; cursor: pointer; transition: all .12s;
          font-family: inherit;
        }
        #player-login-modal .plm-chip:hover {
          border-color: ${c}55; color: ${c};
        }
        #player-login-modal .plm-loading {
          font-size: .72rem; color: #2e3255;
        }
        #player-login-modal .plm-empty {
          font-size: .72rem; color: #2e3255;
        }
        #player-login-modal input {
          background: #060712; border: 1px solid #1c2035; border-radius: 5px;
          color: #ddd; font-family: inherit; font-size: .88rem;
          padding: 8px 10px; outline: none; width: 100%;
          transition: border-color .15s;
        }
        #player-login-modal input:focus { border-color: ${c}55; }
        #player-login-modal label {
          font-size: .58rem; letter-spacing: 1.5px; text-transform: uppercase; color: #444;
        }
        #player-login-modal .plm-err {
          font-size: .72rem; color: #f87171; display: none;
        }
        #player-login-modal .plm-btn {
          padding: 9px 20px; border: none; border-radius: 5px;
          font-family: inherit; font-size: .85rem; font-weight: 700;
          cursor: pointer; background: ${c}; color: #fff;
          transition: filter .12s; align-self: flex-start;
        }
        #player-login-modal .plm-btn:hover { filter: brightness(1.1); }
        #player-login-modal .plm-btn:disabled { opacity: .5; cursor: default; filter: none; }
      </style>
      <div class="plm-box">
        <div class="plm-title">Identificação</div>
        <div class="plm-tabs">
          <button class="plm-tab active" data-tab="login">Entrar</button>
          <button class="plm-tab" data-tab="create">Criar perfil</button>
        </div>

        <!-- Entrar (contas salvas) -->
        <div class="plm-panel active" id="plm-panel-login">
          <p class="plm-loading" id="plm-loading">Carregando contas...</p>
          <div id="plm-chips" class="plm-chips" style="display:none"></div>
          <p class="plm-empty" id="plm-no-accounts" style="display:none">
            Nenhuma conta neste dispositivo.<br>Crie um perfil na aba ao lado.
          </p>
        </div>

        <!-- Criar -->
        <div class="plm-panel" id="plm-panel-create">
          <div style="display:flex;flex-direction:column;gap:4px">
            <label>Username</label>
            <input id="plm-username" type="text" maxlength="20" placeholder="sem espaços, ex: nicchon">
          </div>
          <div style="display:flex;flex-direction:column;gap:4px">
            <label>Nome de exibição</label>
            <input id="plm-name" type="text" maxlength="30" placeholder="ex: Nicchon">
          </div>
          <span class="plm-err" id="plm-err"></span>
          <button class="plm-btn" id="plm-create-btn">Criar e entrar</button>
        </div>
      </div>
    `;
    return el;
  }

  // ── Public API ──────────────────────────────────────────
  let _callback = null;
  let _modal    = null;

  async function showModal(accentColor, cb) {
    _callback = cb;
    if (_modal) _modal.remove();
    _modal = createModal(accentColor);
    document.body.appendChild(_modal);

    // Carrega contas: localStorage primeiro (rápido), depois mescla com servidor
    const localAccounts  = loadAccounts();
    const chipsEl        = _modal.querySelector('#plm-chips');
    const noAcc          = _modal.querySelector('#plm-no-accounts');
    const loadingEl      = _modal.querySelector('#plm-loading');

    function renderChips(accounts) {
      chipsEl.innerHTML = '';
      loadingEl.style.display = 'none';
      if (accounts.length === 0) {
        noAcc.style.display = 'block';
        chipsEl.style.display = 'none';
        if (_modal) _modal.querySelector('[data-tab="create"]').click();
      } else {
        noAcc.style.display = 'none';
        chipsEl.style.display = 'flex';
        accounts.forEach(acc => {
          const btn = document.createElement('button');
          btn.className = 'plm-chip';
          btn.textContent = acc.name + ' @' + acc.username;
          btn.addEventListener('click', () => selectAccount(acc));
          chipsEl.appendChild(btn);
        });
      }
    }

    // Mostra locais imediatamente
    if (localAccounts.length > 0) {
      loadingEl.style.display = 'none';
      renderChips(localAccounts);
    }

    // Tabs
    _modal.querySelectorAll('.plm-tab').forEach(tab => {
      tab.addEventListener('click', function() {
        _modal.querySelectorAll('.plm-tab').forEach(t => t.classList.remove('active'));
        _modal.querySelectorAll('.plm-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        _modal.querySelector('#plm-panel-' + this.dataset.tab).classList.add('active');
      });
    });

    // Create
    const createBtn = _modal.querySelector('#plm-create-btn');
    createBtn.addEventListener('click', async () => {
      const u = _modal.querySelector('#plm-username').value.trim().replace(/[^a-zA-Z0-9_]/g,'');
      const n = _modal.querySelector('#plm-name').value.trim();
      const err = _modal.querySelector('#plm-err');
      if (u.length < 3) { err.textContent='Username mínimo 3 chars'; err.style.display='block'; return; }
      if (n.length < 2) { err.textContent='Nome mínimo 2 chars'; err.style.display='block'; return; }
      err.style.display = 'none';
      createBtn.disabled = true;
      createBtn.textContent = 'Salvando...';
      await selectAccount({username: u, name: n});
    });
    _modal.querySelector('#plm-username').addEventListener('keydown', e => {
      if (e.key === 'Enter') _modal.querySelector('#plm-name').focus();
    });
    _modal.querySelector('#plm-name').addEventListener('keydown', e => {
      if (e.key === 'Enter') createBtn.click();
    });

    // Se não havia contas locais, busca do servidor
    if (localAccounts.length === 0) {
      const serverAccounts = await fetchServerAccounts();
      if (_modal) renderChips(serverAccounts);
    }
  }

  async function selectAccount(player) {
    saveUserLocal(player);
    await registerOnServer(player);
    if (_modal) { _modal.remove(); _modal = null; }
    if (_callback) _callback(player);
  }

  function init(accentColor, cb) {
    const existing = loadUser();
    if (existing?.username) {
      // Sincroniza silenciosamente com o servidor em segundo plano
      registerOnServer(existing);
      cb(existing);
      return;
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => showModal(accentColor, cb));
    } else {
      showModal(accentColor, cb);
    }
  }

  function showSwitch(accentColor, cb) {
    showModal(accentColor, cb);
  }

  global.PlayerLogin = { init, showSwitch, getPlayer: loadUser };
})(window);
