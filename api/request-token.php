<?php
// ─────────────────────────────────────────────
//  POST /api/request-token.php
//  Body: { "email": "user@example.com", "planter_id": "bg001" }
//  Response: { "ok": true }
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$body  = json_decode(file_get_contents('php://input'), true);
$email = trim(strtolower($body['email'] ?? ''));
$pid   = trim($body['planter_id'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('Invalid email address.');
if ($pid === '')                                 json_err('Missing planter_id.');

$db = get_db();

// ─── Upsert user
$db->prepare('INSERT IGNORE INTO users (email) VALUES (?)')
   ->execute([$email]);

$user = $db->prepare('SELECT id FROM users WHERE email = ?');
$user->execute([$email]);
$userId = $user->fetchColumn();

// ─── Expire any old unused tokens for this user
$db->prepare('UPDATE magic_tokens SET used = 1 WHERE user_id = ? AND used = 0')
   ->execute([$userId]);

// ─── Generate a new token
$token     = bin2hex(random_bytes(32)); // 64 hex chars
$expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_MINUTES * 60);

$db->prepare('INSERT INTO magic_tokens (user_id, token, expires_at) VALUES (?, ?, ?)')
   ->execute([$userId, $token, $expiresAt]);

// ─── Build magic link (includes planter_id so we land back on right page)
$link = SITE_URL . '/water.html?id=' . urlencode($pid) . '&token=' . $token;

// ─── Send via Resend
$emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Georgia', serif; background: #f7f2eb; margin: 0; padding: 40px 20px; color: #3f3c38; }
    .card { background: white; border-radius: 10px; max-width: 480px; margin: 0 auto; padding: 40px; border: 1px solid #e8e0d4; }
    .brand { font-size: 11px; letter-spacing: 0.3em; text-transform: uppercase; color: #a07850; margin-bottom: 24px; }
    h1 { font-size: 24px; color: #1e3326; margin: 0 0 12px; font-weight: normal; }
    p { font-size: 15px; line-height: 1.6; color: #6b4c2a; margin: 0 0 24px; }
    .btn { display: inline-block; background: #2d4a35; color: #f7f2eb; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 13px; letter-spacing: 0.1em; text-transform: uppercase; }
    .expiry { font-size: 12px; color: #c8d4c0; margin-top: 24px; }
    .footer { text-align: center; margin-top: 32px; font-size: 11px; color: #c8d4c0; letter-spacing: 0.1em; text-transform: uppercase; }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">Boreal Goods · Made in the North</div>
    <h1>Your sign-in link 🌿</h1>
    <p>Tap the button below to verify your email and start tracking your plant waterings.</p>
    <a class="btn" href="{$link}">Open Boreal Goods</a>
    <p class="expiry">This link expires in 30 minutes and can only be used once.</p>
  </div>
  <div class="footer">borealgoods.ca · Edmonton, Alberta</div>
</body>
</html>
HTML;

$response = (function() use ($email, $emailBody) {
  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer ' . RESEND_API_KEY,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
      'from'    => 'Boreal Goods <' . FROM_EMAIL . '>',
      'to'      => [$email],
      'subject' => 'Your Boreal Goods sign-in link 🌿',
      'html'    => $emailBody,
    ]),
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['code' => $code, 'body' => json_decode($res, true)];
})();

if ($response['code'] !== 200 && $response['code'] !== 201) {
  error_log('Resend error: ' . json_encode($response['body']));
  json_err('Failed to send email. Please try again.', 500);
}

json_ok();
