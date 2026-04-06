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
function initialChessFen(): string {
    return 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
}
function initialDamasBoard(): array {
    $b = array_fill(0, 8, array_fill(0, 8, null));
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if (($r + $c) % 2 === 0) continue;
            if ($r < 3) $b[$r][$c] = ['color' => 'black', 'king' => false];
            if ($r > 4) $b[$r][$c] = ['color' => 'white', 'king' => false];
        }
    }
    return $b;
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
    echo json_encode(stripTokens($room)); exit;
}

// ── POST create ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', substr($body['username'] ?? '', 0, 20));
    $name     = esc($body['name'] ?? '');
    $game     = in_array($body['game'] ?? '', ['chess','damas']) ? $body['game'] : 'chess';
    $roomName = esc($body['roomName'] ?? '');
    $color    = ($body['color'] ?? 'white') === 'black' ? 'black' : 'white';

    if (strlen($username) < 3 || strlen($name) < 2) {
        http_response_code(400); echo json_encode(['error'=>'Dados inválidos']); exit;
    }

    $id    = strtoupper(bin2hex(random_bytes(3)));
    $token = bin2hex(random_bytes(16));

    $room = [
        'id'          => $id,
        'game'        => $game,
        'name'        => $roomName ?: "$name #$id",
        'mode'        => 'pvp',
        'status'      => 'waiting',
        'createdAt'   => time(),
        'updatedAt'   => time(),
        'result'      => null,
        'resultReason'=> null,
        'players'     => [
            ['color'=>$color, 'username'=>$username, 'name'=>$name, 'token'=>$token]
        ],
        'state' => $game === 'chess'
            ? ['fen' => initialChessFen(), 'moveHistory' => [], 'lastMove' => null]
            : ['board' => initialDamasBoard(), 'turn' => 'white', 'moveHistory' => [], 'lastMove' => null, 'mustCapture' => null],
    ];
    saveRoom($room);
    echo json_encode(['roomId' => $id, 'token' => $token, 'color' => $color]); exit;
}

// ── POST join ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'join') {
    $id       = cleanId($body['roomId'] ?? '');
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', substr($body['username'] ?? '', 0, 20));
    $name     = esc($body['name'] ?? '');

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
    if (count($room['players']) >= 2) { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Sala cheia']); exit; }

    $existingColor = $room['players'][0]['color'];
    $color = $existingColor === 'white' ? 'black' : 'white';
    $token = bin2hex(random_bytes(16));

    $room['players'][] = ['color'=>$color,'username'=>$username,'name'=>$name,'token'=>$token];
    $room['status']    = 'playing';
    $room['updatedAt'] = time();

    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['roomId'=>$id,'token'=>$token,'color'=>$color]); exit;
}

// ── POST move ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'move') {
    $id    = cleanId($body['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');
    $move  = $body['move'] ?? null;

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($room['status'] !== 'playing') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Partida não está em andamento']); exit; }

    $playerIdx = tokenOf($room, $token);
    if ($playerIdx === null) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Token inválido']); exit; }

    $playerColor = $room['players'][$playerIdx]['color'];

    // Valida turno (servidor só checa turno e ownership — legalidade é no client)
    if ($room['game'] === 'chess') {
        $fen = $room['state']['fen'];
        $parts = explode(' ', $fen);
        $turn = $parts[1] ?? 'w';
        $colorChar = $playerColor === 'white' ? 'w' : 'b';
        if ($turn !== $colorChar) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Não é sua vez']); exit; }
    } else {
        $turn = $room['state']['turn'];
        if ($turn !== $playerColor) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Não é sua vez']); exit; }
    }

    // Aplica estado enviado pelo client
    if (isset($body['state'])) {
        $room['state']     = $body['state'];
        $room['updatedAt'] = time();
    }

    // Resultado
    if (isset($body['result'])) {
        $room['result']      = $body['result'];
        $room['resultReason']= $body['resultReason'] ?? null;
        $room['status']      = 'finished';
    }

    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok' => true]); exit;
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

    $loserColor  = $room['players'][$playerIdx]['color'];
    $winnerColor = $loserColor === 'white' ? 'black' : 'white';

    $room['status']      = 'finished';
    $room['result']      = $winnerColor . '_wins';
    $room['resultReason']= 'resignation';
    $room['updatedAt']   = time();

    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok' => true]); exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
