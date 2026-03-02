<?php
ob_start();
session_start();
require_once "db.php";

/* =========================
   VALIDACIÓN DE SESIÓN Y ROL
========================= */
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: login_notifications.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$rolesPermitidos = ['root','administrator','author'];
if (!in_array($role, $rolesPermitidos)) {
    session_destroy();
    header("Location: login_notifications.php");
    exit;
}

/* =========================
   MENU DINÁMICO POR ROL
========================= */
$menu_vistas = [];
switch($role) {
    case 'root':
        $menu_vistas = [
            'Registro' => 'register.php',
            'Usuarios' => 'usuarios_admin.php',
            'Reportes' => 'reportes_mensajes.php',
            'Mensajes' => 'onesignal_panel.php',
        ];
        break;
    case 'administrator':
        $menu_vistas = [
            'Reportes' => 'reportes_mensajes.php',
            'Mensajes' => 'onesignal_panel.php',
        ];
        break;
    case 'author':
        $menu_vistas = [
            'Mensajes' => 'onesignal_panel.php',
        ];
        break;
}

/* =========================
   CONTROL DE INACTIVIDAD
========================= */
$tiempo_limite = 1800; // 30 min
if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $tiempo_limite)) {
    session_destroy();
    header("Location: login_notifications.php?expirado=1");
    exit;
}
$_SESSION['ultimo_acceso'] = time();

/* =========================
   OBTENER APPS DEL USUARIO
========================= */
$stmt = $mysqli->prepare("
    SELECT a.id, a.nombre 
    FROM apps a
    INNER JOIN user_apps ua ON ua.app_id = a.id
    WHERE ua.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$apps_usuario = $result->fetch_all(MYSQLI_ASSOC);

/* =========================
   ENVÍO DE NOTIFICACIÓN
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {

    error_reporting(E_ERROR | E_PARSE);
    header('Content-Type: application/json; charset=utf-8');

    $app_id = (int)($_POST['app'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $fechaEnvio = $_POST['fecha_envio'] ?? '';

    if (!$app_id || $titulo === '' || $mensaje === '') {
        echo json_encode(['success' => false, 'message' => 'Campos obligatorios']);
        exit;
    }

    // Verificar app del usuario
    $stmt = $mysqli->prepare("
        SELECT *
        FROM apps a
        INNER JOIN user_apps ua ON ua.app_id = a.id
        WHERE ua.user_id = ? AND a.id = ?
    ");
    $stmt->bind_param("ii", $user_id, $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if (!$app) {
        echo json_encode(['success' => false, 'message' => 'App no autorizada']);
        exit;
    }

    if (empty($app['onesignal_api_key'])) {
        echo json_encode(['success' => false, 'message' => 'API Key de OneSignal no configurada']);
        exit;
    }

    $data = [
        "app_id" => $app['onesignal_app_id'],
        "included_segments" => ["All"],
        "headings" => ["en" => $titulo],
        "contents" => ["en" => $mensaje]
    ];

    if (!empty($fechaEnvio)) {
        try {
            $date = new DateTime($fechaEnvio);
            $data['send_after'] = $date->format(DateTime::ATOM);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido']);
            exit;
        }
    }

    // Enviar a OneSignal
    $ch = curl_init("https://api.onesignal.com/notifications");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json; charset=utf-8",
            "Authorization: Key " . $app['onesignal_api_key']
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

    if ($response === false || !in_array($http_code, [200,201])) {
        echo json_encode(['success' => false, 'message' => 'Error al conectar con OneSignal']);
        exit;
    }

    // Guardar reporte
    $stmt = $mysqli->prepare("
        INSERT INTO reportes_mensajes (user_id, app_id, titulo, descripcion, fecha_envio)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiss", $user_id, $app_id, $titulo, $mensaje);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Notificación enviada']);
    ob_end_flush();
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
body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:0; }
.top-menu { display:flex; align-items:center; gap:15px; padding:10px 20px; background:#00696B; color:white; flex-wrap:wrap;}
.top-menu .menu-link { color:white; text-decoration:none; font-weight:bold; padding:6px 12px; border-radius:4px; transition:0.2s;}
.top-menu .menu-link:hover { background:#004d4f; }
.logout-btn { background:#dc3545; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; margin-left:auto;}
.logout-btn:hover { background:#b02a37;}
.container { width:350px; padding:25px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.2); background:#fff; margin:20px auto;}
h2 { text-align:center; margin-bottom:20px; }
input, textarea, button, select { width:100%; margin-top:10px; padding:10px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box;}
button { background:#00696B; color:#fff; font-weight:bold; border:none; cursor:pointer;}
button:hover { background:#0056b3;}
.error { background:#ffdddd; padding:10px; margin-top:10px; display:none; color:red;}
.success { background:#e6ffe6; padding:10px; margin-top:10px; display:none; color:green;}
</style>
</head>
<body>

<div class="top-menu">
    <?php foreach($menu_vistas as $nombre => $url): ?>
        <a href="<?= $url ?>" class="menu-link"><?= $nombre ?></a>
    <?php endforeach; ?>
    <form action="logout.php" method="POST" style="margin-left:auto;">
        <button type="submit" class="logout-btn">Cerrar sesión</button>
    </form>
</div>

<div class="container">
<h2>Enviar Notificación</h2>

<form id="formNoti" method="POST">
<label>Aplicación</label>
<?php if (count($apps_usuario) > 1): ?>
<select name="app" required>
    <?php foreach ($apps_usuario as $app): ?>
        <option value="<?= $app['id'] ?>"><?= strtoupper(htmlspecialchars($app['nombre'])) ?></option>
    <?php endforeach; ?>
</select>
<?php else: ?>
<input type="hidden" name="app" value="<?= htmlspecialchars($apps_usuario[0]['id']) ?>">
<input type="text" value="<?= strtoupper(htmlspecialchars($apps_usuario[0]['nombre'])) ?>" disabled>
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
    form.append('action','send');

    fetch("", { method:"POST", body: form })
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            document.getElementById("mensajeExito").style.display="block";
            document.getElementById("mensajeError").style.display="none";
            this.reset();
            setTimeout(()=>{document.getElementById("mensajeExito").style.display="none";},3000);
        } else {
            document.getElementById("mensajeError").innerText=data.message || "Error al enviar";
            document.getElementById("mensajeError").style.display="block";
        }
    })
    .catch(()=>{ document.getElementById("mensajeError").innerText="Error de conexión"; document.getElementById("mensajeError").style.display="block"; });
});

// Control de inactividad
let tiempoLimite = 18000;
let temporizador;
function reiniciar(){ clearTimeout(temporizador); temporizador=setTimeout(()=>{ window.location.href="logout.php"; }, tiempoLimite);}
window.onload = reiniciar;
document.onmousemove=reiniciar;
document.onkeypress=reiniciar;
document.onclick=reiniciar;
document.onscroll=reiniciar;
</script>
</body>
</html>