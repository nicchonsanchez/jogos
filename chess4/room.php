<?php
$roomId    = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['id'] ?? ''));
$isSolo    = isset($_GET['solo']);
$paramSlot = max(0, min(3, (int)($_GET['slot'] ?? 0)));
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
include '../shared/icons.php';
$accent    = '#8b5cf6';
$title     = 'XADREZ 4';
$subtitle  = $isSolo ? 'Solo vs Bots' : 'Multiplayer 4 Jogadores';
$backHref  = 'index.php';
$backLabel = 'Lobby';
$roomId    = $roomId ?: null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xadrez 4 — Partida</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../shared/games.css">
  <style>
    :root { --c: #8b5cf6; }
    body { display:flex; flex-direction:column; align-items:center; gap:12px; padding:0 0 32px; }

    /* ── Telas ── */
    .screen { display:none; flex-direction:column; align-items:center; gap:14px; width:100%; }
    .screen.active { display:flex; }

    /* ── Waiting ── */
    .room-code { font-size:2.4rem; font-weight:900; letter-spacing:8px; color:#8b5cf6;
      text-shadow:0 0 30px #8b5cf655; background:#0b0d1e; border:1px solid #8b5cf633;
      border-radius:10px; padding:14px 28px; }
    .copy-btn { background:transparent; border:1px solid #1c2035; border-radius:4px;
      color:#555; font-size:.72rem; padding:5px 14px; cursor:pointer; font-family:inherit; transition:all .12s; }
    .copy-btn:hover { border-color:#8b5cf644; color:#8b5cf6; }
    .wait-msg { color:#444; font-size:.82rem; animation:blink 1.4s infinite; }
    @keyframes blink { 0%,100%{opacity:.4}50%{opacity:1} }
    .wait-slots { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; }
    .wait-slot { display:flex; align-items:center; gap:8px; background:#0b0d1e;
      border:1px solid #181b30; border-radius:8px; padding:10px 16px; font-size:.8rem; }
    .wait-dot { width:11px; height:11px; border-radius:50%; }

    /* ── Layout do jogo ── */
    .game-wrap { display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;
      justify-content:center; width:100%; padding:0 12px; max-width:900px; }

    /* ── Tabuleiro ── */
    #board {
      display:grid;
      grid-template-columns: repeat(14, 1fr);
      width: min(616px, calc(100vw - 28px));
      aspect-ratio: 1;
      user-select: none;
      flex-shrink: 0;
    }
    .cell {
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; position:relative;
      font-size: clamp(11px, 3.6vw, 28px);
      aspect-ratio: 1;
      transition: background .08s;
    }
    .cell.invalid { background: #060712; cursor:default; pointer-events:none; }
    .cell.light   { background: #d9b382; }
    .cell.dark    { background: #9b6840; }

    /* Áreas dos jogadores — destaque sutil */
    .cell.zone-R  { background: #4a1010; }
    .cell.zone-R.light { background: #6b2020; }
    .cell.zone-U  { background: #0e1a3a; }
    .cell.zone-U.light { background: #172450; }
    .cell.zone-Y  { background: #3a3000; }
    .cell.zone-Y.light { background: #504200; }
    .cell.zone-G  { background: #0a2e10; }
    .cell.zone-G.light { background: #123e18; }

    .cell.selected   { background: #8b5cf699 !important; }
    .cell.hint::after {
      content:''; position:absolute; width:38%; height:38%;
      border-radius:50%; background:rgba(74,222,128,.55); pointer-events:none;
    }
    .cell.hint-cap   { background: rgba(248,113,113,.45) !important; }
    .cell.last-from  { background: rgba(139,92,246,.2) !important; }
    .cell.last-to    { background: rgba(139,92,246,.38) !important; }

    /* Peças */
    .piece { line-height:1; pointer-events:none; }
    .piece-R { color:#f87171; filter:drop-shadow(0 0 3px #ef444488); }
    .piece-U { color:#93c5fd; filter:drop-shadow(0 0 3px #3b82f688); }
    .piece-Y { color:#fde047; filter:drop-shadow(0 0 3px #eab30888); }
    .piece-G { color:#86efac; filter:drop-shadow(0 0 3px #22c55e88); }

    /* ── Sidebar ── */
    .sidebar { display:flex; flex-direction:column; gap:10px; min-width:170px; max-width:210px; flex:1; }
    .panel { background:#0b0d1e; border:1px solid #181b30; border-radius:8px; padding:12px 14px; }
    .panel-title { font-size:.58rem; letter-spacing:1.5px; text-transform:uppercase; color:#333; margin-bottom:8px; }

    .player-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #101328; }
    .player-row:last-child { border-bottom:none; }
    .player-row.eliminated { opacity:.35; }
    .player-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .player-name { font-size:.8rem; color:#aaa; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .player-name.active { color:#fff; font-weight:700; }
    .player-tag { font-size:.62rem; color:#555; margin-left:2px; }
    .turn-arrow { font-size:.7rem; color:#8b5cf6; }

    .move-log { font-size:.65rem; color:#555; max-height:140px; overflow-y:auto;
      display:flex; flex-direction:column; gap:2px; font-family:monospace; }
    .log-entry { color:#666; }
    .log-entry:last-child { color:#aaa; }

    .btn { padding:8px 16px; border:none; border-radius:5px; font-family:inherit;
      font-size:.8rem; font-weight:700; cursor:pointer; letter-spacing:.5px; transition:all .12s; width:100%; }
    .btn-resign { background:transparent; border:1px solid #f8717133; color:#f87171; }
    .btn-resign:hover { background:#f8717118; }
    .btn-primary { background:var(--c); color:#fff; margin-top:4px; }
    .btn-primary:hover { filter:brightness(1.1); }
    .btn-ghost { background:transparent; border:1px solid #1c2035; color:#555; }
    .btn-ghost:hover { color:#fff; border-color:#333; }
    .status-msg { font-size:.76rem; color:#777; text-align:center; margin-bottom:6px; }

    /* ── Resultado ── */
    .result-box { background:#0b0d1e; border:1px solid #181b30; border-radius:12px;
      padding:32px; text-align:center; display:flex; flex-direction:column;
      align-items:center; gap:14px; max-width:340px; }
    .result-title { font-size:1.4rem; font-weight:700; letter-spacing:3px; }
    .result-sub { font-size:.82rem; color:#555; }

    /* ── Promoção ── */
    #promo-dialog { position:fixed; inset:0; background:rgba(6,7,18,.92);
      display:none; align-items:center; justify-content:center; z-index:100; }
    #promo-dialog.open { display:flex; }
    .promo-box { background:#0b0d1e; border:1px solid #2e3255; border-radius:10px;
      padding:24px; display:flex; flex-direction:column; align-items:center; gap:14px; }
    .promo-box h3 { color:#8b5cf6; font-size:.88rem; letter-spacing:1px; }
    .promo-pieces { display:flex; gap:10px; }
    .promo-btn { width:60px; height:60px; background:#0a0a16; border:1px solid #1c2035;
      border-radius:8px; font-size:2rem; cursor:pointer; display:flex; align-items:center;
      justify-content:center; transition:border-color .12s; }
    .promo-btn:hover { border-color:#8b5cf655; }

    @media(max-width:680px) {
      .sidebar { min-width:unset; width:100%; max-width:500px; flex-direction:row; flex-wrap:wrap; }
      .panel { flex:1; min-width:140px; }
    }
  </style>
  <script src="../shared/player.js"></script>
</head>
<body style="padding-top:0">
<?php include '../shared/header.php'; ?>

<div style="width:100%;max-width:900px;display:flex;flex-direction:column;align-items:center;gap:14px;margin:0 auto;padding:14px 0 32px">

<!-- Tela: Aguardando -->
<div class="screen" id="screen-waiting">
  <p style="color:#555;font-size:.78rem">Sala criada! Compartilhe o código:</p>
  <div class="room-code" id="display-code"><?= esc($roomId ?? '') ?></div>
  <button class="copy-btn" id="copy-code-btn">Copiar código</button>
  <p class="wait-msg">Aguardando jogadores...</p>
  <div class="wait-slots" id="wait-slots"></div>
</div>

<!-- Tela: Jogo -->
<div class="screen" id="screen-game">
  <div class="game-wrap">
    <div id="board"></div>
    <div class="sidebar">
      <div class="panel">
        <div class="panel-title">Jogadores</div>
        <div id="player-list"></div>
      </div>
      <div class="panel">
        <div class="panel-title">Histórico</div>
        <div class="move-log" id="move-log"></div>
      </div>
      <div class="panel">
        <p class="status-msg" id="status-msg"></p>
        <button class="btn btn-resign" id="btn-resign">Desistir</button>
      </div>
    </div>
  </div>
</div>

<!-- Tela: Resultado -->
<div class="screen" id="screen-result">
  <div class="result-box">
    <div style="font-size:2.5rem" id="result-emoji"></div>
    <div class="result-title" id="result-title"></div>
    <div class="result-sub" id="result-sub"></div>
    <button class="btn btn-primary" onclick="location.href='index.php'">Jogar novamente</button>
  </div>
</div>

</div><!-- /wrapper -->

<!-- Promoção -->
<div id="promo-dialog">
  <div class="promo-box">
    <h3>PROMOÇÃO</h3>
    <div class="promo-pieces" id="promo-pieces"></div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
// Config
// ═══════════════════════════════════════════════════════════════
const ROOM_ID   = '<?= esc($roomId ?? '') ?>';
const SOLO_MODE = <?= $isSolo ? 'true' : 'false' ?>;
const SOLO_SLOT = <?= $paramSlot ?>;
const API       = 'api.php';

let myToken = sessionStorage.getItem('chess4_token') || '';
let mySlot  = SOLO_MODE ? SOLO_SLOT : parseInt(sessionStorage.getItem('chess4_slot') ?? '-1');
let player  = null;
try { player = JSON.parse(localStorage.getItem('snake_user')); } catch(e) {}

let pollTimer = null;
let roomData  = null;

// ═══════════════════════════════════════════════════════════════
// Constantes do jogo
// ═══════════════════════════════════════════════════════════════
const SYMBOLS = { k:'♚', q:'♛', r:'♜', b:'♝', n:'♞', p:'♟' };
const COLOR_NAMES = { R:'Vermelho', U:'Azul', Y:'Amarelo', G:'Verde' };
const COLOR_HEX   = { R:'#f87171', U:'#93c5fd', Y:'#fde047', G:'#86efac' };
const SLOT_COLORS = ['R','U','Y','G'];  // índice do slot → letra da cor
const PIECE_NAMES = { r:'Torre', n:'Cavalo', b:'Bispo', q:'Rainha', k:'Rei', p:'Peão' };

// Direções de avanço do peão por slot
const PAWN_FWD = [[-1,0],[0,1],[1,0],[0,-1]]; // R=cima, U=direita, Y=baixo, G=esquerda
// Diagonais de captura do peão por slot
const PAWN_CAP = [
  [[-1,-1],[-1,1]],  // R
  [[-1,1],[1,1]],    // U
  [[1,-1],[1,1]],    // Y
  [[-1,-1],[1,-1]],  // G
];
// Linha/coluna de promoção
function isPromotionSquare(r, c, slot) {
  return (slot===0&&r===0)||(slot===1&&c===13)||(slot===2&&r===13)||(slot===3&&c===0);
}
// Linha/coluna de início do peão (duplo avanço)
function isPawnStart(r, c, slot) {
  return (slot===0&&r===12)||(slot===1&&c===1)||(slot===2&&r===1)||(slot===3&&c===12);
}

// ═══════════════════════════════════════════════════════════════
// Tabuleiro
// ═══════════════════════════════════════════════════════════════
function isValid(r, c) {
  if (r<0||r>13||c<0||c>13) return false;
  if (r<=2&&c<=2) return false;
  if (r<=2&&c>=11) return false;
  if (r>=11&&c<=2) return false;
  if (r>=11&&c>=11) return false;
  return true;
}

function initBoard() {
  const b = Array.from({length:14}, () => Array(14).fill(null));
  for (let r=0;r<14;r++)
    for (let c=0;c<14;c++)
      if (isValid(r,c)) b[r][c] = '';

  // Vermelho (R): linha 13 peças, linha 12 peões — cols 3-10
  const bk = ['r','n','b','q','k','b','n','r'];
  for (let i=0;i<8;i++) { b[13][3+i]='R'+bk[i]; b[12][3+i]='Rp'; }

  // Azul (U): col 0 peças, col 1 peões — linhas 3-10
  const bkU = ['r','n','b','k','q','b','n','r'];
  for (let i=0;i<8;i++) { b[3+i][0]='U'+bkU[i]; b[3+i][1]='Up'; }

  // Amarelo (Y): linha 0 peças, linha 1 peões — cols 3-10
  const bkY = ['r','n','b','k','q','b','n','r'];
  for (let i=0;i<8;i++) { b[0][3+i]='Y'+bkY[i]; b[1][3+i]='Yp'; }

  // Verde (G): col 13 peças, col 12 peões — linhas 3-10
  // Mesma ordem que api.php: Gr,Gn,Gb,Gq,Gk,Gb,Gn,Gr (linhas 3-10)
  const bkG = ['r','n','b','q','k','b','n','r'];
  for (let i=0;i<8;i++) { b[3+i][13]='G'+bkG[i]; b[3+i][12]='Gp'; }

  return b;
}

function copyBoard(b) { return b.map(row => [...row]); }

// ═══════════════════════════════════════════════════════════════
// Geração de movimentos
// ═══════════════════════════════════════════════════════════════
function sliding(b, r, c, color, dirs) {
  const moves = [];
  for (const [dr,dc] of dirs) {
    let nr=r+dr, nc=c+dc;
    while (isValid(nr,nc)) {
      const t = b[nr][nc];
      if (t === '') { moves.push([nr,nc]); }
      else { if (t[0] !== color) moves.push([nr,nc]); break; }
      nr+=dr; nc+=dc;
    }
  }
  return moves;
}

function getRawMoves(b, r, c) {
  const piece = b[r][c];
  if (!piece || piece==='') return [];
  const color = piece[0];
  const type  = piece[1];
  const slot  = SLOT_COLORS.indexOf(color);
  const moves = [];

  switch(type) {
    case 'p': {
      const [dr,dc] = PAWN_FWD[slot];
      const nr=r+dr, nc=c+dc;
      if (isValid(nr,nc) && b[nr][nc]==='') {
        moves.push([nr,nc]);
        if (isPawnStart(r,c,slot)) {
          const nr2=r+2*dr, nc2=c+2*dc;
          if (isValid(nr2,nc2) && b[nr2][nc2]==='') moves.push([nr2,nc2]);
        }
      }
      for (const [cdr,cdc] of PAWN_CAP[slot]) {
        const cr=r+cdr, cc=c+cdc;
        if (isValid(cr,cc) && b[cr][cc]!=='' && b[cr][cc][0]!==color) moves.push([cr,cc]);
      }
      break;
    }
    case 'n': {
      for (const [dr,dc] of [[2,1],[2,-1],[-2,1],[-2,-1],[1,2],[1,-2],[-1,2],[-1,-2]]) {
        const nr=r+dr, nc=c+dc;
        if (isValid(nr,nc) && (b[nr][nc]==='' || b[nr][nc][0]!==color)) moves.push([nr,nc]);
      }
      break;
    }
    case 'k': {
      for (const [dr,dc] of [[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]]) {
        const nr=r+dr, nc=c+dc;
        if (isValid(nr,nc) && (b[nr][nc]==='' || b[nr][nc][0]!==color)) moves.push([nr,nc]);
      }
      break;
    }
    case 'r': return sliding(b,r,c,color,[[1,0],[-1,0],[0,1],[0,-1]]);
    case 'b': return sliding(b,r,c,color,[[1,1],[1,-1],[-1,1],[-1,-1]]);
    case 'q': return sliding(b,r,c,color,[[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]]);
  }
  return moves;
}

function isInCheck(b, color) {
  let kr=-1, kc=-1;
  for (let r=0;r<14;r++) for (let c=0;c<14;c++) if (b[r][c]===color+'k') { kr=r; kc=c; }
  if (kr===-1) return false;
  for (let r=0;r<14;r++) for (let c=0;c<14;c++) {
    const p=b[r][c];
    if (!p||p===''||p[0]===color) continue;
    if (getRawMoves(b,r,c).some(([mr,mc])=>mr===kr&&mc===kc)) return true;
  }
  return false;
}

function getLegalMoves(b, r, c) {
  const piece = b[r][c];
  if (!piece||piece==='') return [];
  const color = piece[0];
  return getRawMoves(b,r,c).filter(([nr,nc]) => {
    const nb = copyBoard(b);
    nb[nr][nc] = piece;
    nb[r][c]   = '';
    return !isInCheck(nb, color);
  });
}

function hasAnyLegalMove(b, color) {
  for (let r=0;r<14;r++) for (let c=0;c<14;c++)
    if (b[r][c]&&b[r][c][0]===color&&getLegalMoves(b,r,c).length>0) return true;
  return false;
}

// ═══════════════════════════════════════════════════════════════
// Estado do jogo (local)
// ═══════════════════════════════════════════════════════════════
let board = initBoard();
let turnIdx = 0;         // 0-3, índice do jogador atual
let activePlayers = [0,1,2,3]; // índices ainda em jogo
let selected = null;     // [r,c] ou null
let legalMoves = [];     // [[r,c],...]
let lastFrom = null, lastTo = null;
let gameOverLocal = false;
let promoResolve = null; // promise resolve para promoção pendente

// Informações dos jogadores (preenchido ao receber estado da sala)
let players = [
  { name:'Vermelho', isBot:false, alive:true },
  { name:'Azul',     isBot:false, alive:true },
  { name:'Amarelo',  isBot:false, alive:true },
  { name:'Verde',    isBot:false, alive:true },
];

// ═══════════════════════════════════════════════════════════════
// Construção do tabuleiro DOM
// ═══════════════════════════════════════════════════════════════
function buildBoardDOM() {
  const el = document.getElementById('board');
  for (let r=0;r<14;r++) {
    for (let c=0;c<14;c++) {
      const cell = document.createElement('div');
      cell.className = 'cell';
      cell.dataset.r = r; cell.dataset.c = c;
      if (!isValid(r,c)) {
        cell.classList.add('invalid');
      } else {
        // Zona de cor do jogador
        if (r>=12) cell.classList.add('zone-R');
        else if (c<=1) cell.classList.add('zone-U');
        else if (r<=1) cell.classList.add('zone-Y');
        else if (c>=12) cell.classList.add('zone-G');
        else cell.classList.add((r+c)%2===0 ? 'light' : 'dark');
        cell.addEventListener('click', () => handleClick(r,c));
      }
      el.appendChild(cell);
    }
  }
}

function cellEl(r, c) {
  return document.querySelector(`[data-r="${r}"][data-c="${c}"]`);
}

// ═══════════════════════════════════════════════════════════════
// Renderização
// ═══════════════════════════════════════════════════════════════
function render() {
  // Limpar estados de highlight
  document.querySelectorAll('.cell').forEach(el => {
    el.classList.remove('selected','hint','hint-cap','last-from','last-to');
    el.innerHTML = '';
  });

  // Destacar última jogada
  if (lastFrom) cellEl(...lastFrom).classList.add('last-from');
  if (lastTo)   cellEl(...lastTo).classList.add('last-to');

  // Destacar seleção e movimentos válidos
  if (selected) {
    cellEl(...selected).classList.add('selected');
    for (const [mr,mc] of legalMoves) {
      const el = cellEl(mr,mc);
      if (board[mr][mc] && board[mr][mc]!=='') el.classList.add('hint-cap');
      else el.classList.add('hint');
    }
  }

  // Renderizar peças
  for (let r=0;r<14;r++) {
    for (let c=0;c<14;c++) {
      const p = board[r][c];
      if (!p || p==='') continue;
      const el = cellEl(r,c);
      const span = document.createElement('span');
      span.className = `piece piece-${p[0]}`;
      span.textContent = SYMBOLS[p[1]] || '?';
      el.appendChild(span);
    }
  }

  renderPlayerList();
  renderStatusMsg();
}

function renderPlayerList() {
  const el = document.getElementById('player-list');
  el.innerHTML = '';
  for (let i=0;i<4;i++) {
    const p = players[i];
    const isTurn = activePlayers.includes(i) && i===turnIdx;
    const row = document.createElement('div');
    row.className = 'player-row' + (p.alive ? '' : ' eliminated');
    row.innerHTML = `
      <div class="player-dot" style="background:${COLOR_HEX[SLOT_COLORS[i]]}"></div>
      <span class="player-name${isTurn?' active':''}">${escHtml(p.name)}</span>
      ${p.isBot ? '<span class="player-tag">bot</span>' : ''}
      ${!p.alive ? '<span class="player-tag">✕</span>' : ''}
      ${isTurn ? '<span class="turn-arrow">◀</span>' : ''}
    `;
    el.appendChild(row);
  }
}

function renderStatusMsg() {
  const el = document.getElementById('status-msg');
  if (gameOverLocal) { el.textContent = ''; return; }
  const color = SLOT_COLORS[turnIdx];
  const name  = players[turnIdx]?.name || COLOR_NAMES[color];
  if (turnIdx === mySlot) el.textContent = 'Sua vez!';
  else el.textContent = `Vez de ${name}`;
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ═══════════════════════════════════════════════════════════════
// Interação do jogador
// ═══════════════════════════════════════════════════════════════
function handleClick(r, c) {
  if (gameOverLocal) return;
  if (turnIdx !== mySlot) return; // não é a vez do jogador
  if (players[mySlot]?.isBot) return;

  const piece = board[r][c];

  // Já tem peça selecionada
  if (selected) {
    const [sr, sc] = selected;

    // Clicou em movimento válido
    const isMove = legalMoves.some(([mr,mc]) => mr===r&&mc===c);
    if (isMove) {
      executeMove([sr,sc],[r,c]);
      return;
    }

    // Clicou em outra peça própria
    if (piece && piece!=='' && piece[0]===SLOT_COLORS[mySlot]) {
      selected = [r,c];
      legalMoves = getLegalMoves(board, r, c);
      render(); return;
    }

    // Clicou em lugar inválido — deseleciona
    selected = null; legalMoves = [];
    render(); return;
  }

  // Selecionar peça própria
  if (piece && piece!=='' && piece[0]===SLOT_COLORS[mySlot]) {
    selected = [r,c];
    legalMoves = getLegalMoves(board, r, c);
    render();
  }
}

// ═══════════════════════════════════════════════════════════════
// Executar movimento
// ═══════════════════════════════════════════════════════════════
async function executeMove(from, to, isBot=false) {
  const [fr,fc] = from;
  const [tr,tc] = to;
  const piece   = board[fr][fc];
  const color   = piece[0];
  const slot    = SLOT_COLORS.indexOf(color);

  selected = null; legalMoves = [];

  // Verificar promoção
  let promo = null;
  if (piece[1]==='p' && isPromotionSquare(tr,tc,slot)) {
    promo = isBot ? 'q' : await askPromo(color);
  }

  // Aplicar no tabuleiro local
  const captured = board[tr][tc];
  board[tr][tc]  = promo ? color+promo : piece;
  board[fr][fc]  = '';

  lastFrom = from; lastTo = to;

  // Captura de rei → eliminação
  if (captured && captured.length === 2 && captured[1]==='k') {
    const capturedSlot = SLOT_COLORS.indexOf(captured[0]);
    if (capturedSlot !== -1) eliminatePlayer(capturedSlot);
  }

  // Verificar vitória
  if (activePlayers.length===1) {
    showWinner(activePlayers[0]);
    render(); return;
  }

  // Avançar turno
  advanceTurn();
  render();

  // Log
  logMove(slot, piece, from, to, captured, promo);

  // Enviar ao servidor (modo multijogador)
  if (ROOM_ID && !isBot) {
    await sendMove(from, to, promo);
  }

  // Se for a vez de um bot local, jogar
  if (!gameOverLocal && activePlayers.includes(turnIdx) && players[turnIdx]?.isBot) {
    setTimeout(botMove, 400 + Math.random()*300);
  }
}

function eliminatePlayer(slot) {
  players[slot].alive = false;
  activePlayers = activePlayers.filter(i => i!==slot);
  // Remover peças do eliminado
  const cl = SLOT_COLORS[slot];
  for (let r=0;r<14;r++) for (let c=0;c<14;c++)
    if (board[r][c]&&board[r][c][0]===cl) board[r][c]='';
}

function advanceTurn() {
  let next = (turnIdx+1)%4;
  let safety=0;
  while (!activePlayers.includes(next) && safety<4) { next=(next+1)%4; safety++; }
  turnIdx = next;

  // Verificar se próximo jogador tem movimentos
  if (!gameOverLocal) {
    const color = SLOT_COLORS[turnIdx];
    if (!hasAnyLegalMove(board, color)) {
      eliminatePlayer(turnIdx);
      if (activePlayers.length===1) { showWinner(activePlayers[0]); return; }
      advanceTurn();
    }
  }
}

// ═══════════════════════════════════════════════════════════════
// Bot
// ═══════════════════════════════════════════════════════════════
function botMove() {
  if (gameOverLocal) return;
  const color = SLOT_COLORS[turnIdx];
  const allMoves = [];
  for (let r=0;r<14;r++) for (let c=0;c<14;c++) {
    if (board[r][c]&&board[r][c][0]===color) {
      for (const to of getLegalMoves(board,r,c)) allMoves.push([[r,c],to]);
    }
  }
  if (allMoves.length===0) { advanceTurn(); render(); return; }

  // Preferir capturas de rei, depois capturas, depois movimentos aleatórios
  const kingCaps = allMoves.filter(([,to]) => board[to[0]][to[1]]&&board[to[0]][to[1]][1]==='k');
  const caps     = allMoves.filter(([,to]) => board[to[0]][to[1]]&&board[to[0]][to[1]]!=='');
  const pool     = kingCaps.length ? kingCaps : caps.length ? caps : allMoves;
  const [from,to] = pool[Math.floor(Math.random()*pool.length)];
  executeMove(from, to, true);
}

// ═══════════════════════════════════════════════════════════════
// Promoção
// ═══════════════════════════════════════════════════════════════
function askPromo(color) {
  return new Promise(resolve => {
    promoResolve = resolve;
    const piecesEl = document.getElementById('promo-pieces');
    piecesEl.innerHTML = '';
    for (const type of ['q','r','b','n']) {
      const btn = document.createElement('button');
      btn.className = `promo-btn piece-${color}`;
      btn.textContent = SYMBOLS[type];
      btn.style.color = COLOR_HEX[color];
      btn.addEventListener('click', () => {
        document.getElementById('promo-dialog').classList.remove('open');
        promoResolve = null;
        resolve(type);
      });
      piecesEl.appendChild(btn);
    }
    document.getElementById('promo-dialog').classList.add('open');
  });
}

// ═══════════════════════════════════════════════════════════════
// Log de movidas
// ═══════════════════════════════════════════════════════════════
const COL_LETTERS = 'abcdefghijklmn';
function sq(r,c) { return COL_LETTERS[c]+(14-r); }

function logMove(slot, piece, from, to, captured, promo) {
  const el = document.getElementById('move-log');
  const color = SLOT_COLORS[slot];
  const dot = `<span style="color:${COLOR_HEX[color]}">■</span>`;
  const text = `${PIECE_NAMES[piece[1]]} ${sq(...from)}→${sq(...to)}${captured&&captured!==''?'×':''}${promo?'='+promo.toUpperCase():''}`;
  const entry = document.createElement('div');
  entry.className = 'log-entry';
  entry.innerHTML = `${dot} ${text}`;
  el.appendChild(entry);
  el.scrollTop = el.scrollHeight;
}

// ═══════════════════════════════════════════════════════════════
// Fim de jogo
// ═══════════════════════════════════════════════════════════════
function showWinner(slot) {
  gameOverLocal = true;
  const color = SLOT_COLORS[slot];
  const name  = players[slot]?.name || COLOR_NAMES[color];
  document.getElementById('result-emoji').textContent = '♛';
  document.getElementById('result-emoji').style.color = COLOR_HEX[color];
  document.getElementById('result-title').textContent = name + ' venceu!';
  document.getElementById('result-sub').textContent = 'Último rei no tabuleiro';
  showScreen('screen-result');
}

// ═══════════════════════════════════════════════════════════════
// Telas
// ═══════════════════════════════════════════════════════════════
function showScreen(id) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

// ═══════════════════════════════════════════════════════════════
// Multiplayer — API
// ═══════════════════════════════════════════════════════════════
async function sendMove(from, to, promo) {
  if (!ROOM_ID || !myToken) return;
  await fetch(API, {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action:'move', roomId:ROOM_ID, token:myToken, from, to, promo:promo||null })
  }).catch(() => {});
}

async function sendResign() {
  if (!ROOM_ID || !myToken) return;
  await fetch(API, {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action:'resign', roomId:ROOM_ID, token:myToken })
  }).catch(() => {});
}

async function poll() {
  if (gameOverLocal) return;
  try {
    const res = await fetch(`${API}?action=poll&roomId=${ROOM_ID}&token=${myToken}`).then(r=>r.json());
    applyServerState(res);
  } catch(e) {}
}

function applyServerState(room) {
  if (!room || !room.state) return;

  // Atualizar info dos jogadores
  if (room.players) {
    room.players.forEach((p,i) => {
      players[i] = { name:p.name, isBot:p.isBot, alive:p.alive };
    });
    activePlayers = room.players.map((_,i)=>i).filter(i=>room.players[i].alive);
  }

  // Waiting
  if (room.status==='waiting') {
    showScreen('screen-waiting');
    renderWaitSlots(room.players);
    return;
  }

  // Finished
  if (room.status==='finished') {
    if (!gameOverLocal) {
      const st = room.state;
      board = room.state.board;
      turnIdx = room.state.turn;
      lastFrom = st.lastMove?.from || null;
      lastTo   = st.lastMove?.to   || null;
      render();
      const winnerSlot = room.winner ?? activePlayers[0];
      showWinner(winnerSlot);
    }
    return;
  }

  // Playing — sincronizar
  showScreen('screen-game');
  const st = room.state;
  board    = st.board;
  turnIdx  = st.turn;
  lastFrom = st.lastMove?.from || null;
  lastTo   = st.lastMove?.to   || null;
  selected = null; legalMoves = [];
  render();

  // Se for a vez de um bot, qualquer jogador humano conectado o aciona
  if (mySlot !== -1 && players[turnIdx]?.isBot && !gameOverLocal) {
    setTimeout(botMove, 500 + Math.random()*300);
  }
}

function renderWaitSlots(plrs) {
  const el = document.getElementById('wait-slots');
  el.innerHTML = '';
  const colorHex = ['#f87171','#93c5fd','#fde047','#86efac'];
  plrs.forEach((p,i) => {
    const div = document.createElement('div');
    div.className = 'wait-slot';
    const filled = p.isBot || !!(p.username);
    div.innerHTML = `<div class="wait-dot" style="background:${colorHex[i]};opacity:${filled?.9:.25}"></div>
      <span style="color:${filled?'#bbb':'#333'}">${escHtml(p.isBot?'Bot':p.name||'Aguardando...')}</span>`;
    el.appendChild(div);
  });
}

// ═══════════════════════════════════════════════════════════════
// Botão Desistir
// ═══════════════════════════════════════════════════════════════
document.getElementById('btn-resign').addEventListener('click', async () => {
  if (gameOverLocal) return;
  if (!confirm('Deseja desistir da partida?')) return;
  if (ROOM_ID && myToken) {
    await sendResign();
    eliminatePlayer(mySlot);
    if (activePlayers.length===1) { showWinner(activePlayers[0]); render(); return; }
    if (turnIdx===mySlot) advanceTurn();
    render();
    if (players[turnIdx]?.isBot) setTimeout(botMove, 400);
  } else {
    eliminatePlayer(mySlot);
    if (activePlayers.length===1) { showWinner(activePlayers[0]); render(); return; }
    if (turnIdx===mySlot) advanceTurn();
    render();
    if (players[turnIdx]?.isBot) setTimeout(botMove, 400);
  }
});

// ═══════════════════════════════════════════════════════════════
// Botão copiar código da sala
// ═══════════════════════════════════════════════════════════════
document.getElementById('copy-code-btn')?.addEventListener('click', function() {
  navigator.clipboard.writeText(ROOM_ID).then(() => {
    this.textContent = 'Copiado!';
    setTimeout(() => this.textContent = 'Copiar código', 2000);
  });
});

// ═══════════════════════════════════════════════════════════════
// Init
// ═══════════════════════════════════════════════════════════════
buildBoardDOM();

if (SOLO_MODE) {
  // Modo solo: 100% client-side, sem servidor
  const colorNames = ['Vermelho', 'Azul', 'Amarelo', 'Verde'];
  const playerName = player?.name || colorNames[SOLO_SLOT];
  for (let i = 0; i < 4; i++) {
    players[i] = { name: i === SOLO_SLOT ? playerName : 'Bot', isBot: i !== SOLO_SLOT, alive: true };
  }
  showScreen('screen-game');
  render();
  // Se o primeiro turno for de um bot (jogador não escolheu Vermelho), inicia bots
  if (players[turnIdx]?.isBot) setTimeout(botMove, 600);
} else if (ROOM_ID) {
  // Modo multijogador: sincronizar com servidor
  poll();
  pollTimer = setInterval(poll, 1500);
}
</script>
</body>
</html>
