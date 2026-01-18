<?php
require_once __DIR__ . "/config.php";

// --- FILES ---
$couponsFile = __DIR__ . "/coupons.txt";   // each line = 1 coupon code
$claimedFile = __DIR__ . "/claimed.txt";   // each line = user_id|coupon|date

if (!file_exists($couponsFile)) file_put_contents($couponsFile, "");
if (!file_exists($claimedFile)) file_put_contents($claimedFile, "");

// log updates
$raw = file_get_contents("php://input");
if ($raw) file_put_contents(__DIR__."/update_log.txt", date("Y-m-d H:i:s")."\n".$raw."\n\n", FILE_APPEND);

function tg_post($method, $params) {
  global $botToken;
  $url = "https://api.telegram.org/bot{$botToken}/{$method}";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);

  if ($res === false) {
    file_put_contents(__DIR__."/bot_debug.log", "CURL ERROR {$method}: {$err}\n", FILE_APPEND);
    return null;
  }
  return json_decode($res, true);
}

function sendMessage($chat_id, $text, $reply_markup=null) {
  $p = ["chat_id"=>$chat_id,"text"=>$text,"parse_mode"=>"HTML","disable_web_page_preview"=>true];
  if ($reply_markup) $p["reply_markup"] = json_encode($reply_markup);
  tg_post("sendMessage", $p);
}
function answerCallback($id) { tg_post("answerCallbackQuery", ["callback_query_id"=>$id]); }

function isJoinedAll($user_id) {
  global $channels;

  // allow if channels not configured yet
  if (!is_array($channels) || count($channels) < 1 || strpos($channels[0], "@channel") === 0) return true;

  foreach ($channels as $ch) {
    $r = tg_post("getChatMember", ["chat_id"=>$ch,"user_id"=>$user_id]);
    if (!$r || empty($r["ok"])) return false;
    $status = $r["result"]["status"] ?? "left";
    if ($status === "left" || $status === "kicked") return false;
  }
  return true;
}

function hasClaimed($user_id, $claimedFile) {
  $lines = @file($claimedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return false;
  foreach ($lines as $ln) {
    $parts = explode("|", $ln);
    if (isset($parts[0]) && trim($parts[0]) == (string)$user_id) return true;
  }
  return false;
}

function popCoupon($couponsFile) {
  $lines = @file($couponsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines || count($lines) === 0) return null;

  $code = trim($lines[0]);
  array_shift($lines);

  // write back remaining
  file_put_contents($couponsFile, implode("\n", $lines) . (count($lines) ? "\n" : ""));
  return $code ?: null;
}

function addCoupon($couponsFile, $code) {
  $code = trim($code);
  if ($code === "") return false;
  file_put_contents($couponsFile, $code . "\n", FILE_APPEND);
  return true;
}

// --- Parse update ---
$update = $raw ? json_decode($raw, true) : null;
if (!$update) { echo "OK"; exit; }

// Handle messages
if (isset($update["message"])) {
  $chat_id = $update["message"]["chat"]["id"];
  $user_id = $update["message"]["from"]["id"];
  $text = $update["message"]["text"] ?? "";

  // Admin command: /addcoupon CODE
  if (strpos($text, "/addcoupon") === 0) {
    if ((string)$user_id !== (string)$GLOBALS["admin_id"]) {
      sendMessage($chat_id, "❌ You are not admin.");
      exit;
    }
    $parts = explode(" ", $text, 2);
    if (count($parts) < 2) {
      sendMessage($chat_id, "Use: <code>/addcoupon CODE123</code>");
      exit;
    }
    $code = trim($parts[1]);
    addCoupon($couponsFile, $code);
    sendMessage($chat_id, "✅ Added coupon: <code>".htmlspecialchars($code)."</code>");
    exit;
  }

  if ($text === "/start") {
    $kb = ["inline_keyboard"=>[[["text"=>"✅ Check Join","callback_data"=>"check_join"]]]];
    sendMessage($chat_id, "Please join all 4 channels first, then click ✅ Check Join.", $kb);
  }
}

// Handle buttons
if (isset($update["callback_query"])) {
  $cb = $update["callback_query"];
  $cb_id = $cb["id"];
  $chat_id = $cb["message"]["chat"]["id"];
  $user_id = $cb["from"]["id"];
  $data = $cb["data"] ?? "";
  answerCallback($cb_id);

  if ($data === "check_join") {
    if (isJoinedAll($user_id)) {
      $kb = ["inline_keyboard"=>[[["text"=>"🎁 Claim Coupon","callback_data"=>"claim"]]]];
      sendMessage($chat_id, "🎉 Welcome to the official giveaway!\nClick 🎁 Claim Coupon to claim your reward.", $kb);
    } else {
      sendMessage($chat_id, "❌ You must join all 4 channels first.\n\nTip: Add bot as ADMIN in your channels.");
    }
  }

  if ($data === "claim") {
    if (hasClaimed($user_id, $claimedFile)) {
      sendMessage($chat_id, "❌ You already claimed a coupon.");
      exit;
    }

    $code = popCoupon($couponsFile);
    if (!$code) {
      sendMessage($chat_id, "❌ No coupons available right now.");
      exit;
    }

    // Mark claimed
    file_put_contents($claimedFile, $user_id."|".$code."|".date("Y-m-d H:i:s")."\n", FILE_APPEND);

    sendMessage($chat_id, "🎁 Your coupon code:\n\n<code>".htmlspecialchars($code)."</code>\n\n✅ You can claim only once.");
  }
}

echo "OK";
