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
function humanCount(array $room): int {
    return count(array_filter($room['players'], fn($p) => !($p['isBot'] ?? false)));
}
include '../shared/icons.php';

$accent   = '#a855f7';
$title    = 'LUDO';
$subtitle = 'Clássico de tabuleiro · 2–4 jogadores';
$backHref = '../';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ludo — Nicchon</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../shared/games.css">
  <style>
    :root { --c: <?= $accent ?>; }
    .color-dots { display:flex; gap:5px; margin-top:5px; }
    .color-dot  { width:9px; height:9px; border-radius:50%; }
  </style>
  <script src="../shared/player.js"></script>
</head>
<body>

<?php include '../shared/header.php'; ?>

<main class="gm-main">

  <section>
    <p class="section-title">Como quer jogar?</p>
    <div class="mode-cards">
      <div class="mode-card" id="card-create">
        <div class="mode-icon"><?= gameIcon('dice') ?></div>
        <h3>Criar sala</h3>
        <p>Crie uma sala com bots ou convide amigos pelo código</p>
      </div>
      <div class="mode-card" id="card-join">
        <div class="mode-icon"><?= gameIcon('log-in') ?></div>
        <h3>Entrar por código</h3>
        <p>Entre numa sala existente usando o código de 6 dígitos</p>
      </div>
    </div>
  </section>

  <section id="create-section" style="display:none">
    <p class="section-title">Criar sala</p>
    <div class="form-box open">
      <div class="form-row">
        <label>Quantos bots?</label>
        <div class="opt-row" id="bots-row">
          <button class="opt-btn" data-bots="0">0 bots</button>
          <button class="opt-btn active" data-bots="1">1 bot</button>
          <button class="opt-btn" data-bots="2">2 bots</button>
          <button class="opt-btn" data-bots="3">3 bots</button>
        </div>
        <small style="color:var(--gm-dim);font-size:.65rem;margin-top:6px">Slots vazios serão preenchidos por bots ao iniciar</small>
      </div>
      <span class="msg-err" id="create-err"></span>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" id="btn-create">Criar sala</button>
        <button class="btn btn-ghost" id="btn-cancel-create">Cancelar</button>
      </div>
    </div>
  </section>

  <section id="join-section" style="display:none">
    <p class="section-title">Entrar por código</p>
    <div class="form-box open">
      <div class="join-row">
        <input id="join-code" type="text" maxlength="6" placeholder="A3F7C1" style="text-transform:uppercase;letter-spacing:3px">
        <button class="btn btn-primary" id="btn-join">Entrar</button>
      </div>
      <span class="msg-err" id="join-err"></span>
      <button class="btn btn-ghost" style="align-self:flex-start" id="btn-cancel-join">Cancelar</button>
    </div>
  </section>

  <section>
    <p class="section-title">Salas aguardando jogadores</p>
    <?php if (empty($rooms)): ?>
      <p class="room-empty">Nenhuma sala aberta agora</p>
    <?php else: ?>
      <div class="rooms-list">
        <?php foreach ($rooms as $r):
          $humans = humanCount($r);
          $total  = count($r['players']);
        ?>
          <div class="room-item">
            <div class="room-info">
              <h4>Sala de <?= esc($r['players'][0]['name'] ?? '?') ?></h4>
              <small><?= $humans ?> humano<?= $humans!==1?'s':'' ?> · <?= $total ?>/4 slots · <?= timeAgo((int)$r['createdAt']) ?></small>
              <div class="color-dots">
                <?php foreach ($r['players'] as $p):
                  $dotColor = ['red'=>'#ef4444','blue'=>'#3b82f6','green'=>'#22c55e','yellow'=>'#eab308'][$p['color']] ?? '#888';
                ?>
                  <span class="color-dot" style="background:<?= $dotColor ?>" title="<?= esc($p['name']) ?>"></span>
                <?php endforeach; ?>
              </div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="quickJoin('<?= esc($r['id']) ?>')">Entrar</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<script>
const $ = id => document.getElementById(id);
let player = null;
function updateBadge() {
  const b = $('gm-player-badge');
  if (player?.username) {
    $('gm-badge-name').textContent = player.name + '  @' + player.username;
    b.style.display = 'flex';
  }
}
PlayerLogin.init('<?= $accent ?>', p => { player = p; updateBadge(); });

$('card-create').addEventListener('click', () => { $('create-section').style.display='block'; $('join-section').style.display='none'; });
$('card-join').addEventListener('click',   () => { $('join-section').style.display='block';  $('create-section').style.display='none'; });
$('btn-cancel-create').addEventListener('click', () => $('create-section').style.display='none');
$('btn-cancel-join').addEventListener('click',   () => $('join-section').style.display='none');

let selBots = 1;
$('bots-row').querySelectorAll('.opt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    $('bots-row').querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    selBots = parseInt(this.dataset.bots);
  });
});

$('btn-create').addEventListener('click', async () => {
  if (!player) return;
  const res = await fetch('api.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'create', username:player.username, name:player.name, bots:selBots})
  }).then(r => r.json()).catch(() => null);
  if (!res?.roomId) { $('create-err').textContent = res?.error || 'Erro ao criar sala'; $('create-err').style.display='block'; return; }
  sessionStorage.setItem('ludo_token', res.token);
  sessionStorage.setItem('ludo_color', res.color);
  sessionStorage.setItem('ludo_slot',  res.slot);
  location.href = 'room.php?id=' + res.roomId;
});

$('btn-join').addEventListener('click', () => doJoin($('join-code').value.trim().toUpperCase()));
$('join-code').addEventListener('keydown', e => { if (e.key === 'Enter') $('btn-join').click(); });

async function doJoin(id) {
  if (!player) return;
  if (!id) { $('join-err').textContent='Insira o código da sala'; $('join-err').style.display='block'; return; }
  const res = await fetch('api.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'join', roomId:id, username:player.username, name:player.name})
  }).then(r => r.json()).catch(() => null);
  if (!res?.token) { $('join-err').textContent = res?.error || 'Sala não encontrada'; $('join-err').style.display='block'; return; }
  sessionStorage.setItem('ludo_token', res.token);
  sessionStorage.setItem('ludo_color', res.color);
  sessionStorage.setItem('ludo_slot',  res.slot);
  location.href = 'room.php?id=' + id;
}
function quickJoin(id) { doJoin(id); }

setTimeout(() => location.reload(), 10000);
</script>
</body>
</html>
