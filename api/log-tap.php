<?php
// ─────────────────────────────────────────────
//  POST /api/log-tap.php
//  Headers: X-Session-Token: <token>
//  Body: { "planter_id": "bg001", "plant_name": "Pothos", "days_freq": 7 }
//  Response: { "ok": true, "tap_id": 42 }
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

// ─── Authenticate via session token header
$sessionToken = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
if ($sessionToken === '') json_err('Missing session token.', 401);

$db = get_db();

$stmt = $db->prepare('SELECT user_id FROM sessions WHERE token = ? LIMIT 1');
$stmt->execute([$sessionToken]);
$session = $stmt->fetch();

if (!$session) json_err('Invalid or expired session.', 401);

$userId = $session['user_id'];

// ─── Parse body
$body      = json_decode(file_get_contents('php://input'), true);
$planterId = trim($body['planter_id'] ?? '');
$plantName = trim($body['plant_name'] ?? '');
$daysFreq  = (int)($body['days_freq'] ?? 0);

if ($planterId === '') json_err('Missing planter_id.');
if ($plantName === '') json_err('Missing plant_name.');
if ($daysFreq < 1)    json_err('Invalid days_freq.');

// ─── Insert tap event
$stmt = $db->prepare(
  'INSERT INTO tap_events (user_id, planter_id, plant_name, days_freq) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$userId, $planterId, $plantName, $daysFreq]);
$tapId = $db->lastInsertId();

json_ok(['tap_id' => (int)$tapId]);
