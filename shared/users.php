<?php
/**
 * shared/users.php — Base de dados centralizada de usuários
 *
 * POST {"action":"register","username":"...","name":"..."}
 *   → {"ok":true,"user":{...}}  ou  {"ok":false,"error":"..."}
 *
 * GET ?action=lookup&username=...
 *   → {"found":true,"user":{...}}  ou  {"found":false}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('USERS_FILE', __DIR__ . '/users.json');

// ── Helpers ────────────────────────────────────────────────
function loadUsers(): array {
    if (!file_exists(USERS_FILE)) {
        // Seed inicial com usuários conhecidos (extraídos do records.json do snake)
        $seed = [
            ['username' => 'onicksanchez', 'name' => 'Nicchon Sanchez', 'createdAt' => 1743897600],
            ['username' => 'Snake_poucas',  'name' => 'Poucas',          'createdAt' => 1743897600],
            ['username' => 'tutu',          'name' => 'piroquebas',       'createdAt' => 1743897600],
        ];
        file_put_contents(USERS_FILE, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $seed;
    }
    $data = json_decode(file_get_contents(USERS_FILE), true);
    return is_array($data) ? $data : [];
}

function saveUsers(array $users): void {
    file_put_contents(USERS_FILE, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function findUser(array $users, string $username): ?array {
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username) return $u;
    }
    return null;
}

// ── Roteamento ────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action   = $_GET['action'] ?? '';
    $username = trim($_GET['username'] ?? '');

    if ($action === 'lookup') {
        if (!$username) { echo json_encode(['found' => false]); exit; }
        $users = loadUsers();
        $user  = findUser($users, $username);
        echo json_encode($user ? ['found' => true, 'user' => $user] : ['found' => false]);
        exit;
    }

    if ($action === 'list') {
        $users = loadUsers();
        // Retorna apenas username e name, sem createdAt
        $list = array_map(fn($u) => ['username' => $u['username'], 'name' => $u['name']], $users);
        echo json_encode($list);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'ação inválida']);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'register') {
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', trim($body['username'] ?? ''));
        $name     = trim($body['name'] ?? '');

        if (strlen($username) < 3) { echo json_encode(['ok' => false, 'error' => 'username mínimo 3 chars']); exit; }
        if (strlen($name) < 2)     { echo json_encode(['ok' => false, 'error' => 'nome mínimo 2 chars']); exit; }
        if (strlen($username) > 20) $username = substr($username, 0, 20);
        if (strlen($name) > 30)     $name     = substr($name, 0, 30);

        $users    = loadUsers();
        $existing = findUser($users, $username);

        if ($existing) {
            // Atualiza nome se mudou
            $users = array_map(function($u) use ($username, $name) {
                if ($u['username'] === $username) $u['name'] = $name;
                return $u;
            }, $users);
        } else {
            $users[] = [
                'username'  => $username,
                'name'      => $name,
                'createdAt' => time(),
            ];
        }

        saveUsers($users);
        echo json_encode(['ok' => true, 'user' => ['username' => $username, 'name' => $name]]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'ação inválida']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'método não permitido']);
