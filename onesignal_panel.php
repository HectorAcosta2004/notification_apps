<?php
ob_start();
session_start();
require_once "db.php";

/* =========================
   ZONA HORARIA
========================= */
date_default_timezone_set('America/Mexico_City');

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
            'Correo a Unión' => 'cambio_correo.php'
        ];
        break;

    case 'administrator':
        $menu_vistas = [
            'Reportes' => 'reportes_mensajes.php',
            'Mensajes' => 'onesignal_panel.php'
        ];
        break;

    case 'author':
        $menu_vistas = [
            'Mensajes' => 'onesignal_panel.php'
        ];
        break;
}

/* =========================
   CONTROL DE INACTIVIDAD
========================= */
$tiempo_maximo = 180;

if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $tiempo_maximo)) {
   session_unset();
   session_destroy();
   header("Location: login_notifications.php?expirado=1");
   exit;
}

$_SESSION['ultimo_acceso'] = time();
/* =========================
   OBTENER APPS DEL USUARIO
========================= */

$stmt = $mysqli->prepare("
    SELECT a.id, a.nombre, a.onesignal_app_id, a.onesignal_api_key
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

    header('Content-Type: application/json; charset=utf-8');

    $app_id = (int)($_POST['app'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $fechaEnvio = $_POST['fecha_envio'] ?? '';

    if (!$app_id || $titulo === '' || $mensaje === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios'
        ]);
        exit;
    }

    /* =========================
       VALIDAR APP
    ========================= */

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
        echo json_encode([
            'success'=>false,
            'message'=>'App no autorizada'
        ]);
        exit;
    }

    if (empty($app['onesignal_api_key'])) {
        echo json_encode([
            'success'=>false,
            'message'=>'API Key de OneSignal no configurada'
        ]);
        exit;
    }

    /* =========================
       DATOS PARA ONESIGNAL
    ========================= */

    $data = [
        "app_id" => $app['onesignal_app_id'],
        "included_segments" => ["All"],
        "headings" => ["en" => $titulo],
        "contents" => ["en" => $mensaje]
    ];

    /* =========================
       PROGRAMAR FECHA
    ========================= */

    $data = [
    "app_id" => $app['onesignal_app_id'],
    "included_segments" => ["All"],
    "headings" => ["en" => $titulo],
    "contents" => ["en" => $mensaje]
];

if (!empty($fechaEnvio)) {

    $fechaSeleccionada = new DateTime($fechaEnvio, new DateTimeZone('America/Mexico_City'));
    $fechaServidor = new DateTime("now", new DateTimeZone('America/Mexico_City'));

    // Validar si la fecha es anterior a la del servidor
    if ($fechaSeleccionada < $fechaServidor) {

        echo json_encode([
            "success" => false,
            "message" => "⚠️ Hora atrasada. La fecha seleccionada es menor a la hora actual del servidor."
        ]);
        exit;
    }

    // Convertir a UTC para OneSignal
    $fechaSeleccionada->setTimezone(new DateTimeZone('UTC'));
    $data['send_after'] = $fechaSeleccionada->format("Y-m-d\TH:i:s\Z");
}

    /* =========================
       ENVIAR A ONESIGNAL
    ========================= */

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
$curl_error = curl_error($ch);

curl_close($ch);

if ($response === false) {
    echo json_encode([
        "success" => false,
        "message" => "cURL error: " . $curl_error
    ]);
    exit;
}

if (!in_array($http_code, [200,201])) {
    echo json_encode([
        "success" => false,
        "http_code" => $http_code,
        "onesignal_response" => json_decode($response, true)
    ]);
    exit;
}

    /* =========================
       GUARDAR REPORTE
    ========================= */

    $stmt = $mysqli->prepare("
        INSERT INTO reportes_mensajes 
        (user_id, app_id, titulo, descripcion, fecha_envio)
        VALUES (?, ?, ?, ?, ?)
    ");

    $fechaGuardar = !empty($fechaEnvio) ? $fechaEnvio : date("Y-m-d H:i:s");

    $stmt->bind_param(
        "iisss",
        $user_id,
        $app_id,
        $titulo,
        $mensaje,
        $fechaGuardar
    );

    $stmt->execute();

    echo json_encode([
        'success'=>true,
        'message'=>'Notificación enviada'
    ]);

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

body{
font-family:Arial;
background:#f4f4f4;
margin:0;
padding:0;
}

.top-menu{
display:flex;
align-items:center;
gap:15px;
padding:10px 20px;
background:#00696B;
color:white;
flex-wrap:wrap;
}

.menu-link{
color:white;
text-decoration:none;
font-weight:bold;
padding:6px 12px;
border-radius:4px;
}

.menu-link:hover{
background:#004d4f;
}

.logout-btn{
background:#dc3545;
color:white;
border:none;
padding:6px 12px;
border-radius:4px;
cursor:pointer;
margin-left:auto;
}

.container{
width:350px;
padding:25px;
border-radius:8px;
box-shadow:0 0 10px rgba(0,0,0,0.2);
background:#fff;
margin:20px auto;
}

input,textarea,button,select{
width:100%;
margin-top:10px;
padding:10px;
border-radius:6px;
border:1px solid #ccc;
box-sizing:border-box;
}

button{
background:#00696B;
color:#fff;
font-weight:bold;
border:none;
cursor:pointer;
}

.error{
background:#ffdddd;
padding:10px;
margin-top:10px;
display:none;
color:red;
}

.success{
background:#e6ffe6;
padding:10px;
margin-top:10px;
display:none;
color:green;
}

</style>

</head>

<body>

<div class="top-menu">

<?php foreach($menu_vistas as $nombre=>$url): ?>

<a href="<?= $url ?>" class="menu-link"><?= $nombre ?></a>

<?php endforeach; ?>

<form action="logout.php" method="POST" style="margin-left:auto;">
<button type="submit" class="logout-btn">Cerrar sesión</button>
</form>

</div>

<div class="container">

<h2>Enviar Notificación</h2>

<form id="formNoti">

<label>Aplicación</label>

<?php if(count($apps_usuario)>1): ?>

<select name="app">

<?php foreach($apps_usuario as $app): ?>

<option value="<?= $app['id'] ?>">

<?= strtoupper(htmlspecialchars($app['nombre'])) ?>

</option>

<?php endforeach; ?>

</select>

<?php else: ?>

<input type="hidden" name="app" value="<?= $apps_usuario[0]['id'] ?>">
<input type="text" value="<?= strtoupper($apps_usuario[0]['nombre']) ?>" disabled>

<?php endif; ?>

<label>Título</label>
<input type="text" name="titulo" required>

<label>Mensaje</label>
<textarea name="mensaje" required></textarea>

<label>Programar envío</label>
<input type="datetime-local" name="fecha_envio">

<small style="color:#dc3545;">Deja vacío para enviar inmediatamente</small>

<button type="submit">Enviar Notificación</button>

</form>

<div id="mensajeError" class="error"></div>
<div id="mensajeExito" class="success">✅ Notificación enviada</div>

</div>

<script>

document.getElementById("formNoti").addEventListener("submit",function(e){

e.preventDefault();

const form=new FormData(this);
form.append("action","send");

fetch("",{method:"POST",body:form})

.then(res=>res.json())

.then(data=>{

if(data.success){

document.getElementById("mensajeExito").style.display="block";

setTimeout(()=>{
document.getElementById("mensajeExito").style.display="none";
},3000);

this.reset();

}else{

let error=document.getElementById("mensajeError");

error.innerText=data.message;
error.style.display="block";

}

})

.catch(()=>{
document.getElementById("mensajeError").innerText="Error de conexión";
});

});
const inputFecha = document.querySelector('input[name="fecha_envio"]');
const errorBox = document.getElementById("mensajeError");

inputFecha.addEventListener("input", function(){

    if(!this.value) return;

    const fechaSeleccionada = new Date(this.value);
    const ahora = new Date();

    if(fechaSeleccionada < ahora){
        errorBox.innerText="⚠️ Hora atrasada. Selecciona una hora futura.";
        errorBox.style.display="block";
    }else{
        errorBox.style.display="none";
        errorBox.innerText="";
    }

});

</script>


</body>
</html>