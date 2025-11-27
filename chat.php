<?php
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/messages.json';

if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

$messages = json_decode(file_get_contents($file), true);
if (!is_array($messages)) {
    $messages = [];
}

$action = $_GET['action'] ?? '';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $user      = trim($data['user']  ?? '');
    $text      = trim($data['text']  ?? '');
    $uid       = trim($data['uid']   ?? '');
    $photo_url = trim($data['photo_url'] ?? '');

    if ($text === '' || $user === '') {
        echo json_encode(['ok' => false, 'error' => 'empty']);
        exit;
    }

    $messages[] = [
        'uid'       => $uid,
        'user'      => $user,
        'text'      => $text,
        'time'      => date('H:i'),
        'photo_url' => $photo_url
    ];

    // garder que les 200 derniers messages
    if (count($messages) > 200) {
        $messages = array_slice($messages, -200);
    }

    file_put_contents($file, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'list') {
    echo json_encode(['ok' => true, 'messages' => $messages]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'invalid_action']);
