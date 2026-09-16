<?php
/* ============================================================
   bisanat — Yönetim API'si
   ------------------------------------------------------------
   yonetim.php panelinin konuştuğu tek uç nokta.
   İşlemler (?action=...):
     me            -> oturum durumu
     login         -> şifre ile giriş (POST: password)
     logout        -> çıkış
     load          -> icerik.json içeriğini döndürür (yetki ister)
     save          -> tüm içeriği kaydeder (POST: JSON gövde, yetki ister)
     upload        -> görsel yükler (POST multipart: file, slug ; yetki ister)
     delete_image  -> sunucudan görsel dosyasını siler (POST: slug,file ; yetki ister)
   ============================================================ */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0'); // hataları JSON'a sızdırma

$config = require __DIR__ . '/config.php';

session_name($config['session_name'] ?? 'bisanat_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/* ---------- yardımcılar ---------- */
function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): void {
    out(['ok' => false, 'error' => $msg], $code);
}
function is_auth(): bool {
    return !empty($_SESSION['bisanat_auth']);
}
function require_auth(): void {
    if (!is_auth()) fail('Yetkisiz. Lütfen tekrar giriş yapın.', 401);
}
function read_body_json() {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return null;
    $d = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $d : null;
}
/** İçerik dosyasını oku (yoksa boş iskelet). */
function load_data(array $config) {
    $f = $config['data_file'];
    if (!is_file($f)) {
        return ['_format' => 'bisanat-icerik', '_version' => 2, 'order' => [],
                'categories' => (object)[], 'pieces' => (object)[], 'texts' => (object)[], 'hyper' => (object)[]];
    }
    $raw = file_get_contents($f);
    $d = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($d)) ? $d : null;
}
/** Geçerli kategori slug'ları (icerik.json'daki order + categories anahtarları). */
function valid_slugs(array $config): array {
    $d = load_data($config);
    $slugs = [];
    if (is_array($d)) {
        if (!empty($d['order']) && is_array($d['order'])) $slugs = $d['order'];
        if (!empty($d['categories']) && is_array($d['categories'])) {
            $slugs = array_values(array_unique(array_merge($slugs, array_keys($d['categories']))));
        }
    }
    return $slugs;
}
/** Slug'ı güvenli biçime indir (yalnızca a-z 0-9 - _). */
function clean_slug(string $s): string {
    $s = strtolower(trim($s));
    return preg_replace('/[^a-z0-9_\-]/', '', $s);
}
/** Dosya adını güvenli hale getir; uzantıyı koru. Unicode harflere izin ver. */
function clean_filename(string $name): string {
    $name = basename($name);                 // yol ayırıcıları at
    $name = str_replace('\\', '', $name);
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name); // kontrol karakterleri
    $name = preg_replace('/[\/:*?"<>|]+/u', '', $name);    // sorunlu karakterler
    $name = ltrim($name, '.');                // baştaki noktaları at (gizli dosya olmasın)
    $name = trim($name);
    if ($name === '') $name = 'gorsel';
    return $name;
}

/* ---------- yönlendirme ---------- */
$action = $_GET['action'] ?? '';

switch ($action) {

case 'me':
    out(['ok' => true, 'auth' => is_auth()]);

case 'login': {
    $body = read_body_json() ?? $_POST;
    $pw = (string)($body['password'] ?? '');
    $hash  = (string)($config['password_hash'] ?? '');
    $plain = (string)($config['password_plain'] ?? '');
    $ok = false;
    if ($hash !== '') {
        $ok = password_verify($pw, $hash);
    } else {
        $ok = ($pw !== '' && hash_equals($plain, $pw));
    }
    if (!$ok) { usleep(400000); fail('Şifre yanlış.', 401); }
    session_regenerate_id(true);
    $_SESSION['bisanat_auth'] = true;
    out(['ok' => true]);
}

case 'logout':
    $_SESSION = [];
    session_destroy();
    out(['ok' => true]);

case 'load':
    require_auth();
    $d = load_data($config);
    if ($d === null) fail('İçerik dosyası okunamadı (bozuk JSON).', 500);
    out(['ok' => true, 'data' => $d]);

case 'save': {
    require_auth();
    $body = read_body_json();
    if (!is_array($body)) fail('Geçersiz veri (JSON çözülemedi).');

    // asıl içerik "data" altında ya da doğrudan gövde olabilir
    $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

    // asgari yapı doğrulaması
    foreach (['order', 'categories', 'pieces', 'texts', 'hyper'] as $k) {
        if (!array_key_exists($k, $data)) fail("Eksik alan: $k");
    }
    if (!is_array($data['order']))   fail('order bir dizi olmalı.');
    if (!is_array($data['pieces']))  fail('pieces bir nesne olmalı.');

    $data['_format']    = 'bisanat-icerik';
    $data['_version']   = 2;
    $data['_updatedAt'] = gmdate('c');

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) fail('İçerik kodlanamadı: ' . json_last_error_msg(), 500);

    $f   = $config['data_file'];
    $dir = dirname($f);
    if (!is_writable($dir) || (is_file($f) && !is_writable($f))) {
        fail('İçerik dosyası yazılamıyor. Sunucuda dosya/klasör iznini (644/755) kontrol edin.', 500);
    }
    // önce yedek
    if (is_file($f)) @copy($f, $f . '.bak');
    // atomik yazım: geçici dosya + rename
    $tmp = $f . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json, LOCK_EX) === false) fail('Yazma hatası (geçici dosya).', 500);
    if (!@rename($tmp, $f)) { @unlink($tmp); fail('Yazma hatası (rename).', 500); }
    @chmod($f, 0644);
    out(['ok' => true, 'updatedAt' => $data['_updatedAt']]);
}

case 'upload': {
    require_auth();
    if (empty($_FILES['file'])) fail('Dosya bulunamadı.');
    $slug = clean_slug((string)($_POST['slug'] ?? ''));
    if ($slug === '') fail('Kategori (slug) belirtilmedi.');
    if (!in_array($slug, valid_slugs($config), true)) fail('Bilinmeyen kategori: ' . $slug);

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) fail('Yükleme hatası (kod ' . $file['error'] . ').');

    $maxBytes = (int)($config['max_upload_mb'] ?? 12) * 1024 * 1024;
    if ($file['size'] <= 0)        fail('Boş dosya.');
    if ($file['size'] > $maxBytes) fail('Dosya çok büyük (en fazla ' . (int)$config['max_upload_mb'] . ' MB).');

    $orig = clean_filename($file['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = $config['allowed_ext'] ?? ['webp','jpg','jpeg','png'];
    if (!in_array($ext, $allowed, true)) fail('İzin verilmeyen uzantı. İzinli: ' . implode(', ', $allowed));

    // içerik türü doğrula (gerçekten görsel mi?)
    $info = @getimagesize($file['tmp_name']);
    $mimeOk = $info !== false;
    if (!$mimeOk && $ext === 'webp') {
        // bazı PHP sürümleri webp'i getimagesize ile tanımayabilir -> imza kontrolü
        $head = @file_get_contents($file['tmp_name'], false, null, 0, 12);
        $mimeOk = ($head !== false && strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP');
    }
    if (!$mimeOk) fail('Dosya geçerli bir görsel değil.');

    $destDir = rtrim($config['assets_dir'], '/') . '/' . $slug;
    if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }
    if (!is_dir($destDir) || !is_writable($destDir)) {
        fail('Hedef klasör yazılamıyor: assets/' . $slug . ' (izin 755 olmalı).', 500);
    }

    // aynı ada sahip dosya varsa -1, -2 ... ekle
    $base = pathinfo($orig, PATHINFO_FILENAME);
    $final = $orig;
    $i = 1;
    while (is_file($destDir . '/' . $final)) {
        $final = $base . '-' . $i . '.' . $ext;
        $i++;
    }
    if (!@move_uploaded_file($file['tmp_name'], $destDir . '/' . $final)) {
        fail('Dosya taşınamadı.', 500);
    }
    @chmod($destDir . '/' . $final, 0644);
    out(['ok' => true, 'slug' => $slug, 'file' => $final, 'path' => 'assets/' . $slug . '/' . $final]);
}

case 'move_image': {
    require_auth();
    $body = read_body_json() ?? $_POST;
    $from = clean_slug((string)($body['from'] ?? ''));
    $to   = clean_slug((string)($body['to'] ?? ''));
    $file = clean_filename((string)($body['file'] ?? ''));
    if ($from === '' || $to === '' || $file === '') fail('from, to ve file gerekli.');
    $slugs = valid_slugs($config);
    if (!in_array($from, $slugs, true) || !in_array($to, $slugs, true)) fail('Bilinmeyen kategori.');
    if ($from === $to) out(['ok' => true, 'file' => $file]);

    $base   = rtrim($config['assets_dir'], '/');
    $srcDir = $base . '/' . $from;
    $dstDir = $base . '/' . $to;
    $src    = $srcDir . '/' . $file;

    // Kaynak dosya yoksa: metadatayı yine de taşımaya izin ver (dosya zaten
    // doğru yerde olabilir ya da henüz yüklenmemiş olabilir).
    if (!is_file($src)) out(['ok' => true, 'file' => $file, 'note' => 'Kaynak dosya bulunamadı, yalnızca liste güncellendi.']);

    if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
    if (!is_dir($dstDir) || !is_writable($dstDir)) fail('Hedef klasör yazılamıyor: assets/' . $to, 500);

    // hedefte aynı ad varsa -1, -2 ...
    $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $stem  = pathinfo($file, PATHINFO_FILENAME);
    $final = $file; $i = 1;
    while (is_file($dstDir . '/' . $final)) { $final = $stem . '-' . $i . '.' . $ext; $i++; }

    if (!@rename($src, $dstDir . '/' . $final)) fail('Dosya taşınamadı.', 500);
    out(['ok' => true, 'file' => $final]);
}

case 'delete_image': {
    require_auth();
    $body = read_body_json() ?? $_POST;
    $slug = clean_slug((string)($body['slug'] ?? ''));
    $file = clean_filename((string)($body['file'] ?? ''));
    if ($slug === '' || $file === '') fail('slug ve file gerekli.');
    if (!in_array($slug, valid_slugs($config), true)) fail('Bilinmeyen kategori.');

    $target = rtrim($config['assets_dir'], '/') . '/' . $slug . '/' . $file;
    // güvenlik: hedef gerçekten assets/{slug}/ altında mı?
    $realBase = realpath(rtrim($config['assets_dir'], '/') . '/' . $slug);
    $realTgt  = realpath($target);
    if ($realTgt === false) out(['ok' => true, 'note' => 'Dosya zaten yok.']); // yoksa sorun değil
    if ($realBase === false || strpos($realTgt, $realBase) !== 0) fail('Geçersiz yol.', 400);

    @unlink($realTgt);
    out(['ok' => true]);
}

default:
    fail('Bilinmeyen işlem.', 404);
}
