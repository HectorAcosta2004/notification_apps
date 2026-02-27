<?php
session_start();

/* =========================
   VALIDACIÓN DE SESIÓN
========================= */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['app'])) {
    header("Location: login_notifications.php");
    exit;
}

/* =========================
   CONTROL DE INACTIVIDAD
========================= */
$tiempo_limite = 180;

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];

    if ($tiempo_transcurrido > $tiempo_limite) {
        session_unset();
        session_destroy();
        header("Location: login_notifications.php?expirado=1");
        exit;
    }
}

$_SESSION['ultimo_acceso'] = time();

/* =========================
   APPS DEL USUARIO
========================= */
$apps_usuario = array_map('trim', explode(",", $_SESSION['app']));

/* =========================
   CONFIGURACIÓN ONESIGNAL
========================= */
$configs = [
    'app1' => [
        'app_id' => '85a1d19b-6490-4c8a-9a7e-b051c0d4de49',
        'api_key' => 'os_v2_app_qwq5dg3esbgivgt6wbi4bvg6jfv32x5edyheyyuqtnes2v2h6tj5kz3io7xbevzhyj5yjncxi6thx36q6cap6ed6hi4zy2np66rhrkq'
    ],
    'app2' => [
        'app_id' => '58318179-63da-4dc6-8ab9-cf054484b51c',
        'api_key' => 'os_v2_app_layyc6ld3jg4ncvzz4cujbfvdrgf55nbxfjebqu4hbssfkwwqebrdgpufjxcsnh4scfyqkhc7dus7gajbaphsauj5hickve25h5euqq'
    ]
];

$app_map = [
    'rpsp' => 'app1',
    'radio' => 'app2'
];

/* =========================
   PROCESAMIENTO DE ENVÍO
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {

    header('Content-Type: application/json; charset=utf-8');

    $appSeleccionada = $_POST['app'] ?? $apps_usuario[0];

    // Seguridad: verificar que la app pertenece al usuario
    if (!in_array($appSeleccionada, $apps_usuario)) {
        echo json_encode(['error' => true, 'message' => 'App no autorizada']);
        exit;
    }

    $app_key = $app_map[$appSeleccionada] ?? null;
    $appConfig = $configs[$app_key] ?? null;

    if (!$appConfig) {
        echo json_encode(['error' => true, 'message' => 'Configuración de App no encontrada']);
        exit;
    }

    $title = trim($_POST['titulo'] ?? '');
    $message = trim($_POST['mensaje'] ?? '');
    $fechaEnvio = $_POST['fecha_envio'] ?? '';

    if ($title === '' || $message === '') {
        echo json_encode(['error' => true, 'message' => 'Campos obligatorios']);
        exit;
    }

    $data = [
        "app_id" => $appConfig['app_id'],
        "included_segments" => ["All"],
        "headings" => ["en" => $title, "es" => $title],
        "contents" => ["en" => $message, "es" => $message]
    ];

    if (!empty($fechaEnvio)) {
        try {
            $date = new DateTime($fechaEnvio);
            $data['send_after'] = $date->format('Y-m-d H:i:s O');
        } catch (Exception $e) {
            echo json_encode(['error' => true, 'message' => 'Formato de fecha inválido']);
            exit;
        }
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://onesignal.com/api/v1/notifications",
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json; charset=utf-8",
            "Authorization: Basic " . $appConfig['api_key']
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
       CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || ($http_code !== 200 && $http_code !== 201)) {
        echo json_encode(['error' => true, 'message' => 'Error al conectar con OneSignal']);
        exit;
    }

    $json = json_decode($response, true);

    if (isset($json['id'])) {
        echo json_encode(['id' => $json['id'], 'recipients' => $json['recipients'] ?? 1]);
        exit;
    }

    echo json_encode(['error' => true, 'message' => 'Respuesta inesperada de OneSignal']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enviar Notificación</title>

<style>
body {
    font-family: Arial, sans-serif;
    background:#f4f4f4;
    margin:0;
    padding:0;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.container {
    width:350px;
    padding:25px;
    border-radius:8px;
    box-shadow:0 0 10px rgba(0,0,0,0.2);
    background:#fff;
    color:#333;
}

h2 {
    text-align:center;
    margin-bottom:20px;
}

input, textarea, button, select {
    width:100%;
    margin-top:10px;
    padding:10px;
    border-radius:6px;
    border:1px solid #ccc;
    box-sizing:border-box;
}

button {
    background:#007bff;
    color:#fff;
    font-weight:bold;
    border:none;
    cursor:pointer;
}

button:hover {
    background:#0056b3;
}

.error {
    background:#ffdddd;
    padding:10px;
    margin-top:10px;
    display:none;
    color:red;
}

.success {
    background:#e6ffe6;
    padding:10px;
    margin-top:10px;
    display:none;
    color:green;
}

.logout-btn {
    background:#dc3545;
    width:auto;
    padding:8px 12px;
    font-size:14px;
    margin-bottom:10px;
    cursor:pointer;
    border:none;
    border-radius:4px;
    color:white;
}
</style>
</head>

<body>
<div class="container">

<form action="logout.php" method="POST" style="text-align:right;">
    <button type="submit" class="logout-btn">Cerrar sesión</button>
</form>

<h2>Enviar Notificación</h2>

<form id="formNoti" method="POST">

<label>Aplicación</label>

<?php if (count($apps_usuario) > 1): ?>

    <select name="app" required>
        <?php foreach ($apps_usuario as $app): ?>
            <option value="<?= htmlspecialchars($app) ?>">
                <?= strtoupper(htmlspecialchars($app)) ?>
            </option>
        <?php endforeach; ?>
    </select>

<?php else: ?>

    <input type="hidden" name="app" value="<?= htmlspecialchars($apps_usuario[0]) ?>">
    <input type="text" value="<?= strtoupper(htmlspecialchars($apps_usuario[0])) ?>" disabled>

<?php endif; ?>

<label>Título</label>
<input type="text" name="titulo" required>

<label>Mensaje</label>
<textarea name="mensaje" required></textarea>

<label>Programar envío</label>
<input type="datetime-local" name="fecha_envio">
<small style="color:#dc3545;">Deja vacío para enviar inmediatamente.</small>

<button type="submit">Enviar Notificación</button>

</form>

<div id="mensajeError" class="error"></div>
<div id="mensajeExito" class="success">✅ Notificación enviada con éxito.</div>

</div>

<script>
document.getElementById("formNoti").addEventListener("submit", function(e) {
    e.preventDefault();

    const form = new FormData(this);
    form.append('action', 'send');

    fetch("", { method: "POST", body: form })
    .then(res => res.json())
    .then(data => {
        if (data.id) {
            document.getElementById("mensajeExito").style.display = "block";
            document.getElementById("mensajeError").style.display = "none";
            this.reset();
            setTimeout(() => {
                document.getElementById("mensajeExito").style.display = "none";
            }, 3000);
        } else {
            document.getElementById("mensajeError").innerText = data.message || "Error al enviar";
            document.getElementById("mensajeError").style.display = "block";
        }
    })
    .catch(() => {
        document.getElementById("mensajeError").innerText = "Error de conexión";
        document.getElementById("mensajeError").style.display = "block";
    });
});

/* =========================
   AUTO LOGOUT JS
========================= */
let tiempoLimite = 180000;
let temporizador;

function reiniciar() {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        window.location.href = "logout.php";
    }, tiempoLimite);
}

window.onload = reiniciar;
document.onmousemove = reiniciar;
document.onkeypress = reiniciar;
document.onclick = reiniciar;
document.onscroll = reiniciar;
</script>

</body>
</html>