<?php
require_once __DIR__ . "/config.php";

/*
  FINAL FLOW:
  - /start -> Promotion message + Join Channel buttons (inline) + ✅ Check Join (inline)
  - ✅ Check Join success -> "🎉Welcome to Ankur TechXSteals Giveaway Bot!" + bottom keyboard "🎁 Claim Coupon"
  - Claim Coupon -> gives code (1 user = 1) with ONLY:
      🎉 Congratulations!
      Your Coupon: CODE
  - NO DATABASE (files): coupons.txt, claimed.txt
*/

// ---- Stop if token missing ----
if (!$botToken) {
  file_put_contents(__DIR__."/bot_debug.log", "BOT_TOKEN MISSING\n", FILE_APPEND);
  echo "BOT TOKEN MISSING";
  exit;
}

// ---- Storage files ----
$couponsFile = __DIR__ . "/coupons.txt";
$claimedFile = __DIR__ . "/claimed.txt";

if (!file_exists($couponsFile)) file_put_contents($couponsFile, "");
if (!file_exists($claimedFile)) file_put_contents($claimedFile, "");

// ---- Telegram helpers ----
function tg_post($method, $params) {
  global $botToken;
  $url = "https://api.telegram.org/bot{$botToken}/{$method}";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
  curl_setopt($ch, CURLOPT_TIMEOUT, 25);
  $res = curl_exec($ch);
  curl_close($ch);

  return $res ? json_decode($res, true) : null;
}

function sendMessage($chat_id, $text, $reply_markup = null) {
  $data = [
    "chat_id" => $chat_id,
    "text" => $text,
    "parse_mode" => "HTML",
    "disable_web_page_preview" => true
  ];
  if ($reply_markup) $data["reply_markup"] = json_encode($reply_markup);
  tg_post("sendMessage", $data);
}

function answerCallback($id) {
  tg_post("answerCallbackQuery", ["callback_query_id" => $id]);
}

// Reply keyboard (bottom bar button like your red circle pic)
function sendReplyKeyboard($chat_id, $text, $buttons) {
  $reply_markup = [
    "keyboard" => $buttons,
    "resize_keyboard" => true,
    "one_time_keyboard" => false
  ];
  sendMessage($chat_id, $text, $reply_markup);
}

function removeReplyKeyboard($chat_id, $text) {
  $reply_markup = ["remove_keyboard" => true];
  sendMessage($chat_id, $text, $reply_markup);
}

// ---- Join check ----
function isJoinedAll($user_id) {
  global $channels;

  // allow if channels not configured yet
  if (!is_array($channels) || count($channels) < 1 || strpos($channels[0], "@") !== 0) return true;

  foreach ($channels as $ch) {
    $r = tg_post("getChatMember", ["chat_id" => $ch, "user_id" => $user_id]);
    if (!$r || empty($r["ok"])) return false;

    $status = $r["result"]["status"] ?? "left";
    if ($status === "left" || $status === "kicked") return false;
  }
  return true;
}

// ---- Coupon functions ----
function hasClaimed($user_id, $claimedFile) {
  $lines = @file($claimedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return false;
  foreach ($lines as $l) {
    $parts = explode("|", $l);
    if (isset($parts[0]) && trim($parts[0]) === (string)$user_id) return true;
  }
  return false;
}

function popCoupon($couponsFile) {
  $lines = @file($couponsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines || count($lines) === 0) return null;

  $code = trim($lines[0]);
  array_shift($lines);

  file_put_contents($couponsFile, implode("\n", $lines) . (count($lines) ? "\n" : ""));
  return $code !== "" ? $code : null;
}

function addCoupon($couponsFile, $code) {
  $code = trim($code);
  if ($code === "") return false;
  file_put_contents($couponsFile, $code . "\n", FILE_APPEND);
  return true;
}

// ---- Read update ----
$raw = file_get_contents("php://input");
$update = $raw ? json_decode($raw, true) : null;
if (!$update) { echo "OK"; exit; }

// ---- Handle normal messages ----
if (isset($update["message"])) {
  $chat_id = $update["message"]["chat"]["id"];
  $user_id = $update["message"]["from"]["id"];
  $text    = $update["message"]["text"] ?? "";

  // Admin command: /addcoupon CODE
  if (strpos($text, "/addcoupon") === 0) {
    if ((string)$user_id !== (string)$admin_id) {
      sendMessage($chat_id, "❌ You are not admin.");
      exit;
    }
    $parts = explode(" ", $text, 2);
    if (count($parts) < 2) {
      sendMessage($chat_id, "Use:\n<code>/addcoupon CODE123</code>");
      exit;
    }
    $code = trim($parts[1]);
    if (addCoupon($couponsFile, $code)) {
      sendMessage($chat_id, "✅ Coupon added:\n<code>".htmlspecialchars($code)."</code>");
    } else {
      sendMessage($chat_id, "❌ Invalid coupon.");
    }
    exit;
  }

  // User pressed bottom reply keyboard button "🎁 Claim Coupon"
  if ($text === "🎁 Claim Coupon") {

    // (Optional) join check again for safety
    if (!isJoinedAll($user_id)) {
      sendMessage($chat_id, "❌ You must join all 4 channels first.\n\nAfter joining, click ✅ Check Join.");
      exit;
    }

    if (hasClaimed($user_id, $claimedFile)) {
      sendMessage($chat_id, "❌ Limit Exceeded: You have already claimed your reward.");
      exit;
    }

    $code = popCoupon($couponsFile);
    if (!$code) {
      sendMessage($chat_id, "😔 Out of Stock: No coupons left at the moment.");
      exit;
    }

    file_put_contents($claimedFile, $user_id."|".$code."|".date("Y-m-d H:i:s")."\n", FILE_APPEND);

    // ONLY this message (as you requested)
    sendMessage($chat_id, "🎉 Congratulations!\n\nYour Coupon:\n<code>".htmlspecialchars($code)."</code>");
    exit;
  }

  // /start
  if ($text === "/start") {

    // ✅ Replace these with your real channel links:
    $join1 = "https://t.me/house_of_floriaa";
    $join2 = "https://t.me/SadistLootHub";
    $join3 = "https://t.me/ANKURXTECHSTEALS";
    $join4 = "https://t.me/ANKURXTECHSTEALSDISCUSS";

    $kb = [
      "inline_keyboard" => [
        [
          ["text"=>"📢 Join Channel 1", "url"=>$join1],
          ["text"=>"📢 Join Channel 2", "url"=>$join2]
        ],
        [
          ["text"=>"📢 Join Channel 3", "url"=>$join3],
          ["text"=>"📢 Join Channel 4", "url"=>$join4]
        ],
        [
          ["text"=>"✅ Check Join", "callback_data"=>"check_join"]
        ]
      ]
    ];

    // Join part exactly like your photo
    sendMessage(
      $chat_id,
      "🎁 <b>Promotion</b>\n\nJoin all 4 channels below, then click ✅ <b>Check Join</b>.",
      $kb
    );
  }
}

// ---- Handle inline buttons ----
if (isset($update["callback_query"])) {
  $cb = $update["callback_query"];
  $cb_id = $cb["id"];
  $chat_id = $cb["message"]["chat"]["id"];
  $user_id = $cb["from"]["id"];
  $data = $cb["data"] ?? "";

  answerCallback($cb_id);

  if ($data === "check_join") {
    if (isJoinedAll($user_id)) {

      // After verify -> your exact message + bottom Claim button
      sendReplyKeyboard(
        $chat_id,
        "🎉Welcome to Ankur TechXSteals Giveaway Bot!",
        [
          [ ["text" => "🎁 Claim Coupon"] ]
        ]
      );

    } else {
      sendMessage($chat_id, "❌ You must join all 4 channels first.\n\nAfter joining, click ✅ Check Join.");
    }
  }
}

echo "OK";
