<?php
$rooms = [];
$apiUrl = 'api.php?action=list';
$json = @file_get_contents(__DIR__ . '/rooms/' . '*.json'); // não usado diretamente
// Lê salas via glob
if (is_dir(__DIR__ . '/rooms/')) {
    foreach (glob(__DIR__ . '/rooms/*.json') as $f) {
        $r = json_decode(file_get_contents($f), true);
        if (!is_array($r) || $r['status'] !== 'waiting') continue;
        // purge
        if (filemtime($f) < time() - 86400) { @unlink($f); continue; }
        $rooms[] = $r;
    }
    usort($rooms, fn($a,$b) => $b['createdAt'] - $a['createdAt']);
}
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function timeAgo(int $ts): string {
    $d = time() - $ts;
    if ($d < 60) return 'agora';
    if ($d < 3600) return floor($d/60) . 'min atrás';
    return floor($d/3600) . 'h atrás';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xadrez — Nicchon</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#080812;color:#fff;font-family:'Segoe UI',sans-serif;min-height:100vh}
    :root{--c:#f59e0b}

    header{padding:32px 24px 24px;border-bottom:1px solid #111128}
    .header-inner{max-width:760px;margin:0 auto;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .back{font-size:.7rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;text-decoration:none;transition:color .15s}
    .back:hover{color:#888}
    h1{font-size:1.7rem;letter-spacing:3px;margin-top:10px}
    h1 em{color:var(--c);font-style:normal;text-shadow:0 0 20px #f59e0b44}
    .subtitle{color:#333;font-size:.75rem;letter-spacing:.5px;margin-top:4px}

    main{max-width:760px;margin:0 auto;padding:32px 24px 60px;display:flex;flex-direction:column;gap:32px}

    .section-title{font-size:.62rem;letter-spacing:2px;text-transform:uppercase;color:#2a2a48;margin-bottom:14px}

    /* ── Modos ── */
    .mode-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .mode-card{background:#0e0e1e;border:1px solid #181830;border-radius:10px;padding:20px;cursor:pointer;transition:border-color .15s,transform .12s;text-align:left}
    .mode-card:hover{border-color:var(--c);transform:translateY(-2px)}
    .mode-card h3{font-size:1rem;color:#e8e8f0;margin-bottom:6px}
    .mode-card p{font-size:.75rem;color:#444;line-height:1.4}
    .mode-icon{font-size:1.6rem;margin-bottom:10px}

    /* ── Formulário criar sala ── */
    .form-box{background:#0e0e1e;border:1px solid #181830;border-radius:10px;padding:20px;display:none;flex-direction:column;gap:12px}
    .form-box.open{display:flex}
    .form-row{display:flex;flex-direction:column;gap:5px}
    label{font-size:.62rem;letter-spacing:1.5px;text-transform:uppercase;color:#444}
    input,select{background:#080812;border:1px solid #1e1e38;border-radius:5px;color:#ddd;font-family:inherit;font-size:.88rem;padding:8px 10px;outline:none;transition:border-color .15s;width:100%}
    input:focus,select:focus{border-color:#f59e0b55}
    .color-row{display:flex;gap:8px}
    .color-opt{flex:1;padding:7px;background:#080812;border:1px solid #1e1e38;border-radius:5px;color:#555;font-size:.78rem;cursor:pointer;text-align:center;transition:all .12s;font-family:inherit}
    .color-opt.active{border-color:#f59e0b55;color:#f59e0b;background:#f59e0b11}

    /* ── Botões ── */
    .btn{padding:9px 24px;border:none;border-radius:5px;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:all .12s}
    .btn-primary{background:var(--c);color:#080812}
    .btn-primary:hover{filter:brightness(1.1)}
    .btn-ghost{background:transparent;border:1px solid #1e1e38;color:#555}
    .btn-ghost:hover{border-color:#444;color:#aaa}
    .btn-sm{padding:5px 14px;font-size:.75rem}

    /* ── Dificuldade bot ── */
    .diff-row{display:flex;gap:8px}
    .diff-btn{flex:1;padding:7px;background:#080812;border:1px solid #1e1e38;border-radius:5px;color:#555;font-size:.78rem;cursor:pointer;text-align:center;transition:all .12s;font-family:inherit}
    .diff-btn.active{border-color:#f59e0b55;color:#f59e0b;background:#f59e0b11}

    /* ── Lista de salas ── */
    .rooms-list{display:flex;flex-direction:column;gap:8px}
    .room-item{background:#0e0e1e;border:1px solid #181830;border-radius:8px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .room-info h4{font-size:.9rem;color:#ddd;margin-bottom:3px}
    .room-info small{font-size:.7rem;color:#333}
    .room-empty{color:#2a2a48;font-size:.82rem;padding:24px 0;text-align:center}

    /* ── Join por código ── */
    .join-row{display:flex;gap:8px}
    .join-row input{flex:1;text-transform:uppercase;letter-spacing:2px}

    /* ── Erro / Info ── */
    .msg-err{color:#f87171;font-size:.75rem;display:none}
    .msg-info{color:#4ade80;font-size:.75rem;display:none}

    @media(max-width:480px){
      .mode-cards{grid-template-columns:1fr}
      header{padding:24px 16px 20px}
      main{padding:24px 16px 48px}
    }
  </style>
  <script src="../shared/player.js"></script>
</head>
<body>
<header>
  <div class="header-inner">
    <div>
      <a class="back" href="../">← Jogos</a>
      <h1>XADREZ <em>//</em></h1>
      <p class="subtitle">Multiplayer ao vivo ou contra bot</p>
    </div>
    <div id="player-badge" style="font-size:.72rem;color:#333;cursor:pointer;text-align:right;display:none" onclick="PlayerLogin.showSwitch('#f59e0b', p => { player=p; updateBadge(); })">
      <span id="badge-name"></span><br><span style="font-size:.6rem;letter-spacing:1px">trocar conta</span>
    </div>
  </div>
</header>

<main>
  <!-- Escolha de modo -->
  <section>
    <p class="section-title">Como quer jogar?</p>
    <div class="mode-cards">
      <div class="mode-card" id="card-pvp">
        <div class="mode-icon">♟</div>
        <h3>Multijogador</h3>
        <p>Crie uma sala e convide um amigo pelo código</p>
      </div>
      <div class="mode-card" id="card-bot">
        <div class="mode-icon">🤖</div>
        <h3>Contra Bot</h3>
        <p>Jogue sozinho contra a IA em 3 dificuldades</p>
      </div>
    </div>
  </section>

  <!-- Formulário criar sala PvP -->
  <section id="pvp-section" style="display:none">
    <p class="section-title">Criar sala</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Nome da sala (opcional)</label>
        <input id="room-name" type="text" maxlength="40" placeholder="ex: partida do nico">
      </div>
      <div class="form-row">
        <label>Suas peças</label>
        <div class="color-row">
          <button class="color-opt active" data-color="white" id="opt-white">♔ Brancas</button>
          <button class="color-opt" data-color="black" id="opt-black">♚ Pretas</button>
          <button class="color-opt" data-color="random" id="opt-random">🎲 Aleatório</button>
        </div>
      </div>
      <span class="msg-err" id="create-err"></span>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" id="btn-create">Criar sala</button>
        <button class="btn btn-ghost" id="btn-cancel-pvp">Cancelar</button>
      </div>
    </div>

    <!-- Entrar por código -->
    <div style="margin-top:20px">
      <p class="section-title">Ou entrar por código</p>
      <div class="join-row">
        <input id="join-code" type="text" maxlength="6" placeholder="A3F7C1">
        <button class="btn btn-primary" id="btn-join">Entrar</button>
      </div>
      <span class="msg-err" id="join-err" style="margin-top:6px;display:block;display:none"></span>
    </div>
  </section>

  <!-- Formulário bot -->
  <section id="bot-section" style="display:none">
    <p class="section-title">Jogar contra bot</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Dificuldade</label>
        <div class="diff-row">
          <button class="diff-btn" data-diff="1">Fácil</button>
          <button class="diff-btn active" data-diff="2">Médio</button>
          <button class="diff-btn" data-diff="3">Difícil</button>
        </div>
      </div>
      <div class="form-row">
        <label>Suas peças</label>
        <div class="color-row">
          <button class="color-opt active" data-bcolor="white" id="bot-white">♔ Brancas</button>
          <button class="color-opt" data-bcolor="black" id="bot-black">♚ Pretas</button>
          <button class="color-opt" data-bcolor="random" id="bot-random">🎲 Aleatório</button>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" id="btn-start-bot">Jogar</button>
        <button class="btn btn-ghost" id="btn-cancel-bot">Cancelar</button>
      </div>
    </div>
  </section>

  <!-- Salas abertas -->
  <section>
    <p class="section-title">Salas aguardando jogador</p>
    <?php if (empty($rooms)): ?>
      <p class="room-empty">Nenhuma sala aberta agora</p>
    <?php else: ?>
      <div class="rooms-list">
        <?php foreach ($rooms as $r): ?>
          <div class="room-item">
            <div class="room-info">
              <h4><?= esc($r['name'] ?? $r['id']) ?></h4>
              <small>Criada por <?= esc($r['players'][0]['name'] ?? '?') ?> · <?= timeAgo((int)$r['createdAt']) ?></small>
            </div>
            <button class="btn btn-primary btn-sm" onclick="quickJoin('<?= esc($r['id']) ?>')">Entrar</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
// ── Perfil ────────────────────────────────────────────────────────────────────
let player = null;
function updateBadge() {
  const b = document.getElementById('player-badge');
  if (player?.username) {
    document.getElementById('badge-name').textContent = player.name + ' @' + player.username;
    b.style.display = 'block';
  }
}
PlayerLogin.init('#f59e0b', p => { player = p; updateBadge(); });

// ── Modo selector ─────────────────────────────────────────────────────────────
document.getElementById('card-pvp').addEventListener('click', () => {
  document.getElementById('pvp-section').style.display = 'block';
  document.getElementById('bot-section').style.display = 'none';
});
document.getElementById('card-bot').addEventListener('click', () => {
  document.getElementById('bot-section').style.display = 'block';
  document.getElementById('pvp-section').style.display = 'none';
});
document.getElementById('btn-cancel-pvp').addEventListener('click', () => document.getElementById('pvp-section').style.display='none');
document.getElementById('btn-cancel-bot').addEventListener('click', () => document.getElementById('bot-section').style.display='none');

// ── Seleção de cor ────────────────────────────────────────────────────────────
let selColor  = 'white';
let selBColor = 'white';
let selDiff   = 2;

document.querySelectorAll('.color-row .color-opt').forEach(btn => {
  btn.addEventListener('click', function() {
    const parent = this.closest('.color-row');
    parent.querySelectorAll('.color-opt').forEach(b=>b.classList.remove('active'));
    this.classList.add('active');
    if (this.dataset.color !== undefined) selColor = this.dataset.color;
    if (this.dataset.bcolor !== undefined) selBColor = this.dataset.bcolor;
  });
});
document.querySelectorAll('.diff-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.diff-btn').forEach(b=>b.classList.remove('active'));
    this.classList.add('active');
    selDiff = parseInt(this.dataset.diff);
  });
});

// ── Criar sala ────────────────────────────────────────────────────────────────
document.getElementById('btn-create').addEventListener('click', async () => {
  if (!player) { alert('Configure seu perfil primeiro'); return; }
  const errEl = document.getElementById('create-err');
  errEl.style.display = 'none';

  let color = selColor;
  if (color === 'random') color = Math.random() < .5 ? 'white' : 'black';

  const res = await fetch('api.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'create', username:player.username, name:player.name,
      game:'chess', color, roomName: document.getElementById('room-name').value.trim()})
  }).then(r=>r.json()).catch(()=>null);

  if (!res?.roomId) { errEl.textContent='Erro ao criar sala'; errEl.style.display='block'; return; }
  sessionStorage.setItem('chess_token', res.token);
  sessionStorage.setItem('chess_color', res.color);
  location.href = 'room.php?id=' + res.roomId;
});

// ── Entrar por código ─────────────────────────────────────────────────────────
document.getElementById('btn-join').addEventListener('click', () => doJoin(document.getElementById('join-code').value.trim().toUpperCase()));
document.getElementById('join-code').addEventListener('keydown', e => { if(e.key==='Enter') document.getElementById('btn-join').click(); });

async function doJoin(id) {
  if (!player) { alert('Configure seu perfil primeiro'); return; }
  const errEl = document.getElementById('join-err');
  errEl.style.display = 'none';

  const res = await fetch('api.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'join', roomId:id, username:player.username, name:player.name})
  }).then(r=>r.json()).catch(()=>null);

  if (!res?.token) { errEl.textContent = res?.error || 'Sala não encontrada'; errEl.style.display='block'; return; }
  sessionStorage.setItem('chess_token', res.token);
  sessionStorage.setItem('chess_color', res.color);
  location.href = 'room.php?id=' + id;
}

async function quickJoin(id) { doJoin(id); }

// ── Bot ───────────────────────────────────────────────────────────────────────
document.getElementById('btn-start-bot').addEventListener('click', () => {
  if (!player) { alert('Configure seu perfil primeiro'); return; }
  let color = selBColor;
  if (color === 'random') color = Math.random() < .5 ? 'white' : 'black';
  location.href = `room.php?bot=${selDiff}&color=${color}`;
});

// ── Auto-refresh das salas a cada 10s ─────────────────────────────────────────
setTimeout(() => location.reload(), 10000);
</script>
</body>
</html>
