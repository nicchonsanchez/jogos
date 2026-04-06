<?php
/**
 * shared/header.php — Cabeçalho padrão de todos os jogos
 *
 * Defina antes do include:
 *   string      $accent    — cor hex, ex: '#f59e0b'
 *   string      $title     — nome do jogo, ex: 'XADREZ'
 *   string      $subtitle  — descrição curta
 *   string      $backHref  — ex: '../' ou 'index.php'
 *   string      $backLabel — ex: 'Jogos' ou 'Lobby'
 *   string|null $roomId    — código da sala (só em rooms)
 */
$backHref  = $backHref  ?? '../';
$backLabel = $backLabel ?? 'Jogos';
$roomId    = $roomId    ?? null;

function _icon(string $name, int $size = 14): string {
    $paths = [
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'copy'  => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        'check' => '<polyline points="20 6 9 12 4 10"/>',
    ];
    $p = $paths[$name] ?? '';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'>{$p}</svg>";
}
?>
<header class="gm-header">
  <div class="gm-header-inner">

    <div class="gm-header-left">
      <a class="gm-back" href="<?= esc($backHref) ?>">
        <?= _icon('chevron-left', 13) ?>
        <?= esc($backLabel) ?>
      </a>
      <div class="gm-sep"></div>
      <div class="gm-game-info">
        <div class="gm-title"><?= esc($title) ?></div>
        <?php if (!empty($subtitle)): ?>
          <div class="gm-subtitle"><?= esc($subtitle) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="gm-header-right">
      <?php if ($roomId): ?>
      <div class="gm-room-badge">
        <span class="gm-room-label">Sala</span>
        <span class="gm-room-code"><?= esc($roomId) ?></span>
        <button class="gm-copy-btn" id="gm-copy-btn" title="Copiar código da sala">
          <?= _icon('copy', 13) ?>
        </button>
      </div>
      <script>
      document.getElementById('gm-copy-btn').addEventListener('click', function() {
        const code = '<?= esc($roomId) ?>';
        const icon_copy  = `<?= _icon('copy', 13) ?>`;
        const icon_check = `<?= _icon('check', 13) ?>`;
        navigator.clipboard.writeText(code).then(() => {
          this.innerHTML = icon_check;
          this.style.color = '#4ade80';
          setTimeout(() => { this.innerHTML = icon_copy; this.style.color = ''; }, 1600);
        });
      });
      </script>
      <?php endif; ?>

      <div id="gm-player-badge" class="gm-player-badge"
           onclick="PlayerLogin.showSwitch('<?= esc($accent) ?>', p => { player=p; updateBadge(); })">
        <span id="gm-badge-name" class="gm-badge-name"></span>
        <span class="gm-badge-sub">trocar conta</span>
      </div>
    </div>

  </div>
</header>
