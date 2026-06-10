<?php
// ═══════════════════════════════════════════════════════════

// -- OVERSIZED POST GUARD --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = intval($_SERVER['CONTENT_LENGTH'] ?? 0);
    $maxBytes = 10 * 1024 * 1024;
    if ($contentLength > $maxBytes) {
        header('Content-Type: application/json');
        http_response_code(413);
        echo json_encode(['success' => false, 'error' => 'Image too large. Please use a smaller image (under 2MB).']);
        exit;
    }
}

// -- DATABASE CONFIG --
define('DB_HOST', 'localhost');
define('DB_NAME', 'fayodelc_Fayodel_db');
define('DB_USER', 'fayodelc_Fayodel_db');
define('DB_PASS', 'Linux@254');

// -- STORE LOCATION --
define('STORE_LAT', -1.2877824);
define('STORE_LNG', 36.8219);
define('STORE_NAME', 'Ronald Ngala Mozart Building, Nairobi');

// -- DELIVERY PRICING --
$DELIVERY_PRICING = [
    ['km' => 5,   'price' => 200],
    ['km' => 10,  'price' => 300],
    ['km' => 15,  'price' => 500],
    ['km' => 20,  'price' => 800],
    ['km' => 25,  'price' => 1200],
    ['km' => 100, 'price' => 2500],
];

// -- STK CONFIG --
define('STK_USERNAME',    'glolink_api');
define('STK_PASSWORD',    'Glolink@Secure123');
define('STK_PAYBILL',     '880100');
define('STK_ACCOUNT',     '070235');
define('STK_API_URL',     'https://api.fayodel.com/stk/push');
define('MPESA_CALLBACK_URL', 'https://fayodel.com/public_html/api.php?action=mpesa_callback');

// -- CORS & JSON headers --
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// -- DB CONNECTION --
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'DB connection failed']));
    }
}

// ── DB INIT: daily flag guard ─────────────────────────────────────────────────
function initDB() {
    // FIX: use a path guaranteed to be writable on cPanel
    $flagDir = dirname(__FILE__);
    $flagFile = $flagDir . '/.db_init_' . date('Ymd') . '.flag';
    if (file_exists($flagFile)) return;

    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price INT NOT NULL DEFAULT 0,
        old_price INT DEFAULT 0,
        category VARCHAR(100) DEFAULT 'bikes',
        stock VARCHAR(100) DEFAULT 'In Stock',
        badge VARCHAR(50) DEFAULT '-15%',
        description TEXT,
        image VARCHAR(500) DEFAULT 'placeholder.jpg',
        image_data LONGTEXT,
        code VARCHAR(100),
        clicks INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    try { $db->exec("ALTER TABLE products ADD COLUMN clicks INT DEFAULT 0"); } catch(Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_data LONGTEXT NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) UNIQUE,
        name VARCHAR(255),
        phone VARCHAR(50),
        email VARCHAR(255),
        location VARCHAR(255),
        items TEXT,
        total INT DEFAULT 0,
        payment_method VARCHAR(50) DEFAULT 'mpesa',
        status VARCHAR(100) DEFAULT 'BEING PROCESSED',
        payment_status VARCHAR(50) DEFAULT 'UNPAID',
        mpesa_receipt VARCHAR(100),
        delivery_method VARCHAR(50) DEFAULT 'pickup',
        delivery_address TEXT,
        delivery_notes TEXT,
        delivery_fee INT DEFAULT 0,
        customer_lat DECIMAL(10,6),
        customer_lng DECIMAL(10,6),
        seen_by_admin TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    foreach ([
        "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'UNPAID'",
        "ALTER TABLE orders ADD COLUMN seen_by_admin TINYINT DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN delivery_method VARCHAR(50) DEFAULT 'pickup'",
        "ALTER TABLE orders ADD COLUMN delivery_address TEXT",
        "ALTER TABLE orders ADD COLUMN delivery_notes TEXT",
        "ALTER TABLE orders ADD COLUMN delivery_fee INT DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN customer_lat DECIMAL(10,6)",
        "ALTER TABLE orders ADD COLUMN customer_lng DECIMAL(10,6)",
    ] as $sql) {
        try { $db->exec($sql); } catch(Exception $e) {}
    }

    $db->exec("CREATE TABLE IF NOT EXISTS posters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        description TEXT,
        image_data LONGTEXT,
        status VARCHAR(50) DEFAULT 'ACTIVE',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS flash_sale (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        discount_pct INT DEFAULT 10,
        active TINYINT DEFAULT 1
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE,
        label VARCHAR(100),
        active TINYINT DEFAULT 1
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT
    )");

    $db->exec("INSERT IGNORE INTO categories (name, label) VALUES
        ('bikes','Bikes (Adults)'),('kids','Kids Bikes'),
        ('scooter','Scooters'),('emobility','E-Mobility'),
        ('furniture','Furniture'),('generator','Generators'),
        ('accessories','Accessories')");

    file_put_contents($flagFile, date('Y-m-d H:i:s'));
    // Clean up old flag files
    foreach (glob($flagDir . '/.db_init_*.flag') as $f) {
        if ($f !== $flagFile) @unlink($f);
    }
}

// -- HELPERS --
function ok($data = [])  { echo json_encode(['success' => true] + (array)$data); exit; }
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
function body() { return json_decode(file_get_contents('php://input'), true) ?? []; }
function genOrderId() { return 'FAY' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)); }

// ── CACHE HELPER ──────────────────────────────────────────────────────────────
// FIX: cache files stored next to api.php (guaranteed writable on cPanel)
//      cache key is now a simple sanitized string, not md5 of a glob pattern
function cacheFile($key) {
    return dirname(__FILE__) . '/.cache_' . preg_replace('/[^a-z0-9_]/', '_', $key) . '.json';
}

function getCached($key, $ttl, callable $fn) {
    $file = cacheFile($key);
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        header('X-Cache: HIT');
        echo file_get_contents($file);
        exit;
    }
    ob_start();
    $fn();
    $output = ob_get_clean();
    file_put_contents($file, $output);
    header('X-Cache: MISS');
    echo $output;
    exit;
}

// FIX: proper cache busting — delete by exact key name
function bustCache($key) {
    $file = cacheFile($key);
    if (file_exists($file)) @unlink($file);
}

// ── DISTANCE / DELIVERY ───────────────────────────────────────────────────────
function distanceKm($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    return round($earth_radius * 2 * asin(sqrt($a)), 2);
}

function calculateDeliveryFee($customerLat, $customerLng) {
    global $DELIVERY_PRICING;
    if (!$customerLat || !$customerLng) return 0;
    $km = distanceKm(STORE_LAT, STORE_LNG, $customerLat, $customerLng);
    foreach ($DELIVERY_PRICING as $tier) {
        if ($km <= $tier['km']) return $tier['price'];
    }
    return end($DELIVERY_PRICING)['price'];
}

// ── STK PUSH ──────────────────────────────────────────────────────────────────
function sendSTKPush($phone, $amount, $orderId) {
    $payload = json_encode([
        'username'  => STK_USERNAME,
        'password'  => STK_PASSWORD,
        'phone'     => $phone,
        'amount'    => intval($amount),
        'paybill'   => STK_PAYBILL,
        'account'   => $orderId,
        'callback'  => MPESA_CALLBACK_URL,
        'reference' => 'Fayodel-' . $orderId,
    ]);
    $ch = curl_init(STK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err)  return ['success' => false, 'error' => 'Network error: ' . $err];
    if (!$res) return ['success' => false, 'error' => 'Empty response'];
    $data = json_decode($res, true);
    if (!$data) return ['success' => false, 'error' => 'Invalid response'];
    $isSuccess = ($data['success'] ?? false) || ($data['status'] ?? '') === 'success' ||
                 ($data['ResponseCode'] ?? '') === '0' || ($data['resultCode'] ?? '') === '0' || $code === 200;
    if ($isSuccess) return ['success' => true, 'message' => 'STK push sent', 'data' => $data];
    return ['success' => false, 'error' => $data['message'] ?? 'Payment request failed'];
}

// ── CSV HELPERS ───────────────────────────────────────────────────────────────
function detectCategoryAdvanced($name, $rawCat, $code = '', $size = '') {
    $text     = strtolower(trim($name . ' ' . $rawCat . ' ' . $code));
    $rawCatLo = strtolower(trim($rawCat));
    $sizeLo   = strtolower(trim($size));

    if (in_array($rawCatLo, ['accessories','accessory','spare parts','spare','tools','tool','lights','bells','locks','pumps']))
        return 'accessories';

    $catMap = [
        'adult'=>'bikes','adults'=>'bikes','adult bikes'=>'bikes','mountain bike'=>'bikes','road bike'=>'bikes',
        'kids'=>'kids','kids bikes'=>'kids','children'=>'kids','toys'=>'kids','kids and toys'=>'kids',
        'scooter'=>'scooter','scooters'=>'scooter','kick scooter'=>'scooter',
        'emobility'=>'emobility','e-mobility'=>'emobility','ebike'=>'emobility','e-bike'=>'emobility','electric'=>'emobility','electric bikes'=>'emobility',
        'furniture'=>'furniture','furnishing'=>'furniture','home'=>'furniture',
        'generator'=>'generator','generators'=>'generator','genset'=>'generator','power'=>'generator','inverter'=>'generator',
    ];
    if (isset($catMap[$rawCatLo])) return $catMap[$rawCatLo];

    if (in_array($sizeLo, ['10','12','14','16','18'])) return 'kids';
    if (in_array($sizeLo, ['26','27','27.5','28','29','700c','700','22'])) return 'bikes';

    if ($sizeLo === '20') {
        if (preg_match('/\b(girl|pink|xinjqi|babyride|princess|toddler|baby|junior|kid|child|fold)\b/', $text)) return 'kids';
        return 'bikes';
    }

    if (preg_match('/\b(toy|toys|doll|tricycle|trike|balance bike|ride.?on|push car|baby walker|rc car|scooter|feeding chair|study table|highchair)\b/', $text)) return 'kids';
    if (preg_match('/\b(kid|child|children|boy|girl|junior|toddler|baby|infant|xinjqi|babycare)\b/', $text)) return 'kids';
    if (preg_match('/\b(electric bicycle|electric bike|e-bike|ebike|lithium|battery bike|motor)\b/', $text)) return 'emobility';
    if (preg_match('/\b(sofa|couch|bed|mattress|dining table|coffee table|chair|desk|wardrobe|cabinet|shelf|mirror|tv stand|storage)\b/', $text)) return 'furniture';
    if (preg_match('/\b(generator|genset|inverter|solar panel|power station|fuel tank|portable power)\b/', $text)) return 'generator';
    if (preg_match('/\b(helmet|lock|pump|tyre|tire|tube|chain|brake|pedal|saddle|handlebar|glove|bell|light|basket|reflector|kickstand|grips|cables)\b/', $text)) return 'accessories';
    if (preg_match('/\b(24|26|27|29|700c)\s*["\x22]?.*bike/', $text)) return 'bikes';
    if (preg_match('/\b(12|16|18|20)\s*["\x22]?.*bike/', $text)) return 'kids';

    return 'bikes';
}

function extractBikeSize($name, $sizeCol = '') {
    $s = trim($sizeCol);
    if ($s !== '') {
        if (strtolower($s) === '700c') return '700c';
        if (preg_match('/^[\d\.]+$/', $s)) return $s . '"';
    }
    if (preg_match('/\b(700c)\b/i', $name)) return '700c';
    if (preg_match('/\b(10|12|14|16|18|20|22|24|26|27\.5|27|28|29)\s*(")?/i', $name, $m)) return $m[1] . '"';
    return null;
}

// ── INVOICE PDF ───────────────────────────────────────────────────────────────
function generatePdfInvoice($orderId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return null;

    $items = json_decode($order['items'], true) ?? [];
    $itemRows = '';
    foreach ($items as $i => $item) {
        $qty = $item['quantity'] ?? 1;
        $price = $item['price'] ?? 0;
        $total = $price * $qty;
        $itemRows .= sprintf(
            "<tr><td>%d</td><td>%s</td><td style='text-align:right'>%d</td><td style='text-align:right'>KSh %s</td><td style='text-align:right;font-weight:700'>KSh %s</td></tr>",
            $i+1, htmlspecialchars($item['name']), $qty, number_format($price), number_format($total)
        );
    }

    $dlLabel = match($order['delivery_method'] ?? 'pickup') {
        'delivery' => '📦 Courier/Bus Delivery',
        'glolink'  => '🏍️ Glolink Rider (same-day Nairobi)',
        default    => '🏪 Pick Up from Store',
    };

    $dlAddr  = $order['delivery_address'] ? '<br><strong>Address:</strong> ' . htmlspecialchars($order['delivery_address']) : '';
    $dlNotes = $order['delivery_notes']   ? '<br><strong>Notes:</strong> '   . htmlspecialchars($order['delivery_notes'])   : '';

    $payStatus   = strtoupper($order['payment_status'] ?? 'UNPAID');
    $statusBadge = $payStatus === 'PAID'
        ? '<div style="background:#dcfce7;color:#166534;padding:8px;border-radius:8px;font-weight:700;margin:16px 0">✓ PAID</div>'
        : '<div style="background:#fee2e2;color:#991b1b;padding:8px;border-radius:8px;font-weight:700;margin:16px 0">⏳ UNPAID — Awaiting payment</div>';

    $storeName = STORE_NAME;
    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice {$order['order_id']}</title>
<style>body{font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:20px}.invoice-header{display:flex;justify-content:space-between;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #1e40af}.logo-name{font-size:24px;font-weight:900;color:#1e40af}.logo-sub{font-size:12px;color:#64748b;margin-top:4px}.meta{text-align:right}.meta strong{display:block;font-size:16px;color:#1e40af;margin-bottom:6px}.meta-item{font-size:13px;color:#555}.section-label{font-size:11px;font-weight:900;color:#94a3b8;text-transform:uppercase;margin:16px 0 8px}.customer-box{background:#f8fafc;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;line-height:1.6}table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px}table th{background:#1e40af;color:white;padding:10px;text-align:left}table td{padding:10px;border-bottom:1px solid #e2e8f0}.total-row{display:flex;justify-content:space-between;font-size:16px;font-weight:900;color:#1e40af;border-top:2px solid #1e40af;padding-top:12px;margin-top:8px}.footer{text-align:center;color:#94a3b8;font-size:11px;margin-top:20px;padding-top:12px;border-top:1px solid #e2e8f0}@media print{.print-note{display:none}}</style>
</head><body>
<div class="print-note" style="text-align:center;color:#1e40af;font-size:12px;font-weight:700;margin-bottom:20px">💡 Ctrl+P → Save as PDF</div>
<div class="invoice-header"><div><div class="logo-name">Fayodel Wholesalers</div><div class="logo-sub">{$storeName}<br>Tel: +254 715 003 007</div></div>
<div class="meta"><strong>INVOICE</strong><div class="meta-item">Order: <strong>{$order['order_id']}</strong></div><div class="meta-item">Date: {$order['created_at']}</div><div class="meta-item">Payment: {$order['payment_method']}</div></div></div>
{$statusBadge}
<div class="section-label">Billed To</div>
<div class="customer-box"><strong>{$order['name']}</strong><br>Phone: {$order['phone']}<br>{$order['email']}<br>County: {$order['location']}</div>
<div class="section-label">Delivery</div>
<div class="customer-box">{$dlLabel}{$dlAddr}{$dlNotes}</div>
<table><thead><tr><th style="width:5%">#</th><th style="width:50%">Item</th><th style="width:15%;text-align:right">Qty</th><th style="width:15%;text-align:right">Unit Price</th><th style="width:15%;text-align:right">Total</th></tr></thead>
<tbody>{$itemRows}</tbody></table>
<div class="total-row"><span>TOTAL AMOUNT</span><span>KSh {$order['total']}</span></div>
<div class="footer">Thank you for shopping with Fayodel Wholesalers!<br>WhatsApp: +254 715 003 007</div>
</body></html>
HTML;
    return $html;
}

// ════════════════════════════════════════════════════════════════════════════════
//  ROUTER
// ════════════════════════════════════════════════════════════════════════════════
$action = $_GET['action'] ?? body()['action'] ?? '';
initDB();
$db = getDB();

switch ($action) {

    case 'health':
        ok(['status' => 'OK', 'db' => 'connected', 'time' => date('Y-m-d H:i:s')]);

    // ── PRODUCTS ──────────────────────────────────────────────
    case 'get_products':
        $cat     = $_GET['category'] ?? '';
        $search  = $_GET['search']   ?? '';
        $instock = $_GET['instock']  ?? '0';
        $cacheKey = 'products_' . md5($cat . $search . $instock);
        getCached($cacheKey, 20, function() use ($db, $cat, $search, $instock) {
            // FIX: include image_data — it's what getImgSrc() needs to display product images.
            // We LIMIT 300 and the front-end caches in sessionStorage so this is a one-time hit.
            $sql = 'SELECT id,name,price,old_price,category,stock,badge,description,image,image_data,code,clicks FROM products WHERE 1=1';
            $params = [];
            if ($cat)    { $sql .= ' AND category = ?'; $params[] = $cat; }
            if ($search) { $sql .= ' AND name LIKE ?';  $params[] = "%$search%"; }
            if ($instock === '1') $sql .= " AND stock != 'Out of Stock'";
            $sql .= ' ORDER BY id DESC LIMIT 300';
            $stmt = $db->prepare($sql); $stmt->execute($params);
            ok(['products' => $stmt->fetchAll()]);
        });

    case 'save_product':
        $d = body(); $name = trim($d['name'] ?? ''); $price = intval($d['price'] ?? 0);
        $cat = detectCategoryAdvanced($name, trim($d['category'] ?? ''), $d['code'] ?? '');
        if (!$name || !$price) fail('Name and price required');
        $db->prepare('INSERT INTO products (name,price,old_price,category,stock,badge,description,image,image_data,code) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $name, $price, intval($d['old_price'] ?? round($price*1.3)), $cat,
            $d['stock'] ?? 'In Stock', $d['badge'] ?? '-15%', $d['description'] ?? $name,
            $d['image'] ?? 'placeholder.jpg', $d['image_data'] ?? null, $d['code'] ?? null,
        ]);
        // FIX: bust the correct cache key
        foreach (glob(dirname(__FILE__) . '/.cache_products_*.json') as $f) @unlink($f);
        ok(['id' => $db->lastInsertId(), 'category' => $cat]);

    case 'update_product':
        $d = body(); $id = intval($d['id'] ?? 0);
        if (!$id) fail('Product ID required');
        if (isset($d['name']) || isset($d['category'])) {
            $ex = $db->prepare('SELECT name,category,code FROM products WHERE id=?');
            $ex->execute([$id]); $ex = $ex->fetch() ?: [];
            $d['category'] = detectCategoryAdvanced($d['name'] ?? $ex['name'] ?? '', $d['category'] ?? $ex['category'] ?? '', $d['code'] ?? $ex['code'] ?? '');
        }
        $fields = []; $params = [];
        foreach (['name','price','old_price','category','stock','badge','description','image','image_data','code'] as $f)
            if (array_key_exists($f, $d)) { $fields[] = "$f=?"; $params[] = $d[$f]; }
        if (!$fields) fail('Nothing to update');
        $params[] = $id;
        $db->prepare('UPDATE products SET ' . implode(',', $fields) . ' WHERE id=?')->execute($params);
        foreach (glob(dirname(__FILE__) . '/.cache_products_*.json') as $f) @unlink($f);
        ok(['message' => 'Updated']);

    // FIX: missing case that admin calls for quick image swap
    case 'update_product_image':
        $d = body(); $id = intval($d['id'] ?? 0); $imgData = $d['image_data'] ?? '';
        if (!$id || !$imgData) fail('id and image_data required');
        $db->prepare('UPDATE products SET image_data=? WHERE id=?')->execute([$imgData, $id]);
        foreach (glob(dirname(__FILE__) . '/.cache_products_*.json') as $f) @unlink($f);
        ok(['message' => 'Image updated']);

    case 'delete_product':
        $d = body(); $id = intval($d['id'] ?? 0);
        if (!$id) fail('Product ID required');
        $db->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        $db->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$id]);
        foreach (glob(dirname(__FILE__) . '/.cache_products_*.json') as $f) @unlink($f);
        ok(['message' => 'Deleted']);

    case 'add_product_image':
        $d = body(); $pid = intval($d['product_id'] ?? 0); $imgData = $d['image_data'] ?? '';
        if (!$pid || !$imgData) fail('product_id and image_data required');
        $db->prepare('INSERT INTO product_images (product_id,image_data) VALUES (?,?)')->execute([$pid,$imgData]);
        ok(['id' => $db->lastInsertId()]);

    case 'delete_product_image':
        $d = body(); $id = intval($d['id'] ?? 0);
        if (!$id) fail('Image ID required');
        $db->prepare('DELETE FROM product_images WHERE id=?')->execute([$id]);
        ok(['message' => 'Deleted']);

    case 'get_product_images':
        $pid = intval($_GET['product_id'] ?? 0);
        if (!$pid) fail('product_id required');
        $stmt = $db->prepare('SELECT id,image_data FROM product_images WHERE product_id=? ORDER BY sort_order ASC');
        $stmt->execute([$pid]);
        ok(['images' => $stmt->fetchAll()]);

    // ── BULK CSV IMPORT ───────────────────────────────────────
    case 'bulk_import_csv':
        $d = body(); $rows = $d['products'] ?? [];
        if (!$rows) fail('No products');
        $stmt = $db->prepare('INSERT INTO products (name,price,old_price,category,stock,badge,description,image,code) VALUES (?,?,?,?,?,?,?,?,?)');
        $count = 0; $byCat = [];
        foreach ($rows as $r) {
            $name = trim($r['Product Name'] ?? $r['name'] ?? '');
            if (!$name) continue;
            $price = (int)preg_replace('/[^0-9]/', '', $r['Unit Selling Price'] ?? $r['price'] ?? '0');
            $buyPrice = (int)preg_replace('/[^0-9]/', '', $r['Buying Price'] ?? '0');
            if (!$price) continue;
            $oldPrice = $buyPrice > $price ? $buyPrice : (int)round($price*1.3);
            $rawCat = trim($r['Product Category'] ?? $r['category'] ?? '');
            $size   = trim($r['Size'] ?? '');
            $code   = trim($r['Product Code'] ?? '');
            $cat    = detectCategoryAdvanced($name, $rawCat, $code, $size);
            $qty    = is_numeric($r['Quantity in Stock'] ?? '') ? (int)$r['Quantity in Stock'] : -1;
            $stock  = $qty===0 ? 'Out of Stock' : ($qty<=3&&$qty>0 ? 'Limited Stock' : 'In Stock');
            $sizeLabel = extractBikeSize($name, $size);
            $badge  = (in_array($cat,['bikes','kids'])&&$sizeLabel) ? $sizeLabel : '-15%';
            $desc   = trim($r['Description'] ?? $name);
            try {
                $stmt->execute([$name,$price,$oldPrice,$cat,$stock,$badge,$desc,'placeholder.jpg',$code?:null]);
                $count++; $byCat[$cat] = ($byCat[$cat]??0)+1;
            } catch (Exception $e) {}
        }
        foreach (glob(dirname(__FILE__) . '/.cache_products_*.json') as $f) @unlink($f);
        ok(['imported'=>$count,'breakdown'=>$byCat]);

    // ── FLASH SALE ────────────────────────────────────────────
    case 'get_flash_sale':
        getCached('flash_sale', 30, function() use ($db) {
            // FIX: include image_data so flash sale images also display
            ok(['products' => $db->query('SELECT p.id,p.name,p.price,p.old_price,p.category,p.stock,p.badge,p.image,p.image_data,p.code,f.discount_pct FROM flash_sale f JOIN products p ON f.product_id=p.id WHERE f.active=1 LIMIT 20')->fetchAll()]);
        });

    case 'add_flash_sale':
        $d = body(); $pid = intval($d['product_id']??0); $disc = intval($d['discount_pct']??10);
        if (!$pid) fail('Product ID required');
        $db->prepare('INSERT INTO flash_sale (product_id,discount_pct) VALUES (?,?) ON DUPLICATE KEY UPDATE active=1,discount_pct=?')->execute([$pid,$disc,$disc]);
        bustCache('flash_sale');
        ok(['message'=>'Added']);

    case 'remove_flash_sale':
        $d = body();
        $db->prepare('UPDATE flash_sale SET active=0 WHERE product_id=?')->execute([intval($d['product_id']??0)]);
        bustCache('flash_sale');
        ok(['message'=>'Removed']);

    // ── CATEGORIES ────────────────────────────────────────────
    case 'get_categories':
        getCached('categories', 60, function() use ($db) {
            ok(['categories' => $db->query('SELECT * FROM categories ORDER BY id')->fetchAll()]);
        });

    case 'add_category':
        $d = body(); $name = strtolower(trim($d['name']??'')); $label = trim($d['label']??$name);
        if (!$name) fail('Category name required');
        $db->prepare('INSERT IGNORE INTO categories (name,label) VALUES (?,?)')->execute([$name,$label]);
        bustCache('categories');
        ok(['message'=>'Added']);

    case 'delete_category':
        $d = body();
        $db->prepare('DELETE FROM categories WHERE id=?')->execute([intval($d['id']??0)]);
        bustCache('categories');
        ok(['message'=>'Deleted']);

    // ── ORDERS ────────────────────────────────────────────────
    case 'get_orders':
        // FIX: reduced to 3s cache so admin polling at 3s actually sees new orders promptly
        getCached('get_orders', 3, function() use ($db) {
            $rows = $db->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 200')->fetchAll();
            foreach ($rows as &$o) {
                $o['delivery_type'] = match($o['delivery_method'] ?? 'pickup') {
                    'delivery' => '📦 Courier',
                    'glolink'  => '🏍️ Glolink',
                    default    => '🏪 Pickup',
                };
            }
            ok(['orders' => $rows]);
        });

    case 'mark_orders_seen':
        $db->exec("UPDATE orders SET seen_by_admin=1 WHERE seen_by_admin=0");
        bustCache('get_orders');
        ok(['message'=>'Marked']);

    case 'mark_invoice_paid':
        $d = body(); $orderId = $d['order_id']??''; $status = strtoupper($d['payment_status']??'PAID');
        if (!$orderId) fail('order_id required');
        $db->prepare('UPDATE orders SET payment_status=? WHERE order_id=?')->execute([$status,$orderId]);
        bustCache('get_orders');
        ok(['message'=>'Updated','payment_status'=>$status]);

    case 'save_order':
        $d = body();
        $orderId = trim($d['order_id'] ?? '');
        if (!$orderId) $orderId = genOrderId();

        $deliveryFee = 0;
        $customerLat = floatval($d['customer_lat'] ?? 0);
        $customerLng = floatval($d['customer_lng'] ?? 0);
        if ($customerLat && $customerLng && ($d['delivery_method'] ?? '') !== 'pickup') {
            $deliveryFee = calculateDeliveryFee($customerLat, $customerLng);
        }

        $itemsJson = is_array($d['items'] ?? null) ? json_encode($d['items']) : ($d['items'] ?? '[]');

        $exists = $db->prepare('SELECT id FROM orders WHERE order_id=?');
        $exists->execute([$orderId]);
        if ($exists->fetch()) {
            $db->prepare('UPDATE orders SET name=?,phone=?,email=?,location=?,items=?,total=?,payment_method=?,delivery_method=?,delivery_address=?,delivery_notes=?,delivery_fee=?,customer_lat=?,customer_lng=? WHERE order_id=?')
            ->execute([
                $d['name']??'', $d['phone']??'', $d['email']??'', $d['location']??'',
                $itemsJson, intval($d['total']??0),
                $d['payment_method'] ?? 'mpesa',
                $d['delivery_method']  ?? 'pickup',
                $d['delivery_address'] ?? '',
                $d['delivery_notes']   ?? '',
                $deliveryFee, $customerLat, $customerLng, $orderId,
            ]);
            bustCache('get_orders');
            ok(['order_id'=>$orderId,'updated'=>true,'delivery_fee'=>$deliveryFee]);
        }

        $db->prepare('INSERT INTO orders (order_id,name,phone,email,location,items,total,payment_method,delivery_method,delivery_address,delivery_notes,delivery_fee,customer_lat,customer_lng,seen_by_admin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)')
        ->execute([
            $orderId,
            $d['name']??'', $d['phone']??'', $d['email']??'', $d['location']??'',
            $itemsJson, intval($d['total']??0),
            $d['payment_method'] ?? 'mpesa',
            $d['delivery_method']  ?? 'pickup',
            $d['delivery_address'] ?? '',
            $d['delivery_notes']   ?? '',
            $deliveryFee, $customerLat, $customerLng,
        ]);
        bustCache('get_orders');
        ok(['order_id'=>$orderId,'delivery_fee'=>$deliveryFee]);

    case 'get_invoice_pdf':
        $orderId = $_GET['order_id'] ?? '';
        if (!$orderId) fail('order_id required');
        $html = generatePdfInvoice($orderId);
        if (!$html) fail('Order not found');
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="Invoice-' . $orderId . '.html"');
        echo $html;
        exit;

    case 'stk_push':
        $d = body();
        $phone = trim($d['phone']??''); $amount = intval($d['amount']??0); $orderId = trim($d['order_id']??'');
        if (!$phone)  fail('phone required');
        if (!$amount) fail('amount required');
        $phone = preg_replace('/[^0-9]/','',$phone);
        if (strlen($phone)===9) $phone='254'.$phone;
        if ($phone[0]==='0') $phone='254'.substr($phone,1);
        if (!preg_match('/^254[0-9]{9}$/',$phone)) fail('Invalid phone format');
        if (!$orderId) $orderId = genOrderId();
        try {
            $db->prepare('INSERT IGNORE INTO orders (order_id,name,phone,email,location,items,total,payment_method,status,payment_status,seen_by_admin) VALUES (?,?,?,?,?,?,?,?,?,?,0)')
            ->execute([$orderId,$d['name']??'Customer',$phone,$d['email']??'',$d['location']??'','[]',$amount,'mpesa','BEING PROCESSED','UNPAID']);
        } catch (Exception $e) {}
        $result = sendSTKPush($phone, $amount, $orderId);
        if ($result['success']) ok(['message'=>'STK push sent','order_id'=>$orderId]);
        else fail($result['error']??'STK failed');

    case 'check_payment':
        $orderId = ($_GET['order_id'] ?? body()['order_id'] ?? '');
        if (!$orderId) fail('order_id required');
        $stmt = $db->prepare('SELECT status,payment_status,mpesa_receipt FROM orders WHERE order_id=?');
        $stmt->execute([$orderId]); $o=$stmt->fetch();
        if (!$o) fail('Not found');
        ok(['status'=>$o['status'],'payment_status'=>$o['payment_status'],'receipt'=>$o['mpesa_receipt']]);

    case 'update_order_status':
        $d=body(); $orderId=$d['order_id']??''; $status=$d['status']??'';
        if (!$orderId||!$status) fail('Order ID and status required');
        $db->prepare('UPDATE orders SET status=? WHERE order_id=?')->execute([$status,$orderId]);
        bustCache('get_orders');
        ok(['message'=>'Updated']);

    case 'delete_order':
        $d=body(); $orderId=$d['order_id']??'';
        if (!$orderId) fail('order_id required');
        $db->prepare('DELETE FROM orders WHERE order_id=?')->execute([$orderId]);
        bustCache('get_orders');
        ok(['message'=>'Deleted']);

    // FIX: handle M-Pesa callback (was defined as URL but had no handler)
    case 'mpesa_callback':
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        $result = $data['Body']['stkCallback'] ?? null;
        if ($result) {
            $resultCode = $result['ResultCode'] ?? -1;
            $orderId    = $result['CheckoutRequestID'] ?? '';
            // Try to get order_id from metadata
            $metaItems  = $result['CallbackMetadata']['Item'] ?? [];
            $receipt    = '';
            $phone      = '';
            foreach ($metaItems as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') $receipt = $item['Value'];
                if ($item['Name'] === 'PhoneNumber')        $phone   = $item['Value'];
            }
            // Match by phone if we don't have the order_id
            if ($resultCode === 0) {
                if ($receipt) {
                    $stmt = $db->prepare('UPDATE orders SET payment_status="PAID", mpesa_receipt=? WHERE (order_id=? OR phone=?) AND payment_status="UNPAID" ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute([$receipt, $orderId, $phone]);
                    bustCache('get_orders');
                }
            }
        }
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'OK']);
        exit;

    // ── POSTERS ───────────────────────────────────────────────
    case 'get_posters':
        getCached('posters', 30, function() use ($db) {
            ok(['posters'=>$db->query('SELECT * FROM posters WHERE status="ACTIVE" ORDER BY id DESC LIMIT 50')->fetchAll()]);
        });

    case 'save_poster':
        $d=body();
        $db->prepare('INSERT INTO posters (title,description,image_data) VALUES (?,?,?)')->execute([$d['title']??'',$d['description']??'',$d['image_data']??'']);
        bustCache('posters');
        ok(['message'=>'Saved']);

    case 'delete_poster':
        $d=body();
        $db->prepare('DELETE FROM posters WHERE id=?')->execute([intval($d['id']??0)]);
        bustCache('posters');
        ok(['message'=>'Deleted']);

    // ── SETTINGS ──────────────────────────────────────────────
    case 'get_settings':
        getCached('settings', 60, function() use ($db) {
            $rows=$db->query('SELECT setting_key,setting_value FROM site_settings')->fetchAll();
            $settings=[];
            foreach ($rows as $r) $settings[$r['setting_key']]=$r['setting_value'];
            ok(['settings'=>$settings]);
        });

    case 'save_settings':
        $d=body(); $settings=$d['settings']??[];
        if (!$settings) fail('settings required');
        $stmt=$db->prepare('INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
        foreach ($settings as $k=>$v) $stmt->execute([$k,$v,$v]);
        bustCache('settings');
        ok(['message'=>'Saved']);

    // ── STATS ─────────────────────────────────────────────────
    case 'get_stats':
        getCached('stats', 30, function() use ($db) {
            ok([
                'total_products'   => $db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
                'total_orders'     => $db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
                'total_posters'    => $db->query('SELECT COUNT(*) FROM posters')->fetchColumn(),
                'total_revenue'    => $db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='PAID'")->fetchColumn(),
                'flash_sale_count' => $db->query('SELECT COUNT(*) FROM flash_sale WHERE active=1')->fetchColumn(),
            ]);
        });

    default:
        fail("Unknown action: $action");
}
