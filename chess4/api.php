<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

define('ROOMS_DIR', __DIR__ . '/rooms/');
define('ROOM_TTL',  86400);   // 24h

if (!is_dir(ROOMS_DIR)) mkdir(ROOMS_DIR, 0775, true);

// ── Helpers ──────────────────────────────────────────────────────────────────
function cleanId(string $s): string {
    return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($s)));
}
function esc(string $s): string {
    return substr(strip_tags(trim($s)), 0, 50);
}
function roomFile(string $id): string {
    return ROOMS_DIR . $id . '.json';
}
function purgeExpired(): void {
    foreach (glob(ROOMS_DIR . '*.json') as $f)
        if (filemtime($f) < time() - ROOM_TTL) @unlink($f);
}
function loadRoom(string $id): ?array {
    $f = roomFile($id);
    if (!file_exists($f)) return null;
    $data = json_decode(file_get_contents($f), true);
    return is_array($data) ? $data : null;
}
function saveRoom(array $room): void {
    $room['updatedAt'] = time();
    $f  = roomFile($room['id']);
    $fp = fopen($f, 'c+');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
}
function stripTokens(array $room): array {
    foreach ($room['players'] as &$p) unset($p['token']);
    return $room;
}
function tokenOf(array $room, string $token): ?int {
    foreach ($room['players'] as $i => $p)
        if (($p['token'] ?? '') === $token) return $i;
    return null;
}

// ── Tabuleiro 14×14 inicial ───────────────────────────────────────────────────
// Notação: "<COLOR><PIECE>" ex: "Rk"=red king, "Bq"=blue queen
// Peças: k=king q=queen r=rook b=bishop n=knight p=pawn
// Cores: R=red Y=yellow G=green U=blue (B ocupado por bishop)
// Casas inválidas (cantos 3×3): null
// Casas vazias: ""

function initialBoard(): array {
    $b = [];
    for ($r = 0; $r < 14; $r++) {
        $b[$r] = [];
        for ($c = 0; $c < 14; $c++) {
            // Cantos cortados (3×3 em cada extremo)
            if (($r < 3 && $c < 3) || ($r < 3 && $c > 10) ||
                ($r > 10 && $c < 3) || ($r > 10 && $c > 10)) {
                $b[$r][$c] = null;
            } else {
                $b[$r][$c] = '';
            }
        }
    }

    // ── Vermelho (Red) — linhas 12-13, colunas 3-10 ──
    // Fileira de peças: linha 13
    $b[13][3]='Rr'; $b[13][4]='Rn'; $b[13][5]='Rb'; $b[13][6]='Rq';
    $b[13][7]='Rk'; $b[13][8]='Rb'; $b[13][9]='Rn'; $b[13][10]='Rr';
    // Peões: linha 12
    for ($c=3;$c<=10;$c++) $b[12][$c]='Rp';

    // ── Azul (Blue) — colunas 0-1, linhas 3-10 ──
    // Fileira de peças: coluna 0
    $b[3][0]='Ur'; $b[4][0]='Un'; $b[5][0]='Ub'; $b[6][0]='Uk';
    $b[7][0]='Uq'; $b[8][0]='Ub'; $b[9][0]='Un'; $b[10][0]='Ur';
    // Peões: coluna 1
    for ($r=3;$r<=10;$r++) $b[$r][1]='Up';

    // ── Amarelo (Yellow) — linhas 0-1, colunas 3-10 ──
    // Fileira de peças: linha 0
    $b[0][3]='Yr'; $b[0][4]='Yn'; $b[0][5]='Yb'; $b[0][6]='Yk';
    $b[0][7]='Yq'; $b[0][8]='Yb'; $b[0][9]='Yn'; $b[0][10]='Yr';
    // Peões: linha 1
    for ($c=3;$c<=10;$c++) $b[1][$c]='Yp';

    // ── Verde (Green) — colunas 12-13, linhas 3-10 ──
    // Fileira de peças: coluna 13
    $b[3][13]='Gr'; $b[4][13]='Gn'; $b[5][13]='Gb'; $b[6][13]='Gq';
    $b[7][13]='Gk'; $b[8][13]='Gb'; $b[9][13]='Gn'; $b[10][13]='Gr';
    // Peões: coluna 12
    for ($r=3;$r<=10;$r++) $b[$r][12]='Gp';

    return $b;
}

function initialState(): array {
    return [
        'board'       => initialBoard(),
        'turn'        => 0,  // índice do jogador (0=red,1=blue,2=yellow,3=green)
        'moveHistory' => [],
        'lastMove'    => null,
        'eliminated'  => [],  // índices de jogadores eliminados
    ];
}

purgeExpired();

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET'
    ? ($_GET['action']  ?? '')
    : (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

$body = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $rooms = [];
    foreach (glob(ROOMS_DIR . '*.json') as $f) {
        $r = json_decode(file_get_contents($f), true);
        if (!is_array($r) || $r['status'] !== 'waiting') continue;
        $rooms[] = [
            'id'        => $r['id'],
            'name'      => $r['name'] ?? $r['id'],
            'creator'   => $r['players'][0]['name'] ?? '—',
            'slots'     => count(array_filter($r['players'], fn($p) => !$p['isBot'])),
            'bots'      => count(array_filter($r['players'], fn($p) => $p['isBot'])),
            'createdAt' => $r['createdAt'],
        ];
    }
    usort($rooms, fn($a,$b) => $b['createdAt'] - $a['createdAt']);
    echo json_encode($rooms); exit;
}

// ── GET poll ─────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'poll') {
    $id    = cleanId($_GET['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
    $room  = $id ? loadRoom($id) : null;
    if (!$room) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($token && tokenOf($room, $token) === null) {
        http_response_code(403); echo json_encode(['error'=>'Token inválido']); exit;
    }

    // Se houver bot para jogar, executa movimento do bot
    if ($room['status'] === 'playing') {
        $room = maybeBotMove($room);
    }

    echo json_encode(stripTokens($room)); exit;
}

// ── POST create ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', substr($body['username'] ?? '', 0, 20));
    $name     = esc($body['name'] ?? '');
    $roomName = esc($body['roomName'] ?? '');
    $slot     = max(0, min(3, (int)($body['slot'] ?? 0)));  // 0=red,1=blue,2=yellow,3=green
    $bots     = max(0, min(3, (int)($body['bots'] ?? 3)));  // quantos bots preencher

    if (strlen($username) < 3 || strlen($name) < 2) {
        http_response_code(400); echo json_encode(['error'=>'Dados inválidos']); exit;
    }

    $id    = strtoupper(bin2hex(random_bytes(3)));
    $token = bin2hex(random_bytes(16));

    $colors  = ['red','blue','yellow','green'];
    $players = [];

    for ($i = 0; $i < 4; $i++) {
        if ($i === $slot) {
            $players[] = [
                'slot'     => $i,
                'color'    => $colors[$i],
                'username' => $username,
                'name'     => $name,
                'isBot'    => false,
                'token'    => $token,
                'alive'    => true,
            ];
        } else {
            $isBot = ($bots > 0);
            if ($isBot) $bots--;
            $players[] = [
                'slot'     => $i,
                'color'    => $colors[$i],
                'username' => '',
                'name'     => $isBot ? 'Bot' : '—',
                'isBot'    => $isBot,
                'token'    => '',
                'alive'    => true,
            ];
        }
    }

    // Quantos slots humanos estão abertos (sem bot e sem jogador)?
    $openSlots = count(array_filter($players, fn($p) => !$p['isBot'] && $p['username'] === ''));
    $status    = $openSlots > 0 ? 'waiting' : 'playing';

    $room = [
        'id'        => $id,
        'name'      => $roomName ?: "$name #$id",
        'status'    => $status,
        'createdAt' => time(),
        'updatedAt' => time(),
        'result'    => null,
        'winner'    => null,
        'players'   => $players,
        'state'     => initialState(),
    ];
    saveRoom($room);
    echo json_encode(['roomId'=>$id, 'token'=>$token, 'slot'=>$slot, 'color'=>$colors[$slot]]); exit;
}

// ── POST join ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'join') {
    $id       = cleanId($body['roomId'] ?? '');
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', substr($body['username'] ?? '', 0, 20));
    $name     = esc($body['name'] ?? '');
    $slot     = isset($body['slot']) ? max(0, min(3, (int)$body['slot'])) : -1;

    if (!$id || strlen($username) < 3 || strlen($name) < 2) {
        http_response_code(400); echo json_encode(['error'=>'Dados inválidos']); exit;
    }

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($room['status'] !== 'waiting') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Sala já iniciada ou encerrada']); exit; }

    // Encontrar slot aberto (não-bot, sem token)
    $assignedSlot = -1;
    foreach ($room['players'] as $i => $p) {
        if (!$p['isBot'] && empty($p['token'])) {
            if ($slot === -1 || $slot === $i) {
                $assignedSlot = $i;
                break;
            }
        }
    }
    if ($assignedSlot === -1) { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Nenhum slot disponível']); exit; }

    $token = bin2hex(random_bytes(16));
    $room['players'][$assignedSlot]['username'] = $username;
    $room['players'][$assignedSlot]['name']     = $name;
    $room['players'][$assignedSlot]['token']    = $token;

    // Verificar se todos os slots humanos estão preenchidos
    $openSlots = count(array_filter($room['players'], fn($p) => !$p['isBot'] && empty($p['token'])));
    if ($openSlots === 0) $room['status'] = 'playing';

    $room['updatedAt'] = time();
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    $colors = ['red','blue','yellow','green'];
    echo json_encode(['roomId'=>$id, 'token'=>$token, 'slot'=>$assignedSlot, 'color'=>$colors[$assignedSlot]]); exit;
}

// ── POST move ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'move') {
    $id    = cleanId($body['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');
    $from  = $body['from'] ?? null;  // [row, col]
    $to    = $body['to']   ?? null;  // [row, col]
    $promo = $body['promo'] ?? null; // 'q','r','b','n'

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($room['status'] !== 'playing') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Partida não está em andamento']); exit; }

    $playerIdx = tokenOf($room, $token);
    if ($playerIdx === null) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Token inválido']); exit; }

    $turn = $room['state']['turn'];
    if ($playerIdx !== $turn) { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Não é sua vez']); exit; }

    if (!is_array($from) || !is_array($to) || count($from)<2 || count($to)<2) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(400); echo json_encode(['error'=>'Movimento inválido']); exit;
    }

    $fr = (int)$from[0]; $fc = (int)$from[1];
    $tr = (int)$to[0];   $tc = (int)$to[1];

    $board   = $room['state']['board'];
    $piece   = $board[$fr][$fc] ?? '';
    $colors  = ['R','U','Y','G'];
    $colorLetter = $colors[$playerIdx];

    if (empty($piece) || $piece[0] !== $colorLetter) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(400); echo json_encode(['error'=>'Peça inválida']); exit;
    }

    // Aplicar movimento
    $captured = $board[$tr][$tc];
    $board[$tr][$tc] = $piece;
    $board[$fr][$fc] = '';

    // Promoção de peão
    if ($piece[1] === 'p') {
        if (($playerIdx === 0 && $tr === 0) ||   // red sobe
            ($playerIdx === 1 && $tc === 13) ||  // blue vai para direita
            ($playerIdx === 2 && $tr === 13) ||  // yellow desce
            ($playerIdx === 3 && $tc === 0)) {   // green vai para esquerda
            $promoP = in_array($promo, ['q','r','b','n']) ? $promo : 'q';
            $board[$tr][$tc] = $colorLetter . $promoP;
        }
    }

    // Verificar se capturou um rei (eliminação)
    $eliminated = $room['state']['eliminated'];
    if ($captured && strlen($captured) === 2 && $captured[1] === 'k') {
        $capturedColorIdx = array_search($captured[0], $colors);
        if ($capturedColorIdx !== false && !in_array($capturedColorIdx, $eliminated)) {
            $eliminated[] = $capturedColorIdx;
            $room['players'][$capturedColorIdx]['alive'] = false;
            // Remover todas as peças do eliminado
            for ($r = 0; $r < 14; $r++) {
                for ($c = 0; $c < 14; $c++) {
                    if ($board[$r][$c] !== null && $board[$r][$c] !== '' && $board[$r][$c][0] === $captured[0]) {
                        $board[$r][$c] = '';
                    }
                }
            }
        }
    }

    // Registrar jogada
    $room['state']['board']     = $board;
    $room['state']['eliminated'] = $eliminated;
    $room['state']['lastMove']  = ['from'=>[$fr,$fc],'to'=>[$tr,$tc],'player'=>$playerIdx];
    $room['state']['moveHistory'][] = [
        'player' => $playerIdx,
        'from'   => [$fr,$fc],
        'to'     => [$tr,$tc],
        'piece'  => $piece,
        'captured' => $captured ?: null,
    ];

    // Verificar vencedor (só 1 rei restante)
    $aliveCount = count(array_filter($room['players'], fn($p) => $p['alive']));
    if ($aliveCount === 1) {
        $winnerIdx = array_keys(array_filter($room['players'], fn($p) => $p['alive']))[0];
        $room['status'] = 'finished';
        $room['winner'] = $winnerIdx;
        $room['result'] = $room['players'][$winnerIdx]['name'] . ' venceu!';
    } else {
        // Próximo turno (pular eliminados)
        $next = ($turn + 1) % 4;
        $safety = 0;
        while (in_array($next, $eliminated) && $safety < 4) {
            $next = ($next + 1) % 4;
            $safety++;
        }
        $room['state']['turn'] = $next;
    }

    $room['updatedAt'] = time();
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(stripTokens($room)); exit;
}

// ── POST resign ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'resign') {
    $id    = cleanId($body['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }

    $playerIdx = tokenOf($room, $token);
    if ($playerIdx === null) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Token inválido']); exit; }

    $room['players'][$playerIdx]['alive'] = false;
    $eliminated = $room['state']['eliminated'];
    if (!in_array($playerIdx, $eliminated)) $eliminated[] = $playerIdx;
    $room['state']['eliminated'] = $eliminated;

    // Remover peças do desistente
    $colors = ['R','U','Y','G'];
    $cl = $colors[$playerIdx];
    $board = $room['state']['board'];
    for ($r=0;$r<14;$r++) for ($c=0;$c<14;$c++)
        if ($board[$r][$c] !== null && $board[$r][$c] !== '' && $board[$r][$c][0] === $cl)
            $board[$r][$c] = '';
    $room['state']['board'] = $board;

    $aliveCount = count(array_filter($room['players'], fn($p) => $p['alive']));
    if ($aliveCount === 1) {
        $winnerIdx = array_keys(array_filter($room['players'], fn($p) => $p['alive']))[0];
        $room['status'] = 'finished';
        $room['winner'] = $winnerIdx;
        $room['result'] = $room['players'][$winnerIdx]['name'] . ' venceu!';
    } else if ($room['state']['turn'] === $playerIdx) {
        $next = ($playerIdx + 1) % 4;
        $safety = 0;
        while (in_array($next, $eliminated) && $safety < 4) {
            $next = ($next + 1) % 4; $safety++;
        }
        $room['state']['turn'] = $next;
    }

    $room['updatedAt'] = time();
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(stripTokens($room)); exit;
}

// ── Bot move (helper chamado durante poll) ───────────────────────────────────
function maybeBotMove(array $room): array {
    $state = $room['state'];
    $turn  = $state['turn'];
    if (!$room['players'][$turn]['isBot']) return $room;

    $board    = $state['board'];
    $colors   = ['R','U','Y','G'];
    $cl       = $colors[$turn];
    $eliminated = $state['eliminated'];

    // Coleta movimentos válidos do bot (movimento aleatório simples)
    $moves = [];
    for ($r=0;$r<14;$r++) {
        for ($c=0;$c<14;$c++) {
            if ($board[$r][$c] === null || $board[$r][$c] === '' || $board[$r][$c][0] !== $cl) continue;
            $targets = getBotMoves($board, $r, $c, $turn, $eliminated);
            foreach ($targets as $t) $moves[] = ['from'=>[$r,$c],'to'=>$t];
        }
    }

    if (empty($moves)) {
        // Bot sem movimentos — desiste
        $room['players'][$turn]['alive'] = false;
        if (!in_array($turn, $eliminated)) $eliminated[] = $turn;
        $room['state']['eliminated'] = $eliminated;

        $aliveCount = count(array_filter($room['players'], fn($p) => $p['alive']));
        if ($aliveCount === 1) {
            $wi = array_keys(array_filter($room['players'], fn($p) => $p['alive']))[0];
            $room['status'] = 'finished';
            $room['winner'] = $wi;
            $room['result'] = $room['players'][$wi]['name'] . ' venceu!';
        } else {
            $next = ($turn+1)%4; $s=0;
            while(in_array($next,$eliminated)&&$s<4){$next=($next+1)%4;$s++;}
            $room['state']['turn'] = $next;
        }
        saveRoom($room);
        return $room;
    }

    // Preferir capturas (especialmente rei)
    $captures = array_filter($moves, function($m) use ($board) {
        $t = $board[$m['to'][0]][$m['to'][1]];
        return $t !== null && $t !== '';
    });
    $kingCaptures = array_filter($captures, function($m) use ($board) {
        $t = $board[$m['to'][0]][$m['to'][1]];
        return $t !== null && $t !== '' && strlen($t)===2 && $t[1]==='k';
    });

    if (!empty($kingCaptures)) {
        $mv = array_values($kingCaptures)[0];
    } elseif (!empty($captures)) {
        $mv = array_values($captures)[array_rand(array_values($captures))];
    } else {
        $mv = $moves[array_rand($moves)];
    }

    [$fr,$fc] = $mv['from'];
    [$tr,$tc] = $mv['to'];
    $piece    = $board[$fr][$fc];
    $captured = $board[$tr][$tc];

    $board[$tr][$tc] = $piece;
    $board[$fr][$fc] = '';

    // Promoção automática para rainha
    if ($piece[1]==='p') {
        if (($turn===0&&$tr===0)||($turn===1&&$tc===13)||($turn===2&&$tr===13)||($turn===3&&$tc===0))
            $board[$tr][$tc] = $cl.'q';
    }

    // Captura de rei → eliminação
    if ($captured && strlen($captured)===2 && $captured[1]==='k') {
        $ci = array_search($captured[0], $colors);
        if ($ci!==false && !in_array($ci,$eliminated)) {
            $eliminated[] = $ci;
            $room['players'][$ci]['alive'] = false;
            for ($r=0;$r<14;$r++) for ($c=0;$c<14;$c++)
                if ($board[$r][$c]!==null && $board[$r][$c]!=='' && $board[$r][$c][0]===$captured[0])
                    $board[$r][$c]='';
        }
    }

    $room['state']['board']       = $board;
    $room['state']['eliminated']  = $eliminated;
    $room['state']['lastMove']    = ['from'=>[$fr,$fc],'to'=>[$tr,$tc],'player'=>$turn];
    $room['state']['moveHistory'][] = ['player'=>$turn,'from'=>[$fr,$fc],'to'=>[$tr,$tc],'piece'=>$piece,'captured'=>$captured?:null];

    $aliveCount = count(array_filter($room['players'], fn($p) => $p['alive']));
    if ($aliveCount===1) {
        $wi = array_keys(array_filter($room['players'], fn($p) => $p['alive']))[0];
        $room['status']='finished'; $room['winner']=$wi;
        $room['result']=$room['players'][$wi]['name'].' venceu!';
    } else {
        $next=($turn+1)%4; $s=0;
        while(in_array($next,$eliminated)&&$s<4){$next=($next+1)%4;$s++;}
        $room['state']['turn']=$next;
    }

    saveRoom($room);
    return $room;
}

// ── Gerador de movimentos para bot (simplificado) ───────────────────────────
function isValid(array $board, int $r, int $c, string $myColor): bool {
    if ($r<0||$r>13||$c<0||$c>13) return false;
    if ($board[$r][$c]===null) return false;           // casa inválida
    if ($board[$r][$c]!=='' && $board[$r][$c][0]===$myColor) return false; // própria peça
    return true;
}

function getBotMoves(array $board, int $r, int $c, int $playerIdx, array $eliminated): array {
    $colors = ['R','U','Y','G'];
    $cl = $colors[$playerIdx];
    $piece = $board[$r][$c];
    if (strlen($piece)<2) return [];
    $type = $piece[1];
    $moves = [];

    // Direções de avanço do peão por jogador: 0=red(up=-1), 1=blue(right=+1col), 2=yellow(down=+1), 3=green(left=-1col)
    $pawnDirs = [[-1,0],[0,1],[1,0],[0,-1]];
    // Diagonais de captura do peão
    $pawnCapDirs = [
        [[-1,-1],[-1,1]],   // red
        [[-1,1],[1,1]],     // blue
        [[1,-1],[1,1]],     // yellow
        [[-1,-1],[1,-1]],   // green
    ];

    switch ($type) {
        case 'p':
            [$dr,$dc] = $pawnDirs[$playerIdx];
            $nr=$r+$dr; $nc=$c+$dc;
            if ($nr>=0&&$nr<=13&&$nc>=0&&$nc<=13&&$board[$nr][$nc]!==null&&$board[$nr][$nc]==='')
                $moves[]=[$nr,$nc];
            foreach ($pawnCapDirs[$playerIdx] as [$cdr,$cdc]) {
                $nr2=$r+$cdr; $nc2=$c+$cdc;
                if ($nr2>=0&&$nr2<=13&&$nc2>=0&&$nc2<=13&&$board[$nr2][$nc2]!==null&&$board[$nr2][$nc2]!==''&&$board[$nr2][$nc2][0]!==$cl)
                    $moves[]=[$nr2,$nc2];
            }
            break;
        case 'r':
            foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dr,$dc]) {
                for ($i=1;$i<14;$i++) {
                    $nr=$r+$dr*$i; $nc=$c+$dc*$i;
                    if (!isValid($board,$nr,$nc,$cl)) break;
                    $moves[]=[$nr,$nc];
                    if ($board[$nr][$nc]!=='') break;
                }
            }
            break;
        case 'b':
            foreach ([[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr,$dc]) {
                for ($i=1;$i<14;$i++) {
                    $nr=$r+$dr*$i; $nc=$c+$dc*$i;
                    if (!isValid($board,$nr,$nc,$cl)) break;
                    $moves[]=[$nr,$nc];
                    if ($board[$nr][$nc]!=='') break;
                }
            }
            break;
        case 'q':
            foreach ([[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr,$dc]) {
                for ($i=1;$i<14;$i++) {
                    $nr=$r+$dr*$i; $nc=$c+$dc*$i;
                    if (!isValid($board,$nr,$nc,$cl)) break;
                    $moves[]=[$nr,$nc];
                    if ($board[$nr][$nc]!=='') break;
                }
            }
            break;
        case 'k':
            foreach ([[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr,$dc]) {
                $nr=$r+$dr; $nc=$c+$dc;
                if (isValid($board,$nr,$nc,$cl)) $moves[]=[$nr,$nc];
            }
            break;
        case 'n':
            foreach ([[2,1],[2,-1],[-2,1],[-2,-1],[1,2],[1,-2],[-1,2],[-1,-2]] as [$dr,$dc]) {
                $nr=$r+$dr; $nc=$c+$dc;
                if (isValid($board,$nr,$nc,$cl)) $moves[]=[$nr,$nc];
            }
            break;
    }
    return $moves;
}

http_response_code(400);
echo json_encode(['error' => 'Ação desconhecida']);
