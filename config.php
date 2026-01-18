<?php
// READ FROM RENDER ENVIRONMENT VARIABLES
$botToken = getenv("BOT_TOKEN");
$admin_id = getenv("ADMIN_ID");

// CHANNEL USERNAMES ONLY (with @)
$channels = [
  "@house_of_floriaa",
  "@SadistLootHub",
  "@ANKURXTECHSTEALS",
  "@ANKURXTECHSTEALSDISCUSS"
];

// SAFETY CHECK (IMPORTANT)
if (!$botToken) {
  file_put_contents(__DIR__."/bot_debug.log", "BOT_TOKEN is EMPTY\n", FILE_APPEND);
}
