<?php
$roomId = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['id'] ?? ''));
if (!$roomId) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..700&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ludo — Nicchon</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#060712;color:#fff;font-family:'Open Sans','Segoe UI',sans-serif;min-height:100vh}
    :root{--c:#a855f7}
    header{padding:20px 24px;border-bottom:1px solid #101328;display:flex;align-items:center;gap:16px}
    .back{font-size:.7rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;text-decoration:none;transition:color .15s}
    .back:hover{color:#888}
    .header-title{font-size:1.1rem;letter-spacing:2px;color:var(--c);text-shadow:0 0 14px #a855f744}
    .room-code{margin-left:auto;font-size:.7rem;letter-spacing:.5px;color:#333}
    .room-code strong{color:#555;letter-spacing:3px}

    /* ── LAYOUT ── */
    .game-wrap{display:flex;gap:16px;padding:20px 16px;max-width:1000px;margin:0 auto;align-items:flex-start}
    .board-area{flex:0 0 auto;display:flex;justify-content:center;align-items:center}
    .sidebar{flex:1;min-width:200px;max-width:260px;display:flex;flex-direction:column;gap:12px}

    /* ── SCREENS ── */
    .screen{display:none;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;text-align:center}
    .screen.active{display:flex}
    #screen-waiting{gap:20px}
    .waiting-code{font-size:3.2rem;letter-spacing:8px;color:var(--c);font-weight:700;text-shadow:0 0 30px #a855f766}
    .waiting-label{font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:#333}
    .waiting-slots{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin:8px 0}
    .slot-chip{padding:6px 14px;border-radius:20px;font-size:.78rem;border:1px solid #1c2035;color:#444}
    .slot-chip.filled{border-color:currentColor}
    .slot-chip.red{color:#ef4444;border-color:#ef444444}
    .slot-chip.blue{color:#3b82f6;border-color:#3b82f644}
    .slot-chip.green{color:#22c55e;border-color:#22c55e44}
    .slot-chip.yellow{color:#eab308;border-color:#eab30844}
    #screen-result{gap:16px}
    .result-box{background:#0b0d1e;border:1px solid #181b30;border-radius:12px;padding:28px 40px;max-width:360px;width:100%}
    .result-title{font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:#444;margin-bottom:16px}
    .rank-row{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #101328}
    .rank-row:last-child{border-bottom:none}
    .rank-num{font-size:1.2rem;font-weight:700;width:28px;color:#444}
    .rank-num.first{color:#fbbf24}
    .rank-num.second{color:#94a3b8}
    .rank-num.third{color:#b87333}
    .rank-name{font-size:.9rem;color:#ddd;flex:1}
    .rank-dot{width:14px;height:14px;border-radius:50%}

    /* ── SIDEBAR PANELS ── */
    .panel{background:#0b0d1e;border:1px solid #181b30;border-radius:8px;padding:12px 14px}
    .panel-title{font-size:.58rem;letter-spacing:2px;text-transform:uppercase;color:#2e3255;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;cursor:default}
    .panel-title.collapsible{cursor:pointer;user-select:none}
    .panel-title.collapsible:hover{color:#555}
    .panel-toggle{font-size:.7rem;color:#2e3255;transition:transform .2s}
    .panel-toggle.open{transform:rotate(180deg)}
    .panel-body{overflow:hidden;transition:max-height .25s ease}
    .panel-body.collapsed{max-height:0!important}
    .player-row{display:flex;align-items:center;gap:8px;padding:4px 0}
    .player-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .player-name{font-size:.8rem;color:#888;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .player-name.active{color:#e8e8f0;font-weight:600}
    .player-bot{font-size:.6rem;color:#444;margin-left:2px}

    /* Dice */
    .dice-panel{text-align:center}
    .dice-face{font-size:3.5rem;margin:8px 0;cursor:pointer;transition:transform .15s;display:inline-block;line-height:1}
    .dice-face:hover:not(.disabled){transform:scale(1.08)}
    .dice-face.disabled{cursor:default;opacity:.5}
    .dice-label{font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;color:#333}
    .btn{padding:8px 20px;border:none;border-radius:5px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:all .12s;display:inline-block}
    .btn-primary{background:var(--c);color:#fff}
    .btn-primary:hover:not(:disabled){filter:brightness(1.1)}
    .btn:disabled{opacity:.4;cursor:default}
    .btn-ghost{background:transparent;border:1px solid #1c2035;color:#555}
    .btn-ghost:hover{border-color:#444;color:#aaa}

    /* History */
    .history-list{max-height:120px;overflow-y:auto;display:flex;flex-direction:column;gap:3px}
    .history-item{font-size:.7rem;color:#333;line-height:1.3}
    .history-item span{color:#555}

    /* ── BOARD ── */
    .ludo-board{
      display:grid;
      grid-template-columns:repeat(15,1fr);
      grid-template-rows:repeat(15,1fr);
      width:min(calc(100vw - 300px),540px);
      aspect-ratio:1;
      min-width:280px;
      border:2px solid #181b30;
      border-radius:4px;
      background:#0b0d1e;
      position:relative;
    }
    .cell{
      position:relative;
      display:flex;
      align-items:center;
      justify-content:center;
      border:1px solid #101328;
    }
    /* Base corners */
    .cell.home-red   {background:#ef444418}
    .cell.home-blue  {background:#3b82f618}
    .cell.home-green {background:#22c55e18}
    .cell.home-yellow{background:#eab30818}
    /* Home inner circle areas */
    .cell.home-red.inner   {background:#ef444430}
    .cell.home-blue.inner  {background:#3b82f630}
    .cell.home-green.inner {background:#22c55e30}
    .cell.home-yellow.inner{background:#eab30830}
    /* Path cells */
    .cell.path{background:#101328}
    /* Colored path (entrance lanes) */
    .cell.path-red   {background:#ef444420}
    .cell.path-blue  {background:#3b82f620}
    .cell.path-green {background:#22c55e20}
    .cell.path-yellow{background:#eab30820}
    /* Safe star cells */
    .cell.safe::after{content:'★';position:absolute;font-size:.55rem;color:#a855f744;pointer-events:none}
    /* Center */
    .cell.center{background:linear-gradient(135deg,#ef444420,#3b82f620,#22c55e20,#eab30820)}
    /* Entry cells colored */
    .cell.entry-red   {background:#ef4444}
    .cell.entry-blue  {background:#3b82f6}
    .cell.entry-green {background:#22c55e}
    .cell.entry-yellow{background:#eab308}
    /* Clickable piece highlight */
    .cell.can-move{cursor:pointer}
    .cell.can-move::before{content:'';position:absolute;inset:3px;border-radius:3px;border:1.5px solid #a855f7;pointer-events:none;z-index:2}
    .cell.selected::before{content:'';position:absolute;inset:2px;border-radius:3px;border:2px solid #fff;pointer-events:none;z-index:2}

    /* Pieces */
    .pieces-in-cell{
      display:grid;
      grid-template-columns:1fr 1fr;
      grid-template-rows:1fr 1fr;
      gap:1px;
      width:80%;
      height:80%;
      position:relative;
      z-index:3;
    }
    .piece{
      border-radius:50%;
      border:1.5px solid rgba(255,255,255,.25);
      cursor:pointer;
      transition:transform .15s;
      position:relative;
    }
    .piece.red   {background:#ef4444}
    .piece.blue  {background:#3b82f6}
    .piece.green {background:#22c55e}
    .piece.yellow{background:#eab308}
    .piece.in-base{opacity:.55}
    .piece.finished{opacity:.3;cursor:default}
    .piece.selectable{box-shadow:0 0 0 2px #fff, 0 0 8px #a855f7;transform:scale(1.1);cursor:pointer}
    .piece.selected{box-shadow:0 0 0 3px #fff, 0 0 12px #fff;transform:scale(1.15)}

    /* Turn indicator */
    .turn-indicator{height:4px;border-radius:2px;margin-top:6px;transition:background .3s}

    /* Notification */
    .notif{position:fixed;top:80px;left:50%;transform:translateX(-50%);background:#0b0d1e;border:1px solid #a855f744;border-radius:8px;padding:10px 20px;font-size:.82rem;color:#ddd;opacity:0;transition:opacity .3s;pointer-events:none;z-index:100;text-align:center}
    .notif.show{opacity:1}

    @media(max-width:700px){
      .game-wrap{flex-direction:column;align-items:center;padding:12px 8px}
      .sidebar{max-width:100%;width:100%;flex-direction:row;flex-wrap:wrap}
      .panel{flex:1;min-width:140px}
      .ludo-board{width:min(calc(100vw - 24px),420px)}
    }
  </style>
</head>
<body>
<header>
  <a class="back" href="index.php">← Lobby</a>
  <span class="header-title">Ludo</span>
  <span class="room-code">Sala <strong><?= htmlspecialchars($roomId) ?></strong></span>
</header>

<!-- Waiting screen -->
<div id="screen-waiting" class="screen active" style="min-height:calc(100vh - 68px)">
  <p class="waiting-label">Código da sala</p>
  <div class="waiting-code"><?= htmlspecialchars($roomId) ?></div>
  <p style="color:#333;font-size:.78rem">Compartilhe este código com seus amigos</p>
  <div id="waiting-slots" class="waiting-slots"></div>
  <div id="waiting-actions" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center"></div>
  <p id="waiting-msg" style="color:#2e3255;font-size:.72rem"></p>
</div>

<!-- Game screen -->
<div id="screen-game" class="screen" style="padding:0">
  <div class="game-wrap">
    <div class="board-area">
      <div class="ludo-board" id="board"></div>
    </div>
    <div class="sidebar">
      <div class="panel">
        <div class="panel-title">Jogadores</div>
        <div id="players-panel"></div>
        <div class="turn-indicator" id="turn-bar"></div>
      </div>
      <div class="panel dice-panel" id="dice-panel">
        <div class="panel-title">Dado</div>
        <div id="dice-display" class="dice-face disabled">⚀</div>
        <div class="dice-label" id="dice-label">Aguardando...</div>
        <div style="margin-top:8px" id="dice-btn-wrap"></div>
      </div>
      <div class="panel" id="history-panel">
        <div class="panel-title collapsible" id="history-toggle">
          Histórico
          <span class="panel-toggle" id="history-arrow">▲</span>
        </div>
        <div class="panel-body collapsed" id="history-body" style="max-height:0">
          <div class="history-list" id="history-list" style="margin-top:8px"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Result screen -->
<div id="screen-result" class="screen" style="min-height:calc(100vh - 68px)">
  <div class="result-box">
    <div class="result-title">Resultado Final</div>
    <div id="ranking-list"></div>
  </div>
  <a href="index.php" class="btn btn-ghost" style="margin-top:16px">← Novo jogo</a>
</div>

<div class="notif" id="notif"></div>

<script>
// ══════════════════════════════════════════════════════════
//  CONFIG
// ══════════════════════════════════════════════════════════
const ROOM_ID = <?= json_encode($roomId) ?>;
const API     = 'api.php';
const COLORS  = ['red','blue','green','yellow'];
const COLOR_HEX = {red:'#ef4444',blue:'#3b82f6',green:'#22c55e',yellow:'#eab308'};
const DICE_FACES = ['⚀','⚁','⚂','⚃','⚄','⚅'];

// ── Path: 52 squares [row,col] in clockwise order ──────────
const PATH = [
  [6,1],[5,1],[4,1],[3,1],[2,1],[1,1],   // 0-5  left col going up
  [0,2],[0,3],[0,4],                      // 6-8  top-left
  [0,6],[0,7],[0,8],                      // 9-11 top-right (gap col5)
  [1,8],[2,8],[3,8],[4,8],[5,8],[6,8],    // 12-17 right col going down
  [6,9],[6,10],[6,11],[6,12],[6,13],[6,14],// 18-23 row6 going right
  [8,14],[8,13],[8,12],[8,11],[8,10],[8,9],// 24-29 row8 going left
  [8,8],[9,8],[10,8],[11,8],[12,8],[13,8], // 30-35 right col going down
  [14,8],[14,7],[14,6],                    // 36-38 bottom-right
  [13,6],[12,6],[11,6],[10,6],[9,6],[8,6], // 39-44 left col going up
  [8,5],[8,4],[8,3],[8,2],[8,1],[8,0],     // 45-50 row8 going left
  [6,0]                                    // 51 wrap-around connector
];

// ── Finish columns (pos 52-56 = 5 cells, pos 57 = center) ─
const FINISH_COLS = {
  red:    [[7,1],[7,2],[7,3],[7,4],[7,5],[7,6]],   // pos 52-56 (6 visual cells toward center)
  blue:   [[1,7],[2,7],[3,7],[4,7],[5,7],[6,7]],
  green:  [[7,13],[7,12],[7,11],[7,10],[7,9],[7,8]],
  yellow: [[13,7],[12,7],[11,7],[10,7],[9,7],[8,7]],
};
// coordOfPos uses pos 52-56 → FINISH_COLS[color][pos-52], pos 57 → [7,7]
// Entry point into finish column (path index that triggers it)
// Each color travels 51 steps from its entry before reaching finish column:
// red:0+51=51, blue:13+51%52=12, green:26+51%52=25, yellow:39+51%52=38
const FINISH_ENTRY = {red:51, blue:12, green:25, yellow:38};
// Home base cell definitions (3x3 inner boxes)
const HOME_CELLS = {
  red:    {rows:[9,10,11],cols:[0,1,2]},
  blue:   {rows:[0,1,2],  cols:[9,10,11]},
  green:  {rows:[9,10,11],cols:[12,13,14]},
  yellow: {rows:[0,1,2],  cols:[3,4,5]},  // NOTE: top-left = yellow, not shown by standard; adjusted below
};
// Safe squares on main path
const SAFE_SQUARES = new Set([0,8,13,21,26,34,39,47]);
// Entry squares (where each color enters the main path)
const ENTRY_SQUARES = {red:0, blue:13, green:26, yellow:39};

// ══════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════
let myToken    = sessionStorage.getItem('ludo_token') || '';
let myColor    = sessionStorage.getItem('ludo_color') || '';
let mySlot     = parseInt(sessionStorage.getItem('ludo_slot') || '0');
let room       = null;
let pollTimer  = null;
let selectedPiece = null; // {color, id}
let botThinking   = false;
let myIsOwner     = false;
let lastStatus    = '';
let lastTurn      = '';
let lastDiceRolled = false;

// ══════════════════════════════════════════════════════════
//  BOARD CONSTRUCTION
// ══════════════════════════════════════════════════════════
function buildBoard() {
  const board = document.getElementById('board');
  board.innerHTML = '';
  const cells = [];
  for (let r = 0; r < 15; r++) {
    cells[r] = [];
    for (let c = 0; c < 15; c++) {
      const cell = document.createElement('div');
      cell.className = 'cell';
      cell.dataset.r = r;
      cell.dataset.c = c;
      cell.style.gridRow = (r+1);
      cell.style.gridColumn = (c+1);
      classifyCell(cell, r, c);
      cell.addEventListener('click', () => onCellClick(r, c));
      board.appendChild(cell);
      cells[r][c] = cell;
    }
  }
  return cells;
}

function classifyCell(cell, r, c) {
  // 1. Center (only the exact center tile)
  if (r===7&&c===7) { cell.classList.add('center'); return; }
  // 2. Finish columns (colored lanes leading to center)
  if (r===7&&c>=1&&c<=6) { cell.classList.add('path','path-red');    return; }
  if (c===7&&r>=1&&r<=6) { cell.classList.add('path','path-blue');   return; }
  if (r===7&&c>=8&&c<=13){ cell.classList.add('path','path-green');  return; }
  if (c===7&&r>=8&&r<=13){ cell.classList.add('path','path-yellow'); return; }
  // 3. Main path (explicit coordinates take priority over home areas)
  const pathIdx = PATH.findIndex(([pr,pc]) => pr===r && pc===c);
  if (pathIdx >= 0) {
    cell.classList.add('path');
    if (SAFE_SQUARES.has(pathIdx)) cell.classList.add('safe');
    if (pathIdx===0)  cell.classList.add('entry-red');
    if (pathIdx===13) cell.classList.add('entry-blue');
    if (pathIdx===26) cell.classList.add('entry-green');
    if (pathIdx===39) cell.classList.add('entry-yellow');
    return;
  }
  // 4. Corridor rows/cols (cross arms not explicitly in PATH)
  if (r===6||r===7||r===8) { cell.classList.add('path'); return; }
  if (c===6||c===7||c===8) { cell.classList.add('path'); return; }
  // 5. Home bases (after path so path cells inside home are correctly classified)
  if (r>=9&&r<=13&&c>=0&&c<=5)  { cell.classList.add('home-red');    if(r>=10&&r<=12&&c>=1&&c<=4)cell.classList.add('inner'); return; }
  if (r>=0&&r<=5&&c>=9&&c<=14)  { cell.classList.add('home-blue');   if(r>=1&&r<=4&&c>=10&&c<=13)cell.classList.add('inner'); return; }
  if (r>=9&&r<=13&&c>=9&&c<=14) { cell.classList.add('home-green');  if(r>=10&&r<=12&&c>=10&&c<=13)cell.classList.add('inner'); return; }
  if (r>=0&&r<=5&&c>=0&&c<=5)   { cell.classList.add('home-yellow'); if(r>=1&&r<=4&&c>=1&&c<=4)cell.classList.add('inner'); return; }
}

let boardCells = [];

// ══════════════════════════════════════════════════════════
//  GAME ENGINE (CLIENT-SIDE)
// ══════════════════════════════════════════════════════════

function getPieces(state, color) {
  return state.pieces.filter(p => p.color === color);
}
function getPiece(state, color, id) {
  return state.pieces.find(p => p.color === color && p.id === id);
}

function coordOfPos(color, pos) {
  if (pos === -1) return null; // in base
  if (pos === 58) return [7,7]; // center (finished, pos 58)
  if (pos >= 52 && pos <= 57) return FINISH_COLS[color][pos-52]; // 6 finish cells
  if (pos >= 0 && pos < PATH.length) return PATH[pos];
  return null;
}

// Given current pos and dice, compute new pos. Returns null if invalid.
function computeNewPos(piece, dice, state) {
  const color = piece.color;

  if (piece.finished) return null;

  if (piece.pos === -1) {
    // Can only exit with 6
    if (dice !== 6) return null;
    // Check if entry square is blocked by own color (2+ pieces)
    const entry = ENTRY_SQUARES[color];
    if (isBlocked(state, color, entry)) return null;
    return entry;
  }

  const entry = FINISH_ENTRY[color];
  let newPos = piece.pos;

  // Step-by-step movement — when AT the FINISH_ENTRY square, next step enters finish col
  for (let step = 0; step < dice; step++) {
    if (newPos >= 52) {
      newPos++; // move along finish column or past center
    } else if (newPos === entry) {
      newPos = 52; // enter first finish column cell
    } else {
      newPos = (newPos + 1) % 52; // wrap around main path
    }
  }

  if (newPos > 58) return null; // overshoots center
  if (newPos >= 52 && newPos <= 57) {
    // In finish column — own color only, not blocked by 2+
    if (isBlockedFinish(state, color, newPos)) return null;
  }
  if (newPos < 52) {
    // On main path — check not blocked by 2+ opponent pieces
    if (isBlockedByOpponent(state, color, newPos)) return null;
  }
  return newPos;
}

function isBlocked(state, color, pos) {
  // 2+ pieces of same color = blocked for own pieces trying to enter
  const count = state.pieces.filter(p => p.color === color && p.pos === pos).length;
  return count >= 2;
}
function isBlockedFinish(state, color, pos) {
  return state.pieces.filter(p => p.color === color && p.pos === pos).length >= 2;
}
function isBlockedByOpponent(state, movingColor, pos) {
  // Opponent has 2+ pieces here = impassable barrier
  const opponents = state.pieces.filter(p => p.color !== movingColor && p.pos === pos);
  return opponents.length >= 2;
}
function isSafe(pos) {
  return pos >= 52 || SAFE_SQUARES.has(pos);
}

function getLegalMoves(state) {
  const color = state.turn;
  const dice  = state.dice;
  if (!dice || !state.diceRolled) return [];
  const moves = [];
  const myPieces = getPieces(state, color);
  for (const p of myPieces) {
    if (p.finished) continue;
    const newPos = computeNewPos(p, dice, state);
    if (newPos !== null) {
      moves.push({color, id:p.id, from:p.pos, to:newPos});
    }
  }
  return moves;
}

function applyMove(state, move) {
  const s = JSON.parse(JSON.stringify(state));
  const piece = getPiece(s, move.color, move.id);
  const dice = s.dice;

  piece.pos = move.to;
  if (move.to === 58) piece.finished = true; // reached center

  // Check if any finished colors updated
  const colorFinished = COLORS.filter(c =>
    s.pieces.filter(p => p.color === c).every(p => p.finished)
  );
  s.finishedColors = colorFinished;

  // Capture: if landed on opponent on main path (not safe, single opponent)
  let capturedAny = false;
  if (move.to >= 0 && move.to < 52 && !isSafe(move.to)) {
    const capturedPieces = s.pieces.filter(p => p.color !== move.color && p.pos === move.to && !p.finished);
    for (const cp of capturedPieces) {
      cp.pos = -1; // send back to base
      capturedAny = true;
    }
  }

  // Extra turn: rolling 6 gives extra turn (up to 2 consecutive sixes; 3rd = lose turn)
  let extraTurn = false;
  if (dice === 6) {
    s.consecutiveSixes = (s.consecutiveSixes || 0) + 1;
    if (s.consecutiveSixes >= 3) {
      s.consecutiveSixes = 0; // 3rd six: lose turn
      extraTurn = false;
    } else {
      extraTurn = true;
    }
  } else {
    s.consecutiveSixes = 0;
    if (capturedAny) extraTurn = true; // capture also grants extra turn
  }

  // Advance turn if no extra turn
  if (!extraTurn) s.turn = nextActiveColor(s, s.turn);

  s.diceRolled = false;
  s.dice = null;
  s.extraTurn = extraTurn;

  // Log
  s.moveHistory = s.moveHistory || [];
  s.moveHistory.push({color:move.color, from:move.from, to:move.to, dice});
  if (s.moveHistory.length > 30) s.moveHistory.shift();

  return s;
}

function nextActiveColor(state, current) {
  const active = room ? room.players.map(p => p.color) : COLORS;
  const idx = COLORS.indexOf(current);
  for (let i = 1; i <= 4; i++) {
    const next = COLORS[(idx + i) % 4];
    if (active.includes(next) && !(state.finishedColors||[]).includes(next)) return next;
  }
  return current;
}

function skipTurn(state) {
  const s = JSON.parse(JSON.stringify(state));
  s.consecutiveSixes = 0;
  s.turn = nextActiveColor(s, s.turn);
  s.diceRolled = false;
  s.dice = null;
  s.extraTurn = false;
  return s;
}

// ══════════════════════════════════════════════════════════
//  BOT STRATEGY
// ══════════════════════════════════════════════════════════
function botChooseMove(state) {
  const moves = getLegalMoves(state);
  if (moves.length === 0) return null;

  const color = state.turn;

  // Priority 1: capture
  for (const m of moves) {
    if (m.to >= 0 && m.to < 52 && !isSafe(m.to)) {
      const hasOpponent = state.pieces.some(p => p.color !== color && p.pos === m.to && !p.finished);
      if (hasOpponent) return m;
    }
  }
  // Priority 2: enter finish column if possible
  for (const m of moves) {
    if (m.to >= 52) return m;
  }
  // Priority 3: exit base
  for (const m of moves) {
    if (m.from === -1) return m;
  }
  // Priority 4: advance piece closest to center
  const ranked = moves.slice().sort((a, b) => {
    const distA = a.to === -1 ? 999 : (a.to >= 52 ? 56 - a.to : 52 - a.to);
    const distB = b.to === -1 ? 999 : (b.to >= 52 ? 56 - b.to : 52 - b.to);
    return distA - distB;
  });
  return ranked[0];
}

// ══════════════════════════════════════════════════════════
//  RENDERING
// ══════════════════════════════════════════════════════════
function renderBoard(state) {
  // Clear all piece containers
  boardCells.flat().forEach(cell => {
    const pw = cell.querySelector('.pieces-in-cell');
    if (pw) pw.remove();
    cell.classList.remove('can-move','selected');
  });

  if (!state) return;

  // Determine legal moves for current player
  const legalMoves = (state.diceRolled && state.turn === myColor) ? getLegalMoves(state) : [];
  const moveableIds = new Set(legalMoves.map(m => m.color + '_' + m.id));

  // Group pieces by grid position
  const byCell = {};
  for (const p of state.pieces) {
    if (p.pos === -1) {
      // In base — render in home area
      const homeKey = `home_${p.color}_${p.id}`;
      byCell[homeKey] = byCell[homeKey] || {isHome:true, color:p.color, pieces:[]};
      byCell[homeKey].pieces.push(p);
    } else {
      const coord = coordOfPos(p.color, p.pos);
      if (!coord) continue;
      const key = coord[0] + '_' + coord[1];
      byCell[key] = byCell[key] || {isHome:false, pieces:[]};
      byCell[key].pieces.push(p);
    }
  }

  // Render home pieces in base cells
  const HOME_POSITIONS = {
    red:    [[10,1],[10,2],[11,1],[11,2]],
    blue:   [[1,11],[1,12],[2,11],[2,12]],
    green:  [[10,11],[10,12],[11,11],[11,12]],
    yellow: [[1,2],[1,3],[2,2],[2,3]],
  };
  for (const [color, positions] of Object.entries(HOME_POSITIONS)) {
    const homePieces = state.pieces.filter(p => p.color === color && p.pos === -1);
    positions.forEach((pos, i) => {
      const cell = boardCells[pos[0]]?.[pos[1]];
      if (!cell) return;
      if (homePieces[i]) {
        const p = homePieces[i];
        const pw = document.createElement('div');
        pw.className = 'pieces-in-cell';
        pw.style.gridTemplateColumns = '1fr';
        pw.style.gridTemplateRows = '1fr';
        const el = makePieceEl(p, moveableIds);
        pw.appendChild(el);
        cell.appendChild(pw);
        if (moveableIds.has(p.color+'_'+p.id) && state.turn===myColor) {
          cell.classList.add('can-move');
        }
        if (selectedPiece?.color===p.color && selectedPiece?.id===p.id) {
          cell.classList.add('selected');
        }
      }
    });
  }

  // Render path/finish pieces
  for (const [key, info] of Object.entries(byCell)) {
    if (info.isHome) continue;
    const [r, c] = key.split('_').map(Number);
    const cell = boardCells[r]?.[c];
    if (!cell) continue;

    const pw = document.createElement('div');
    pw.className = 'pieces-in-cell';
    if (info.pieces.length === 1) {
      pw.style.gridTemplateColumns = '1fr';
      pw.style.gridTemplateRows = '1fr';
    }
    for (const p of info.pieces) {
      const el = makePieceEl(p, moveableIds);
      pw.appendChild(el);
    }
    cell.appendChild(pw);

    const hasMyMovable = info.pieces.some(p => moveableIds.has(p.color+'_'+p.id) && state.turn===myColor);
    if (hasMyMovable) cell.classList.add('can-move');
    if (info.pieces.some(p => selectedPiece?.color===p.color && selectedPiece?.id===p.id)) {
      cell.classList.add('selected');
    }
  }
}

function makePieceEl(p, moveableIds) {
  const el = document.createElement('div');
  el.className = `piece ${p.color}`;
  el.dataset.color = p.color;
  el.dataset.id = p.id;
  if (p.pos === -1) el.classList.add('in-base');
  if (p.finished) el.classList.add('finished');
  if (moveableIds.has(p.color+'_'+p.id) && room?.state?.turn===myColor) {
    el.classList.add('selectable');
  }
  if (selectedPiece?.color===p.color && selectedPiece?.id===p.id) {
    el.classList.add('selected');
  }
  el.addEventListener('click', e => { e.stopPropagation(); onPieceClick(p.color, p.id); });
  return el;
}

function onPieceClick(color, id) {
  if (!room || room.state.turn !== myColor || !room.state.diceRolled) return;
  const state = room.state;
  const moves = getLegalMoves(state);
  const canMove = moves.some(m => m.color===color && m.id===id);
  if (!canMove) return;
  selectedPiece = {color, id};
  renderBoard(state);
}

function onCellClick(r, c) {
  if (!selectedPiece || !room) return;
  const state = room.state;
  const moves = getLegalMoves(state);
  // Find move that lands on this cell
  const move = moves.find(m => {
    if (m.color !== selectedPiece.color || m.id !== selectedPiece.id) return false;
    const coord = coordOfPos(m.color, m.to);
    return coord && coord[0]===r && coord[1]===c;
  });
  if (move) {
    selectedPiece = null;
    doMove(move);
  } else {
    // Maybe they clicked a different own piece
    // Check if click is on a piece we can select
    for (const p of state.pieces) {
      const coord = coordOfPos(p.color, p.pos);
      if (coord && coord[0]===r && coord[1]===c) {
        onPieceClick(p.color, p.id);
        return;
      }
    }
    selectedPiece = null;
    renderBoard(state);
  }
}

// ══════════════════════════════════════════════════════════
//  SIDEBAR
// ══════════════════════════════════════════════════════════
function renderSidebar(state) {
  if (!room) return;

  // Players
  const pp = document.getElementById('players-panel');
  pp.innerHTML = '';
  for (const p of room.players) {
    const row = document.createElement('div');
    row.className = 'player-row';
    const isActive = state.turn === p.color;
    const dot = document.createElement('div');
    dot.className = 'player-dot';
    dot.style.background = COLOR_HEX[p.color];
    const name = document.createElement('div');
    name.className = 'player-name' + (isActive?' active':'');
    name.textContent = p.name + (p.isBot?' 🤖':'');
    if (state.finishedColors?.includes(p.color)) name.style.textDecoration='line-through';
    row.appendChild(dot);
    row.appendChild(name);
    pp.appendChild(row);
  }
  const bar = document.getElementById('turn-bar');
  bar.style.background = COLOR_HEX[state.turn] || '#333';

  // Dice
  const diceEl = document.getElementById('dice-display');
  const diceLabel = document.getElementById('dice-label');
  const diceBtnWrap = document.getElementById('dice-btn-wrap');
  diceBtnWrap.innerHTML = '';

  if (state.diceRolled && state.dice) {
    diceEl.textContent = DICE_FACES[state.dice - 1];
    diceEl.classList.add('disabled');
    if (state.turn === myColor) {
      diceLabel.textContent = 'Escolha uma peça';
      const skipBtn = document.createElement('button');
      skipBtn.className = 'btn btn-ghost';
      skipBtn.textContent = 'Passar turno';
      skipBtn.style.fontSize='.72rem';
      skipBtn.style.padding='5px 12px';
      const moves = getLegalMoves(state);
      if (moves.length > 0) skipBtn.style.display='none';
      skipBtn.addEventListener('click', doSkip);
      diceBtnWrap.appendChild(skipBtn);
    } else {
      diceLabel.textContent = 'Turno de ' + (room.players.find(p=>p.color===state.turn)?.name || state.turn);
    }
  } else {
    diceEl.textContent = '🎲';
    diceEl.classList.remove('disabled');
    if (state.turn === myColor) {
      diceLabel.textContent = 'Sua vez!';
      const rollBtn = document.createElement('button');
      rollBtn.className = 'btn btn-primary';
      rollBtn.textContent = 'Rolar dado';
      rollBtn.style.fontSize='.8rem';
      rollBtn.style.padding='6px 16px';
      rollBtn.addEventListener('click', doRoll);
      diceBtnWrap.appendChild(rollBtn);
    } else {
      const turnPlayer = room.players.find(p=>p.color===state.turn);
      diceLabel.textContent = 'Vez de ' + (turnPlayer?.name || state.turn);
    }
  }

  // History
  const hl = document.getElementById('history-list');
  hl.innerHTML = '';
  const history = (state.moveHistory || []).slice().reverse();
  for (const h of history.slice(0,12)) {
    const item = document.createElement('div');
    item.className = 'history-item';
    const colorName = {red:'Verm',blue:'Azul',green:'Verde',yellow:'Amar'}[h.color]||h.color;
    item.innerHTML = `<span style="color:${COLOR_HEX[h.color]}">${colorName}</span> ⚄${h.dice} pos${h.from}→${h.to}`;
    hl.appendChild(item);
  }
}

// ══════════════════════════════════════════════════════════
//  ACTIONS
// ══════════════════════════════════════════════════════════
async function doRoll() {
  if (!myToken) return;
  const res = await fetch(API, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'roll', roomId:ROOM_ID, token:myToken})
  }).then(r=>r.json()).catch(()=>null);
  if (!res?.ok) { notify(res?.error||'Erro ao rolar dado'); return; }
  // Update local state
  room.state.dice = res.dice;
  room.state.diceRolled = true;
  notify(DICE_FACES[res.dice-1] + '  Você tirou ' + res.dice + (res.dice===6?' — turno extra!':''));
  renderBoard(room.state);
  renderSidebar(room.state);
  // Check if no legal moves
  const moves = getLegalMoves(room.state);
  if (moves.length === 0) {
    setTimeout(() => {
      notify('Sem movimentos válidos — turno passado');
      doSkip();
    }, 1200);
  } else if (moves.length === 1) {
    // Auto-select single moveable piece
    setTimeout(() => {
      selectedPiece = {color:moves[0].color, id:moves[0].id};
      renderBoard(room.state);
    }, 200);
  }
}

async function doMove(move) {
  if (!myToken) return;
  const newState = applyMove(room.state, move);
  // Check for game over
  let result = null;
  const activePlayers = room.players.length;
  if (newState.finishedColors.length >= activePlayers) {
    result = {ranking: newState.finishedColors.concat(
      COLORS.filter(c => room.players.some(p=>p.color===c) && !newState.finishedColors.includes(c))
    )};
  }
  const body = {action:'move', roomId:ROOM_ID, token:myToken, state:newState};
  if (result) body.result = result;
  const res = await fetch(API, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  }).then(r=>r.json()).catch(()=>null);
  if (!res?.ok) { notify(res?.error||'Erro ao mover'); return; }
  room.state = newState;
  if (result) room.result = result;
  renderBoard(room.state);
  renderSidebar(room.state);
  if (result) showResult(room);
}

async function doSkip() {
  if (!myToken) return;
  const newState = skipTurn(room.state);
  const res = await fetch(API, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'skip', roomId:ROOM_ID, token:myToken, state:newState})
  }).then(r=>r.json()).catch(()=>null);
  if (!res?.ok) { notify(res?.error||'Erro'); return; }
  room.state = newState;
  renderBoard(room.state);
  renderSidebar(room.state);
}

// ── Bot loop ───────────────────────────────────────────────
async function runBot() {
  if (botThinking || !room || room.status!=='playing') return;
  if (!myIsOwner) return; // Only owner controls bots

  const state = room.state;
  const turnPlayer = room.players.find(p => p.color === state.turn);
  if (!turnPlayer?.isBot) return;

  botThinking = true;

  // Bot needs to roll
  if (!state.diceRolled) {
    await new Promise(r => setTimeout(r, 800));
    // Server-side roll using bot's empty token — bots use owner's token for roll
    const res = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'roll', roomId:ROOM_ID, token:myToken})
    }).then(r=>r.json()).catch(()=>null);
    if (!res?.ok) { botThinking=false; return; }
    room.state.dice = res.dice;
    room.state.diceRolled = true;
    renderBoard(room.state);
    renderSidebar(room.state);
    await new Promise(r => setTimeout(r, 800));
  }

  // Bot chooses and makes a move
  const move = botChooseMove(room.state);
  if (!move) {
    // No moves — skip
    await new Promise(r => setTimeout(r, 400));
    const newState = skipTurn(room.state);
    await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'skip', roomId:ROOM_ID, token:myToken, state:newState})
    }).then(r=>r.json()).catch(()=>null);
    room.state = newState;
  } else {
    await new Promise(r => setTimeout(r, 600));
    const newState = applyMove(room.state, move);
    let result = null;
    if (newState.finishedColors.length >= room.players.length) {
      result = {ranking: newState.finishedColors.concat(
        COLORS.filter(c => room.players.some(p=>p.color===c) && !newState.finishedColors.includes(c))
      )};
    }
    const body = {action:'move', roomId:ROOM_ID, token:myToken, state:newState};
    if (result) body.result = result;
    await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(body)
    }).then(r=>r.json()).catch(()=>null);
    room.state = newState;
    if (result) { room.result = result; showResult(room); botThinking=false; return; }
  }

  renderBoard(room.state);
  renderSidebar(room.state);
  botThinking = false;

  // If next turn is also bot, recurse after a delay
  const nextPlayer = room.players.find(p => p.color === room.state.turn);
  if (nextPlayer?.isBot) setTimeout(runBot, 400);
}

// ══════════════════════════════════════════════════════════
//  SCREEN MANAGEMENT
// ══════════════════════════════════════════════════════════
function showScreen(id) {
  ['screen-waiting','screen-game','screen-result'].forEach(s => {
    document.getElementById(s).classList.toggle('active', s===id);
    document.getElementById(s).style.display = s===id ? 'flex' : 'none';
  });
}

function renderWaiting(r) {
  const slotsEl = document.getElementById('waiting-slots');
  slotsEl.innerHTML = '';
  for (const p of r.players) {
    const chip = document.createElement('div');
    chip.className = `slot-chip ${p.color} filled`;
    chip.textContent = (p.isBot?'🤖 ':'')+p.name;
    slotsEl.appendChild(chip);
  }
  const actionsEl = document.getElementById('waiting-actions');
  actionsEl.innerHTML = '';
  const msgEl = document.getElementById('waiting-msg');

  if (myIsOwner) {
    const humanCount = r.players.filter(p=>!p.isBot).length;
    const canStart = r.players.length >= 2; // need at least 2 slots (human+bot or 2 humans)
    if (canStart) {
      const btn = document.createElement('button');
      btn.className = 'btn btn-primary';
      btn.textContent = 'Iniciar jogo';
      btn.addEventListener('click', doStart);
      actionsEl.appendChild(btn);
      msgEl.textContent = '';
    } else {
      msgEl.textContent = 'Adicione bots ou aguarde jogadores...';
    }
  } else {
    msgEl.textContent = 'Aguardando o criador iniciar...';
  }
}

function showResult(r) {
  const ranking = r.result?.ranking || [];
  const list = document.getElementById('ranking-list');
  list.innerHTML = '';
  const medals = ['first','second','third',''];
  ranking.forEach((color, i) => {
    const player = r.players.find(p => p.color === color);
    const row = document.createElement('div');
    row.className = 'rank-row';
    row.innerHTML = `
      <div class="rank-num ${medals[i]||''}">${['🥇','🥈','🥉','4º'][i]||i+1+'º'}</div>
      <div class="rank-name">${player?.name||color}</div>
      <div class="rank-dot" style="background:${COLOR_HEX[color]}"></div>
    `;
    list.appendChild(row);
  });
  showScreen('screen-result');
}

// ══════════════════════════════════════════════════════════
//  SERVER ACTIONS
// ══════════════════════════════════════════════════════════
async function doStart() {
  const res = await fetch(API, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'start', roomId:ROOM_ID, token:myToken})
  }).then(r=>r.json()).catch(()=>null);
  if (!res?.ok) notify(res?.error||'Erro ao iniciar');
}

// ══════════════════════════════════════════════════════════
//  POLLING
// ══════════════════════════════════════════════════════════
async function poll() {
  const url = `${API}?action=poll&roomId=${ROOM_ID}&token=${encodeURIComponent(myToken)}`;
  const data = await fetch(url).then(r=>r.json()).catch(()=>null);
  if (!data || data.error) return;

  const prevStatus = room?.status;
  const prevTurn = room?.state?.turn;
  const prevDiceRolled = room?.state?.diceRolled;
  room = data;

  // Determine owner
  myIsOwner = room.players.some(p => !p.isBot && p.color === myColor && p.slot === 0);

  if (data.status === 'waiting') {
    if (lastStatus !== 'waiting') { showScreen('screen-waiting'); lastStatus='waiting'; }
    renderWaiting(room);
    return;
  }

  if (data.status === 'finished') {
    if (pollTimer) { clearInterval(pollTimer); pollTimer=null; }
    showResult(room);
    return;
  }

  // Playing
  if (lastStatus !== 'playing') {
    showScreen('screen-game');
    lastStatus = 'playing';
    boardCells = buildBoard();
  }

  // Only re-render board on state change to avoid flicker
  if (prevTurn !== data.state.turn || prevDiceRolled !== data.state.diceRolled) {
    selectedPiece = null;
  }
  renderBoard(data.state);
  renderSidebar(data.state);

  // Notify turn change
  if (prevTurn && prevTurn !== data.state.turn && data.state.turn === myColor) {
    notify('Sua vez!');
  }

  // Run bot if needed
  if (myIsOwner && !botThinking) {
    const turnPlayer = room.players.find(p=>p.color===data.state.turn);
    if (turnPlayer?.isBot) setTimeout(runBot, 300);
  }
}

// ══════════════════════════════════════════════════════════
//  NOTIFICATIONS
// ══════════════════════════════════════════════════════════
let notifTimer = null;
function notify(msg) {
  const el = document.getElementById('notif');
  el.textContent = msg;
  el.classList.add('show');
  if (notifTimer) clearTimeout(notifTimer);
  notifTimer = setTimeout(() => el.classList.remove('show'), 2800);
}

// ══════════════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════════════
// If no token (spectator or direct link), still allow watching
if (!myToken) {
  // Check if we're in the room from query param fallback
  myColor = '';
  mySlot = -1;
}

// History toggle
(function() {
  const toggle = document.getElementById('history-toggle');
  const body   = document.getElementById('history-body');
  const arrow  = document.getElementById('history-arrow');
  toggle.addEventListener('click', () => {
    const isCollapsed = body.classList.contains('collapsed');
    if (isCollapsed) {
      body.classList.remove('collapsed');
      body.style.maxHeight = '160px';
      arrow.classList.add('open');
    } else {
      body.classList.add('collapsed');
      body.style.maxHeight = '0';
      arrow.classList.remove('open');
    }
  });
})();

// Initial board build (for game screen)
boardCells = buildBoard();
showScreen('screen-waiting');

// Start polling
poll();
pollTimer = setInterval(poll, 1500);
</script>
</body>
</html>
