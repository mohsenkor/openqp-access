<?php
// OpenQP downloads on qchem.knu.ac.kr (PHP 7.4, MySQL, local postfix).
//  1. A visitor asks for a sign-in link and accepts the Academic Use Agreement; the
//     link is emailed and works once, for 20 minutes.
//  2. Signed in, they give name, institution and use, and accept the licence.
//  3. A university email is approved at once; others wait for an admin.
//  4. Approved people get short-lived links to the files of a private GitHub release.
// Lives outside the web root (/opt/openqp-access); /var/www/html/download/index.php
// only requires this file. Settings are in config.php next to it.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
const LOGIN_MINUTES = 20;
const SESSION_HOURS = 12;
const LICENCE = 'OpenQP Research License 1.0';
const AGREEMENT = 'OpenQP Academic Use and Good-Faith Research Agreement, 2026-10-27';   // text: agreement.php

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
     . "form-action 'self' https://release-assets.githubusercontent.com https://objects.githubusercontent.com; "
     . "frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

session_name('oqp_download');
session_set_cookie_params(['lifetime' => 0, 'path' => '/download/', 'secure' => true,
                           'httponly' => true, 'samesite' => 'Lax']);
session_start();

// ---------------------------------------------------------------- helpers
function db(): PDO {
    static $pdo = null;
    global $cfg;
    if ($pdo === null) {
        $pdo = new PDO($cfg['db_dsn'], $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function q(string $sql, array $args = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function page_url(): string { global $cfg; return rtrim($cfg['site_url'], '/') . '/'; }
function go(string $query = ''): void {
    header('Location: ' . page_url() . ($query !== '' ? '?' . $query : ''), true, 303);
    exit;
}
function flash(string $kind, string $text): void { $_SESSION['flash'] = [$kind, $text]; }
function csrf(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function check_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Form expired. Go back and reload the page.'); }
}
function clean_email(string $raw): ?string {
    $e = strtolower(trim($raw));
    if ($e === '' || strlen($e) > 254 || preg_match('/[\r\n\s]/', $e) || !filter_var($e, FILTER_VALIDATE_EMAIL)) { return null; }
    return $e;
}
function is_academic(string $email): bool {
    $domain = substr(strrchr($email, '@'), 1);
    foreach (q('SELECT domain FROM academic_domains')->fetchAll(PDO::FETCH_COLUMN) as $d) {
        if ($domain === $d || substr($domain, -strlen($d) - 1) === '.' . $d) { return true; }
    }
    return false;
}
function signed_in(): ?string {
    $e = $_SESSION['email'] ?? null;
    if ($e && ($_SESSION['since'] ?? 0) > time() - SESSION_HOURS * 3600) { return $e; }
    return null;
}
function is_admin(?string $email): bool {
    global $cfg;
    return $email !== null && in_array($email, array_map('strtolower', $cfg['admin_emails']), true);
}

// ---------------------------------------------------------------- email
function send_mail(string $to, string $subject, string $body, string $kind): bool {
    global $cfg;
    $headers = 'From: ' . $cfg['mail_from_name'] . ' <' . $cfg['mail_from'] . ">\r\n"
             . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    $ok = mail($to, $subject, $body, $headers, '-f' . $cfg['mail_from']);
    q('INSERT INTO mail_log (email, ip, kind, sent_at) VALUES (?, ?, ?, ?)', [$to, ip(), $kind, now()]);
    return $ok;
}
function too_many_mails(string $email): bool {
    $hour = gmdate('Y-m-d H:i:s', time() - 3600);
    $by_email = (int)q('SELECT COUNT(*) FROM mail_log WHERE email = ? AND sent_at > ?', [$email, $hour])->fetchColumn();
    $by_ip = (int)q('SELECT COUNT(*) FROM mail_log WHERE ip = ? AND sent_at > ?', [ip(), $hour])->fetchColumn();
    return $by_email >= 3 || $by_ip >= 10;
}
// $agreement: the agreement version accepted with this request (null when a signed-in
// person asks for a link to the page again).
function send_login_link(string $email, ?string $agreement): void {
    $secret = bin2hex(random_bytes(32));
    q('INSERT INTO login_tokens (token_hash, email, created_at, expires_at, ip, agreement_version) VALUES (?, ?, ?, ?, ?, ?)',
      [hash('sha256', $secret), $email, now(), gmdate('Y-m-d H:i:s', time() + LOGIN_MINUTES * 60), ip(), $agreement]);
    $link = page_url() . '?login=' . $secret;
    send_mail($email, 'Your OpenQP download sign-in link',
        "Open this link to sign in to the OpenQP download page:\n\n$link\n\n"
        . "It works once and expires in " . LOGIN_MINUTES . " minutes.\n"
        . "If you did not ask for it, you can ignore this email.\n", 'login');
}

// ---------------------------------------------------------------- GitHub
function github(string $path, string $accept, bool $follow = true): array {
    global $cfg;
    $ch = curl_init('https://api.github.com/repos/' . $cfg['github_repo'] . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['github_token'], 'Accept: ' . $accept,
                               'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: openqp-access'],
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException('GitHub is not reachable: ' . $err); }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$status, substr($raw, 0, $hsize), substr($raw, $hsize)];
}
function release_files(): array {
    global $cfg;
    $tag = $cfg['github_tag'] ?? '';
    [$status, , $body] = github($tag !== '' ? '/releases/tags/' . rawurlencode($tag) : '/releases/latest', 'application/vnd.github+json');
    if ($status !== 200) { throw new RuntimeException("GitHub release lookup failed ($status)"); }
    $release = json_decode($body, true);
    $files = [];
    foreach ($release['assets'] ?? [] as $a) {
        if (preg_match($cfg['asset_pattern'], $a['name'])) {
            $files[] = ['id' => (int)$a['id'], 'name' => $a['name'], 'size' => (int)$a['size']];
        }
    }
    return [(string)($release['tag_name'] ?? ''), $files];
}

// ---------------------------------------------------------------- actions
$me = signed_in();
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : '';

if (isset($_GET['login'])) {
    $secret = (string)$_GET['login'];
    $row = preg_match('/^[0-9a-f]{64}$/', $secret)
        ? q('SELECT * FROM login_tokens WHERE token_hash = ?', [hash('sha256', $secret)])->fetch() : false;
    if (!$row || $row['used_at'] !== null || strtotime($row['expires_at'] . ' UTC') < time()) {
        flash('bad', 'That sign-in link is invalid, already used or expired. Ask for a new one below.');
        go();
    }
    q('UPDATE login_tokens SET used_at = ? WHERE token_hash = ?', [now(), $row['token_hash']]);
    session_regenerate_id(true);
    $_SESSION['email'] = $row['email'];
    $_SESSION['since'] = time();
    $_SESSION['agreement'] = $row['agreement_version'] !== null ? [$row['agreement_version'], $row['created_at']] : null;
    go();
}

if ($action !== '') { check_csrf(); }

if ($action === 'send_link') {
    $email = clean_email((string)($_POST['email'] ?? ''));
    if ($email === null) { flash('bad', 'Enter a valid email address.'); go(); }
    if (empty($_POST['agree'])) {   // no email before the agreement is accepted
        $_SESSION['agree_email'] = $email;
        go('agreement');
    }
    unset($_SESSION['agree_email']);
    if (!too_many_mails($email)) { send_login_link($email, AGREEMENT); }
    flash('ok', "If $email can receive email, a sign-in link is on its way. Open it within " . LOGIN_MINUTES . ' minutes.');
    go();
}
if ($action === 'logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    go();
}
if ($me !== null && $action === 'email_link') {
    if (!too_many_mails($me)) { send_login_link($me, null); }
    flash('ok', "Sent to $me. The link in it signs you in and opens your downloads.");
    go();
}
if ($me !== null && $action === 'details') {
    $name = trim((string)($_POST['full_name'] ?? ''));
    $inst = trim((string)($_POST['institution'] ?? ''));
    $purpose = trim((string)($_POST['purpose'] ?? ''));
    if ($name === '' || $inst === '' || mb_strlen($name) > 200 || mb_strlen($inst) > 300 || mb_strlen($purpose) > 2000) {
        flash('bad', 'Enter your name and institution.'); go();
    }
    if (empty($_POST['academic']) || empty($_POST['licence'])) { flash('bad', 'Tick both boxes to accept the licence terms.'); go(); }
    $status = is_academic($me) ? 'approved' : 'pending';
    [$agreement, $agreed_at] = $_SESSION['agreement'] ?? [null, null];
    q('INSERT IGNORE INTO access_requests (email, full_name, institution, purpose, licence_version, licence_accepted_at,'
      . ' agreement_version, agreement_accepted_at, status, created_at, decided_at, decided_by)'
      . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [$me, $name, $inst, $purpose !== '' ? $purpose : null, LICENCE, now(), $agreement, $agreed_at, $status, now(),
       $status === 'approved' ? now() : null, $status === 'approved' ? 'university email' : null]);
    go();
}
if ($me !== null && $action === 'download') {
    $req = q('SELECT status FROM access_requests WHERE email = ?', [$me])->fetch();
    if (!$req || $req['status'] !== 'approved') { http_response_code(403); exit('Access not approved.'); }
    try {
        [, $files] = release_files();
        $want = (int)($_POST['asset_id'] ?? 0);
        $file = null;
        foreach ($files as $f) { if ($f['id'] === $want) { $file = $f; } }
        if ($file === null) { flash('bad', 'That file is not available.'); go(); }
        [$status, $headers] = github('/releases/assets/' . $file['id'], 'application/octet-stream', false);
        if ($status < 300 || $status >= 400 || !preg_match('/^location:\s*(\S+)/mi', $headers, $m)) {
            throw new RuntimeException("GitHub did not return a download link ($status)");
        }
        q('INSERT INTO download_log (email, asset_name, ip, at) VALUES (?, ?, ?, ?)', [$me, $file['name'], ip(), now()]);
        header('Location: ' . $m[1], true, 303);
        exit;
    } catch (RuntimeException $e) {
        flash('bad', 'Could not create the download link: ' . $e->getMessage()); go();
    }
}
if (is_admin($me) && $action === 'decide') {
    $who = clean_email((string)($_POST['email'] ?? ''));
    $decision = (string)($_POST['decision'] ?? '');
    if ($who !== null && in_array($decision, ['approved', 'declined'], true)) {
        q('UPDATE access_requests SET status = ?, decided_at = ?, decided_by = ? WHERE email = ?', [$decision, now(), $me, $who]);
        if ($decision === 'approved') {
            send_mail($who, 'Your OpenQP download access is approved',
                "Your request to download OpenQP was approved.\n\nSign in at " . page_url() . " with this email to download.\n", 'approved');
        }
        flash('ok', "$who: $decision.");
    }
    go();
}

// ---------------------------------------------------------------- views
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$req = $me !== null ? q('SELECT * FROM access_requests WHERE email = ?', [$me])->fetch() : false;

ob_start();
if ($flash) { echo '<p class="msg ' . h($flash[0]) . '">' . h($flash[1]) . '</p>'; }

if ($me === null) {
    // The button opens the agreement (agreement.js); without JavaScript the server shows it after the first click.
    $agree_email = (string)($_SESSION['agree_email'] ?? '');
    $show_agreement = isset($_GET['agreement']) && $agree_email !== ''; ?>
  <div class="card"><h2>Get OpenQP</h2>
    <p>OpenQP is free for academic and nonprofit research and teaching. Sign in with your university email: we email you a one-time link, there is no password. University addresses get access right away; other addresses are reviewed by hand.</p>
    <form method="post" class="stack" id="signin"><?= csrf_field() ?><input type="hidden" name="action" value="send_link">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="email" required placeholder="name@university.ac.kr" value="<?= h($agree_email) ?>">
      <button class="primary" type="submit">Email me a sign-in link</button>
    </form>
  </div>
  <dialog id="agreement" class="agreement<?= $show_agreement ? ' fallback' : '' ?>" aria-labelledby="agreement-title"<?= $show_agreement ? ' open' : '' ?>>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_link"><input type="hidden" name="email" value="<?= h($agree_email) ?>">
      <div class="agreement-head"><h2 id="agreement-title">OpenQP Academic Use and Good-Faith Research Agreement</h2></div>
      <div class="agreement-text" tabindex="0" autofocus><?php require __DIR__ . '/agreement.php'; ?></div>
      <div class="agreement-foot">
        <h3>Acceptance</h3>
        <label class="check"><input type="checkbox" name="agree" value="1" required> <strong>I have read and agree to this Agreement and the license accompanying this OpenQP version. I confirm that my intended use qualifies for free academic and noncommercial use, or that I hold an appropriate written commercial license.</strong></label>
        <p class="fine">The license for this version: <a href="/download/LICENSE.txt" target="_blank" rel="noopener"><?= h(LICENCE) ?></a>.</p>
        <div class="row"><button class="primary" type="submit">I Agree and Download</button><button type="submit" formmethod="dialog" formnovalidate>Cancel</button></div>
      </div>
    </form>
  </dialog>
  <script src="/download/agreement.js" defer></script>
<?php } elseif (!$req) { $academic = is_academic($me); ?>
  <div class="card"><h2>Your details</h2>
    <p>We detected your email <strong><?= h($me) ?></strong>. <?= $academic
      ? 'It is a university address, so you get access as soon as you submit this form.'
      : 'It is not on the university list, so your request will be reviewed by hand.' ?></p>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="details">
      <label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="200" autocomplete="name">
      <label for="institution">Institution</label><input id="institution" name="institution" required maxlength="300" autocomplete="organization">
      <label for="purpose">Planned research use (optional)</label><textarea id="purpose" name="purpose" rows="3" maxlength="2000"></textarea>
      <label class="check"><input type="checkbox" name="academic" value="1"> I will use OpenQP only for non-commercial academic or nonprofit research or teaching.</label>
      <label class="check"><input type="checkbox" name="licence" value="1"> I have read and accept the <a href="/download/LICENSE.txt" target="_blank" rel="noopener"><?= h(LICENCE) ?></a>.</label>
      <button class="primary" type="submit">Submit</button>
    </form>
    <p class="fine">We store your email, name, institution, the use you describe, the licence version and the date, and which files you download. Nothing else.</p>
  </div>
<?php } elseif ($req['status'] === 'approved') {
    try { [$tag, $files] = release_files(); $error = null; } catch (RuntimeException $e) { $tag = ''; $files = []; $error = $e->getMessage(); } ?>
  <div class="card"><h2>Downloads</h2>
    <p>Access approved for <?= h($me) ?>. Each button opens a download link that works for a few minutes.</p>
    <?php if ($error !== null) { ?><p class="msg bad">Could not list the files: <?= h($error) ?></p>
    <?php } elseif (!$files) { ?><p>No files are available yet.</p>
    <?php } else { ?><p class="fine">OpenQP <?= h(ltrim($tag, 'v')) ?></p><div class="files">
      <?php foreach ($files as $f) { ?>
        <form method="post" class="file"><?= csrf_field() ?><input type="hidden" name="action" value="download"><input type="hidden" name="asset_id" value="<?= (int)$f['id'] ?>">
          <span class="name"><?= h($f['name']) ?></span><span class="size"><?= $f['size'] ? number_format($f['size'] / 1048576, 1) . ' MB' : '' ?></span>
          <button type="submit">Download</button></form>
      <?php } ?></div><?php } ?>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="email_link"><button type="submit">Email me a link to this page</button></form>
  </div>
<?php } elseif ($req['status'] === 'pending') { ?>
  <div class="card"><h2>Request received</h2><p>Thank you, <?= h($req['full_name']) ?>. Your email <?= h($me) ?> is not on the university list, so the request is reviewed by hand. You get an email when it is approved.</p></div>
<?php } else { ?>
  <div class="card"><h2>Request declined</h2><p>Your request for <?= h($me) ?> was not approved. Write to the contact in Section 13 of the <a href="/download/LICENSE.txt">licence</a> if you think this is a mistake.</p></div>
<?php }

if (is_admin($me)) {
    $pending = q("SELECT * FROM access_requests WHERE status = 'pending' ORDER BY created_at")->fetchAll();
    $recent = q('SELECT email, full_name, institution, status, created_at FROM access_requests ORDER BY created_at DESC LIMIT 30')->fetchAll(); ?>
  <div class="card"><h2>Requests to review</h2>
    <?php if (!$pending) { ?><p>No requests are waiting.</p><?php } foreach ($pending as $p) { ?>
      <div class="review"><div><strong><?= h($p['full_name']) ?></strong> · <?= h($p['email']) ?><br><?= h($p['institution']) ?><?= $p['purpose'] ? '<br><span class="fine">' . h($p['purpose']) . '</span>' : '' ?></div>
        <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="decide"><input type="hidden" name="email" value="<?= h($p['email']) ?>">
          <button class="primary" name="decision" value="approved" type="submit">Approve</button><button name="decision" value="declined" type="submit">Decline</button></form></div>
    <?php } ?>
    <h3>Latest requests</h3>
    <div class="table"><table><tr><th>Date (UTC)</th><th>Email</th><th>Name</th><th>Institution</th><th>Status</th></tr>
    <?php foreach ($recent as $r) { ?><tr><td><?= h(substr($r['created_at'], 0, 16)) ?></td><td><?= h($r['email']) ?></td><td><?= h($r['full_name']) ?></td><td><?= h($r['institution']) ?></td><td><?= h($r['status']) ?></td></tr><?php } ?>
    </table></div>
  </div>
<?php }
$body = ob_get_clean();
$who = $me !== null
    ? 'Signed in as <strong>' . h($me) . '</strong> · <form method="post" class="inline">' . csrf_field()
      . '<input type="hidden" name="action" value="logout"><button class="link" type="submit">Sign out</button></form>'
    : '';
require __DIR__ . '/layout.php';
