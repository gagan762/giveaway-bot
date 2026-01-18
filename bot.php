$parts = explode(" ", $text, 2);
    if (count($parts) < 2) {
      sendMessage($chat_id, "Use:\n<code>/addcoupon CODE123</code>");
      exit;
    }

    $code = trim($parts[1]);
    if (addCoupon($couponsFile, $code)) {
      sendMessage($chat_id, "✅ Coupon added:\n<code>" . htmlspecialchars($code) . "</code>");
    } else {
      sendMessage($chat_id, "❌ Invalid coupon.");
    }
    exit;
  }

  // Start
  if ($text === "/start"  $text === "start"  $text === "/Start") {

    // IMPORTANT: Replace these links with your real channel usernames:
    $join1 = "https://t.me/house_of_floriaa";
    $join2 = "https://t.me/SadistLootHub";
    $join3 = "https://t.me/ANKURXTECHSTEALS";
    $join4 = "https://t.me/ANKURXTECHSTEALSDISCUSS";

    $kb = [
      "inline_keyboard" => [
        [
          ["text" => "📢 Join Channel 1", "url" => $join1],
          ["text" => "📢 Join Channel 2", "url" => $join2]
        ],
        [
          ["text" => "📢 Join Channel 3", "url" => $join3],
          ["text" => "📢 Join Channel 4", "url" => $join4]
        ],
        [
          ["text" => "✅ Check Join", "callback_data" => "check_join"]
        ]
      ]
    ];

    sendMessage(
      $chat_id,
      "🎁 <b>Promotion</b>\n\nPlease join all 4 channels first, then click ✅ <b>Check Join</b>.",
      $kb
    );
  }
}

// ---------- Handle callback buttons ----------
if (isset($update["callback_query"])) {
  $cb      = $update["callback_query"];
  $cb_id   = $cb["id"];
  $chat_id = $cb["message"]["chat"]["id"];
  $user_id = $cb["from"]["id"];
  $data    = $cb["data"] ?? "";

  answerCallback($cb_id);

  // Check join
  if ($data === "check_join") {
    if (isJoinedAll($user_id)) {
      $kb = [
        "inline_keyboard" => [
          [
            ["text" => "🎁 Claim Coupon", "callback_data" => "claim"]
          ]
        ]
      ];

      sendMessage(
        $chat_id,
        "🎉 Welcome to the official giveaway!\n\nClick 🎁 <b>Claim Coupon</b> to get your reward.",
        $kb
      );
    } else {
      // Tip removed (as you requested)
      sendMessage($chat_id, "❌ You must join all 4 channels first.\n\nAfter joining, click ✅ Check Join.");
    }
  }

  // Claim coupon
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
    file_put_contents($claimedFile, $user_id . "|" . $code . "|" . date("Y-m-d H:i:s") . "\n", FILE_APPEND);

    sendMessage(
      $chat_id,
      "🎁 Your coupon code:\n\n<code>" . htmlspecialchars($code) . "</code>\n\n✅ You can claim only once."
    );
  }
}

echo "OK";
