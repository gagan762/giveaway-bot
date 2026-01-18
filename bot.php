<?php
// ================== LOAD CONFIG ==================
require_once __DIR__ . "/config.php";

// STOP if token missing (prevents silent failure)
if (!$botToken) {
    file_put_contents(__DIR__."/bot_debug.log", "BOT_TOKEN MISSING\n", FILE_APPEND);
    echo "BOT TOKEN MISSING";
    exit;
}

// ================== FILE STORAGE ==================
$couponsFile = __DIR__ . "/coupons.txt";   // one coupon per line
$claimedFile = __DIR__ . "/claimed.txt";   // user_id|coupon|date

if (!file_exists($couponsFile)) file_put_contents($couponsFile, "");
if (!file_exists($claimedFile)) file_put_contents($claimedFile, "");

// ================== TELEGRAM HELPERS ==================
function tg_post($method, $params) {
    global $botToken;
    $url = "https://api.telegram.org/bot{$botToken}/{$method}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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

// ================== LOG UPDATES ==================
$raw = file_get_contents("php://input");
file_put_contents(__DIR__."/update_log.txt", date("Y-m-d H:i:s")."\n".$raw."\n\n", FILE_APPEND);
$update = json_decode($raw, true);
if (!$update) { echo "OK"; exit; }

// ================== JOIN CHECK ==================
function isJoinedAll($user_id) {
    global $channels;

    // Allow if channels not configured yet
    if (!$channels || strpos($channels[0], "@") === false) return true;

    foreach ($channels as $ch) {
        $r = tg_post("getChatMember", [
            "chat_id" => $ch,
            "user_id" => $user_id
        ]);
        if (!$r || !$r["ok"]) return false;

        $status = $r["result"]["status"] ?? "left";
        if ($status === "left" || $status === "kicked") return false;
    }
    return true;
}

// ================== COUPON HELPERS ==================
function hasClaimed($user_id) {
    global $claimedFile;
    $lines = file($claimedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $l) {
        if (explode("|", $l)[0] == $user_id) return true;
    }
    return false;
}

function getCoupon() {
    global $couponsFile;
    $lines = file($couponsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return null;

    $code = trim($lines[0]);
    array_shift($lines);
    file_put_contents($couponsFile, implode("\n", $lines)."\n");
    return $code;
}

// ================== HANDLE MESSAGES ==================
if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $user_id = $update["message"]["from"]["id"];
    $text = $update["message"]["text"] ?? "";

    // ADMIN ADD COUPON
    if (strpos($text, "/addcoupon") === 0) {
        if ((string)$user_id !== (string)$admin_id) {
            sendMessage($chat_id, "❌ You are not admin.");
            exit;
        }
        $code = trim(str_replace("/addcoupon", "", $text));
        if ($code) {
            file_put_contents($couponsFile, $code."\n", FILE_APPEND);
            sendMessage($chat_id, "✅ Coupon added:\n<code>{$code}</code>");
        } else {
            sendMessage($chat_id, "Use:\n<code>/addcoupon CODE123</code>");
        }
        exit;
    }

    // /START
    if ($text === "/start") {

        // 🔴 CHANGE THESE TO YOUR REAL CHANNEL LINKS
        $kb = [
            "inline_keyboard" => [
                [
                    ["text"=>"📢 Join Channel 1","url"=>"https://t.me/house_of_floriaa"],
                    ["text"=>"📢 Join Channel 2","url"=>"https://t.me/SadistLootHub"]
                ],
                [
                    ["text"=>"📢 Join Channel 3","url"=>"https://t.me/ANKURXTECHSTEALS"],
                    ["text"=>"📢 Join Channel 4","url"=>"https://t.me/ANKURXTECHSTEALSDISCUSS"]
                ],
                [
                    ["text"=>"✅ Check Join","callback_data"=>"check_join"]
                ]
            ]
        ];

        sendMessage(
            $chat_id,
            "🎁 <b>Promotion</b>\n\nJoin all 4 channels below, then click ✅ <b>Check Join</b>.",
            $kb
        );
    }
}

// ================== HANDLE CALLBACKS ==================
if (isset($update["callback_query"])) {
    $cb = $update["callback_query"];
    $chat_id = $cb["message"]["chat"]["id"];
    $user_id = $cb["from"]["id"];
    $data = $cb["data"];
    answerCallback($cb["id"]);

    if ($data === "check_join") {
        if (isJoinedAll($user_id)) {
            $kb = [
                "inline_keyboard" => [
                    [["text"=>"🎁 Claim Coupon","callback_data"=>"claim"]]
                ]
            ];
            sendMessage($chat_id, "🎉 Welcome!\nClick 🎁 <b>Claim Coupon</b>.", $kb);
        } else {
            sendMessage($chat_id, "❌ You must join all 4 channels first.\n\nAfter joining, click ✅ Check Join.");
        }
    }

    if ($data === "claim") {
        if (hasClaimed($user_id)) {
            sendMessage($chat_id, "❌ You already claimed a coupon.");
            exit;
        }

        $code = getCoupon();
        if (!$code) {
            sendMessage($chat_id, "❌ No coupons available.");
            exit;
        }

        file_put_contents($claimedFile, $user_id."|".$code."|".date("Y-m-d")."\n", FILE_APPEND);
        sendMessage($chat_id, "🎁 Your coupon:\n\n<code>{$code}</code>\n\n✅ One per user.");
    }
}

echo "OK";
