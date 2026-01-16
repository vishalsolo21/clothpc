<?php
// --- Configuration ---
const BOT_TOKEN     = 'bot_token';
const ADMIN_CHAT_ID = '';
const WEBHOOK_URL   = '';
const CHANNEL_IDS   = ['@'];

function httpCallAdvanced($url, $data = null, $headers = [], $method = "GET", $returnHeaders = false) {
    $ch = curl_init();
    
    // Anti-Akamai: Use HTTP/2 and modern TLS
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, "gzip, deflate, br");
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Anti-bot: Realistic connection behavior
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 45);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($returnHeaders) {
        curl_setopt($ch, CURLOPT_HEADER, 1);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    curl_close($ch);

    if ($err) {
        error_log("cURL Error: " . $err);
        return ['success' => false, 'error' => $err, 'http_code' => $httpCode, 'body' => ''];
    }

    if ($httpCode == 403) {
        return ['success' => false, 'error' => 'Akamai blocked (403)', 'http_code' => 403, 'body' => $response];
    }

    if ($returnHeaders && $response) {
        $header_size = strlen($response) - strlen(ltrim($response));
        if (preg_match('/\r\n\r\n/', $response, $matches, PREG_OFFSET_CAPTURE)) {
            $header_size = $matches[0][1] + 4;
        }
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        return ['success' => true, 'headers' => $header, 'body' => $body, 'http_code' => $httpCode];
    }

    return ['success' => true, 'body' => $response, 'http_code' => $httpCode];
}

function HttpCallnohead($url, $data = null, $headers = [], $method = "GET", $returnHeaders = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    } elseif ($method === "GET" && $data) {
        $url .= '?' . $data;
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    $curlHeaders = [];
    foreach ($headers as $header) {
        $curlHeaders[] = $header;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

    if ($returnHeaders) {
        curl_setopt($ch, CURLOPT_HEADER, 1);
    }

    $response = curl_exec($ch);
    $err      = curl_error($ch);

    if ($returnHeaders && !$err) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);
        curl_close($ch);
        return ['headers' => $header, 'body' => $body];
    }

    curl_close($ch);

    if ($err) {
        error_log("cURL Error: " . $err);
        return false;
    }

    return $response;
}

function httpCall($url, $data = null, $headers = [], $method = "GET", $returnHeaders = false, $proxy = false, $ip = null, $auth = null) {
    if (empty($headers)) {
        $ip = long2ip(mt_rand());
        $headers = [
            "X-Forwarded-For: $ip",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36"
        ];
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => $returnHeaders,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10
    ]);
    if ($proxy) {
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        curl_setopt($ch, CURLOPT_PROXY, $ip);
        if ($auth) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
        }
    }
    if (strtoupper($method) === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } else {
        if ($data) {
            $url .= "?" . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// ======================================================================
//  TELEGRAM HELPERS
// ======================================================================

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $url  = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => $parse_mode,
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    HttpCallnohead($url, http_build_query($data), [], 'POST');
}

function editMessageText($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $url  = 'https://api.telegram.org/bot' . BOT_TOKEN . '/editMessageText';
    $data = [
        'chat_id'    => $chat_id,
        'message_id' => $message_id,
        'text'       => $text,
        'parse_mode' => $parse_mode,
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    HttpCallnohead($url, http_build_query($data), [], 'POST');
}

function randIp() { 
    return rand(100,200).".".rand(10,250).".".rand(10,250).".".rand(1,250); 
}

function randName() {
    $names=['Aarav','Vihaan','Reyansh','Ayaan','Arjun','Kabir','Advait','Vivaan','Aadhya','Anaya','Diya','Myra','Kiara','Isha','Meera','Pari','Saanvi','Navya','Riya','Anika'];
    return $names[array_rand($names)].rand(100,999);
}

function randPhone() { 
    return "9".rand(100000000,999999999); 
}

function randGender() { 
    return rand(0,1)?"MALE":"FEMALE"; 
}

function randUserId() { 
    return bin2hex(random_bytes(8)); 
}

function genDeviceId() { 
    return bin2hex(random_bytes(8)); 
}

function getOrCreateUserData($chat_id) {
    $filePath = __DIR__ . "/data/{$chat_id}_shein.json";
    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) $data = [];
        if (!isset($data['shein_accounts']) || !is_array($data['shein_accounts'])) {
            $data['shein_accounts'] = [];
        }
        if (!isset($data['coupon_count'])) {
            $data['coupon_count'] = 0;
        }
        if (!isset($data['cookie_workflow_active'])) {
            $data['cookie_workflow_active'] = false;
        }
        return $data;
    }
    return [
        'chat_id'        => $chat_id,
        'step'           => 'start',
        'shein_accounts' => [],
        'coupon_count'   => 0,
        'cookie_workflow_active' => false,
        'created_at'     => date('Y-m-d H:i:s'),
    ];
}

function saveUserData($chat_id, array $data) {
    $dir = __DIR__ . "/data";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $filePath = $dir . "/{$chat_id}_shein.json";
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

function saveInstagramData($chat_id, array $data) {
    $dir = __DIR__ . "/data";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $filePath = $dir . "/{$chat_id}_insta.json";
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

function checkChannelMembership($chat_id, $channel_ids) {
    foreach ($channel_ids as $channel_id) {
        $url  = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getChatMember';
        $data = [
            'chat_id' => $channel_id,
            'user_id' => $chat_id,
        ];
        $response = HttpCallnohead($url, http_build_query($data), [], 'POST');
        if ($response === false) {
            return false;
        }
        $decoded = json_decode($response, true);

        if (
            !is_array($decoded) ||
            !isset($decoded['ok']) ||
            !$decoded['ok'] ||
            !isset($decoded['result']['status']) ||
            !in_array($decoded['result']['status'], ['member', 'administrator', 'creator'])
        ) {
            return false;
        }
    }
    return true;
}

function sendSheinAccountMenu($chat_id, &$userData, $prefixText = '') {
    if (empty($userData['shein_accounts']) || !is_array($userData['shein_accounts'])) {
        sendMessage(
            $chat_id,
            $prefixText .
            "❌ No saved Shein accounts.\n\nClick 'Generate Coupon' to create one automatically."
        );
        $userData['step'] = 'idle';
        saveUserData($chat_id, $userData);
        return;
    }

    $keyboard = ['inline_keyboard' => []];

    foreach ($userData['shein_accounts'] as $mobile => $acc) {
        $label = '+91-' . $mobile;
        if (!empty($acc['instagram']['username'])) {
            $label .= ' (IG: @' . $acc['instagram']['username'] . ')';
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => $label,                 'callback_data' => 'select_shein_' . $mobile],
            ['text' => '🗑',                   'callback_data' => 'delete_shein_' . $mobile],
        ];
    }

    $text = $prefixText . "📱 <b>Select a Shein account:</b>";
    sendMessage($chat_id, $text, $keyboard);

    $userData['step'] = 'select_shein';
    saveUserData($chat_id, $userData);
}

function sendMainMenu($chat_id, &$userData, $prefixText = '') {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎫 Generate Coupon', 'callback_data' => 'generate_coupon'],
                ['text' => '📱 My Numbers',      'callback_data' => 'my_numbers']
            ],
            [
                ['text' => '❌ Cancel', 'callback_data' => 'cancel_workflow']
            ]
        ]
    ];
    sendMessage($chat_id, $prefixText . "Welcome! Use the buttons below:", $keyboard);
    $userData['step'] = 'idle';
    saveUserData($chat_id, $userData);
}

function autoGenerateSheinAccount($chat_id, &$userData) {
    sendMessage($chat_id, "🔄 Generating new Shein account automatically (no OTP needed)...");

    $clientToken = null;
    for ($i = 0; $i < 5; $i++) {
        $ip = randIp(); $adId = genDeviceId();
        $url = "https://api.sheinindia.in/uaas/jwt/token/client";
        $headers = [
            "Client_type: Android/29", "Accept: application/json", "Client_version: 1.0.8",
            "User-Agent: Android", "X-Tenant-Id: SHEIN", "Ad_id: $adId",
            "X-Tenant: B2C", "Content-Type: application/x-www-form-urlencoded",
            "X-Forwarded-For: $ip"
        ];
        $data = "grantType=client_credentials&clientName=trusted_client&clientSecret=secret";
        $res = HttpCallnohead($url, $data, $headers, "POST");
        $j = json_decode($res, true);
        if (!empty($j['access_token'])) {
            $clientToken = $j['access_token'];
            break;
        }
    }
    if (!$clientToken) {
        sendMessage($chat_id, "❌ Failed to get client token.");
        return;
    }

    $mobile = null;
    for ($i = 0; $i < 50; $i++) {
        $candidate = randPhone();
        $ip = randIp(); $adId = genDeviceId();
        $url = "https://api.sheinindia.in/uaas/accountCheck?client_type=Android%2F29&client_version=1.0.8";
        $headers = [
            "Authorization: Bearer $clientToken", "Requestid: account_check", "X-Tenant: B2C",
            "Accept: application/json", "User-Agent: Android", "Client_type: Android/29",
            "Client_version: 1.0.8", "X-Tenant-Id: SHEIN", "Ad_id: $adId",
            "Content-Type: application/x-www-form-urlencoded", "X-Forwarded-For: $ip"
        ];
        $data = "mobileNumber=$candidate";
        $res = HttpCallnohead($url, $data, $headers, "POST");
        $j = json_decode($res, true);
        if (isset($j['success']) && $j['success'] === false) {
            $mobile = $candidate;
            break;
        }
    }
    if (!$mobile) {
        sendMessage($chat_id, "❌ Could not find unregistered number. Try again later.");
        return;
    }

    $name = randName();
    $gender = randGender();
    $userId = randUserId();

    $payload = json_encode([
        "client_type" => "Android/29",
        "client_version" => "1.0.8",
        "gender" => $gender,
        "phone_number" => $mobile,
        "secret_key" => "3LFcKwBTXcsMzO5LaUbNYoyMSpt7M3RP5dW9ifWffzg",
        "user_id" => $userId,
        "user_name" => $name
    ]);
    $ip = randIp(); $adId = genDeviceId();
    $headers = [
        "Accept: application/json", "User-Agent: Android", "Client_type: Android/29",
        "Client_version: 1.0.8", "X-Tenant-Id: SHEIN", "Ad_id: $adId",
        "Content-Type: application/json; charset=UTF-8",
        "X-Forwarded-For: $ip"
    ];
    $res = HttpCallnohead("https://shein-creator-backend-151437891745.asia-south1.run.app/api/v1/auth/generate-token", $payload, $headers, "POST");
    $j = json_decode($res, true);

    if (empty($j['access_token'])) {
        sendMessage($chat_id, "❌ Failed to generate creator token.");
        return;
    }

    $creatorToken = $j['access_token'];

    if (!isset($userData['shein_accounts'])) $userData['shein_accounts'] = [];
    $userData['shein_accounts'][$mobile] = [
        'mobile_number' => $mobile,
        'creator_token' => $creatorToken,
        'profile_data'  => ['firstName' => $name, 'mobileNumber' => $mobile, 'genderType' => $gender],
        'instagram'     => [],
        'created_at'    => date('Y-m-d H:i:s'),
    ];
    saveUserData($chat_id, $userData);

    sendMessage($chat_id, "✅ New Shein account generated!\n\n📱 Mobile: +91-$mobile\n👤 Name: $name\n🔑 Ready for Instagram linking");
    sendSheinAccountMenu($chat_id, $userData, "✅ Account created successfully!\n\n");
}

function validateInstagramCookie($cookieString) {
    $required = ['sessionid', 'csrftoken', 'ds_user_id'];
    $found = [];
    
    $parts = explode(';', $cookieString);
    foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, '=') !== false) {
            list($key, $value) = explode('=', $part, 2);
            $key = trim($key);
            if (in_array($key, $required)) {
                $found[$key] = trim($value);
            }
        }
    }
    
    foreach ($required as $req) {
        if (!isset($found[$req]) || empty($found[$req])) {
            return ['valid' => false, 'missing' => $req];
        }
    }
    
    return ['valid' => true, 'cookies' => $found];
}

function getinsta($input, $creatorToken, $adId, $ip, $mode = 'connect') {
    $userpass = 'unknown:unknown';
    $useragent = 'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36';
    $cookieString = $input;

    if (strpos($input, '|') !== false) {
        $parts_all = explode('|', $input);
        $cookieString = trim(array_pop($parts_all));
        if (!empty($parts_all)) {
            $maybe_ua = trim(array_pop($parts_all));
            if (!empty($maybe_ua)) {
                $useragent = $maybe_ua;
            }
        }
        if (!empty($parts_all)) {
            $maybe_up = trim(array_pop($parts_all));
            if (!empty($maybe_up)) {
                $userpass = $maybe_up;
            }
        }
    } else {
        if (stripos($cookieString, 'cookie:') === 0) {
            $cookieString = trim(preg_replace('/^cookie:\s*/i', '', $cookieString));
        }
    }

    $cookieValidation = validateInstagramCookie($cookieString);
    if (!$cookieValidation['valid']) {
        return ['success' => false, 'error' => 'Invalid cookie: missing ' . ($cookieValidation['missing'] ?? 'unknown')];
    }

    $cookieString = trim($cookieString);
    $cookie = preg_replace('/Authorization=[^;]+;?\s*/i', '', $cookieString);
    $cookie .= "; ig_nrcb=1; ps_l=1; ps_n=1";
    $cookie = trim(preg_replace('/\s*;\s*/', '; ', $cookie), '; ');

    $url = "https://www.instagram.com/consent/?flow=ig_biz_login_oauth&params_json=%7B%22client_id%22%3A%22713904474873404%22%2C%22redirect_uri%22%3A%22https%3A%5C%2F%5C%2Fsheinverse.galleri5.com%5C%2Finstagram%22%2C%22response_type%22%3A%22code%22%2C%22state%22%3A%22your_csrf_token%22%2C%22scope%22%3A%22instagram_business_basic%22%2C%22logger_id%22%3A%22732d54ea-9582-49f0-8dfb-48c5015047ea%22%2C%22app_id%22%3A%22713904474873404%22%2C%22platform_app_id%22%3A%22713904474873404%22%7D&source=oauth_permissions_page_www";
    
    $headers = [
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
        "accept-encoding: gzip, deflate, br",
        "accept-language: en-US,en;q=0.9",
        "cache-control: max-age=0",
        "cookie: $cookie",
        "dpr: 2.625",
        "priority: u=0, i",
        "sec-ch-prefers-color-scheme: light",
        "sec-ch-ua: \"Chromium\";v=\"120\", \"Not(A:Brand\";v=\"24\", \"Google Chrome\";v=\"120\"",
        "sec-ch-ua-full-version-list: \"Chromium\";v=\"120.0.6099.144\", \"Not(A:Brand\";v=\"24.0.0.0\", \"Google Chrome\";v=\"120.0.6099.144\"",
        "sec-ch-ua-mobile: ?1",
        "sec-ch-ua-model: \"SM-S908B\"",
        "sec-ch-ua-platform: \"Android\"",
        "sec-ch-ua-platform-version: \"13.0.0\"",
        "sec-fetch-dest: document",
        "sec-fetch-mode: navigate",
        "sec-fetch-site: none",
        "sec-fetch-user: ?1",
        "upgrade-insecure-requests: 1",
        "user-agent: $useragent",
        "viewport-width: 412"
    ];

    $response = httpCallAdvanced($url, null, $headers, "GET", true);
    
    if (!$response['success']) {
        return $response;
    }

    $res = $response['body'];

    preg_match('/"actorID"\s*:\s*"(\d+)"/', $res, $m);  $av = $m[1] ?? '';
    preg_match('/"rev"\s*:\s*(\d+)/', $res, $m);        $rev = $m[1] ?? '';
    preg_match('/"haste_session"\s*:\s*"([^"]+)"/', $res, $m); $hs = $m[1] ?? '';
    preg_match('/"hsi"\s*:\s*"([^"]+)"/', $res, $m);    $hsi = $m[1] ?? '';
    preg_match('/"f"\s*:\s*"([^"]+)"/', $res, $m);      $fb_dtsg = $m[1] ?? '';
    preg_match('/jazoest=(\d+)/', $res, $m);            $jazoest = $m[1] ?? '';
    preg_match('/"LSD",\[,\{"token":"([^"]+)"\}/', $res, $m); $lsd = $m[1] ?? '';
    preg_match('/"__spin_r"\s*:\s*(\d+)/', $res, $m);   $spin_r = $m[1] ?? '';
    preg_match('/"__spin_t"\s*:\s*(\d+)/', $res, $m);   $spin_t = $m[1] ?? '';
    preg_match('/"experience_id"\s*:\s*"([^"]+)"/', $res, $m); $experience_id = $m[1] ?? '';

    if (empty($fb_dtsg) || empty($lsd) || empty($experience_id)) {
        return ['success' => false, 'error' => 'Failed to extract tokens (cookie may be expired)'];
    }

    $csrftoken = '';
    $parts = explode(';', $cookie);
    foreach ($parts as $part) {
        if (strpos(trim($part), 'csrftoken=') === 0) {
            $csrftoken = trim(substr(trim($part), 10));
            break;
        }
    }

    if ($mode === 'unlink') {
        $convertHeaders = [
            'accept: */*',
            'accept-language: en-US,en;q=0.9',
            'content-type: application/x-www-form-urlencoded',
            "cookie: $cookie",
            'origin: https://www.instagram.com',
            'referer: https://www.instagram.com/accounts/convert_to_professional_account/',
            "user-agent: $useragent",
            'x-asbd-id: 129477',
            "x-csrftoken: $csrftoken",
            'x-ig-app-id: 936619743392459',
            "x-instagram-ajax: $rev",
            'x-requested-with: XMLHttpRequest',
            "sec-ch-ua: \"Chromium\";v=\"120\", \"Not(A:Brand\";v=\"24\"",
            "sec-ch-ua-mobile: ?1",
            "sec-ch-ua-platform: \"Android\"",
            "sec-fetch-dest: empty",
            "sec-fetch-mode: cors",
            "sec-fetch-site: same-origin"
        ];
        $convertPayload = 'category_id=2903&create_business_id=true&entry_point=ig_web_settings&set_public=true&should_bypass_contact_check=true&should_show_category=0&to_account_type=3';
        httpCallAdvanced('https://www.instagram.com/api/v1/business/account/convert_account/', $convertPayload, $convertHeaders, 'POST');
        usleep(800000);
    }

    $variables = json_encode([
        "input" => [
            "client_mutation_id" => "2",
            "actor_id" => $av,
            "device_id" => null,
            "experience_id" => $experience_id,
            "extra_params_json" => json_encode([
                "client_id" => "713904474873404",
                "redirect_uri" => "https://sheinverse.galleri5.com/instagram",
                "response_type" => "code",
                "state" => null,
                "scope" => "instagram_business_basic",
                "logger_id" => bin2hex(random_bytes(18)),
                "app_id" => "713904474873404",
                "platform_app_id" => "713904474873404"
            ]),
            "flow" => "IG_BIZ_LOGIN_OAUTH",
            "inputs_json" => json_encode(["instagram_business_basic" => "true"]),
            "outcome" => "APPROVED",
            "outcome_data_json" => json_encode(new stdClass()),
            "prompt" => "IG_BIZ_LOGIN_OAUTH_PERMISSION_CARD",
            "runtime" => "SAHARA",
            "source" => "oauth_permissions_page_www",
            "surface" => "INSTAGRAM_WEB"
        ]
    ], JSON_UNESCAPED_SLASHES);

    $postData = http_build_query([
        'av' => $av,
        '__user' => '0',
        '__a' => '1',
        '__req' => '6',
        '__hs' => $hs,
        'dpr' => '2',
        '__ccg' => 'EXCELLENT',
        '__rev' => $rev,
        '__hsi' => $hsi,
        '__comet_req' => '15',
        'fb_dtsg' => $fb_dtsg,
        'jazoest' => $jazoest,
        'lsd' => $lsd,
        '__spin_r' => $spin_r,
        '__spin_b' => 'trunk',
        '__spin_t' => $spin_t,
        'fb_api_caller_class' => 'RelayModern',
        'fb_api_req_friendly_name' => 'useSaharaCometConsentPostPromptOutcomeServerMutation',
        'variables' => $variables,
        'server_timestamps' => 'true',
        'doc_id' => '9822638027828705'
    ]);

    $graphqlHeaders = [
        'accept: */*',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/x-www-form-urlencoded',
        "cookie: $cookie",
        'origin: https://www.instagram.com',
        'referer: ' . $url,
        "user-agent: $useragent",
        'x-asbd-id: 129477',
        'x-fb-friendly-name: useSaharaCometConsentPostPromptOutcomeServerMutation',
        "x-fb-lsd: $lsd",
        'x-ig-app-id: 1217981644879628',
        'x-requested-with: XMLHttpRequest',
        "sec-ch-ua: \"Chromium\";v=\"120\", \"Not(A:Brand\";v=\"24\"",
        "sec-ch-ua-mobile: ?1",
        "sec-ch-ua-platform: \"Android\"",
        "sec-ch-ua-platform-version: \"13.0.0\"",
        "sec-fetch-dest: empty",
        "sec-fetch-mode: cors",
        "sec-fetch-site: same-origin"
    ];

    $graphqlResponse = httpCallAdvanced('https://www.instagram.com/api/graphql/', $postData, $graphqlHeaders, 'POST');
    
    if (!$graphqlResponse['success']) {
        return $graphqlResponse;
    }

    return ['success' => true, 'body' => $graphqlResponse['body']];
}

// ======================================================================
//  VOUCHER POLLING
// ======================================================================

function fetchVoucherWithRetry($creatorToken, $ip, $maxRetries = 30, $retryDelay = 4) {
    $url = "https://shein-creator-backend-151437891745.asia-south1.run.app/api/v1/user";
    $headers = [
        "Connection: keep-alive",
        "Authorization: Bearer $creatorToken",
        "User-Agent: Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36",
        "Content-Type: application/json",
        "Accept: */*",
        "Origin: https://sheinverse.galleri5.com",
        "Referer: https://sheinverse.galleri5.com/",
        "X-Forwarded-For: $ip"
    ];

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $response = HttpCallnohead($url, null, $headers, "GET");
        if (!$response) { 
            sleep($retryDelay); 
            continue; 
        }
        $result = json_decode($response, true);
        if (!$result || !isset($result['message']) || $result['message'] !== 'Profile fetched successfully') {
            sleep($retryDelay); 
            continue;
        }

        $voucherData = $result['user_data']['voucher_data'] ?? null;
        $vouchers = $result['user_data']['vouchers'] ?? [];

        if ($voucherData && !empty($voucherData['voucher_code'])) return $voucherData;
        if (!empty($vouchers) && !empty($vouchers[0]['voucher_code'])) return $vouchers[0];

        sleep($retryDelay);
    }
    return null;
}

// ======================================================================
//  IMPROVED INSTAGRAM LINKING WITH RETRY LIMITS
// ======================================================================

function handleInstagramLinking($chat_id, $message_id, &$userData, $mode = 'connect') {
    $cookieRaw = $userData['instagram_cookie_raw'] ?? null;
    $creatorToken = $userData['creator_token'] ?? null;
    $ip = randIp();
    $adId = genDeviceId();
    $currentMobile = $userData['current_mobile'] ?? null;

    if (!$cookieRaw || !$creatorToken || !$currentMobile) {
        sendMessage($chat_id, "❌ Missing data. Please select an account first.");
        return;
    }

    $cookieValidation = validateInstagramCookie($cookieRaw);
    if (!$cookieValidation['valid']) {
        sendMessage($chat_id, "❌ Invalid cookie format!\n\nMissing: " . ($cookieValidation['missing'] ?? 'unknown') . "\n\n<b>Required cookies:</b>\n• sessionid\n• csrftoken\n• ds_user_id\n\nPlease send a valid Instagram cookie string.");
        $userData['step'] = $mode === 'unlink' ? 'ask_instagram_cookie_unlink' : 'ask_instagram_cookie';
        saveUserData($chat_id, $userData);
        return;
    }

    sendMessage($chat_id, "⏳ Connecting Instagram account...\n\n⚠️ Max 5 retries. Send /cancel to stop.");

    $userData['cookie_workflow_active'] = true;
    saveUserData($chat_id, $userData);

    $maxRetries = 5;
    $retryCount = 0;

    while ($retryCount < $maxRetries) {
        $userData = getOrCreateUserData($chat_id);
        if (empty($userData['cookie_workflow_active'])) {
            sendMessage($chat_id, "❌ Cancelled by user.");
            return;
        }

        $retryCount++;
        sendMessage($chat_id, "🔄 Attempt $retryCount/$maxRetries - Getting OAuth code...");

        $instagram_response = getinsta($cookieRaw, $creatorToken, $adId, $ip, $mode);
        
        if (!$instagram_response['success']) {
            $errorMsg = $instagram_response['error'] ?? 'Unknown error';
            $httpCode = $instagram_response['http_code'] ?? 0;
            
            if ($httpCode == 403) {
                sendMessage($chat_id, "❌ Akamai blocked (403)\n\n💡 <b>Solutions:</b>\n• Wait 10-15 minutes\n• Use VPN/different network\n• Get fresh cookie from IG mobile app\n• Try during off-peak hours\n\nRetrying in 12 seconds...");
                sleep(12);
                continue;
            }
            
            sendMessage($chat_id, "⚠️ Error: $errorMsg (HTTP $httpCode)\n\nRetrying in 6 seconds...");
            sleep(6);
            continue;
        }

        $instagram_json = json_decode($instagram_response['body'], true);
        if (!$instagram_json) {
            sendMessage($chat_id, "⚠️ Invalid JSON response. Cookie might be expired.\n\nRetrying...");
            sleep(5);
            continue;
        }

        saveInstagramData($chat_id, $instagram_json);

        $oauth_code = null;
        if (isset($instagram_json['data']['post_prompt_outcome']['finish_flow_action']['link_uri']['uri'])) {
            $uri = $instagram_json['data']['post_prompt_outcome']['finish_flow_action']['link_uri']['uri'];
            $p = parse_url($uri);
            if (!empty($p['query'])) {
                parse_str($p['query'], $q);
                if (!empty($q['u'])) {
                    $decoded_url = urldecode($q['u']);
                    $fp = parse_url($decoded_url);
                    if (!empty($fp['query'])) {
                        parse_str($fp['query'], $q2);
                        if (!empty($q2['code'])) {
                            $oauth_code = $q2['code'];
                        }
                    }
                }
            }
        }

        if (!$oauth_code) {
            sendMessage($chat_id, "⚠️ No OAuth code in response. This might mean:\n• Cookie expired\n• Account already linked\n• Instagram API changed\n\nRetrying...");
            sleep(7);
            continue;
        }

        sendMessage($chat_id, "✅ OAuth code obtained! Connecting to Shein backend...");

        $instagram_api_url = "https://shein-creator-backend-151437891745.asia-south1.run.app/api/v5/instagram";
        $instagram_api_headers = [
            "Authorization: Bearer " . $creatorToken,
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36",
            "Accept: */*",
            "Origin: https://sheinverse.galleri5.com",
            "Referer: https://sheinverse.galleri5.com/",
            "Sec-Fetch-Site: cross-site",
            "Sec-Fetch-Mode: cors",
            "Sec-Fetch-Dest: empty"
        ];

        $instagram_api_data = json_encode([
            "code" => $oauth_code,
            "redirectUri" => "https://sheinverse.galleri5.com/instagram"
        ]);

        $sheinResponse = httpCallAdvanced($instagram_api_url, $instagram_api_data, $instagram_api_headers, "POST");

        if (!$sheinResponse['success']) {
            sendMessage($chat_id, "❌ Shein API connection failed: " . ($sheinResponse['error'] ?? 'Unknown') . "\n\nRetrying in 8 seconds...");
            sleep(8);
            continue;
        }

        $instagram_api_json = json_decode($sheinResponse['body'], true);
        saveInstagramData($chat_id, $instagram_api_json);

        if (!isset($instagram_api_json['message']) || $instagram_api_json['message'] !== 'Instagram connection successful') {
            $errorMsg = $instagram_api_json['message'] ?? json_encode($instagram_api_json);
            sendMessage($chat_id, "❌ Shein says: $errorMsg\n\nRetrying in 8 seconds...");
            sleep(8);
            continue;
        }

        $igUsername = $instagram_api_json['user_data']['username'] ?? 'N/A';
        $followers = $instagram_api_json['user_data']['followers_count'] ?? 'N/A';
        $accountType = $instagram_api_json['user_data']['account_type'] ?? 'N/A';

        $voucherData = null;
        if (isset($instagram_api_json['voucher']) && !empty($instagram_api_json['voucher']['voucher_amount'])) {
            $voucherData = $instagram_api_json['voucher'];
        }

        if (!$voucherData) {
            sendMessage($chat_id, "⌛ Voucher not immediately available. Polling Shein API...");
            $voucherData = fetchVoucherWithRetry($creatorToken, $ip, 20, 5);
        }

        $voucherBlock = $voucherData 
            ? "🎫 <b>Voucher Code:</b> <code>{$voucherData['voucher_code']}</code>\n💵 <b>Amount:</b> ₹{$voucherData['voucher_amount']}\n📅 <b>Expires:</b> {$voucherData['expiry_date']}" 
            : "⚠️ Voucher not generated yet. Try checking later.";

        if ($currentMobile && isset($userData['shein_accounts'][$currentMobile])) {
            $userData['shein_accounts'][$currentMobile]['instagram'] = [
                'username' => $igUsername,
                'followers_count' => $followers,
                'account_type' => $accountType,
                'voucher' => $voucherData,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if ($voucherData) {
                $userData['coupon_count'] = ($userData['coupon_count'] ?? 0) + 1;
            }
            saveUserData($chat_id, $userData);
        }

        $response = "🎉 <b>SUCCESS!</b>\n\n👤 <b>Instagram:</b> @$igUsername\n👥 <b>Followers:</b> $followers\n📊 <b>Account Type:</b> $accountType\n\n$voucherBlock";
        sendMessage($chat_id, $response);
        
        sleep(1);
        sendSheinAccountMenu($chat_id, $userData, "✅ Instagram linked successfully!\n\n");

        $userData['cookie_workflow_active'] = false;
        $userData['step'] = 'idle';
        saveUserData($chat_id, $userData);
        return;
    }

    sendMessage($chat_id, "❌ <b>Failed after $maxRetries attempts</b>\n\n💡 <b>Troubleshooting:</b>\n\n1️⃣ <b>Check cookie validity:</b>\n   • Login to Instagram\n   • Get fresh cookies\n   • Make sure sessionid is valid\n\n2️⃣ <b>Network issues:</b>\n   • Try different WiFi/mobile data\n   • Use VPN (US/EU recommended)\n   • Wait 15-20 minutes\n\n3️⃣ <b>Account issues:</b>\n   • Make sure it's a business account\n   • Check if account is already linked\n   • Try different Instagram account\n\n4️⃣ <b>Akamai blocking:</b>\n   • Use Instagram mobile app cookies\n   • Try during off-peak hours (2-6 AM IST)\n   • Use residential proxy if available");
    
    $userData['cookie_workflow_active'] = false;
    $userData['step'] = 'idle';
    saveUserData($chat_id, $userData);
}

// ======================================================================
//  CHANNEL CHECK HANDLER
// ======================================================================

function handleChannelCheck($chat_id, $message_id = "", &$userData) {
    $allJoined = checkChannelMembership($chat_id, CHANNEL_IDS);
    if ($allJoined) {
        sendMainMenu($chat_id, $userData, "✅ Joined all channels!");
    } else {
        $keyboard = ['inline_keyboard' => []];
        foreach (CHANNEL_IDS as $c) {
            $keyboard['inline_keyboard'][] = [['text' => "Join $c", 'url' => "https://t.me/" . substr($c, 1)]];
        }
        $keyboard['inline_keyboard'][] = [['text' => '✅ I Joined All', 'callback_data' => 'check_channels_again']];
        $msg = "❌ <b>Please join all channels first:</b>\n\n" . implode("\n", CHANNEL_IDS) . "\n\nThen click the button below.";
        $message_id ? editMessageText($chat_id, $message_id, $msg, $keyboard) : sendMessage($chat_id, $msg, $keyboard);
    }
}

// ======================================================================
//  MAIN WEBHOOK HANDLER
// ======================================================================

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit('Not a Telegram update.');

$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
$message_id = $update['message']['message_id'] ?? $update['callback_query']['message']['message_id'] ?? null;
$text = $update['message']['text'] ?? null;
$callback_data = $update['callback_query']['data'] ?? null;

if (!$chat_id) exit('No chat ID.');

$userData = getOrCreateUserData($chat_id);
$step = $userData['step'] ?? 'start';

if ($text === '/start') {
    $userData['step'] = 'check_channels';
    saveUserData($chat_id, $userData);
    handleChannelCheck($chat_id, null, $userData);
    exit();
}

if ($text === '/cancel' || $text === '/cancle') {
    $userData['cookie_workflow_active'] = false;
    $userData['step'] = 'idle';
    saveUserData($chat_id, $userData);
    sendMessage($chat_id, "❌ Operation cancelled.");
    exit();
}

if ($callback_data) {
    if ($callback_data === 'check_channels_again') {
        handleChannelCheck($chat_id, $message_id, $userData);

    } elseif ($callback_data === 'generate_coupon') {
        autoGenerateSheinAccount($chat_id, $userData);

    } elseif ($callback_data === 'my_numbers') {
        sendSheinAccountMenu($chat_id, $userData, "");

    } elseif ($callback_data === 'cancel_workflow') {
        $userData['cookie_workflow_active'] = false;
        $userData['step'] = 'idle';
        saveUserData($chat_id, $userData);
        editMessageText($chat_id, $message_id, "❌ Operation cancelled.");

    } elseif (strpos($callback_data, 'select_shein_') === 0) {
        $mobile = substr($callback_data, strlen('select_shein_'));
        if (isset($userData['shein_accounts'][$mobile])) {
            $acc = $userData['shein_accounts'][$mobile];
            $text = "📱 <b>Shein Account: +91-$mobile</b>\n\n";
            
            if (!empty($acc['instagram']['username'])) {
                $text .= "📷 <b>Instagram:</b> @{$acc['instagram']['username']}\n";
                $text .= "👥 <b>Followers:</b> {$acc['instagram']['followers_count']}\n\n";
                
                if (!empty($acc['instagram']['voucher']['voucher_code'])) {
                    $v = $acc['instagram']['voucher'];
                    $text .= "🎫 <b>Voucher:</b> <code>{$v['voucher_code']}</code>\n";
                    $text .= "💵 <b>Amount:</b> ₹{$v['voucher_amount']}\n";
                    $text .= "📅 <b>Expires:</b> {$v['expiry_date']}\n";
                }
            } else {
                $text .= "📷 <b>Instagram:</b> Not linked\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔗 Link Instagram', 'callback_data' => 'ig_step1_' . $mobile]],
                    [['text' => '🔁 Unlink & Relink', 'callback_data' => 'ig_step2_' . $mobile]],
                    [['text' => '🗑 Delete Account', 'callback_data' => 'delete_shein_' . $mobile]],
                    [['text' => '⬅️ Back', 'callback_data' => 'my_numbers']]
                ]
            ];
            sendMessage($chat_id, $text, $keyboard);
            $userData['current_mobile'] = $mobile;
            $userData['creator_token'] = $acc['creator_token'];
            saveUserData($chat_id, $userData);
        }

    } elseif (strpos($callback_data, 'delete_shein_') === 0) {
        $mobile = substr($callback_data, strlen('delete_shein_'));
        unset($userData['shein_accounts'][$mobile]);
        saveUserData($chat_id, $userData);
        sendSheinAccountMenu($chat_id, $userData, "🗑 Account deleted.\n\n");

    } elseif (strpos($callback_data, 'ig_step1_') === 0) {
        $mobile = substr($callback_data, strlen('ig_step1_'));
        $userData['current_mobile'] = $mobile;
        $userData['creator_token'] = $userData['shein_accounts'][$mobile]['creator_token'];
        $userData['insta_flow'] = 'connect';
        $userData['step'] = 'ask_instagram_cookie';
        saveUserData($chat_id, $userData);
        sendMessage($chat_id, "🔗 <b>Link Instagram Account</b>\n\n📋 Send your Instagram cookie string.\n\n<b>Required cookies:</b>\n• sessionid\n• csrftoken\n• ds_user_id\n\n<i>Get cookies from Instagram login session.</i>");

    } elseif (strpos($callback_data, 'ig_step2_') === 0) {
        $mobile = substr($callback_data, strlen('ig_step2_'));
        $userData['current_mobile'] = $mobile;
        $userData['creator_token'] = $userData['shein_accounts'][$mobile]['creator_token'];
        $userData['insta_flow'] = 'unlink';
        $userData['step'] = 'ask_instagram_cookie_unlink';
        saveUserData($chat_id, $userData);
        sendMessage($chat_id, "🔁 <b>Unlink & Relink Instagram</b>\n\n📋 Send Instagram cookie for <b>SECOND account</b> (to replace current one).\n\n<b>Required cookies:</b>\n• sessionid\n• csrftoken\n• ds_user_id");

    }
    exit();
}

switch ($step) {
    case 'check_channels':
        handleChannelCheck($chat_id, $message_id, $userData);
        break;

    case 'ask_instagram_cookie':
    case 'ask_instagram_cookie_unlink':
        if ($text) {
            $userData['instagram_cookie_raw'] = $text;
            saveUserData($chat_id, $userData);
            handleInstagramLinking($chat_id, $message_id, $userData, $userData['insta_flow'] ?? 'connect');
        }
        break;

    default:
        sendMessage($chat_id, "Use /start to begin");
        break;
}
?>