<?php
session_start();

/* 🔐 Validar sesión (silencioso) */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

/* 🔇 Silenciar errores en salida */
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

/* ----------------------
   RECIBIR DATOS
---------------------- */
$app        = $_POST['app'] ?? '';
$title      = trim($_POST['titulo'] ?? '');
$message    = trim($_POST['mensaje'] ?? '');
$fechaEnvio = $_POST['fecha_envio'] ?? '';

/* Log interno */
file_put_contents(
    __DIR__ . "/debug.log",
    "POST:\n" . print_r($_POST, true) . "\n",
    FILE_APPEND
);

/* Validación silenciosa */
if ($title === '' || $message === '') {
    exit;
}

/* ----------------------
   CONFIG ONESIGNAL
---------------------- */
$configs = [
    'app1' => [
        'app_id' => '85a1d19b-6490-4c8a-9a7e-b051c0d4de49',
        'api_key' => 'os_v2_app_qwq5dg3esbgivgt6wbi4bvg6jgftn2hgkfmu62n7sm46r5zkxcg7p2nmjw32znkbid3q3a534l5bmnf77igov3g6wni7glmv2o44bsa'
    ],
    'app2' => [
        'app_id' => '58318179-63da-4dc6-8ab9-cf054484b51c',
        'api_key' => 'os_v2_app_layyc6ld3jg4ncvzz4cujbfvdr6fnfkxohxenemrn7oejy2njzr6nwtcq3flz2qgnvr7ipvorave2pup4ttfbwisg6e5y2oy46te7uq'
    ]
];

if (!isset($configs[$app])) {
    exit;
}

$appConfig = $configs[$app];

/* ----------------------
   PAYLOAD ONESIGNAL
---------------------- */
$data = [
    "app_id" => $appConfig['app_id'],
    "included_segments" => ["All"],
    "headings" => [
        "en" => $title,
        "es" => $title
    ],
    "contents" => [
        "en" => $message,
        "es" => $message
    ]
];

/* Programación opcional */
if (!empty($fechaEnvio)) {
    try {
        $date = new DateTime($fechaEnvio);
        $data['send_after'] = $date->format('Y-m-d H:i:s O');
    } catch (Exception $e) {
        exit;
    }
}

/* Log payload */
file_put_contents(
    __DIR__ . "/debug.log",
    "ONESIGNAL_PAYLOAD:\n" .
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
    "\n",
    FILE_APPEND
);

/* ----------------------
   ENVÍO A ONESIGNAL
---------------------- */
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://onesignal.com/api/v1/notifications",
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json; charset=utf-8",
        "Authorization: Basic " . $appConfig['api_key']
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    file_put_contents(
        __DIR__ . "/debug.log",
        "CURL_ERROR: " . curl_error($ch) . "\n",
        FILE_APPEND
    );
    curl_close($ch);
    exit;
}

curl_close($ch);

/* Log respuesta */
file_put_contents(
    __DIR__ . "/debug.log",
    "ONESIGNAL_RESPONSE:\n$response\n",
    FILE_APPEND
);

$json = json_decode($response, true);

/* ✅ SOLO ÉXITO */
if (isset($json['id'])) {
    echo json_encode([
        "id" => $json['id'],
        "recipients" => $json['recipients'] ?? 1
    ]);
    exit;
}

/* ❌ Cualquier otra cosa → silencio */
exit;
