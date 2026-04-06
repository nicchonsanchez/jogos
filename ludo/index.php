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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ludo — Nicchon</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#08080f;color:#fff;font-family:'Open Sans','Segoe UI',sans-serif;min-height:100vh}
    :root{--c:#a855f7}
    header{padding:32px 24px 24px;border-bottom:1px solid #111120}
    .header-inner{max-width:760px;margin:0 auto;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .back{font-size:.7rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;text-decoration:none;transition:color .15s}
    .back:hover{color:#888}
    h1{font-size:1.7rem;letter-spacing:3px;margin-top:10px}
    h1 em{color:var(--c);font-style:normal;text-shadow:0 0 20px #a855f744}
    .subtitle{color:#333;font-size:.75rem;letter-spacing:.5px;margin-top:4px}
    main{max-width:760px;margin:0 auto;padding:32px 24px 60px;display:flex;flex-direction:column;gap:32px}
    .section-title{font-size:.62rem;letter-spacing:2px;text-transform:uppercase;color:#252542;margin-bottom:14px}
    .mode-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .mode-card{background:#0d0d1a;border:1px solid #1a1a2c;border-radius:10px;padding:20px;cursor:pointer;transition:border-color .15s,transform .12s;text-align:left}
    .mode-card:hover{border-color:var(--c);transform:translateY(-2px)}
    .mode-card h3{font-size:1rem;color:#e8e8f0;margin-bottom:6px}
    .mode-card p{font-size:.75rem;color:#444;line-height:1.4}
    .mode-icon{font-size:1.6rem;margin-bottom:10px}
    .form-box{background:#0d0d1a;border:1px solid #1a1a2c;border-radius:10px;padding:20px;flex-direction:column;gap:14px}
    .form-box.open{display:flex}
    .form-row{display:flex;flex-direction:column;gap:5px}
    label{font-size:.62rem;letter-spacing:1.5px;text-transform:uppercase;color:#444}
    input,select{background:#08080f;border:1px solid #1e1e30;border-radius:5px;color:#ddd;font-family:inherit;font-size:.88rem;padding:8px 10px;outline:none;transition:border-color .15s;width:100%}
    input:focus,select:focus{border-color:#a855f755}
    .bot-row{display:flex;gap:8px}
    .bot-btn{flex:1;padding:8px 4px;background:#08080f;border:1px solid #1e1e30;border-radius:5px;color:#555;font-size:.8rem;cursor:pointer;text-align:center;transition:all .12s;font-family:inherit}
    .bot-btn.active{border-color:#a855f755;color:#a855f7;background:#a855f711}
    .btn{padding:9px 24px;border:none;border-radius:5px;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:all .12s}
    .btn-primary{background:var(--c);color:#fff}
    .btn-primary:hover{filter:brightness(1.1)}
    .btn-ghost{background:transparent;border:1px solid #1e1e30;color:#555}
    .btn-ghost:hover{border-color:#444;color:#aaa}
    .btn-sm{padding:5px 14px;font-size:.75rem}
    .rooms-list{display:flex;flex-direction:column;gap:8px}
    .room-item{background:#0d0d1a;border:1px solid #1a1a2c;border-radius:8px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .room-info h4{font-size:.9rem;color:#ddd;margin-bottom:3px}
    .room-info small{font-size:.7rem;color:#333}
    .room-empty{color:#252542;font-size:.82rem;padding:24px 0;text-align:center}
    .join-row{display:flex;gap:8px}
    .join-row input{flex:1;text-transform:uppercase;letter-spacing:2px}
    .msg-err{color:#f87171;font-size:.75rem;margin-top:4px;display:none}
    .color-dots{display:flex;gap:5px;margin-top:4px}
    .color-dot{width:10px;height:10px;border-radius:50%}
    @media(max-width:480px){.mode-cards{grid-template-columns:1fr}header{padding:24px 16px 20px}main{padding:24px 16px 48px}}
  </style>
  <script src="../shared/player.js"></script>
</head>
<body>
<header>
  <div class="header-inner">
    <div>
      <a class="back" href="../">← Jogos</a>
      <h1>LUDO</h1>
      <p class="subtitle">Clássico de tabuleiro · 2-4 jogadores com bots</p>
    </div>
    <div id="player-badge" style="font-size:.72rem;color:#333;cursor:pointer;text-align:right;display:none" onclick="PlayerLogin.showSwitch('#a855f7', p => { player=p; updateBadge(); })">
      <span id="badge-name"></span><br><span style="font-size:.6rem;letter-spacing:1px">trocar conta</span>
    </div>
  </div>
</header>
<main>
  <section>
    <p class="section-title">Como quer jogar?</p>
    <div class="mode-cards">
      <div class="mode-card" id="card-create">
        <div class="mode-icon">🎲</div>
        <h3>Criar sala</h3>
        <p>Crie uma sala com bots ou convide amigos pelo código</p>
      </div>
      <div class="mode-card" id="card-join">
        <div class="mode-icon">🔗</div>
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
        <div class="bot-row">
          <button class="bot-btn" data-bots="0">0 — Só humanos</button>
          <button class="bot-btn active" data-bots="1">1 Bot</button>
          <button class="bot-btn" data-bots="2">2 Bots</button>
          <button class="bot-btn" data-bots="3">3 Bots</button>
        </div>
        <small style="color:#252542;font-size:.68rem;margin-top:4px">Slots vazios serão preenchidos por bots ao iniciar</small>
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
    <?php if(empty($rooms)): ?>
      <p class="room-empty">Nenhuma sala aberta agora</p>
    <?php else: ?>
      <div class="rooms-list">
        <?php foreach($rooms as $r):
          $humans = humanCount($r);
          $total  = count($r['players']);
        ?>
          <div class="room-item">
            <div class="room-info">
              <h4>Sala de <?= esc($r['players'][0]['name'] ?? '?') ?></h4>
              <small><?= $humans ?> humano<?= $humans!==1?'s':'' ?> · <?= $total ?>/4 slots · <?= timeAgo((int)$r['createdAt']) ?></small>
              <div class="color-dots">
                <?php foreach($r['players'] as $p):
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
let player = null;
function updateBadge() {
  const b = document.getElementById('player-badge');
  if (player?.username) {
    document.getElementById('badge-name').textContent = player.name + ' @' + player.username;
    b.style.display = 'block';
  }
}
PlayerLogin.init('#a855f7', p => { player = p; updateBadge(); });

const $ = id => document.getElementById(id);

$('card-create').addEventListener('click', () => { $('create-section').style.display='block'; $('join-section').style.display='none'; });
$('card-join').addEventListener('click',   () => { $('join-section').style.display='block'; $('create-section').style.display='none'; });
$('btn-cancel-create').addEventListener('click', () => $('create-section').style.display='none');
$('btn-cancel-join').addEventListener('click',   () => $('join-section').style.display='none');

let selBots = 1;
document.querySelectorAll('.bot-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.bot-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    selBots = parseInt(this.dataset.bots);
  });
});

$('btn-create').addEventListener('click', async () => {
  if (!player) { alert('Configure seu perfil primeiro'); return; }
  const res = await fetch('api.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'create', username:player.username, name:player.name, bots:selBots})
  }).then(r => r.json()).catch(() => null);
  if (!res?.roomId) {
    $('create-err').textContent = res?.error || 'Erro ao criar sala';
    $('create-err').style.display = 'block';
    return;
  }
  sessionStorage.setItem('ludo_token', res.token);
  sessionStorage.setItem('ludo_color', res.color);
  sessionStorage.setItem('ludo_slot',  res.slot);
  location.href = 'room.php?id=' + res.roomId;
});

$('btn-join').addEventListener('click', () => doJoin($('join-code').value.trim().toUpperCase()));
$('join-code').addEventListener('keydown', e => { if (e.key === 'Enter') $('btn-join').click(); });

async function doJoin(id) {
  if (!player) { alert('Configure seu perfil primeiro'); return; }
  if (!id) { $('join-err').textContent = 'Insira o código da sala'; $('join-err').style.display='block'; return; }
  const res = await fetch('api.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'join', roomId:id, username:player.username, name:player.name})
  }).then(r => r.json()).catch(() => null);
  if (!res?.token) {
    $('join-err').textContent = res?.error || 'Sala não encontrada';
    $('join-err').style.display = 'block';
    return;
  }
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
