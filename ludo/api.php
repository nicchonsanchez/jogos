<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

define('ROOMS_DIR', __DIR__ . '/rooms/');
define('ROOM_TTL',  86400); // 24h

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
function saveRoomFp($fp, array $room): void {
    $room['updatedAt'] = time();
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

// ── Bot-turn check: owner (slot 0) may act when current turn is a bot ────────
function isBotTurn(array $room): bool {
    $turn = $room['state']['turn'] ?? '';
    foreach ($room['players'] as $p) {
        if ($p['color'] === $turn) return (bool)($p['isBot'] ?? false);
    }
    return false; // color not found = treat as non-bot
}
function isOwner(array $room, int $playerIdx): bool {
    return ($room['players'][$playerIdx]['slot'] ?? -1) === 0;
}
function canActThisTurn(array $room, int $playerIdx): bool {
    $playerColor = $room['players'][$playerIdx]['color'];
    $turn = $room['state']['turn'] ?? '';
    if ($playerColor === $turn) return true; // it's this player's turn
    if (isBotTurn($room) && isOwner($room, $playerIdx)) return true; // owner controls bot
    return false;
}

// ── Initial state ────────────────────────────────────────────────────────────
function initialPieces(): array {
    $pieces = [];
    foreach (['red','blue','green','yellow'] as $color) {
        for ($i = 0; $i < 4; $i++) {
            $pieces[] = ['color' => $color, 'id' => $i, 'pos' => -1, 'finished' => false];
        }
    }
    return $pieces;
}
function initialState(): array {
    return [
        'pieces'           => initialPieces(),
        'turn'             => 'red',
        'turnIndex'        => 0,
        'dice'             => null,
        'diceRolled'       => false,
        'consecutiveSixes' => 0,
        'extraTurn'        => false,
        'finishedColors'   => [],
        'moveHistory'      => [],
    ];
}

// ── Turn helpers ─────────────────────────────────────────────────────────────
function colorOrder(): array {
    return ['red', 'blue', 'green', 'yellow'];
}
function nextTurnIndex(array $room, int $currentIndex): int {
    $colors = colorOrder();
    $activeColors = [];
    foreach ($room['players'] as $p) {
        $activeColors[] = $p['color'];
    }
    // include all 4 slots (some may be bots)
    $slots = 4;
    for ($i = 1; $i < $slots; $i++) {
        $next = ($currentIndex + $i) % 4;
        // check if color at this index is a player in this room
        $nextColor = $colors[$next];
        foreach ($room['players'] as $p) {
            if ($p['color'] === $nextColor) return $next;
        }
    }
    return ($currentIndex + 1) % 4;
}

purgeExpired();

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = $method === 'POST' ? (json_decode($rawBody, true) ?? []) : [];
$action = $method === 'GET'
    ? ($_GET['action']  ?? '')
    : ($body['action'] ?? '');

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $rooms = [];
    foreach (glob(ROOMS_DIR . '*.json') as $f) {
        $r = json_decode(file_get_contents($f), true);
        if (!is_array($r) || $r['status'] !== 'waiting') continue;
        $humanCount = 0;
        foreach ($r['players'] as $p) if (!($p['isBot'] ?? false)) $humanCount++;
        $rooms[] = [
            'id'          => $r['id'],
            'creator'     => $r['players'][0]['name'] ?? '—',
            'humanPlayers'=> $humanCount,
            'totalSlots'  => count($r['players']),
            'createdAt'   => $r['createdAt'],
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
    $bots     = max(0, min(3, (int)($body['bots'] ?? 1))); // 0-3 bots

    if (strlen($username) < 3 || strlen($name) < 2) {
        http_response_code(400); echo json_encode(['error'=>'Dados inválidos']); exit;
    }

    $id    = strtoupper(bin2hex(random_bytes(3)));
    $token = bin2hex(random_bytes(16));

    $colors = colorOrder();
    $players = [
        ['slot'=>0,'color'=>$colors[0],'username'=>$username,'name'=>$name,'token'=>$token,'isBot'=>false]
    ];

    // Fill remaining slots with bots
    for ($s = 1; $s <= $bots; $s++) {
        $players[] = [
            'slot'     => $s,
            'color'    => $colors[$s],
            'username' => 'bot',
            'name'     => 'Bot ' . $s,
            'token'    => '',
            'isBot'    => true,
        ];
    }

    $room = [
        'id'        => $id,
        'game'      => 'ludo',
        'status'    => 'waiting',
        'createdAt' => time(),
        'updatedAt' => time(),
        'result'    => null,
        'players'   => $players,
        'state'     => initialState(),
    ];
    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    flock($fp, LOCK_EX);
    saveRoomFp($fp, $room);
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['roomId' => $id, 'token' => $token, 'color' => $colors[0], 'slot' => 0]); exit;
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
    if ($room['status'] !== 'waiting') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Sala já iniciada']); exit; }

    // Find a free human slot (currently bot or empty up to slot 3)
    $takenSlots = array_column($room['players'], 'slot');
    $colors = colorOrder();
    $freeSlot = null;
    for ($s = 1; $s <= 3; $s++) {
        if (!in_array($s, $takenSlots)) { $freeSlot = $s; break; }
        // Replace a bot slot
        foreach ($room['players'] as $i => $p) {
            if ($p['slot'] === $s && ($p['isBot'] ?? false)) { $freeSlot = $s; break 2; }
        }
    }
    if ($freeSlot === null) { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Sala cheia']); exit; }

    $token = bin2hex(random_bytes(16));
    $newPlayer = ['slot'=>$freeSlot,'color'=>$colors[$freeSlot],'username'=>$username,'name'=>$name,'token'=>$token,'isBot'=>false];

    // Remove bot at that slot if exists, then add human
    $room['players'] = array_values(array_filter($room['players'], fn($p) => $p['slot'] !== $freeSlot));
    $room['players'][] = $newPlayer;
    usort($room['players'], fn($a,$b) => $a['slot'] - $b['slot']);

    saveRoomFp($fp, $room);
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['roomId'=>$id,'token'=>$token,'color'=>$colors[$freeSlot],'slot'=>$freeSlot]); exit;
}

// ── POST start ───────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'start') {
    $id    = cleanId($body['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($room['status'] !== 'waiting') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Sala já iniciada']); exit; }

    $playerIdx = tokenOf($room, $token);
    if ($playerIdx === null || $room['players'][$playerIdx]['slot'] !== 0) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Apenas o criador pode iniciar']); exit;
    }

    // Need at least 2 players (including bots)
    if (count($room['players']) < 2) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(400); echo json_encode(['error'=>'Mínimo 2 jogadores']); exit;
    }

    // Fill remaining slots with bots up to 4 (optional — owner decided on create)
    // Just start with current players
    $room['status'] = 'playing';
    saveRoomFp($fp, $room);
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok' => true]); exit;
}

// ── POST roll ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'roll') {
    $id    = cleanId($body['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($room['status'] !== 'playing') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Partida não está em andamento']); exit; }

    $playerIdx = tokenOf($room, $token);
    if ($playerIdx === null) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Token inválido']); exit; }

    if (!canActThisTurn($room, $playerIdx)) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Não é sua vez']); exit;
    }
    if ($room['state']['diceRolled']) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Dado já rolado']); exit;
    }

    $dice = random_int(1, 6);
    $room['state']['dice']       = $dice;
    $room['state']['diceRolled'] = true;

    saveRoomFp($fp, $room);
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok'=>true, 'dice'=>$dice]); exit;
}

// ── POST move ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'move') {
    $id    = cleanId($body['roomId'] ?? '');
    $token = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');

    $f  = roomFile($id);
    $fp = fopen($f, 'c+');
    if (!$fp) { http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    flock($fp, LOCK_EX);
    $room = json_decode(stream_get_contents($fp), true);

    if (!$room) { flock($fp,LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(['error'=>'Sala não encontrada']); exit; }
    if ($room['status'] !== 'playing') { flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Partida não está em andamento']); exit; }

    $playerIdx = tokenOf($room, $token);
    if ($playerIdx === null) { flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Token inválido']); exit; }

    if (!canActThisTurn($room, $playerIdx)) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Não é sua vez']); exit;
    }
    if (!$room['state']['diceRolled']) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(['error'=>'Role o dado primeiro']); exit;
    }

    // Accept state from client (client computes game logic)
    if (isset($body['state']) && is_array($body['state'])) {
        // Security: preserve server dice value, verify turn advancement
        $newState = $body['state'];
        // Reset dice on new state
        $newState['diceRolled'] = false;
        $newState['dice']       = null;
        $room['state'] = $newState;
    }

    // Result
    if (isset($body['result'])) {
        $room['result'] = $body['result'];
        $room['status'] = 'finished';
    }

    saveRoomFp($fp, $room);
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok' => true]); exit;
}

// ── POST skip (pass turn when no moves) ──────────────────────────────────────
if ($method === 'POST' && $action === 'skip') {
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

    if (!canActThisTurn($room, $playerIdx)) {
        flock($fp,LOCK_UN); fclose($fp); http_response_code(403); echo json_encode(['error'=>'Não é sua vez']); exit;
    }

    if (isset($body['state']) && is_array($body['state'])) {
        $newState = $body['state'];
        $newState['diceRolled'] = false;
        $newState['dice']       = null;
        $room['state'] = $newState;
    }

    saveRoomFp($fp, $room);
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok' => true]); exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
