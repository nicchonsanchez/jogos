<?php
$roomId   = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['id'] ?? ''));
$botDepth = isset($_GET['bot']) ? max(1,min(5,(int)$_GET['bot'])) : 0;
$botMode  = $botDepth > 0;
$paramColor = in_array($_GET['color']??'', ['white','black']) ? $_GET['color'] : 'white';
function esc($s){ return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Damas — Partida</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#080812;color:#fff;font-family:'Segoe UI',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:16px;gap:12px}
    :root{--c:#ef4444}
    a.back{font-size:.7rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;text-decoration:none;align-self:flex-start}
    a.back:hover{color:#888}
    h2{font-size:1.2rem;letter-spacing:2px;color:#ef4444;text-shadow:0 0 20px #ef444444}
    .screen{display:none;flex-direction:column;align-items:center;gap:14px;width:100%}
    .screen.active{display:flex}
    .room-code{font-size:2.8rem;font-weight:900;letter-spacing:8px;color:#ef4444;text-shadow:0 0 30px #ef444455;background:#0e0e1e;border:1px solid #ef444433;border-radius:10px;padding:16px 32px}
    .copy-btn{background:transparent;border:1px solid #1e1e38;border-radius:4px;color:#555;font-size:.72rem;padding:5px 14px;cursor:pointer;font-family:inherit;transition:all .12s}
    .copy-btn:hover{border-color:#ef444444;color:#ef4444}
    .wait-msg{color:#444;font-size:.82rem;animation:blink 1.4s infinite}
    @keyframes blink{0%,100%{opacity:.4}50%{opacity:1}}

    .game-wrap{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;justify-content:center;width:100%}

    /* Board */
    #board{display:grid;grid-template-columns:repeat(8,1fr);border:2px solid #2a2a40;border-radius:4px;overflow:hidden;user-select:none}
    .sq{width:52px;height:52px;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;transition:background .1s}
    .sq.light{background:#e8c99a;cursor:default}
    .sq.dark{background:#8b4513}
    .sq.selected{background:#ef444488!important}
    .sq.hint{background:#4ade8088!important}
    .sq.last-from{background:#fbbf2444!important}
    .sq.last-to{background:#fbbf2488!important}

    /* Pieces */
    .piece{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;letter-spacing:.5px;transition:transform .1s;box-shadow:0 2px 6px rgba(0,0,0,.5)}
    .piece-white{background:radial-gradient(circle at 35% 35%,#fff,#ccc);border:2px solid #aaa;color:#888}
    .piece-black{background:radial-gradient(circle at 35% 35%,#555,#111);border:2px solid #333;color:#888}
    .piece.king::after{content:'♛';font-size:1rem;position:absolute;color:gold;text-shadow:0 0 4px #000}
    .piece-white.king::after{color:#c8a000}
    .piece-black.king::after{color:#ffd700}

    /* Sidebar */
    .sidebar{display:flex;flex-direction:column;gap:10px;min-width:180px;max-width:220px}
    .panel{background:#0e0e1e;border:1px solid #181830;border-radius:8px;padding:12px 14px}
    .panel-title{font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:#333;margin-bottom:8px}
    .player-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #111128}
    .player-row:last-child{border-bottom:none}
    .player-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .player-dot.white{background:#fff;border:1px solid #555}
    .player-dot.black{background:#222;border:1px solid #888}
    .player-name{font-size:.82rem;color:#aaa;flex:1}
    .player-name.active{color:#fff;font-weight:600}
    .captures-count{font-size:.7rem;color:#ef4444;font-weight:600}
    .move-history{font-size:.7rem;color:#555;max-height:200px;overflow-y:auto;font-family:monospace;display:flex;flex-direction:column;gap:2px}
    .move-entry{color:#888}
    .btn{padding:8px 18px;border:none;border-radius:5px;font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:all .12s;width:100%}
    .btn-resign{background:transparent;border:1px solid #f8717133;color:#f87171}
    .btn-resign:hover{background:#f8717122}
    .btn-primary{background:var(--c);color:#fff;margin-top:4px}
    .btn-primary:hover{filter:brightness(1.1)}
    .status-msg{font-size:.78rem;color:#888;text-align:center;margin-bottom:6px}

    .result-box{background:#0e0e1e;border:1px solid #181830;border-radius:12px;padding:32px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:14px;max-width:340px}
    .result-title{font-size:1.5rem;font-weight:700;letter-spacing:3px}
    .result-sub{font-size:.82rem;color:#555}
    .result-emoji{font-size:2.5rem}

    @media(max-width:540px){
      .sq{width:40px;height:40px}
      .piece{width:30px;height:30px;font-size:.55rem}
      .sidebar{min-width:unset;width:100%;max-width:340px;flex-direction:row;flex-wrap:wrap}
      .panel{flex:1;min-width:150px}
    }
  </style>
</head>
<body>
<a class="back" href="index.php">← Damas</a>
<h2>DAMAS</h2>

<div class="screen <?= !$botMode && $roomId ? 'active' : '' ?>" id="screen-waiting">
  <p style="color:#555;font-size:.78rem">Sala criada! Compartilhe o código:</p>
  <div class="room-code" id="display-code"><?= esc($roomId) ?></div>
  <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= esc($roomId) ?>').then(()=>{this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar código',2000)})">Copiar código</button>
  <p class="wait-msg">Aguardando adversário...</p>
</div>

<div class="screen <?= $botMode ? 'active' : '' ?>" id="screen-game">
  <div class="game-wrap">
    <div><div id="board"></div></div>
    <div class="sidebar">
      <div class="panel" id="players-panel">
        <div class="panel-title">Jogadores</div>
        <div id="player-list"></div>
      </div>
      <div class="panel">
        <div class="panel-title">Movimentos</div>
        <div class="move-history" id="move-history"></div>
      </div>
      <div class="panel">
        <p class="status-msg" id="status-msg"></p>
        <button class="btn btn-resign" id="btn-resign">Desistir</button>
      </div>
    </div>
  </div>
</div>

<div class="screen" id="screen-result">
  <div class="result-box">
    <div class="result-emoji" id="result-emoji"></div>
    <div class="result-title" id="result-title"></div>
    <div class="result-sub" id="result-sub"></div>
    <button class="btn btn-primary" onclick="location.href='index.php'">Jogar novamente</button>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════════
//  Config
// ═══════════════════════════════════════════════════════════════════════════════
const BOT_MODE  = <?= $botMode?'true':'false' ?>;
const BOT_DEPTH = <?= $botDepth ?>;
const ROOM_ID   = '<?= esc($roomId) ?>';
const API       = 'api.php';

let player = null;
try { player = JSON.parse(localStorage.getItem('snake_user')); } catch(e) {}

let myToken = sessionStorage.getItem('damas_token') || '';
let myColor = sessionStorage.getItem('damas_color') || '<?= esc($paramColor) ?>';
if(BOT_MODE) myColor = '<?= esc($paramColor) ?>';

let pollTimer = null;
let gameOver  = false;

// ═══════════════════════════════════════════════════════════════════════════════
//  Damas Engine — Regras Brasileiras
// ═══════════════════════════════════════════════════════════════════════════════
// board: 8x8 array, null | {color:'white'|'black', king:bool}
// Turn: 'white' | 'black'
// State: {board, turn, mustCapture:{r,c}|null, moveHistory:[]}

function makeBoard() {
  const b = Array.from({length:8},()=>Array(8).fill(null));
  for(let r=0;r<8;r++) for(let c=0;c<8;c++){
    if((r+c)%2===0) continue;
    if(r<3) b[r][c]={color:'black',king:false};
    if(r>4) b[r][c]={color:'white',king:false};
  }
  return b;
}

function cloneBoard(b){ return b.map(row=>row.map(c=>c?{...c}:null)); }
function cloneState(s){ return {board:cloneBoard(s.board),turn:s.turn,mustCapture:s.mustCapture?{...s.mustCapture}:null,moveHistory:[...s.moveHistory],capturedW:s.capturedW,capturedB:s.capturedB,noCaptureMoves:s.noCaptureMoves,positionHistory:[...s.positionHistory]}; }

// Generate all legal moves for current state
function generateMoves(s) {
  const caps = generateCaptures(s);
  if(caps.length) return caps;
  return generateSimple(s);
}

function generateSimple(s) {
  const moves=[];
  for(let r=0;r<8;r++) for(let c=0;c<8;c++){
    const p=s.board[r][c];
    if(!p||p.color!==s.turn) continue;
    const dirs = p.king ? [[-1,-1],[-1,1],[1,-1],[1,1]] : (p.color==='white'?[[-1,-1],[-1,1]]:[[1,-1],[1,1]]);
    for(const [dr,dc] of dirs){
      if(p.king){
        let nr=r+dr,nc=c+dc;
        while(nr>=0&&nr<8&&nc>=0&&nc<8&&!s.board[nr][nc]){
          moves.push({from:{r,c},steps:[{to:{r:nr,c:nc},captured:null}]});
          nr+=dr; nc+=dc;
        }
      } else {
        const nr=r+dr,nc=c+dc;
        if(nr>=0&&nr<8&&nc>=0&&nc<8&&!s.board[nr][nc])
          moves.push({from:{r,c},steps:[{to:{r:nr,c:nc},captured:null}]});
      }
    }
  }
  return moves;
}

function generateCaptures(s) {
  const allCaps=[];
  const b=s.board;
  const col=s.turn;
  const forced=s.mustCapture;
  for(let r=0;r<8;r++) for(let c=0;c<8;c++){
    if(!b[r][c]||b[r][c].color!==col) continue;
    if(forced&&(r!==forced.r||c!==forced.c)) continue;
    const chains=captureChains(b,r,c,b[r][c].king,col,[]);
    chains.forEach(steps=>allCaps.push({from:{r,c},steps}));
  }
  if(!allCaps.length) return [];
  const maxLen=Math.max(...allCaps.map(m=>m.steps.length));
  return allCaps.filter(m=>m.steps.length===maxLen);
}

function captureChains(board, r, c, isKing, color, usedCaptures) {
  const opp = color==='white'?'black':'white';
  const dirs=[[-1,-1],[-1,1],[1,-1],[1,1]];
  let found=false;
  const results=[];

  for(const [dr,dc] of dirs){
    if(isKing){
      // Scan ray for opponent piece
      let nr=r+dr,nc=c+dc;
      while(nr>=0&&nr<8&&nc>=0&&nc<8){
        const cell=board[nr][nc];
        if(cell){
          if(cell.color===opp&&!usedCaptures.some(u=>u.r===nr&&u.c===nc)){
            // Try landing squares beyond
            let lr=nr+dr,lc=nc+dc;
            while(lr>=0&&lr<8&&lc>=0&&lc<8&&!board[lr][lc]){
              found=true;
              const newUsed=[...usedCaptures,{r:nr,c:nc}];
              const b2=cloneBoard(board);
              b2[r][c]=null;
              const piece=b2[nr][nc]; b2[nr][nc]=null;
              b2[lr][lc]={color,king:isKing};
              const capStep={to:{r:lr,c:lc},captured:{r:nr,c:nc}};
              const sub=captureChains(b2,lr,lc,isKing,color,newUsed);
              if(sub.length) sub.forEach(s=>results.push([capStep,...s]));
              else results.push([capStep]);
              lr+=dr; lc+=dc;
            }
          }
          break; // blocked
        }
        nr+=dr; nc+=dc;
      }
    } else {
      const mr=r+dr,mc=c+dc;
      const lr=r+dr*2,lc=c+dc*2;
      if(lr<0||lr>=8||lc<0||lc>=8) continue;
      const mid=board[mr][mc];
      if(!mid||mid.color!==opp||usedCaptures.some(u=>u.r===mr&&u.c===mc)) continue;
      if(board[lr][lc]) continue;
      found=true;
      const newUsed=[...usedCaptures,{r:mr,c:mc}];
      const b2=cloneBoard(board);
      b2[r][c]=null; b2[mr][mc]=null;
      b2[lr][lc]={color,king:isKing};
      const capStep={to:{r:lr,c:lc},captured:{r:mr,c:mc}};
      const sub=captureChains(b2,lr,lc,isKing,color,newUsed);
      if(sub.length) sub.forEach(s=>results.push([capStep,...s]));
      else results.push([capStep]);
    }
  }
  return results;
}

function applyMove(s, m) {
  const b=cloneBoard(s.board);
  const {r:fr,c:fc}=m.from;
  const piece={...b[fr][fc]};
  b[fr][fc]=null;

  // Apply all steps
  for(const step of m.steps){
    if(step.captured) b[step.captured.r][step.captured.c]=null;
  }
  const lastStep=m.steps[m.steps.length-1];
  const {r:tr,c:tc}=lastStep.to;

  // Promotion — check BEFORE placing
  const promotesNow=(piece.color==='white'&&tr===0)||(piece.color==='black'&&tr===7);
  if(promotesNow) piece.king=true;

  b[tr][tc]=piece;

  const capCount=m.steps.filter(st=>st.captured).length;
  const noCaptureMoves = capCount > 0 ? 0 : (s.noCaptureMoves || 0) + 1;

  // Serializa posição para detectar repetição
  const posKey = boardKey(b) + '|' + (s.turn==='white'?'black':'white');
  const positionHistory = [...(s.positionHistory||[]), posKey];

  const ns={
    board:b,
    turn:s.turn==='white'?'black':'white',
    mustCapture:null,
    moveHistory:[...s.moveHistory],
    capturedW: s.capturedW + (s.turn==='white' ? capCount : 0),
    capturedB: s.capturedB + (s.turn==='black' ? capCount : 0),
    noCaptureMoves,
    positionHistory,
  };

  const capStr = capCount > 0 ? ` (x${capCount})` : '';
  const desc=`${colorLabel(s.turn)} ${rc2label(fr,fc)}→${rc2label(tr,tc)}${capStr}`;
  ns.moveHistory.push(desc);
  return ns;
}

function colorLabel(c){ return c==='white'?'⚪':'⚫'; }
function rc2label(r,c){ return String.fromCharCode(65+c)+(8-r); }

// Serializa o tabuleiro em string compacta para detectar repetição
function boardKey(b) {
  let k='';
  for(let r=0;r<8;r++) for(let c=0;c<8;c++){
    const p=b[r][c];
    if(!p) k+='.';
    else k+=(p.color==='white'?'w':'b')+(p.king?'K':'p');
  }
  return k;
}

// Conta peças por tipo
function countPieces(b) {
  let wK=0,wP=0,bK=0,bP=0;
  for(let r=0;r<8;r++) for(let c=0;c<8;c++){
    const p=b[r][c]; if(!p) continue;
    if(p.color==='white') p.king?wK++:wP++;
    else                  p.king?bK++:bP++;
  }
  return {wK,wP,bK,bP};
}

// Verifica regra de perseguição: damas vs 1 dama
// Retorna o limite de movimentos sem captura para essa configuração, ou null se não se aplica
function kingPursuitLimit(b) {
  const {wK,wP,bK,bP}=countPieces(b);
  // Só se aplica quando um lado tem apenas 1 dama e o outro tem só damas
  const whiteOnlyKings = wP===0 && wK>0;
  const blackOnlyKings = bP===0 && bK>0;
  if(!whiteOnlyKings || !blackOnlyKings) return null;
  // 2 damas vs 1 dama = empate imediato (não é possível forçar vitória)
  const maxKings=Math.max(wK,bK), minKings=Math.min(wK,bK);
  if(minKings===1 && maxKings===2) return 0;  // empate imediato
  if(minKings===1 && maxKings<=5)  return 5;  // 5 movimentos para vencer
  return null;
}

// ── Evaluation ────────────────────────────────────────────────────────────────
function evaluate(s) {
  let score=0;
  for(let r=0;r<8;r++) for(let c=0;c<8;c++){
    const p=s.board[r][c];
    if(!p) continue;
    const v=p.king?300:100;
    score+=p.color==='white'?v:-v;
  }
  return score;
}

function minimax(s, depth, alpha, beta, isMax) {
  const moves=generateMoves(s);
  if(!moves.length||depth===0) return evaluate(s);
  if(isMax){
    let best=-Infinity;
    for(const m of moves){
      const ns=applyMove(s,m);
      best=Math.max(best,minimax(ns,depth-1,alpha,beta,false));
      alpha=Math.max(alpha,best); if(alpha>=beta) break;
    }
    return best;
  } else {
    let best=Infinity;
    for(const m of moves){
      const ns=applyMove(s,m);
      best=Math.min(best,minimax(ns,depth-1,alpha,beta,true));
      beta=Math.min(beta,best); if(alpha>=beta) break;
    }
    return best;
  }
}

function getBotMove(s) {
  const moves=generateMoves(s);
  if(!moves.length) return null;
  let best=null, bestScore=s.turn==='white'?-Infinity:Infinity;
  for(const m of moves){
    const ns=applyMove(s,m);
    const score=minimax(ns,BOT_DEPTH-1,-Infinity,Infinity,s.turn!=='white');
    if(s.turn==='white'?score>bestScore:score<bestScore){ bestScore=score; best=m; }
  }
  return best;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Game State
// ═══════════════════════════════════════════════════════════════════════════════
let gameState = {board:makeBoard(),turn:'white',mustCapture:null,moveHistory:[],capturedW:0,capturedB:0,noCaptureMoves:0,positionHistory:[]};
let selected  = null; // {r,c}
let hintMoves = [];   // moves from selected piece
let allMoves  = [];
let lastMove  = null;
let flipped   = myColor==='black';

// ── Render ────────────────────────────────────────────────────────────────────
function renderBoard() {
  const el=document.getElementById('board');
  el.innerHTML='';

  const hintTos=new Set();
  hintMoves.forEach(m=>{ const s=m.steps[m.steps.length-1]; hintTos.add(`${s.to.r},${s.to.c}`); });

  for(let i=0;i<64;i++){
    const idx=flipped?63-i:i;
    const r=Math.floor(idx/8),c=idx%8;
    const isLight=(r+c)%2===0;

    const div=document.createElement('div');
    div.className='sq '+(isLight?'light':'dark');
    div.dataset.r=r; div.dataset.c=c;

    if(selected&&selected.r===r&&selected.c===c) div.classList.add('selected');
    if(!isLight&&hintTos.has(`${r},${c}`)) div.classList.add('hint');
    if(lastMove){
      const lf=lastMove.from, ls=lastMove.steps[lastMove.steps.length-1];
      if(lf.r===r&&lf.c===c) div.classList.add('last-from');
      if(ls.to.r===r&&ls.to.c===c) div.classList.add('last-to');
    }

    const piece=gameState.board[r][c];
    if(piece&&!isLight){
      const pd=document.createElement('div');
      pd.className=`piece piece-${piece.color}${piece.king?' king':''}`;
      div.appendChild(pd);
    }

    if(!isLight) div.addEventListener('click',()=>onSqClick(r,c));
    el.appendChild(div);
  }
}

function onSqClick(r,c) {
  if(gameOver) return;
  const myTurn=gameState.turn===myColor;
  if(!myTurn) return;

  // Try move
  const move=hintMoves.find(m=>{ const s=m.steps[m.steps.length-1]; return s.to.r===r&&s.to.c===c; });
  if(move){ commitMove(move); return; }

  // Select piece
  const p=gameState.board[r][c];
  if(p&&p.color===myColor){
    // Check if this piece has captures when captures are mandatory
    const allCaps=generateCaptures(gameState);
    const hasCaps=allCaps.length>0;
    const pieceMoves=allMoves.filter(m=>m.from.r===r&&m.from.c===c);
    if(hasCaps&&!pieceMoves.some(m=>m.steps[0]?.captured)){
      selected=null; hintMoves=[]; renderBoard(); return; // piece can't capture, can't select
    }
    selected={r,c};
    hintMoves=pieceMoves;
  } else {
    selected=null; hintMoves=[];
  }
  renderBoard();
}

function commitMove(m) {
  lastMove=m;
  gameState=applyMove(gameState,m);
  selected=null; hintMoves=[];
  allMoves=generateMoves(gameState);
  renderBoard();
  updateSidebar();
  checkGameOver();

  if(!BOT_MODE&&ROOM_ID) sendMoveToServer(m);
  else if(BOT_MODE&&!gameOver) setTimeout(doBotMove,500);
}

function doBotMove() {
  if(gameOver||gameState.turn===myColor) return;
  const m=getBotMove(gameState);
  if(!m){ checkGameOver(); return; }
  lastMove=m;
  gameState=applyMove(gameState,m);
  allMoves=generateMoves(gameState);
  renderBoard();
  updateSidebar();
  checkGameOver();
}

function checkGameOver() {
  const s = gameState;

  // Sem movimentos → derrota de quem não pode mover
  if(!allMoves.length){
    const winner = s.turn==='white' ? 'Pretas' : 'Brancas';
    showResult(`${winner} vencem!`, 'Sem movimentos disponíveis', '🏆');
    if(!BOT_MODE) endGameOnServer((winner==='Brancas'?'white':'black')+'_wins','no_moves');
    return;
  }

  // Regra dos 20 movimentos sem captura
  if((s.noCaptureMoves||0) >= 20){
    showResult('Empate!', 'Regra dos 20 movimentos sem captura', '🤝');
    if(!BOT_MODE) endGameOnServer('draw','twenty_moves');
    return;
  }

  // Repetição de posição (3 vezes)
  const ph = s.positionHistory || [];
  if(ph.length >= 3){
    const last = ph[ph.length-1];
    const count = ph.filter(p=>p===last).length;
    if(count >= 3){
      showResult('Empate!', 'Mesma posição repetida 3 vezes', '🤝');
      if(!BOT_MODE) endGameOnServer('draw','repetition');
      return;
    }
  }

  // Regra de perseguição: damas vs 1 dama
  const limit = kingPursuitLimit(s.board);
  if(limit !== null){
    if(limit === 0){
      // 2 damas vs 1 dama = empate imediato
      showResult('Empate!', '2 damas contra 1 dama — vitória impossível', '🤝');
      if(!BOT_MODE) endGameOnServer('draw','king_pursuit');
      return;
    }
    // Conta movimentos desde que a configuração se formou usando noCaptureMoves
    if((s.noCaptureMoves||0) >= limit){
      showResult('Empate!', `${limit} movimentos sem captura — regra de perseguição`, '🤝');
      if(!BOT_MODE) endGameOnServer('draw','king_pursuit');
      return;
    }
  }
}

function showResult(title,sub,emoji) {
  gameOver=true; clearInterval(pollTimer);
  document.getElementById('result-title').textContent=title;
  document.getElementById('result-sub').textContent=sub;
  document.getElementById('result-emoji').textContent=emoji;
  showScreen('result');
}

// ── Sidebar ───────────────────────────────────────────────────────────────────
function updateSidebar() {
  const myTurn=gameState.turn===myColor;
  document.getElementById('status-msg').textContent=gameOver?'':(myTurn?'Sua vez':'Vez do adversário');
  const h=document.getElementById('move-history');
  h.innerHTML=gameState.moveHistory.slice(-30).map(m=>`<div class="move-entry">${m}</div>`).join('');
  h.scrollTop=h.scrollHeight;
}

function renderPlayers(white, black) {
  const pl=document.getElementById('player-list');
  const myTurn=gameState.turn;
  pl.innerHTML=`
    <div class="player-row">
      <span class="player-dot white"></span>
      <span class="player-name ${myTurn==='white'?'active':''}">${white}</span>
      <span class="captures-count">${gameState.capturedW||0}×</span>
    </div>
    <div class="player-row">
      <span class="player-dot black"></span>
      <span class="player-name ${myTurn==='black'?'active':''}">${black}</span>
      <span class="captures-count">${gameState.capturedB||0}×</span>
    </div>`;
}

function showScreen(id) {
  ['waiting','game','result'].forEach(s=>document.getElementById('screen-'+s).classList.toggle('active',s===id));
}

// ── Server sync ───────────────────────────────────────────────────────────────
async function sendMoveToServer(m) {
  if(!ROOM_ID||!myToken) return;
  const state={board:gameState.board,turn:gameState.turn,moveHistory:gameState.moveHistory,lastMove:m,mustCapture:gameState.mustCapture,capturedW:gameState.capturedW,capturedB:gameState.capturedB,noCaptureMoves:gameState.noCaptureMoves,positionHistory:gameState.positionHistory};
  const body={action:'move',roomId:ROOM_ID,token:myToken,move:m,state};
  if(gameOver){ body.result=gameState.turn==='white'?'black_wins':'white_wins'; body.resultReason='no_moves'; }
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).catch(()=>{});
}

async function endGameOnServer(result,reason) {
  if(!ROOM_ID||!myToken) return;
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'move',roomId:ROOM_ID,token:myToken,move:null,
      state:{board:gameState.board,turn:gameState.turn,moveHistory:gameState.moveHistory},
      result,resultReason:reason})
  }).catch(()=>{});
}

async function poll() {
  if(gameOver) return;
  const res=await fetch(`${API}?action=poll&roomId=${ROOM_ID}&token=${myToken}`).then(r=>r.json()).catch(()=>null);
  if(!res) return;

  if(res.status==='playing'&&document.getElementById('screen-waiting').classList.contains('active')){
    const white=res.players.find(p=>p.color==='white');
    const black=res.players.find(p=>p.color==='black');
    renderPlayers(white?.name||'?',black?.name||'?');
    startGame(); return;
  }

  if(res.status==='finished'&&!gameOver){
    applyServerState(res.state);
    const r=res.result;
    if(r==='draw') showResult('Empate!','Fim de jogo','🤝');
    else if(r==='white_wins') showResult('Brancas vencem!',res.resultReason==='resignation'?'Desistência':'','🏆');
    else showResult('Pretas vencem!',res.resultReason==='resignation'?'Desistência':'','🏆');
    return;
  }

  if(res.status==='playing'){
    const serverTurn=res.state.turn;
    if(serverTurn!==gameState.turn){
      applyServerState(res.state);
    }
    const white=res.players.find(p=>p.color==='white');
    const black=res.players.find(p=>p.color==='black');
    renderPlayers(white?.name||'?',black?.name||'?');
    updateSidebar();
  }
}

function applyServerState(state) {
  gameState={
    board:state.board,
    turn:state.turn,
    mustCapture:state.mustCapture||null,
    moveHistory:state.moveHistory||[],
    capturedW:state.capturedW||0,
    capturedB:state.capturedB||0,
    noCaptureMoves:state.noCaptureMoves||0,
    positionHistory:state.positionHistory||[],
  };
  if(state.lastMove) lastMove=state.lastMove;
  allMoves=generateMoves(gameState);
  selected=null; hintMoves=[];
  renderBoard(); updateSidebar(); checkGameOver();
}

// ── Start ─────────────────────────────────────────────────────────────────────
function startGame() {
  if(BOT_MODE){
    renderPlayers(myColor==='white'?(player?.name||'Você'):'Bot', myColor==='black'?(player?.name||'Você'):'Bot');
  }
  allMoves=generateMoves(gameState);
  renderBoard(); updateSidebar();
  showScreen('game');
  if(BOT_MODE&&myColor==='black') setTimeout(doBotMove,600);
}

document.getElementById('btn-resign').addEventListener('click',async()=>{
  if(!confirm('Deseja desistir?')) return;
  if(BOT_MODE){ showResult('Bot vence!','Você desistiu','🏳️'); return; }
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'resign',roomId:ROOM_ID,token:myToken})}).catch(()=>{});
  showResult(myColor==='white'?'Pretas vencem!':'Brancas vencem!','Você desistiu','🏳️');
});

// Init
if(BOT_MODE){
  flipped=myColor==='black';
  startGame();
} else if(ROOM_ID){
  (async()=>{
    const res=await fetch(`${API}?action=poll&roomId=${ROOM_ID}&token=${myToken}`).then(r=>r.json()).catch(()=>null);
    if(!res){ alert('Sala não encontrada'); location.href='index.php'; return; }
    if(res.status==='playing'){
      myColor=res.players.find(p=>p.username===player?.username)?.color||myColor;
      flipped=myColor==='black';
      const white=res.players.find(p=>p.color==='white');
      const black=res.players.find(p=>p.color==='black');
      renderPlayers(white?.name||'?',black?.name||'?');
      applyServerState(res.state);
      showScreen('game');
    } else {
      flipped=myColor==='black';
      showScreen('waiting');
    }
    pollTimer=setInterval(poll,1500);
  })();
}
</script>
</body>
</html>
