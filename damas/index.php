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

$accent   = '#ef4444';
$title    = 'DAMAS';
$subtitle = 'Regras brasileiras · Multiplayer ou Bot';
$backHref = '../';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Damas — Nicchon</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../shared/games.css">
  <style>
    :root { --c: <?= $accent ?>; }
  </style>
  <script src="../shared/player.js"></script>
</head>
<body>

<?php include '../shared/header.php'; ?>

<main class="gm-main">

  <section>
    <p class="section-title">Como quer jogar?</p>
    <div class="mode-cards">
      <div class="mode-card" id="card-pvp">
        <div class="mode-icon"><?= gameIcon('users') ?></div>
        <h3>Multijogador</h3>
        <p>Crie uma sala e convide um amigo pelo código</p>
      </div>
      <div class="mode-card" id="card-bot">
        <div class="mode-icon"><?= gameIcon('cpu') ?></div>
        <h3>Contra Bot</h3>
        <p>Jogue sozinho contra a IA em 3 dificuldades</p>
      </div>
    </div>
  </section>

  <section id="pvp-section" style="display:none">
    <p class="section-title">Criar sala</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Nome da sala (opcional)</label>
        <input id="room-name" type="text" maxlength="40" placeholder="ex: partida do nico">
      </div>
      <div class="form-row">
        <label>Suas peças</label>
        <div class="opt-row" id="pvp-color-row">
          <button class="opt-btn active" data-color="white">⚪ Brancas</button>
          <button class="opt-btn" data-color="black">⚫ Pretas</button>
          <button class="opt-btn" data-color="random"><?= gameIcon('shuffle', 14) ?> Aleatório</button>
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

  <section id="bot-section" style="display:none">
    <p class="section-title">Jogar contra bot</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Dificuldade</label>
        <div class="opt-row" id="diff-row">
          <button class="opt-btn" data-diff="1">Fácil</button>
          <button class="opt-btn active" data-diff="3">Médio</button>
          <button class="opt-btn" data-diff="5">Difícil</button>
        </div>
      </div>
      <div class="form-row">
        <label>Suas peças</label>
        <div class="opt-row" id="bot-color-row">
          <button class="opt-btn active" data-bcolor="white">⚪ Brancas</button>
          <button class="opt-btn" data-bcolor="black">⚫ Pretas</button>
          <button class="opt-btn" data-bcolor="random"><?= gameIcon('shuffle', 14) ?> Aleatório</button>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" id="btn-start-bot">Jogar</button>
        <button class="btn btn-ghost" id="btn-cancel-bot">Cancelar</button>
      </div>
    </div>
  </section>

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
let player = null;
function updateBadge() {
  const b = document.getElementById('gm-player-badge');
  if (player?.username) {
    document.getElementById('gm-badge-name').textContent = player.name + '  @' + player.username;
    b.style.display = 'flex';
  }
}
PlayerLogin.init('<?= $accent ?>', p => { player = p; updateBadge(); });

document.getElementById('card-pvp').addEventListener('click', () => {
  document.getElementById('pvp-section').style.display = 'block';
  document.getElementById('bot-section').style.display = 'none';
});
document.getElementById('card-bot').addEventListener('click', () => {
  document.getElementById('bot-section').style.display = 'block';
  document.getElementById('pvp-section').style.display = 'none';
});
document.getElementById('btn-cancel-pvp').addEventListener('click', () => document.getElementById('pvp-section').style.display = 'none');
document.getElementById('btn-cancel-bot').addEventListener('click', () => document.getElementById('bot-section').style.display = 'none');

let selColor = 'white', selBColor = 'white', selDiff = 3;

document.getElementById('pvp-color-row').querySelectorAll('.opt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('pvp-color-row').querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    selColor = this.dataset.color;
  });
});
document.getElementById('diff-row').querySelectorAll('.opt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('diff-row').querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    selDiff = parseInt(this.dataset.diff);
  });
});
document.getElementById('bot-color-row').querySelectorAll('.opt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('bot-color-row').querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    selBColor = this.dataset.bcolor;
  });
});

document.getElementById('btn-create').addEventListener('click', async () => {
  if (!player) return;
  const errEl = document.getElementById('create-err');
  errEl.style.display = 'none';
  let color = selColor === 'random' ? (Math.random() < .5 ? 'white' : 'black') : selColor;
  const res = await fetch('api.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'create', username:player.username, name:player.name, game:'damas', color, roomName: document.getElementById('room-name').value.trim()})
  }).then(r => r.json()).catch(() => null);
  if (!res?.roomId) { errEl.textContent = 'Erro ao criar sala'; errEl.style.display = 'block'; return; }
  sessionStorage.setItem('damas_token', res.token);
  sessionStorage.setItem('damas_color', res.color);
  location.href = 'room.php?id=' + res.roomId;
});

document.getElementById('btn-join').addEventListener('click', () => doJoin(document.getElementById('join-code').value.trim().toUpperCase()));
document.getElementById('join-code').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btn-join').click(); });

async function doJoin(id) {
  if (!player) return;
  const errEl = document.getElementById('join-err');
  errEl.style.display = 'none';
  if (!id) { errEl.textContent = 'Insira o código da sala'; errEl.style.display = 'block'; return; }
  const res = await fetch('api.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'join', roomId:id, username:player.username, name:player.name})
  }).then(r => r.json()).catch(() => null);
  if (!res?.token) { errEl.textContent = res?.error || 'Sala não encontrada'; errEl.style.display = 'block'; return; }
  sessionStorage.setItem('damas_token', res.token);
  sessionStorage.setItem('damas_color', res.color);
  location.href = 'room.php?id=' + id;
}
function quickJoin(id) { doJoin(id); }

document.getElementById('btn-start-bot').addEventListener('click', () => {
  if (!player) return;
  let color = selBColor === 'random' ? (Math.random() < .5 ? 'white' : 'black') : selBColor;
  location.href = `room.php?bot=${selDiff}&color=${color}`;
});

setTimeout(() => location.reload(), 10000);
</script>
</body>
</html>
