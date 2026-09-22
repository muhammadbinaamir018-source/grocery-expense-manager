<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

/* =========================
   SETTINGS
========================= */

$tesseractPath = 'C:\Program Files\Tesseract-OCR\tesseract.exe';

$maxFileSize = 5 * 1024 * 1024; // 5 MB

/* =========================
   CHECK TESSERACT
========================= */

if (!file_exists($tesseractPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Tesseract OCR not found at: ' . $tesseractPath
    ]);
    exit;
}

if (!function_exists('exec')) {
    echo json_encode([
        'success' => false,
        'message' => 'PHP exec() function is disabled.'
    ]);
    exit;
}

/* =========================
   CHECK FILE
========================= */

if (!isset($_FILES['bill_image'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No bill image received.'
    ]);
    exit;
}

$file = $_FILES['bill_image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'File upload failed. Error code: ' . $file['error']
    ]);
    exit;
}

if ($file['size'] > $maxFileSize) {
    echo json_encode([
        'success' => false,
        'message' => 'Image size must be less than 5 MB.'
    ]);
    exit;
}

/* =========================
   VALIDATE MIME TYPE
========================= */

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (!in_array($mime, $allowedTypes, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Only JPG, PNG and WEBP images are allowed.'
    ]);
    exit;
}

/* =========================
   TEMP FILE
========================= */

$extension = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg'
};

$tempDir = __DIR__ . DIRECTORY_SEPARATOR . '../uploads/bills/ocr_temp';

if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$tempFile = $tempDir . DIRECTORY_SEPARATOR .
    'ocr_' . bin2hex(random_bytes(8)) . '.' . $extension;

if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not create temporary OCR file.'
    ]);
    exit;
}

/* =========================
   RUN TESSERACT
========================= */

$command =
    '"' . $tesseractPath . '" ' .
    '"' . $tempFile . '" ' .
    'stdout --psm 6 2>NUL';

$output = [];
$returnCode = 0;

exec($command, $output, $returnCode);

/* Delete temporary file */
if (file_exists($tempFile)) {
    unlink($tempFile);
}

if ($returnCode !== 0 || empty($output)) {
    echo json_encode([
        'success' => false,
        'message' => 'OCR failed. Tesseract could not read the image.'
    ]);
    exit;
}

/* =========================
   RAW OCR TEXT
========================= */

$rawText = implode("\n", $output);

$lines = preg_split('/\R/', $rawText);

$cleanLines = [];

foreach ($lines as $line) {

    $line = trim($line);

    if ($line === '') {
        continue;
    }

    $line = preg_replace('/\s+/', ' ', $line);

    $cleanLines[] = $line;
}

/* =========================
   HELPER FUNCTIONS
========================= */

function cleanNumber($value)
{
    $value = str_replace(',', '', $value);
    $value = preg_replace('/[^\d.]/', '', $value);

    if ($value === '') {
        return 0;
    }

    return (float)$value;
}

function isHeaderLine($line)
{
    $lineLower = strtolower($line);

    $headers = [
        'sales items',
        'product description',
        'description',
        'qty',
        'quantity',
        'unit price',
        'total',
        'discount',
        'invoice value',
        'transaction',
        'payment',
        'cash',
        'change',
        'fbr',
        'ntn'
    ];

    foreach ($headers as $header) {
        if (str_contains($lineLower, $header)) {
            return true;
        }
    }

    return false;
}

/* =========================
   DEFAULT DATA
========================= */

$data = [
    'store_name' => '',
    'invoice_number' => '',
    'transaction_number' => '',
    'purchase_date' => '',
    'purchase_time' => '',
    'items' => [],
    'discount' => 0,
    'subtotal' => 0,
    'tax' => 0,
    'total' => 0
];

/* =========================
   STORE NAME
========================= */

foreach ($cleanLines as $line) {

    if (
        stripos($line, 'MEGA') !== false &&
        (
            stripos($line, 'BAHRIA') !== false ||
            stripos($line, 'TOWN') !== false
        )
    ) {
        $data['store_name'] = $line;
        break;
    }
}

/* =========================
   TRANSACTION NUMBER
========================= */

foreach ($cleanLines as $line) {

    if (
        preg_match(
            '/transaction\s+no\.?\s*[:#-]?\s*([0-9\s]+)/i',
            $line,
            $match
        )
    ) {
        $data['transaction_number'] =
            preg_replace('/\D/', '', $match[1]);

        break;
    }
}

/* =========================
   INVOICE NUMBER
========================= */

foreach ($cleanLines as $line) {

    if (
        preg_match(
            '/(?:tbr\s+)?invoice\s*(?:#|no\.?)?\s*[:\-]?\s*([0-9][0-9\s\]\[\)\(]{5,})/i',
            $line,
            $match
        )
    ) {

        $invoice = preg_replace('/\D/', '', $match[1]);

        if (strlen($invoice) >= 6) {
            $data['invoice_number'] = $invoice;
            break;
        }
    }
}

/* =========================
   DATE + TIME
========================= */

foreach ($cleanLines as $line) {

    if (
        preg_match(
            '/transaction\s+date\s*[:\-]?\s*(.+)/i',
            $line,
            $match
        )
    ) {

        $dateText = trim($match[1]);

        $timestamp = strtotime($dateText);

        if ($timestamp !== false) {

            $data['purchase_date'] =
                date('Y-m-d', $timestamp);

            $data['purchase_time'] =
                date('H:i', $timestamp);
        }

        break;
    }
}

/* =========================
   DISCOUNT
========================= */

foreach ($cleanLines as $line) {

    if (
        stripos($line, 'discount') !== false &&
        preg_match(
            '/([0-9]+(?:[.,][0-9]+)?)\s*$/',
            $line,
            $match
        )
    ) {

        $data['discount'] =
            cleanNumber($match[1]);

        break;
    }
}

/* =========================
   ITEM DETECTION
========================= */

for ($i = 1; $i < count($cleanLines); $i++) {

    $line = $cleanLines[$i];

    /*
       Typical OCR line:

       1.00 219.00 0.00 Rs219.00

       or

       10.00 229.00 0.00 Rs2,290.00
    */

    $pattern =
        '/^\s*' .
        '([0-9]+(?:[.,][0-9]+)?)\s+' .
        '([0-9]+(?:[.,][0-9]+)?)\s+' .
        '([0-9]+(?:[.,][0-9]+)?)\s+' .
        '(?:[A-Za-z]*\s*)?' .
        '([0-9]+(?:[.,][0-9]+)?)' .
        '\s*$/';

    if (preg_match($pattern, str_replace(',', '', $line), $match)) {

        $itemName = $cleanLines[$i - 1];

        if (isHeaderLine($itemName)) {
            continue;
        }

        /*
           Ignore obvious summary/payment lines
        */

        if (
            stripos($itemName, 'subtotal') !== false ||
            stripos($itemName, 'invoice value') !== false ||
            stripos($itemName, 'rounding') !== false ||
            stripos($itemName, 'cash') !== false
        ) {
            continue;
        }

        $quantity = cleanNumber($match[1]);
        $unitPrice = cleanNumber($match[2]);
        $itemTotal = cleanNumber($match[4]);

        if ($quantity <= 0 || $unitPrice <= 0) {
            continue;
        }

        $data['items'][] = [
            'item_name' => $itemName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'item_total' => $itemTotal
        ];
    }
}

/* =========================
   CALCULATE SUBTOTAL
========================= */

foreach ($data['items'] as $item) {
    $data['subtotal'] += (float)$item['item_total'];
}

/*
   We calculate an initial total.
   User will be able to correct it before saving.
*/

$data['total'] =
    $data['subtotal'] - $data['discount'];

if ($data['total'] < 0) {
    $data['total'] = 0;
}

/* =========================
   RESPONSE
========================= */

echo json_encode([
    'success' => true,
    'message' => 'OCR completed successfully.',
    'data' => $data,
    'raw_text' => $rawText
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

exit;
?>