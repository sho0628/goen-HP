<?php
/**
 * お問い合わせフォーム送信処理（Xサーバー用）
 *
 * contact.html のフォームから POST を受け取り、次の順に処理します。
 *   1. スパム対策 … IP ごとの回数制限／ハニーポット／表示から送信までの所要時間
 *                  （所要時間は main.js が入れるので、JavaScript なしの送信は受け付けない）
 *   2. 入力検証   … 必須・形式・文字数／名前・本文の URL 禁止／本文は日本語必須
 *   3. 通知メール … RECIPIENT へ（Reply-To は送信者のアドレス）
 *   4. 自動返信   … 送信者へ受付確認（AUTO_REPLY が true のとき）
 *
 * 結果は、fetch 経由（Accept: application/json）なら JSON で、
 * JS 無効時の通常送信なら contact.html へリダイレクトして返します。
 *
 * 設定は下の定数だけです。パスワード等の秘密情報は含みません。
 * 動作確認: /contact.php?ping=1 を開くと {"ok":true,...} が返ります（メールは送りません）。
 */
declare(strict_types=1);

// ---------------------------------------------------------------- 設定
const RECIPIENT   = 'info@goen-partner.com';        // 通知メールの宛先
const FROM_EMAIL  = 'info@goen-partner.com';        // 送信元（このサーバー上に実在するアドレス）
const FROM_NAME   = '結婚相談所Goen life partner';   // 送信者名
const SITE_URL    = 'https://goen-partner.com';
const PHONE       = '070-7603-5050';
const AUTO_REPLY  = true;   // 送信者への自動返信を行うか
const MIN_SECONDS = 3;      // ページ表示から送信までがこの秒数未満なら bot とみなす
const RATE_LIMIT  = 5;      // 同一 IP から RATE_WINDOW 秒間に受け付ける上限回数
const RATE_WINDOW = 600;
// ----------------------------------------------------------------------

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');
mb_language('uni'); // メールを UTF-8（BASE64）で送る（絵文字も化けない）

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 疎通確認（デプロイ後の動作チェック用。メールは送らない）
if ($httpMethod === 'GET' && isset($_GET['ping'])) {
    respond(200, [
        'ok'       => true,
        'time'     => date('c'),
        'mail'     => function_exists('mail'),
        'mbstring' => function_exists('mb_send_mail'),
    ]);
}

if ($httpMethod !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'このURLには直接アクセスできません。'], 'invalid');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// 1. スパム対策 --------------------------------------------------------
if (rate_limited($ip)) {
    error_log("[goen-contact] rate limited ip={$ip}");
    respond(429, [
        'ok'      => false,
        'message' => '短時間に多くの送信がありました。しばらく時間をおいてから再度お試しください。',
    ], 'limit');
}

// 人間には見えない欄（fax）への入力や、ありえない所要時間は bot。
// 成功したように見せて、メールは送らない（bot に対策を学習させないため）
$honeypot = clean(post('fax'), 50, false);
$elapsed  = post('elapsed');
$badTime  = $elapsed !== '' && (!ctype_digit($elapsed) || (int) $elapsed < MIN_SECONDS * 1000);
if ($honeypot !== '' || $badTime) {
    error_log("[goen-contact] spam suppressed ip={$ip} honeypot=" . ($honeypot !== '' ? 'y' : 'n') . ' elapsed=' . mb_substr($elapsed, 0, 20));
    respond(200, ['ok' => true, 'message' => 'お問い合わせを受け付けました。'], 'sent');
}

// elapsed は main.js が送信時に必ず入れる値。空なら JavaScript が動いていない。
// ページを開かずに直接送りつけてくる bot の大半はここで止まる。
// まれに JavaScript を切っている人もいるので、黙って捨てずに理由を返す。
if ($elapsed === '') {
    error_log("[goen-contact] rejected: no elapsed (JavaScript off or bot) ip={$ip}");
    respond(422, [
        'ok'      => false,
        'message' => 'JavaScriptが無効のため送信できませんでした。JavaScriptを有効にして再度お試しいただくか、お電話・メールにてご連絡ください。',
    ], 'js');
}

// 2. 入力検証 ----------------------------------------------------------
$name    = clean(post('name'),     50, false);
$kana    = clean(post('kana'),     50, false);
$email   = clean(post('email'),   254, false);
$tel     = clean(post('tel'),      30, false);
$age     = clean(post('age'),      10, false);
$consult = clean(post('method'),   30, false);
$message = clean(post('message'), 4000, true);
$consent = isset($_POST['consent']);

// 名前欄の URL は自動返信の宛名（○○ 様）に載ってしまい、第三者宛ての宣伝に悪用されるため禁止。
// 本文の URL・日本語を含まない本文は、海外からの宣伝スパムと営業メールの典型なので受け付けない。
// どれも人が書き直せば送れるよう、理由を表示する（黙って捨てない）。
$errors = [];
if ($name === '') {
    $errors['name'] = 'お名前をご入力ください。';
} elseif (has_url($name)) {
    $errors['name'] = 'お名前にURLは使用できません。';
}
if ($kana !== '' && has_url($kana)) {
    $errors['kana'] = 'フリガナにURLは使用できません。';
}
if ($email === '') {
    $errors['email'] = 'メールアドレスをご入力ください。';
} elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors['email'] = 'メールアドレスの形式をご確認ください。';
}
if ($tel !== '' && !preg_match('/^[0-9+\-() ]{8,20}$/', $tel)) {
    $errors['tel'] = '電話番号の形式をご確認ください。';
}
if ($message === '') {
    $errors['message'] = 'ご相談内容をご入力ください。';
} elseif (has_url($message)) {
    $errors['message'] = 'ご相談内容にURL（http〜、www.〜）は記載できません。';
} elseif (!has_japanese($message)) {
    $errors['message'] = 'ご相談内容は日本語でご記入ください。';
}
if (!$consent) {
    $errors['consent'] = '個人情報の取り扱いへのご同意をお願いいたします。';
}
if (!in_array($consult, ['どちらでも', 'カフェなどで対面', 'オンライン', 'お電話'], true)) {
    $consult = 'どちらでも';
}
if ($errors !== []) {
    // 入力内容は記録しない（どの欄で弾いたかだけ。正しい問い合わせを弾いていないかの確認用）
    error_log("[goen-contact] invalid input ip={$ip} fields=" . implode(',', array_keys($errors)));
    respond(422, ['ok' => false, 'message' => '入力内容をご確認ください。', 'errors' => $errors], 'invalid');
}

// 3. 通知メール --------------------------------------------------------
$ua   = clean((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 300, false);
$body = implode("\n", [
    FROM_NAME . ' のホームページより、お問い合わせが届きました。',
    '',
    '■ お名前：' . $name,
    '■ フリガナ：' . blank($kana),
    '■ メールアドレス：' . $email,
    '■ 電話番号：' . blank($tel),
    '■ ご年齢：' . blank($age),
    '■ ご希望の相談方法：' . $consult,
    '',
    '■ ご相談内容：',
    $message,
    '',
    '────────────────────────',
    '送信日時：' . date('Y年n月j日 H:i'),
    '送信元IP：' . $ip,
    'ブラウザ：' . blank($ua),
]);

if (!send_mail(RECIPIENT, '【HPお問い合わせ】' . $name . ' 様より', $body, ['Reply-To' => $email])) {
    error_log("[goen-contact] notification mail failed ip={$ip}");
    respond(500, [
        'ok'      => false,
        'message' => '送信に失敗しました。お手数ですが、お電話またはメールにてご連絡ください。',
    ], 'send');
}

// 4. 自動返信 ----------------------------------------------------------
// 送信者が書いた本文は含めない（第三者宛のスパム中継に悪用されないため）
if (AUTO_REPLY) {
    $reply = implode("\n", [
        $name . ' 様',
        '',
        'この度は、' . FROM_NAME . ' にお問い合わせいただき、誠にありがとうございます。',
        'お問い合わせを受け付けいたしました。',
        '',
        '内容を確認のうえ、2〜3営業日以内に担当者よりご連絡いたします。',
        'お急ぎの場合は、お電話（' . PHONE . '）でも承っております。',
        '',
        '※このメールは自動送信です。ご不明な点は、このメールにそのままご返信ください。',
        '',
        '────────────────────────',
        FROM_NAME,
        'TEL：' . PHONE,
        'MAIL：' . FROM_EMAIL,
        'WEB：' . SITE_URL,
    ]);
    if (!send_mail($email, '【' . FROM_NAME . '】お問い合わせを受け付けました', $reply)) {
        error_log("[goen-contact] auto-reply failed to={$email}");
    }
}

respond(200, ['ok' => true, 'message' => 'お問い合わせを受け付けました。'], 'sent');

// ================================================================ 関数

/** POST 値を文字列で取り出す（配列などが来たら空文字） */
function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}

/** 不正な文字を除き、長さを揃える。$multiline が false なら改行・タブも空白にする */
function clean(string $s, int $max, bool $multiline): string
{
    if (!mb_check_encoding($s, 'UTF-8')) {
        return '';
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[^\P{C}\n\t]+/u', '', $s) ?? '';   // 改行・タブ以外の制御文字を除去
    if (!$multiline) {
        $s = preg_replace('/[\n\t]+/', ' ', $s) ?? '';
    }
    $s = trim($s);
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
}

/** URL らしき文字列（http://〜、https://〜、www.〜）を含むか。全角で書いたものも検出する */
function has_url(string $s): bool
{
    return preg_match('~https?://|www\.~i', mb_convert_kana($s, 'a')) === 1;
}

/** ひらがな・カタカナ・漢字を含むか */
function has_japanese(string $s): bool
{
    return preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $s) === 1;
}

function blank(string $v): string
{
    return $v === '' ? '（未入力）' : $v;
}

/** 表示名を MIME encoded-word にする（ASCII のみならそのまま） */
function encode_name(string $s): string
{
    return preg_match('/^[\x20-\x7E]*$/', $s) === 1 ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/** UTF-8 でメール送信。件名・本文のエンコードは mb_send_mail に任せる。
 *  $extraHeaders は ['Reply-To' => '...'] のような連想配列（ヘッダー名 => 値） */
function send_mail(string $to, string $subject, string $body, array $extraHeaders = []): bool
{
    $headers = ['From' => encode_name(FROM_NAME) . ' <' . FROM_EMAIL . '>'];
    foreach ($extraHeaders as $header => $value) {
        $headers[$header] = $value;
    }
    try {
        return @mb_send_mail($to, $subject, $body, $headers, '-f' . FROM_EMAIL);
    } catch (\Throwable $e) {
        error_log('[goen-contact] mail error: ' . $e->getMessage());
        return false;
    }
}

/** 同一 IP の送信回数を一時ファイルで数え、上限を超えていれば true */
function rate_limited(string $ip): bool
{
    $dir = sys_get_temp_dir();
    if ($ip === '' || !is_dir($dir) || !is_writable($dir)) {
        return false;   // 数えられない環境では制限しない
    }
    $file = $dir . '/goen-contact-' . hash('sha256', $ip) . '.json';
    $fh   = @fopen($file, 'c+');
    if ($fh === false) {
        return false;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            return false;
        }
        $now   = time();
        $raw   = stream_get_contents($fh);
        $times = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        $times = array_values(array_filter(
            is_array($times) ? $times : [],
            static fn ($t) => is_int($t) && $t > $now - RATE_WINDOW
        ));
        $limited = count($times) >= RATE_LIMIT;
        if (!$limited) {
            $times[] = $now;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($times));
        fflush($fh);
        flock($fh, LOCK_UN);
        return $limited;
    } finally {
        fclose($fh);
    }
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0;
}

/**
 * JSON を返して終了。JS 無効時の通常送信（JSON を求めていない）なら
 * contact.html へ戻し、main.js が ?sent=1 / ?error=… を読んで結果を表示する。
 */
function respond(int $status, array $payload, string $redirectKey = ''): never
{
    if ($redirectKey === '' || wants_json()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $query = $redirectKey === 'sent' ? 'sent=1' : 'error=' . rawurlencode($redirectKey);
    header('Location: contact.html?' . $query, true, 303);
    exit;
}
