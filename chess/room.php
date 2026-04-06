<?php
$roomId   = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['id'] ?? ''));
$botDepth = isset($_GET['bot']) ? max(1,min(3,(int)$_GET['bot'])) : 0;
$botMode  = $botDepth > 0;
$paramColor = in_array($_GET['color']??'', ['white','black']) ? $_GET['color'] : 'white';
function esc($s){ return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xadrez — Partida</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#08080f;color:#fff;font-family:'Open Sans','Segoe UI',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:16px;gap:12px}
    :root{--c:#f59e0b}
    a{color:var(--c);text-decoration:none}
    .back{font-size:.7rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;align-self:flex-start}
    .back:hover{color:#888}
    h2{font-size:1.2rem;letter-spacing:2px;color:#f59e0b;text-shadow:0 0 20px #f59e0b44}

    /* ── Screens ── */
    .screen{display:none;flex-direction:column;align-items:center;gap:14px;width:100%}
    .screen.active{display:flex}

    /* ── Waiting ── */
    .room-code{font-size:2.8rem;font-weight:900;letter-spacing:8px;color:#f59e0b;text-shadow:0 0 30px #f59e0b55;background:#0d0d1a;border:1px solid #f59e0b33;border-radius:10px;padding:16px 32px}
    .copy-btn{background:transparent;border:1px solid #1e1e30;border-radius:4px;color:#555;font-size:.72rem;padding:5px 14px;cursor:pointer;font-family:inherit;transition:all .12s}
    .copy-btn:hover{border-color:#f59e0b44;color:#f59e0b}
    .wait-msg{color:#444;font-size:.82rem;animation:blink 1.4s infinite}
    @keyframes blink{0%,100%{opacity:.4}50%{opacity:1}}

    /* ── Game layout ── */
    .game-wrap{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;justify-content:center;width:100%}

    /* ── Board ── */
    .board-wrap{position:relative}
    #board{display:grid;grid-template-columns:repeat(8,1fr);border:2px solid #2a2a40;border-radius:4px;overflow:hidden;user-select:none}
    .sq{width:52px;height:52px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;cursor:pointer;position:relative;transition:background .1s}
    .sq.light{background:#b58863}
    .sq.dark{background:#725141}
    .sq.selected{background:#f59e0b99!important}
    .sq.hint{background:#4ade8066!important}
    .sq.hint-capture{background:#f8717166!important}
    .sq.last-from{background:#f59e0b33!important}
    .sq.last-to{background:#f59e0b55!important}
    .sq.in-check{background:#f8717188!important}
    .rank-label,.file-label{position:absolute;font-size:.55rem;color:rgba(255,255,255,.4);font-weight:600;line-height:1}
    .rank-label{top:2px;left:3px}
    .file-label{bottom:2px;right:3px}

    /* ── Sidebar ── */
    .sidebar{display:flex;flex-direction:column;gap:10px;min-width:180px;max-width:220px}
    .panel{background:#0d0d1a;border:1px solid #1a1a2c;border-radius:8px;padding:12px 14px}
    .panel-title{font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;margin-bottom:8px}
    .player-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #111120}
    .player-row:last-child{border-bottom:none}
    .player-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .player-dot.white{background:#fff;border:1px solid #555}
    .player-dot.black{background:#222;border:1px solid #888}
    .player-name{font-size:.82rem;color:#aaa;flex:1}
    .player-name.active{color:#fff;font-weight:600}
    .turn-indicator{font-size:.72rem;color:#f59e0b;display:none}

    .move-history{font-size:.68rem;color:#555;max-height:200px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;font-family:monospace}
    .move-pair{display:flex;gap:6px}
    .move-num{color:#333;width:18px}
    .move-san{color:#888;cursor:default}
    .move-san:last-child{color:#ddd}

    .btn{padding:8px 18px;border:none;border-radius:5px;font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:all .12s;width:100%}
    .btn-resign{background:transparent;border:1px solid #f8717133;color:#f87171}
    .btn-resign:hover{background:#f8717122}
    .btn-primary{background:var(--c);color:#08080f;margin-top:4px}
    .btn-primary:hover{filter:brightness(1.1)}

    .status-msg{font-size:.78rem;color:#888;text-align:center}

    /* ── Promotion dialog ── */
    #promo-dialog{position:fixed;inset:0;background:rgba(8,8,18,.92);display:none;align-items:center;justify-content:center;z-index:100}
    #promo-dialog.open{display:flex}
    .promo-box{background:#0d0d1a;border:1px solid #252542;border-radius:10px;padding:24px;display:flex;flex-direction:column;align-items:center;gap:14px}
    .promo-box h3{color:#f59e0b;font-size:.9rem;letter-spacing:1px}
    .promo-pieces{display:flex;gap:10px}
    .promo-piece{width:60px;height:60px;background:#0a0a16;border:1px solid #1e1e30;border-radius:8px;font-size:2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:border-color .12s}
    .promo-piece:hover{border-color:#f59e0b55}

    /* ── Result screen ── */
    .result-box{background:#0d0d1a;border:1px solid #1a1a2c;border-radius:12px;padding:32px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:14px;max-width:340px}
    .result-title{font-size:1.5rem;font-weight:700;letter-spacing:3px}
    .result-sub{font-size:.82rem;color:#555}
    .result-emoji{font-size:2.5rem}

    @media(max-width:540px){
      .sq{width:40px;height:40px;font-size:1.4rem}
      .sidebar{min-width:unset;width:100%;max-width:340px;flex-direction:row;flex-wrap:wrap}
      .panel{flex:1;min-width:150px}
    }
  </style>
</head>
<body>

<a class="back" href="index.php">← Xadrez</a>
<h2>XADREZ</h2>

<!-- Screen: Waiting -->
<div class="screen <?= !$botMode && $roomId ? 'active' : '' ?>" id="screen-waiting">
  <p style="color:#555;font-size:.78rem">Sala criada! Compartilhe o código:</p>
  <div class="room-code" id="display-code"><?= esc($roomId) ?></div>
  <button class="copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('display-code').textContent).then(()=>{this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar código',2000})">Copiar código</button>
  <p class="wait-msg">Aguardando adversário...</p>
</div>

<!-- Screen: Game -->
<div class="screen <?= $botMode ? 'active' : '' ?>" id="screen-game">
  <div class="game-wrap">
    <div class="board-wrap">
      <div id="board"></div>
    </div>
    <div class="sidebar">
      <div class="panel" id="players-panel">
        <div class="panel-title">Jogadores</div>
        <div id="player-list"></div>
      </div>
      <div class="panel">
        <div class="panel-title">Histórico</div>
        <div class="move-history" id="move-history"></div>
      </div>
      <div class="panel">
        <p class="status-msg" id="status-msg"></p>
        <button class="btn btn-resign" id="btn-resign">Desistir</button>
      </div>
    </div>
  </div>
</div>

<!-- Screen: Result -->
<div class="screen" id="screen-result">
  <div class="result-box">
    <div class="result-emoji" id="result-emoji"></div>
    <div class="result-title" id="result-title"></div>
    <div class="result-sub" id="result-sub"></div>
    <button class="btn btn-primary" onclick="location.href='index.php'">Jogar novamente</button>
  </div>
</div>

<!-- Promotion dialog -->
<div id="promo-dialog">
  <div class="promo-box">
    <h3>PROMOÇÃO — Escolha a peça</h3>
    <div class="promo-pieces" id="promo-pieces"></div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════════
//  Config
// ═══════════════════════════════════════════════════════════════════════════════
const BOT_MODE  = <?= $botMode ? 'true' : 'false' ?>;
const BOT_DEPTH = <?= $botDepth ?>;
const ROOM_ID   = '<?= esc($roomId) ?>';
const API       = 'api.php';

let player = null;
try { player = JSON.parse(localStorage.getItem('snake_user')); } catch(e) {}

let myToken = sessionStorage.getItem('chess_token') || '';
let myColor = sessionStorage.getItem('chess_color') || '<?= esc($paramColor) ?>';
if (BOT_MODE) myColor = '<?= esc($paramColor) ?>';

let pollTimer  = null;
let gameOver   = false;
let waitingPromo = null;  // resolve function when promotion pending

// ═══════════════════════════════════════════════════════════════════════════════
//  Chess Engine
// ═══════════════════════════════════════════════════════════════════════════════
const EMPTY=0,WP=1,WN=2,WB=3,WR=4,WQ=5,WK=6,BP=-1,BN=-2,BB=-3,BR=-4,BQ=-5,BK=-6;

const PNAMES   = {1:'P',2:'N',3:'B',4:'R',5:'Q',6:'K'};
const PIECE_VAL= {1:100,2:320,3:330,4:500,5:900,6:20000};

// Piece-square tables (white perspective, flipped for black)
const PST = {
  1:[ // Pawn
     0,  0,  0,  0,  0,  0,  0,  0,
    50, 50, 50, 50, 50, 50, 50, 50,
    10, 10, 20, 30, 30, 20, 10, 10,
     5,  5, 10, 25, 25, 10,  5,  5,
     0,  0,  0, 20, 20,  0,  0,  0,
     5, -5,-10,  0,  0,-10, -5,  5,
     5, 10, 10,-20,-20, 10, 10,  5,
     0,  0,  0,  0,  0,  0,  0,  0
  ],
  2:[ // Knight
   -50,-40,-30,-30,-30,-30,-40,-50,
   -40,-20,  0,  0,  0,  0,-20,-40,
   -30,  0, 10, 15, 15, 10,  0,-30,
   -30,  5, 15, 20, 20, 15,  5,-30,
   -30,  0, 15, 20, 20, 15,  0,-30,
   -30,  5, 10, 15, 15, 10,  5,-30,
   -40,-20,  0,  5,  5,  0,-20,-40,
   -50,-40,-30,-30,-30,-30,-40,-50
  ],
  3:[ // Bishop
   -20,-10,-10,-10,-10,-10,-10,-20,
   -10,  0,  0,  0,  0,  0,  0,-10,
   -10,  0,  5, 10, 10,  5,  0,-10,
   -10,  5,  5, 10, 10,  5,  5,-10,
   -10,  0, 10, 10, 10, 10,  0,-10,
   -10, 10, 10, 10, 10, 10, 10,-10,
   -10,  5,  0,  0,  0,  0,  5,-10,
   -20,-10,-10,-10,-10,-10,-10,-20
  ],
  4:[ // Rook
     0,  0,  0,  0,  0,  0,  0,  0,
     5, 10, 10, 10, 10, 10, 10,  5,
    -5,  0,  0,  0,  0,  0,  0, -5,
    -5,  0,  0,  0,  0,  0,  0, -5,
    -5,  0,  0,  0,  0,  0,  0, -5,
    -5,  0,  0,  0,  0,  0,  0, -5,
    -5,  0,  0,  0,  0,  0,  0, -5,
     0,  0,  0,  5,  5,  0,  0,  0
  ],
  5:[ // Queen
   -20,-10,-10, -5, -5,-10,-10,-20,
   -10,  0,  0,  0,  0,  0,  0,-10,
   -10,  0,  5,  5,  5,  5,  0,-10,
    -5,  0,  5,  5,  5,  5,  0, -5,
     0,  0,  5,  5,  5,  5,  0, -5,
   -10,  5,  5,  5,  5,  5,  0,-10,
   -10,  0,  5,  0,  0,  0,  0,-10,
   -20,-10,-10, -5, -5,-10,-10,-20
  ],
  6:[ // King midgame
   -30,-40,-40,-50,-50,-40,-40,-30,
   -30,-40,-40,-50,-50,-40,-40,-30,
   -30,-40,-40,-50,-50,-40,-40,-30,
   -30,-40,-40,-50,-50,-40,-40,-30,
   -20,-30,-30,-40,-40,-30,-30,-20,
   -10,-20,-20,-20,-20,-20,-20,-10,
    20, 20,  0,  0,  0,  0, 20, 20,
    20, 30, 10,  0,  0, 10, 30, 20
  ]
};

function mirrorSq(sq){ return (7-(sq>>3))*8+(sq&7); }

// ── State ─────────────────────────────────────────────────────────────────────
function makeState() {
  return {
    board: new Int8Array(64),
    turn: 'w',
    castling: {wK:true,wQ:true,bK:true,bQ:true},
    enPassant: -1,
    halfMove: 0,
    fullMove: 1,
    moveHistory: []
  };
}

function initialState() {
  const s = makeState();
  const back = [WR,WN,WB,WQ,WK,WB,WN,WR];
  for(let c=0;c<8;c++){
    s.board[c]    = -back[c];  // row0 = rank8 black
    s.board[8+c]  = BP;
    s.board[48+c] = WP;
    s.board[56+c] = back[c];   // row7 = rank1 white
  }
  return s;
}

function cloneState(s) {
  return {
    board: new Int8Array(s.board),
    turn: s.turn,
    castling: {...s.castling},
    enPassant: s.enPassant,
    halfMove: s.halfMove,
    fullMove: s.fullMove,
    moveHistory: [...s.moveHistory]
  };
}

// sq helpers
const sqRow = sq => sq >> 3;
const sqCol = sq => sq & 7;
const rc2sq = (r,c) => r*8+c;
const onBoard = sq => sq>=0 && sq<64;
const pieceColor = p => p>0?'w':p<0?'b':null;
const pieceType  = p => Math.abs(p);

// ── Move generation ───────────────────────────────────────────────────────────
const KNIGHT_DELTAS = [-17,-15,-10,-6,6,10,15,17];
const DIRS_ROOK   = [-8,-1,1,8];
const DIRS_BISHOP = [-9,-7,7,9];
const DIRS_QUEEN  = [-9,-8,-7,-1,1,7,8,9];

function generateMoves(s) {
  const moves = [];
  const color = s.turn;
  for(let sq=0;sq<64;sq++){
    const p = s.board[sq];
    if(!p || pieceColor(p)!==color) continue;
    const t = pieceType(p);
    if(t===Math.abs(WP)) genPawnMoves(s, sq, color, moves);
    else if(t===Math.abs(WN)) genJumpMoves(s, sq, color, KNIGHT_DELTAS, moves);
    else if(t===Math.abs(WB)) genSlideMoves(s, sq, color, DIRS_BISHOP, moves);
    else if(t===Math.abs(WR)) genSlideMoves(s, sq, color, DIRS_ROOK, moves);
    else if(t===Math.abs(WQ)) genSlideMoves(s, sq, color, DIRS_QUEEN, moves);
    else if(t===Math.abs(WK)) genKingMoves(s, sq, color, moves);
  }
  return moves;
}

function genPawnMoves(s, sq, color, moves) {
  const row = sqRow(sq), col = sqCol(sq);
  const dir = color==='w' ? -1 : 1;
  const startRow = color==='w' ? 6 : 1;
  const promoRow = color==='w' ? 0 : 7;

  // Forward
  const fwd = sq + dir*8;
  if(onBoard(fwd) && !s.board[fwd]){
    addPawnMove(sq, fwd, sqRow(fwd)===promoRow, color, s, moves);
    // Double push
    if(row===startRow){
      const fwd2 = sq + dir*16;
      if(!s.board[fwd2]) moves.push({from:sq,to:fwd2,piece:WP*Math.sign(s.board[sq]),enPassantSet:fwd});
    }
  }

  // Captures
  for(const dc of [-1,1]){
    const tc = col+dc;
    if(tc<0||tc>7) continue;
    const to = sq+dir*8+dc;
    if(!onBoard(to)) continue;
    if(s.board[to] && pieceColor(s.board[to])!==color)
      addPawnMove(sq, to, sqRow(to)===promoRow, color, s, moves);
    // En passant
    if(to===s.enPassant)
      moves.push({from:sq,to,piece:s.board[sq],captured:s.board[sq-dir*8+0], // captured pawn sq computed in apply
        isEnPassant:true,capturedSq:sq+dc});
  }
}

function addPawnMove(from, to, isPromo, color, s, moves) {
  if(isPromo){
    const promos = color==='w'?[WQ,WR,WB,WN]:[-WQ,-WR,-WB,-WN];
    for(const promo of promos)
      moves.push({from,to,piece:s.board[from],captured:s.board[to]||0,promotion:promo});
  } else {
    moves.push({from,to,piece:s.board[from],captured:s.board[to]||0});
  }
}

function genJumpMoves(s, sq, color, deltas, moves) {
  const row=sqRow(sq),col=sqCol(sq);
  for(const d of deltas){
    const to=sq+d;
    if(!onBoard(to)) continue;
    // Check wrap-around
    const dr=Math.abs(sqRow(to)-row),dc=Math.abs(sqCol(to)-col);
    if(dr+dc!==3) continue; // knight moves exactly 3 squares manhattan
    if(s.board[to] && pieceColor(s.board[to])===color) continue;
    moves.push({from:sq,to,piece:s.board[sq],captured:s.board[to]||0});
  }
}

function genSlideMoves(s, sq, color, dirs, moves) {
  for(const d of dirs){
    let to=sq+d;
    while(onBoard(to)){
      // Wrap guard: diagonal moves always shift col by 1; horizontal by 1 but must stay same rank
      const prevCol=sqCol(to-d), curCol=sqCol(to);
      const colDiff=Math.abs(curCol-prevCol);
      // For diagonal (d: ±7,±9): colDiff must be 1. For rook horiz (d:±1): colDiff must be 1 and same rank.
      if(colDiff===0 && (d===1||d===-1)) break; // wrapped across rank boundary
      if(colDiff>1) break; // diagonal wrapped
      if(s.board[to]){
        if(pieceColor(s.board[to])!==color)
          moves.push({from:sq,to,piece:s.board[sq],captured:s.board[to]});
        break;
      }
      moves.push({from:sq,to,piece:s.board[sq],captured:0});
      to+=d;
    }
  }
}

function genKingMoves(s, sq, color, moves) {
  for(const d of DIRS_QUEEN){
    const to=sq+d;
    if(!onBoard(to)) continue;
    if(Math.abs(sqCol(to)-sqCol(sq))>1) continue;
    if(s.board[to] && pieceColor(s.board[to])===color) continue;
    moves.push({from:sq,to,piece:s.board[sq],captured:s.board[to]||0});
  }
  // Castling
  if(color==='w' && sq===60){
    if(s.castling.wK && !s.board[61] && !s.board[62] && !isAttacked(s,60,'b') && !isAttacked(s,61,'b') && !isAttacked(s,62,'b') && s.board[63]===WR)
      moves.push({from:60,to:62,piece:WK,isCastle:'wK'});
    if(s.castling.wQ && !s.board[59] && !s.board[58] && !s.board[57] && !isAttacked(s,60,'b') && !isAttacked(s,59,'b') && !isAttacked(s,58,'b') && s.board[56]===WR)
      moves.push({from:60,to:58,piece:WK,isCastle:'wQ'});
  }
  if(color==='b' && sq===4){
    if(s.castling.bK && !s.board[5] && !s.board[6] && !isAttacked(s,4,'w') && !isAttacked(s,5,'w') && !isAttacked(s,6,'w') && s.board[7]===BK)
      moves.push({from:4,to:6,piece:BK,isCastle:'bK'});
    if(s.castling.bQ && !s.board[3] && !s.board[2] && !s.board[1] && !isAttacked(s,4,'w') && !isAttacked(s,3,'w') && !isAttacked(s,2,'w') && s.board[0]===BK)
      moves.push({from:4,to:2,piece:BK,isCastle:'bQ'});
  }
}

function isAttacked(s, sq, byColor) {
  const opp = byColor;
  // Pawns
  const dir = opp==='w' ? 1 : -1; // attacker's forward
  for(const dc of [-1,1]){
    const from = sq+dir*8+dc;
    if(!onBoard(from)) continue;
    if(Math.abs(sqCol(from)-sqCol(sq))!==1) continue;
    const p=s.board[from];
    if(pieceColor(p)===opp && pieceType(p)===1) return true;
  }
  // Knights
  for(const d of KNIGHT_DELTAS){
    const from=sq+d;
    if(!onBoard(from)) continue;
    const dr=Math.abs(sqRow(from)-sqRow(sq)),dc=Math.abs(sqCol(from)-sqCol(sq));
    if(dr+dc!==3) continue;
    const p=s.board[from];
    if(pieceColor(p)===opp && pieceType(p)===2) return true;
  }
  // Sliders (bishop/rook/queen)
  for(const d of DIRS_BISHOP){
    let t=sq+d;
    while(onBoard(t)){
      if(Math.abs(sqCol(t)-sqCol(t-d))>1) break;
      if(s.board[t]){
        const p=s.board[t];
        if(pieceColor(p)===opp && (pieceType(p)===3||pieceType(p)===5)) return true;
        break;
      }
      t+=d;
    }
  }
  for(const d of DIRS_ROOK){
    let t=sq+d, prev=sq;
    while(onBoard(t)){
      if((d===1||d===-1) && sqRow(t)!==sqRow(prev)) break;
      if(s.board[t]){
        const p=s.board[t];
        if(pieceColor(p)===opp && (pieceType(p)===4||pieceType(p)===5)) return true;
        break;
      }
      prev=t; t+=d;
    }
  }
  // King
  for(const d of DIRS_QUEEN){
    const t=sq+d;
    if(!onBoard(t)) continue;
    if(Math.abs(sqCol(t)-sqCol(sq))>1) continue;
    const p=s.board[t];
    if(pieceColor(p)===opp && pieceType(p)===6) return true;
  }
  return false;
}

function findKing(s, color) {
  const k = color==='w'?WK:BK;
  for(let i=0;i<64;i++) if(s.board[i]===k) return i;
  return -1;
}

function isInCheck(s, color) {
  const kSq = findKing(s, color);
  return kSq>=0 && isAttacked(s, kSq, color==='w'?'b':'w');
}

// ── Apply / Undo ──────────────────────────────────────────────────────────────
function applyMove(s, m) {
  const undo = {
    from:m.from, to:m.to, piece:m.piece, captured:m.captured||0,
    castling:{...s.castling}, enPassant:s.enPassant,
    halfMove:s.halfMove, fullMove:s.fullMove,
    capturedSq: m.isEnPassant ? m.capturedSq : m.to,
    isCastle:m.isCastle||null
  };

  s.board[m.to]   = m.promotion || m.piece;
  s.board[m.from] = EMPTY;
  if(m.isEnPassant){ s.board[m.capturedSq]=EMPTY; undo.captured=BP*Math.sign(m.piece)*-1; }
  if(m.isCastle){
    if(m.isCastle==='wK'){ s.board[61]=WR; s.board[63]=EMPTY; }
    if(m.isCastle==='wQ'){ s.board[59]=WR; s.board[56]=EMPTY; }
    if(m.isCastle==='bK'){ s.board[5]=BR;  s.board[7]=EMPTY;  }
    if(m.isCastle==='bQ'){ s.board[3]=BR;  s.board[0]=EMPTY;  }
  }

  // Update castling rights
  if(m.piece===WK){ s.castling.wK=false; s.castling.wQ=false; }
  if(m.piece===BK){ s.castling.bK=false; s.castling.bQ=false; }
  if(m.from===63||m.to===63) s.castling.wK=false;
  if(m.from===56||m.to===56) s.castling.wQ=false;
  if(m.from===7 ||m.to===7 ) s.castling.bK=false;
  if(m.from===0 ||m.to===0 ) s.castling.bQ=false;

  s.enPassant  = m.enPassantSet ?? -1;
  s.halfMove   = (m.captured||m.isEnPassant||pieceType(m.piece)===1) ? 0 : s.halfMove+1;
  if(s.turn==='b') s.fullMove++;
  s.turn = s.turn==='w'?'b':'w';
  return undo;
}

function undoMove(s, u) {
  s.board[u.from] = u.piece;
  s.board[u.to]   = u.capturedSq===u.to ? u.captured : EMPTY;
  if(u.capturedSq!==u.to) s.board[u.capturedSq] = u.captured;
  if(u.isCastle){
    if(u.isCastle==='wK'){ s.board[63]=WR; s.board[61]=EMPTY; }
    if(u.isCastle==='wQ'){ s.board[56]=WR; s.board[59]=EMPTY; }
    if(u.isCastle==='bK'){ s.board[7]=BR;  s.board[5]=EMPTY;  }
    if(u.isCastle==='bQ'){ s.board[0]=BR;  s.board[3]=EMPTY;  }
  }
  s.castling  = u.castling;
  s.enPassant = u.enPassant;
  s.halfMove  = u.halfMove;
  s.fullMove  = u.fullMove;
  s.turn = s.turn==='w'?'b':'w';
}

function getLegalMoves(s) {
  return generateMoves(s).filter(m=>{
    const u=applyMove(s,m);
    const legal=!isInCheck(s, s.turn==='w'?'b':'w');
    undoMove(s,u);
    return legal;
  });
}

// ── Evaluation ────────────────────────────────────────────────────────────────
function evaluate(s) {
  let score=0;
  for(let sq=0;sq<64;sq++){
    const p=s.board[sq]; if(!p) continue;
    const t=pieceType(p), isW=p>0;
    const pst=PST[t];
    const pstSq=isW?mirrorSq(sq):sq;
    const val=(PIECE_VAL[t]||0)+(pst?pst[pstSq]:0);
    score+=isW?val:-val;
  }
  return score;
}

function orderMoves(moves) {
  moves.sort((a,b)=>{
    const va=a.captured?PIECE_VAL[pieceType(a.captured)]||0:0;
    const vb=b.captured?PIECE_VAL[pieceType(b.captured)]||0:0;
    return vb-va;
  });
}

function minimax(s, depth, alpha, beta, isMax) {
  if(depth===0) return evaluate(s);
  const moves=getLegalMoves(s);
  if(!moves.length) return isInCheck(s,s.turn)?(isMax?-99999:99999):0;
  orderMoves(moves);
  if(isMax){
    let best=-Infinity;
    for(const m of moves){
      const u=applyMove(s,m);
      best=Math.max(best,minimax(s,depth-1,alpha,beta,false));
      undoMove(s,u);
      alpha=Math.max(alpha,best);
      if(alpha>=beta) break;
    }
    return best;
  } else {
    let best=Infinity;
    for(const m of moves){
      const u=applyMove(s,m);
      best=Math.min(best,minimax(s,depth-1,alpha,beta,true));
      undoMove(s,u);
      beta=Math.min(beta,best);
      if(alpha>=beta) break;
    }
    return best;
  }
}

function getBotMove(s, depth) {
  const moves=getLegalMoves(s);
  if(!moves.length) return null;
  orderMoves(moves);
  let best=null, bestScore=s.turn==='w'?-Infinity:Infinity;
  for(const m of moves){
    const u=applyMove(s,m);
    const score=minimax(s, depth-1, -Infinity, Infinity, s.turn==='w');
    undoMove(s,u);
    if(s.turn==='w'?score>bestScore:score<bestScore){ bestScore=score; best=m; }
  }
  // Auto-promote to queen
  if(best && !best.promotion && pieceType(best.piece)===1 && (sqRow(best.to)===0||sqRow(best.to)===7))
    best.promotion = s.turn==='w'?WQ:BQ;
  return best;
}

// ── FEN ───────────────────────────────────────────────────────────────────────
const FEN_MAP={'P':WP,'N':WN,'B':WB,'R':WR,'Q':WQ,'K':WK,'p':BP,'n':BN,'b':BB,'r':BR,'q':BQ,'k':BK};
const FEN_REV={[WP]:'P',[WN]:'N',[WB]:'B',[WR]:'R',[WQ]:'Q',[WK]:'K',[BP]:'p',[BN]:'n',[BB]:'b',[BR]:'r',[BQ]:'q',[BK]:'k'};

function fenToState(fen) {
  const s=makeState();
  const [pos,turn,cast,ep,hm,fm]=fen.split(' ');
  let sq=0;
  for(const ch of pos){
    if(ch==='/') continue;
    if(ch>='1'&&ch<='8'){ sq+=parseInt(ch); }
    else { s.board[sq]=FEN_MAP[ch]||0; sq++; }
  }
  s.turn=turn||'w';
  s.castling={wK:cast?.includes('K')??true,wQ:cast?.includes('Q')??true,bK:cast?.includes('k')??true,bQ:cast?.includes('q')??true};
  s.enPassant=ep&&ep!=='-'?rc2sq('87654321'.indexOf(ep[1]),ep.charCodeAt(0)-97):-1;
  s.halfMove=parseInt(hm)||0;
  s.fullMove=parseInt(fm)||1;
  return s;
}

function stateToFen(s) {
  let fen='';
  for(let r=0;r<8;r++){
    let empty=0;
    for(let c=0;c<8;c++){
      const p=s.board[r*8+c];
      if(!p){ empty++; }
      else { if(empty){ fen+=empty; empty=0; } fen+=FEN_REV[p]||'?'; }
    }
    if(empty) fen+=empty;
    if(r<7) fen+='/';
  }
  const ep=s.enPassant>=0?String.fromCharCode(97+(s.enPassant&7))+(8-(s.enPassant>>3)):'-';
  const cast=(s.castling.wK?'K':'')+(s.castling.wQ?'Q':'')+(s.castling.bK?'k':'')+(s.castling.bQ?'q':'')||'-';
  return `${fen} ${s.turn} ${cast} ${ep} ${s.halfMove} ${s.fullMove}`;
}

// ── SAN (simplified) ─────────────────────────────────────────────────────────
function toSan(s, m) {
  const files='abcdefgh', t=pieceType(m.piece);
  const toFile=files[sqCol(m.to)], toRank=8-sqRow(m.to);
  const fromFile=files[sqCol(m.from)];
  if(m.isCastle) return m.isCastle.endsWith('K')?'O-O':'O-O-O';
  let san='';
  if(t===1){ if(m.captured||m.isEnPassant) san=fromFile+'x'; san+=toFile+toRank; if(m.promotion) san+='='+(FEN_REV[m.promotion]||'Q').toUpperCase(); }
  else { san=PNAMES[t]+(m.captured?'x':'')+(toFile+toRank); }
  return san;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Game state
// ═══════════════════════════════════════════════════════════════════════════════
let chess = initialState();
let selected = -1;
let legalMoves = [];
let allLegalMoves = [];
let lastMove = null;
let sanHistory = [];
let flipped = myColor === 'black'; // flip board if playing black

const UNICODE = {
  [WK]:'♔',[WQ]:'♕',[WR]:'♖',[WB]:'♗',[WN]:'♘',[WP]:'♙',
  [BK]:'♚',[BQ]:'♛',[BR]:'♜',[BB]:'♝',[BN]:'♞',[BP]:'♟'
};

let roomData = null; // current poll data (pvp mode)

// ── Render board ─────────────────────────────────────────────────────────────
function renderBoard() {
  const boardEl = document.getElementById('board');
  boardEl.innerHTML = '';

  const hintsTo  = new Set(legalMoves.map(m=>m.to));
  const hintsCapture = new Set(legalMoves.filter(m=>m.captured||m.isEnPassant).map(m=>m.to));
  const inCheck  = isInCheck(chess, chess.turn);
  const kingSq   = inCheck ? findKing(chess, chess.turn) : -1;

  for(let i=0;i<64;i++){
    const sq    = flipped ? 63-i : i;
    const r     = sqRow(sq), c = sqCol(sq);
    const isLight = (r+c)%2===0;
    const div   = document.createElement('div');
    div.className = 'sq '+(isLight?'light':'dark');
    div.dataset.sq = sq;

    if(sq===selected)        div.classList.add('selected');
    if(hintsCapture.has(sq)) div.classList.add('hint-capture');
    else if(hintsTo.has(sq)) div.classList.add('hint');
    if(lastMove && sq===lastMove.from) div.classList.add('last-from');
    if(lastMove && sq===lastMove.to)   div.classList.add('last-to');
    if(sq===kingSq) div.classList.add('in-check');

    // Labels
    if((!flipped&&c===0)||(flipped&&c===7)){ const l=document.createElement('span'); l.className='rank-label'; l.textContent=8-r; div.appendChild(l); }
    if((!flipped&&r===7)||(flipped&&r===0)){ const l=document.createElement('span'); l.className='file-label'; l.textContent='abcdefgh'[c]; div.appendChild(l); }

    const p = chess.board[sq];
    if(p) div.textContent = UNICODE[p]||'';

    div.addEventListener('click', () => onSquareClick(sq));
    boardEl.appendChild(div);
  }
}

async function onSquareClick(sq) {
  if(gameOver) return;
  const myTurn = chess.turn === (myColor==='white'?'w':'b');
  if(!myTurn) return;

  // Try to move to hint square
  const move = legalMoves.find(m => m.to===sq);
  if(move){
    // Promotion?
    let finalMove = move;
    if(!move.promotion && pieceType(move.piece)===1 && (sqRow(move.to)===0||sqRow(move.to)===7)){
      const promoChoice = await askPromotion(chess.turn==='w');
      // Apply chosen promotion from one of the generated promo moves
      finalMove = legalMoves.find(m=>m.to===sq && m.promotion===promoChoice) || move;
    }
    await commitMove(finalMove);
    return;
  }

  // Select piece
  const p = chess.board[sq];
  if(p && pieceColor(p)===(myColor==='white'?'w':'b')){
    selected = sq;
    legalMoves = allLegalMoves.filter(m=>m.from===sq);
  } else {
    selected = -1;
    legalMoves = [];
  }
  renderBoard();
}

async function commitMove(m) {
  const sanStr = toSan(chess, m);
  applyMove(chess, m);
  lastMove = m;
  selected = -1;
  legalMoves = [];
  allLegalMoves = getLegalMoves(chess);
  sanHistory.push(sanStr);
  renderBoard();
  updateSidebar();
  checkGameOver();

  if(!BOT_MODE && ROOM_ID){
    await sendMoveToServer(m, sanStr);
  } else if(BOT_MODE && !gameOver){
    setTimeout(doBotMove, 400);
  }
}

function doBotMove() {
  if(gameOver) return;
  if(chess.turn===(myColor==='white'?'w':'b')) return; // not bot's turn
  const m = getBotMove(chess, BOT_DEPTH);
  if(!m){ checkGameOver(); return; }
  const sanStr = toSan(chess, m);
  if(!m.promotion && pieceType(m.piece)===1 && (sqRow(m.to)===0||sqRow(m.to)===7))
    m.promotion = chess.turn==='w'?WQ:BQ;
  applyMove(chess, m);
  lastMove = m;
  allLegalMoves = getLegalMoves(chess);
  sanHistory.push(sanStr);
  renderBoard();
  updateSidebar();
  checkGameOver();
}

function checkGameOver() {
  if(!allLegalMoves.length){
    const inCheck = isInCheck(chess, chess.turn);
    const winner  = chess.turn==='w'?'b':'w';
    if(inCheck) showResult(winner==='w'?'Brancas vencem!':'Pretas vencem!', 'Xeque-mate', '♔');
    else         showResult('Empate!', 'Afogamento (stalemate)', '🤝');
    if(!BOT_MODE) endGameOnServer(inCheck?(winner==='w'?'white_wins':'black_wins'):'draw', inCheck?'checkmate':'stalemate');
  } else if(chess.halfMove>=100){
    showResult('Empate!', '50 movimentos sem captura ou peão', '🤝');
    if(!BOT_MODE) endGameOnServer('draw','fifty_move');
  }
}

function showResult(title, sub, emoji) {
  gameOver = true;
  clearInterval(pollTimer);
  document.getElementById('result-title').textContent  = title;
  document.getElementById('result-sub').textContent    = sub;
  document.getElementById('result-emoji').textContent  = emoji;
  showScreen('result');
}

// ── Promotion dialog ──────────────────────────────────────────────────────────
function askPromotion(isWhite) {
  return new Promise(resolve => {
    const pieces = isWhite ? [WQ,WR,WB,WN] : [BQ,BR,BB,BN];
    const container = document.getElementById('promo-pieces');
    container.innerHTML = '';
    pieces.forEach(p => {
      const btn = document.createElement('div');
      btn.className='promo-piece';
      btn.textContent = UNICODE[p];
      btn.addEventListener('click', () => {
        document.getElementById('promo-dialog').classList.remove('open');
        resolve(p);
      });
      container.appendChild(btn);
    });
    document.getElementById('promo-dialog').classList.add('open');
  });
}

// ── Sidebar ───────────────────────────────────────────────────────────────────
function updateSidebar() {
  const myTurn = chess.turn === (myColor==='white'?'w':'b');
  document.getElementById('status-msg').textContent = gameOver?'':(myTurn?'Sua vez':'Vez do adversário');

  // Move history
  const histEl = document.getElementById('move-history');
  histEl.innerHTML = '';
  for(let i=0;i<sanHistory.length;i+=2){
    const div=document.createElement('div'); div.className='move-pair';
    const num=document.createElement('span'); num.className='move-num'; num.textContent=Math.floor(i/2+1)+'.';
    const w=document.createElement('span'); w.className='move-san'; w.textContent=sanHistory[i]||'';
    const b=document.createElement('span'); b.className='move-san'; b.textContent=sanHistory[i+1]||'';
    div.append(num,w,b); histEl.appendChild(div);
  }
  histEl.scrollTop=histEl.scrollHeight;
}

function renderPlayers(white, black) {
  const pl = document.getElementById('player-list');
  const myTurn = chess.turn;
  pl.innerHTML = `
    <div class="player-row">
      <span class="player-dot white"></span>
      <span class="player-name ${myTurn==='w'?'active':''}">${white}</span>
    </div>
    <div class="player-row">
      <span class="player-dot black"></span>
      <span class="player-name ${myTurn==='b'?'active':''}">${black}</span>
    </div>`;
}

// ── Screen control ─────────────────────────────────────────────────────────────
function showScreen(id) {
  ['waiting','game','result'].forEach(s=>{
    document.getElementById('screen-'+s).classList.toggle('active', s===id);
  });
}

// ── Server sync (PvP) ─────────────────────────────────────────────────────────
async function sendMoveToServer(m, sanStr) {
  if(!ROOM_ID || !myToken) return;
  const newFen = stateToFen(chess);
  const state  = { fen:newFen, moveHistory:[...sanHistory], lastMove:{from:m.from,to:m.to} };
  const body   = { action:'move', roomId:ROOM_ID, token:myToken, move:m, state };
  if(gameOver){
    const loser  = chess.turn; // who has no moves (current turn after move = loser)
    const winner = loser==='w'?'black_wins':'white_wins';
    body.result  = winner; body.resultReason='checkmate';
  }
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).catch(()=>{});
}

async function endGameOnServer(result, reason) {
  if(!ROOM_ID || !myToken) return;
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'move',roomId:ROOM_ID,token:myToken,move:null,
      state:{fen:stateToFen(chess),moveHistory:sanHistory,lastMove:lastMove?{from:lastMove.from,to:lastMove.to}:null},
      result, resultReason:reason})
  }).catch(()=>{});
}

async function resign() {
  if(!confirm('Deseja desistir?')) return;
  if(BOT_MODE){ showResult('Pretas vencem!', 'Você desistiu', '🏳️'); return; }
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'resign',roomId:ROOM_ID,token:myToken})
  }).catch(()=>{});
  showResult(myColor==='white'?'Pretas vencem!':'Brancas vencem!', 'Você desistiu', '🏳️');
}

// ── Polling ───────────────────────────────────────────────────────────────────
async function poll() {
  if(gameOver) return;
  const res = await fetch(`${API}?action=poll&roomId=${ROOM_ID}&token=${myToken}`).then(r=>r.json()).catch(()=>null);
  if(!res) return;
  roomData = res;

  if(res.status==='waiting'){ return; } // still waiting

  if(res.status==='playing' && document.getElementById('screen-waiting').classList.contains('active')){
    // opponent joined — start game
    const myP   = res.players.find(p=>p.color===myColor);
    const oppP  = res.players.find(p=>p.color!==myColor);
    const white = res.players.find(p=>p.color==='white');
    const black = res.players.find(p=>p.color==='black');
    renderPlayers(white?.name||'?', black?.name||'Bot');
    startGame();
    return;
  }

  if(res.status==='finished' && !gameOver){
    applyServerState(res.state);
    const r=res.result;
    const reason=res.resultReason==='resignation'?'Desistência':'Fim de jogo';
    if(r==='draw') showResult('Empate!', reason, '🤝');
    else if(r==='white_wins') showResult('Brancas vencem!', reason, '♔');
    else showResult('Pretas vencem!', reason, '♚');
    return;
  }

  // Check if opponent moved
  if(res.status==='playing'){
    const serverTurn = res.state.fen.split(' ')[1];
    const localTurn  = chess.turn;
    if(serverTurn !== localTurn){
      applyServerState(res.state);
    }
    const white = res.players.find(p=>p.color==='white');
    const black = res.players.find(p=>p.color==='black');
    renderPlayers(white?.name||'?', black?.name||'?');
    updateSidebar();
  }
}

function applyServerState(state) {
  chess = fenToState(state.fen);
  sanHistory = state.moveHistory || [];
  if(state.lastMove) lastMove = state.lastMove;
  allLegalMoves = getLegalMoves(chess);
  selected = -1; legalMoves = [];
  renderBoard();
  updateSidebar();
  checkGameOver();
}

// ── Start ─────────────────────────────────────────────────────────────────────
function startGame() {
  if(BOT_MODE){
    const white = myColor==='white'?(player?.name||'Você'):'Bot';
    const black = myColor==='black'?(player?.name||'Você'):'Bot';
    renderPlayers(white, black);
  }
  allLegalMoves = getLegalMoves(chess);
  renderBoard();
  updateSidebar();
  showScreen('game');
  // If bot plays white (player chose black), bot moves first
  if(BOT_MODE && myColor==='black') setTimeout(doBotMove, 600);
}

document.getElementById('btn-resign').addEventListener('click', resign);

// Init
if(BOT_MODE){
  flipped = myColor==='black';
  startGame();
} else if(ROOM_ID){
  // PvP mode
  (async()=>{
    const res=await fetch(`${API}?action=poll&roomId=${ROOM_ID}&token=${myToken}`).then(r=>r.json()).catch(()=>null);
    if(!res){ alert('Sala não encontrada'); location.href='index.php'; return; }
    document.getElementById('display-code').textContent=ROOM_ID;

    if(res.status==='playing'){
      // We're the 2nd player joining (already playing)
      myColor = res.players.find(p=>p.username===player?.username)?.color || myColor;
      flipped  = myColor==='black';
      const white=res.players.find(p=>p.color==='white');
      const black=res.players.find(p=>p.color==='black');
      renderPlayers(white?.name||'?', black?.name||'?');
      applyServerState(res.state);
      showScreen('game');
    } else {
      flipped = myColor==='black';
      showScreen('waiting');
    }
    pollTimer = setInterval(poll, 1500);
  })();
}
</script>
</body>
</html>
