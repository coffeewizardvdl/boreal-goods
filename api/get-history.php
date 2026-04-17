<?php
// ─────────────────────────────────────────────
//  GET /api/get-history.php?planter_id=bg001
//  Headers: X-Session-Token: <token>
//  Response: { "ok": true, "history": [ { "tapped_at": "...", "plant_name": "...", "days_freq": 7 }, ... ] }
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_err('Method not allowed', 405);

// ─── Authenticate
$sessionToken = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
if ($sessionToken === '') json_err('Missing session token.', 401);

$db = get_db();

$stmt = $db->prepare('SELECT user_id FROM sessions WHERE token = ? LIMIT 1');
$stmt->execute([$sessionToken]);
$session = $stmt->fetch();

if (!$session) json_err('Invalid or expired session.', 401);

$userId    = $session['user_id'];
$planterId = trim($_GET['planter_id'] ?? '');

if ($planterId === '') json_err('Missing planter_id.');

// ─── Fetch last 20 taps for this user + planter
$stmt = $db->prepare(
  'SELECT plant_name, days_freq, tapped_at
   FROM tap_events
   WHERE user_id = ? AND planter_id = ?
   ORDER BY tapped_at DESC
   LIMIT 20'
);
$stmt->execute([$userId, $planterId]);
$rows = $stmt->fetchAll();

json_ok(['history' => $rows]);
