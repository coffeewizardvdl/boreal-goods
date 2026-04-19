<?php
// ─────────────────────────────────────────────
//  GET /api/get-planters.php
//  Headers: X-Session-Token: <token>
//  Response: { "ok": true, "planters": [
//    {
//      "planter_id": "bg001",
//      "plant_name": "Pothos",
//      "days_freq": 7,
//      "last_watered": "2026-04-18 22:00:00",
//      "history": [ { "tapped_at": "...", "plant_name": "...", "days_freq": 7 }, ... ]
//    }, ...
//  ]}
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_err('Method not allowed', 405);

$sessionToken = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
if ($sessionToken === '') json_err('Missing session token.', 401);

$db = get_db();

$stmt = $db->prepare('SELECT user_id FROM sessions WHERE token = ? LIMIT 1');
$stmt->execute([$sessionToken]);
$session = $stmt->fetch();

if (!$session) json_err('Invalid or expired session.', 401);

$userId = $session['user_id'];

// ─── Get all distinct planters for this user with latest plant_name + days_freq
$stmt = $db->prepare(
  'SELECT
     planter_id,
     plant_name,
     days_freq,
     MAX(tapped_at) AS last_watered
   FROM tap_events
   WHERE user_id = ?
   GROUP BY planter_id
   ORDER BY last_watered DESC'
);
$stmt->execute([$userId]);
$planters = $stmt->fetchAll();

// ─── For each planter, fetch last 20 tap events
$result = [];
foreach ($planters as $p) {
  $hStmt = $db->prepare(
    'SELECT plant_name, days_freq, tapped_at
     FROM tap_events
     WHERE user_id = ? AND planter_id = ?
     ORDER BY tapped_at DESC
     LIMIT 20'
  );
  $hStmt->execute([$userId, $p['planter_id']]);
  $p['history'] = $hStmt->fetchAll();
  $result[] = $p;
}

json_ok(['planters' => $result]);
