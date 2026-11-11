<?php
// Rotana Bot endpoint, used by the chat window (assets/js/bot.js) and the
// admin test box. Public, like the rest of the site.
//   GET            -> name, welcome message and starter questions
//   POST q=<text>  -> the bot's reply (see bot_reply() in includes/bot.php)
require __DIR__ . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$json = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = (string)($_POST['q'] ?? '');
    echo json_encode(bot_reply(substr($question, 0, 1000)), $json);
    exit;
}

$s = bot_settings();
$entries = bot_entries();
echo json_encode([
    'ready' => count($entries) > 0,
    'name' => $s['name'],
    'topic' => $s['topic'],
    'welcome' => bot_welcome($s),
    'suggestions' => bot_starter_questions($entries),
    'suggestions_label' => 'You can ask me:',
], $json);
