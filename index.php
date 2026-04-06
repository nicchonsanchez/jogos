<?php
// ── Lê os jogos automaticamente das subpastas ──────────────────────────────
// Para cada jogo novo, basta criar uma pasta com index.html e um game.json.

$IGNORE = ['tarefas-md', 'rooms'];   // pastas que não são jogos

$games = [];
foreach (glob(__DIR__ . '/*/') as $dir) {
    $slug = basename($dir);
    if (in_array($slug, $IGNORE)) continue;
    if (!file_exists($dir . 'index.html') && !file_exists($dir . 'index.php')) continue;

    $meta = [
        'name'  => ucfirst($slug),
        'desc'  => '',
        'icon'  => 'default',
        'color' => '#4ade80',
        'slug'  => $slug,
    ];
    if (file_exists($dir . 'game.json')) {
        $json = json_decode(file_get_contents($dir . 'game.json'), true);
        if (is_array($json)) $meta = array_merge($meta, $json);
    }
    $meta['slug'] = $slug;
    $games[] = $meta;
}

// ── Ícones SVG inline por nome ─────────────────���───────────────────────────
function gameIcon(string $icon): string {
    $icons = [
        'snake' => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="6"  y="18" width="8" height="8" rx="2" fill="currentColor"/>
            <rect x="14" y="18" width="8" height="8" rx="2" fill="currentColor" opacity=".75"/>
            <rect x="22" y="18" width="8" height="8" rx="2" fill="currentColor" opacity=".5"/>
            <rect x="22" y="10" width="8" height="8" rx="2" fill="currentColor" opacity=".5"/>
            <rect x="22" y="26" width="8" height="8" rx="2" fill="currentColor" opacity=".3"/>
            <circle cx="8" cy="20" r="1.5" fill="#0a0a15"/>
            <circle cx="10" cy="20" r="1.5" fill="#0a0a15"/>
        </svg>',
        'chess' => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="6" y="30" width="28" height="4" rx="1" fill="currentColor" opacity=".9"/>
            <rect x="10" y="26" width="20" height="4" rx="1" fill="currentColor" opacity=".8"/>
            <rect x="14" y="14" width="12" height="12" rx="1" fill="currentColor" opacity=".7"/>
            <rect x="17" y="8"  width="6"  height="6"  rx="1" fill="currentColor"/>
            <rect x="15" y="6"  width="10" height="3"  rx="1" fill="currentColor"/>
        </svg>',
        'damas' => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="6" fill="currentColor" opacity=".9"/>
            <circle cx="28" cy="12" r="6" fill="currentColor" opacity=".5"/>
            <circle cx="12" cy="28" r="6" fill="currentColor" opacity=".5"/>
            <circle cx="28" cy="28" r="6" fill="currentColor" opacity=".9"/>
            <circle cx="12" cy="12" r="3" fill="currentColor"/>
            <circle cx="28" cy="28" r="3" fill="currentColor"/>
        </svg>',
        'ludo' => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="4"  y="4"  width="14" height="14" rx="3" fill="currentColor" opacity=".9"/>
            <rect x="22" y="4"  width="14" height="14" rx="3" fill="currentColor" opacity=".5"/>
            <rect x="4"  y="22" width="14" height="14" rx="3" fill="currentColor" opacity=".5"/>
            <rect x="22" y="22" width="14" height="14" rx="3" fill="currentColor" opacity=".9"/>
            <circle cx="11" cy="11" r="3.5" fill="#080812"/>
            <circle cx="29" cy="29" r="3.5" fill="#080812"/>
            <rect x="16" y="16" width="8" height="8" rx="1" fill="currentColor"/>
        </svg>',
        'default' => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="8" width="24" height="24" rx="4" stroke="currentColor" stroke-width="2"/>
            <circle cx="20" cy="20" r="5" fill="currentColor"/>
        </svg>',
    ];
    return $icons[$icon] ?? $icons['default'];
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Jogos — Nicchon</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      background: #080812;
      color: #fff;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
    }

    /* ── Header ── */
    header {
      padding: 40px 32px 32px;
      border-bottom: 1px solid #111128;
    }
    .header-inner {
      max-width: 900px;
      margin: 0 auto;
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .site-back {
      font-size: 0.72rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: #333;
      text-decoration: none;
      transition: color 0.15s;
    }
    .site-back:hover { color: #888; }
    .site-back span { margin-right: 6px; }

    h1 {
      font-size: 2rem;
      letter-spacing: 4px;
      color: #fff;
      margin-top: 12px;
    }
    h1 em {
      color: #4ade80;
      font-style: normal;
      text-shadow: 0 0 24px #4ade8044;
    }
    .subtitle {
      color: #333;
      font-size: 0.78rem;
      letter-spacing: 1px;
      margin-top: 6px;
    }

    /* ── Grid de jogos ── */
    main {
      max-width: 900px;
      margin: 0 auto;
      padding: 40px 32px 60px;
    }

    .section-label {
      font-size: 0.65rem;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #2a2a48;
      margin-bottom: 20px;
    }

    .games-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 16px;
    }

    /* ── Card ── */
    .game-card {
      background: #0e0e1e;
      border: 1px solid #181830;
      border-radius: 12px;
      overflow: hidden;
      text-decoration: none;
      display: flex;
      flex-direction: column;
      transition: border-color 0.18s, transform 0.15s, box-shadow 0.18s;
      position: relative;
    }
    .game-card:hover {
      border-color: var(--c);
      transform: translateY(-3px);
      box-shadow: 0 8px 32px color-mix(in srgb, var(--c) 12%, transparent);
    }

    .card-accent {
      height: 3px;
      background: var(--c);
      opacity: 0.7;
    }

    .card-body {
      padding: 20px 20px 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .card-icon {
      width: 40px;
      height: 40px;
      color: var(--c);
    }

    .card-name {
      font-size: 1.1rem;
      font-weight: 600;
      color: #e8e8f0;
      letter-spacing: 0.5px;
    }

    .card-desc {
      font-size: 0.78rem;
      color: #444;
      line-height: 1.5;
      flex: 1;
    }

    .card-footer {
      padding: 12px 20px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .play-btn {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--c);
      background: color-mix(in srgb, var(--c) 10%, transparent);
      border: 1px solid color-mix(in srgb, var(--c) 30%, transparent);
      border-radius: 5px;
      padding: 6px 16px;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.12s, border-color 0.12s;
    }
    .play-btn:hover {
      background: color-mix(in srgb, var(--c) 20%, transparent);
      border-color: color-mix(in srgb, var(--c) 60%, transparent);
    }

    /* ── Vazio ── */
    .no-games {
      color: #2a2a48;
      font-size: 0.88rem;
      padding: 48px 0;
      text-align: center;
    }

    /* ── Footer ── */
    footer {
      border-top: 1px solid #111128;
      text-align: center;
      padding: 20px;
      font-size: 0.65rem;
      letter-spacing: 1px;
      color: #1e1e38;
    }

    /* ── Responsivo ── */
    @media (max-width: 500px) {
      header { padding: 28px 20px 24px; }
      main   { padding: 28px 20px 48px; }
      h1     { font-size: 1.5rem; }
      .games-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<header>
  <div class="header-inner">
    <div>
      <a class="site-back" href="https://nicchon.com">
        <span>&#8592;</span> nicchon.com
      </a>
      <h1>JOGOS <em>//</em></h1>
      <p class="subtitle">
        <?= count($games) ?> jogo<?= count($games) !== 1 ? 's' : '' ?> disponível<?= count($games) !== 1 ? 'is' : '' ?>
      </p>
    </div>
  </div>
</header>

<main>
  <?php if (empty($games)): ?>
    <p class="no-games">Nenhum jogo encontrado ainda. Em breve!</p>
  <?php else: ?>
    <p class="section-label">Escolha um jogo</p>
    <div class="games-grid">
      <?php foreach ($games as $g): ?>
        <?php $color = esc($g['color']); ?>
        <a class="game-card" href="<?= esc($g['slug']) ?>/" style="--c: <?= $color ?>">
          <div class="card-accent"></div>
          <div class="card-body">
            <div class="card-icon"><?= gameIcon($g['icon']) ?></div>
            <div class="card-name"><?= esc($g['name']) ?></div>
            <?php if ($g['desc']): ?>
              <div class="card-desc"><?= esc($g['desc']) ?></div>
            <?php endif; ?>
          </div>
          <div class="card-footer">
            <span class="play-btn">Jogar</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<footer>NICCHON.COM &nbsp;/&nbsp; JOGOS</footer>

</body>
</html>
