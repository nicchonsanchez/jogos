<?php
$rooms = [];
if (is_dir(__DIR__ . '/rooms/')) {
    foreach (glob(__DIR__ . '/rooms/*.json') as $f) {
        $r = json_decode(file_get_contents($f), true);
        if (!is_array($r) || $r['status'] !== 'waiting') continue;
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
include '../shared/icons.php';

$accent   = '#8b5cf6';
$title    = 'XADREZ 4';
$subtitle = 'Multiplayer 4 jogadores · Bots';
$backHref = '../';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xadrez 4 Jogadores — Nicchon</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../shared/games.css">
  <style>
    :root { --c: <?= $accent ?>; }

    .color-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .color-btn {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 14px;
      background: #0b0d1e; border: 1px solid #1c2035;
      border-radius: 8px; cursor: pointer;
      font-family: inherit; font-size: .82rem; color: #aaa;
      transition: all .15s; text-align: left;
    }
    .color-btn:hover { border-color: #333; color: #fff; }
    .color-btn.active { border-color: var(--dot-color); color: #fff; background: color-mix(in srgb, var(--dot-color) 8%, #0b0d1e); }
    .color-dot {
      width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
      background: var(--dot-color);
    }

    .bot-row { display: flex; gap: 8px; flex-wrap: wrap; }
    .bot-btn {
      padding: 7px 16px; background: #0b0d1e; border: 1px solid #1c2035;
      border-radius: 6px; cursor: pointer; font-family: inherit;
      font-size: .8rem; color: #888; transition: all .15s;
    }
    .bot-btn:hover { border-color: #333; color: #fff; }
    .bot-btn.active { border-color: var(--c); color: var(--c); }
  </style>
  <script src="../shared/player.js"></script>
</head>
<body>

<?php include '../shared/header.php'; ?>

<main class="gm-main">

  <!-- Como quer jogar -->
  <section>
    <p class="section-title">Como quer jogar?</p>
    <div class="mode-cards">
      <div class="mode-card" id="card-solo">
        <div class="mode-icon"><?= gameIcon('cpu') ?></div>
        <h3>Solo vs Bots</h3>
        <p>Jogue contra 1, 2 ou 3 bots</p>
      </div>
      <div class="mode-card" id="card-pvp">
        <div class="mode-icon"><?= gameIcon('users') ?></div>
        <h3>Multijogador</h3>
        <p>Crie uma sala e convide amigos</p>
      </div>
    </div>
  </section>

  <!-- Solo -->
  <section id="solo-section" style="display:none">
    <p class="section-title">Jogar contra bots</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Sua cor</label>
        <div class="color-grid" id="solo-color-grid">
          <button class="color-btn active" data-slot="0" style="--dot-color:#ef4444">
            <span class="color-dot"></span>Vermelho
          </button>
          <button class="color-btn" data-slot="1" style="--dot-color:#60a5fa">
            <span class="color-dot"></span>Azul
          </button>
          <button class="color-btn" data-slot="2" style="--dot-color:#eab308">
            <span class="color-dot"></span>Amarelo
          </button>
          <button class="color-btn" data-slot="3" style="--dot-color:#22c55e">
            <span class="color-dot"></span>Verde
          </button>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" id="btn-start-solo">Jogar</button>
        <button class="btn btn-ghost" id="btn-cancel-solo">Cancelar</button>
      </div>
    </div>
  </section>

  <!-- PvP -->
  <section id="pvp-section" style="display:none">
    <p class="section-title">Criar sala</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Nome da sala (opcional)</label>
        <input id="room-name" type="text" maxlength="40" placeholder="ex: partida do nico">
      </div>
      <div class="form-row">
        <label>Sua cor</label>
        <div class="color-grid" id="pvp-color-grid">
          <button class="color-btn active" data-slot="0" style="--dot-color:#ef4444">
            <span class="color-dot"></span>Vermelho
          </button>
          <button class="color-btn" data-slot="1" style="--dot-color:#60a5fa">
            <span class="color-dot"></span>Azul
          </button>
          <button class="color-btn" data-slot="2" style="--dot-color:#eab308">
            <span class="color-dot"></span>Amarelo
          </button>
          <button class="color-btn" data-slot="3" style="--dot-color:#22c55e">
            <span class="color-dot"></span>Verde
          </button>
        </div>
      </div>
      <div class="form-row">
        <label>Bots para preencher slots vazios</label>
        <div class="bot-row" id="bot-row">
          <button class="bot-btn" data-bots="0">0 bots</button>
          <button class="bot-btn" data-bots="1">1 bot</button>
          <button class="bot-btn active" data-bots="2">2 bots</button>
          <button class="bot-btn" data-bots="3">3 bots</button>
        </div>
      </div>
      <span class="msg-err" id="create-err"></span>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" id="btn-create">Criar sala</button>
        <button class="btn btn-ghost" id="btn-cancel-pvp">Cancelar</button>
      </div>
    </div>

    <div style="margin-top:20px">
      <p class="section-title">Ou entrar por código</p>
      <div class="join-row">
        <input id="join-code" type="text" maxlength="6" placeholder="A3F7C1">
        <button class="btn btn-primary" id="btn-join">Entrar</button>
      </div>
      <span class="msg-err" id="join-err" style="margin-top:6px"></span>
    </div>
  </section>

  <!-- Salas abertas -->
  <section>
    <p class="section-title">Salas aguardando jogadores</p>
    <?php if (empty($rooms)): ?>
      <p class="room-empty">Nenhuma sala aberta agora</p>
    <?php else: ?>
      <div class="rooms-list">
        <?php foreach ($rooms as $r):
          $human = count(array_filter($r['players'], fn($p) => !$p['isBot'] && !empty($p['token'])));
          $total = count(array_filter($r['players'], fn($p) => !$p['isBot']));
        ?>
          <div class="room-item">
            <div class="room-info">
              <h4><?= esc($r['name'] ?? $r['id']) ?></h4>
              <small>Criada por <?= esc($r['players'][0]['name'] ?? '?') ?> · <?= timeAgo((int)$r['createdAt']) ?> · <?= $human ?>/<?= $total ?> jogadores</small>
            </div>
            <button class="btn btn-primary btn-sm" onclick="quickJoin('<?= esc($r['id']) ?>')">Entrar</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<script>
let player = null;
function updateBadge() {
  const b = document.getElementById('gm-player-badge');
  if (player?.username) {
    document.getElementById('gm-badge-name').textContent = player.name + '  @' + player.username;
    b.style.display = 'flex';
  }
}
PlayerLogin.init('<?= $accent ?>', p => { player = p; updateBadge(); });

// ── Navegação entre seções ──
document.getElementById('card-solo').addEventListener('click', () => {
  document.getElementById('solo-section').style.display = 'block';
  document.getElementById('pvp-section').style.display = 'none';
});
document.getElementById('card-pvp').addEventListener('click', () => {
  document.getElementById('pvp-section').style.display = 'block';
  document.getElementById('solo-section').style.display = 'none';
});
document.getElementById('btn-cancel-solo').addEventListener('click', () => document.getElementById('solo-section').style.display = 'none');
document.getElementById('btn-cancel-pvp').addEventListener('click', () => document.getElementById('pvp-section').style.display = 'none');

// ── Seleção de cor ──
let soloSlot = 0, pvpSlot = 0;

function initColorGrid(gridId, onChange) {
  document.getElementById(gridId).querySelectorAll('.color-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      this.closest('.color-grid').querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      onChange(parseInt(this.dataset.slot));
    });
  });
}
initColorGrid('solo-color-grid', s => soloSlot = s);
initColorGrid('pvp-color-grid', s => pvpSlot = s);

// ── Seleção de bots ──
let selBots = 2;
document.getElementById('bot-row').querySelectorAll('.bot-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('bot-row').querySelectorAll('.bot-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    selBots = parseInt(this.dataset.bots);
  });
});

// ── Solo: redirecionar direto (sem servidor) ──
document.getElementById('btn-start-solo').addEventListener('click', () => {
  if (!player) { PlayerLogin.show('<?= $accent ?>', p => { player=p; updateBadge(); document.getElementById('btn-start-solo').click(); }); return; }
  location.href = 'room.php?solo=1&slot=' + soloSlot;
});

// ── PvP: criar sala ──
document.getElementById('btn-create').addEventListener('click', async () => {
  if (!player) { PlayerLogin.show('<?= $accent ?>', p => { player=p; updateBadge(); document.getElementById('btn-create').click(); }); return; }
  const errEl = document.getElementById('create-err');
  errEl.style.display = 'none';
  const res = await fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'create', username: player.username, name: player.name,
      slot: pvpSlot, bots: selBots,
      roomName: document.getElementById('room-name').value.trim()
    })
  }).then(r => r.json()).catch(() => null);
  if (!res?.roomId) { errEl.textContent = 'Erro ao criar sala'; errEl.style.display = 'block'; return; }
  sessionStorage.setItem('chess4_token', res.token);
  sessionStorage.setItem('chess4_slot', res.slot);
  location.href = 'room.php?id=' + res.roomId;
});

// ── Entrar por código ──
document.getElementById('btn-join').addEventListener('click', () => doJoin(document.getElementById('join-code').value.trim().toUpperCase()));
document.getElementById('join-code').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btn-join').click(); });

async function doJoin(id) {
  if (!player) { PlayerLogin.show('<?= $accent ?>', p => { player=p; updateBadge(); doJoin(id); }); return; }
  const errEl = document.getElementById('join-err');
  errEl.style.display = 'none';
  if (!id) { errEl.textContent = 'Insira o código da sala'; errEl.style.display = 'block'; return; }
  const res = await fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action:'join', roomId:id, username:player.username, name:player.name })
  }).then(r => r.json()).catch(() => null);
  if (!res?.token) { errEl.textContent = res?.error || 'Sala não encontrada'; errEl.style.display = 'block'; return; }
  sessionStorage.setItem('chess4_token', res.token);
  sessionStorage.setItem('chess4_slot', res.slot);
  location.href = 'room.php?id=' + id;
}
function quickJoin(id) { doJoin(id); }

setTimeout(() => location.reload(), 10000);
</script>
</body>
</html>
