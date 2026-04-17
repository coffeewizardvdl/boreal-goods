<?php
// ─────────────────────────────────────────────
//  POST /api/verify-token.php
//  Body: { "token": "<64-char hex>" }
//  Response: { "ok": true, "session_token": "...", "email": "..." }
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$body  = json_decode(file_get_contents('php://input'), true);
$token = trim($body['token'] ?? '');

if (strlen($token) !== 64) json_err('Invalid token format.');

$db = get_db();

// ─── Look up the token
$stmt = $db->prepare(
  'SELECT mt.id, mt.user_id, mt.expires_at, mt.used, u.email
   FROM magic_tokens mt
   JOIN users u ON u.id = mt.user_id
   WHERE mt.token = ?
   LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row)               json_err('Token not found.', 404);
if ($row['used'])        json_err('This link has already been used. Please request a new one.');
if (strtotime($row['expires_at']) < time()) json_err('This link has expired. Please request a new one.');

// ─── Mark token used
$db->prepare('UPDATE magic_tokens SET used = 1 WHERE id = ?')
   ->execute([$row['id']]);

// ─── Issue a session token
$sessionToken = bin2hex(random_bytes(32));
$db->prepare('INSERT INTO sessions (user_id, token) VALUES (?, ?)')
   ->execute([$row['user_id'], $sessionToken]);

json_ok([
  'session_token' => $sessionToken,
  'email'         => $row['email'],
]);
