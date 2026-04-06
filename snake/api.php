<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

define('RECORDS_FILE', __DIR__ . '/records.json');
define('MAX_MAPS',     10);
define('TOP_N',        10);

// ── Carrega / inicializa o arquivo de records ──────────────────────────────
function loadRecords(): array {
    if (!file_exists(RECORDS_FILE)) return initEmpty();
    $data = json_decode(file_get_contents(RECORDS_FILE), true);
    return is_array($data) ? $data : initEmpty();
}

function initEmpty(): array {
    $r = [];
    for ($i = 0; $i < MAX_MAPS; $i++) $r[(string)$i] = [];
    return $r;
}

function saveRecords(array $data): void {
    file_put_contents(RECORDS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── Sanitização ────────────────────────────────────────────────────────────
function sanitizeUsername(string $s): string {
    return preg_replace('/[^a-zA-Z0-9_]/', '', substr(trim($s), 0, 20));
}

function sanitizeName(string $s): string {
    // Remove tags e limita tamanho
    return substr(strip_tags(trim($s)), 0, 50);
}

// ── Roteamento ─────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $records = loadRecords();

    // Lookup: verifica se username existe e retorna o nome
    if (($_GET['action'] ?? '') === 'lookup') {
        $username = sanitizeUsername($_GET['username'] ?? '');
        if (!$username) { echo json_encode(['found' => false]); exit; }
        foreach ($records as $mapRecords) {
            foreach ($mapRecords as $entry) {
                if ($entry['username'] === $username) {
                    echo json_encode(['found' => true, 'name' => $entry['name']]);
                    exit;
                }
            }
        }
        echo json_encode(['found' => false]);
        exit;
    }

    // Check: só verifica se username já está em uso (para criação de novo perfil)
    if (($_GET['action'] ?? '') === 'check') {
        $username = sanitizeUsername($_GET['username'] ?? '');
        if (!$username) { echo json_encode(['taken' => false]); exit; }
        foreach ($records as $mapRecords) {
            foreach ($mapRecords as $entry) {
                if ($entry['username'] === $username) {
                    echo json_encode(['taken' => true]);
                    exit;
                }
            }
        }
        echo json_encode(['taken' => false]);
        exit;
    }

    // Ranking por mapa
    $map = max(0, min(MAX_MAPS - 1, (int)($_GET['map'] ?? 0)));
    echo json_encode($records[(string)$map] ?? []);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $username = sanitizeUsername($body['username'] ?? '');
    $name     = sanitizeName($body['name'] ?? '');
    $map      = (int)($body['map'] ?? -1);
    $score    = (int)($body['score'] ?? 0);

    // Validações
    if (strlen($username) < 3) {
        http_response_code(400);
        echo json_encode(['error' => 'Username inválido']);
        exit;
    }
    if (strlen($name) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome inválido']);
        exit;
    }
    if ($map < 0 || $map >= MAX_MAPS) {
        http_response_code(400);
        echo json_encode(['error' => 'Mapa inválido']);
        exit;
    }
    if ($score <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Score inválido']);
        exit;
    }

    $records   = loadRecords();
    $mapKey    = (string)$map;
    $mapList   = &$records[$mapKey];

    // Verifica se já existe entrada para este username
    $found = false;
    foreach ($mapList as &$entry) {
        if ($entry['username'] === $username) {
            $found = true;
            if ($score > $entry['score']) {
                $entry['score'] = $score;
                $entry['name']  = $name;   // atualiza nome se mudou
                $entry['date']  = date('d/m/Y');
            }
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $mapList[] = [
            'username' => $username,
            'name'     => $name,
            'score'    => $score,
            'date'     => date('d/m/Y'),
        ];
    }

    // Ordena por score decrescente e mantém top N
    usort($mapList, fn($a, $b) => $b['score'] - $a['score']);
    $records[$mapKey] = array_slice($mapList, 0, TOP_N);

    saveRecords($records);

    // Retorna posição do jogador no ranking
    $position = array_search($username, array_column($records[$mapKey], 'username'));
    echo json_encode([
        'ok'       => true,
        'position' => $position !== false ? $position + 1 : null,
        'ranking'  => $records[$mapKey],
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
