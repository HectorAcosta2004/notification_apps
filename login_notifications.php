<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    header("Content-Type: application/json; charset=UTF-8");

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!$data || empty($data["email"]) || empty($data["password"])) {
        echo json_encode(["status" => "error", "message" => "Campos obligatorios"]);
        exit;
    }

    $email = $data["email"];
    $password = $data["password"];

    /* =========================
       BUSCAR USUARIO
    ========================= */
    $stmt = $mysqli->prepare("
        SELECT u.id, u.name, u.password, u.roles,
               i.nombre AS institucion
        FROM users u
        LEFT JOIN instituciones i ON u.institucion_id = i.id
        WHERE u.email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
        exit;
    }

    if (!password_verify($password, $user["password"])) {
        echo json_encode(["status" => "error", "message" => "Contraseña incorrecta"]);
        exit;
    }

    /* =========================
       VERIFICAR APPS ASIGNADAS
    ========================= */
    $stmtApps = $mysqli->prepare("
        SELECT a.id, a.nombre
        FROM apps a
        INNER JOIN user_apps ua ON ua.app_id = a.id
        WHERE ua.user_id = ?
    ");

    $stmtApps->bind_param("i", $user["id"]);
    $stmtApps->execute();
    $resultApps = $stmtApps->get_result();
    $apps = $resultApps->fetch_all(MYSQLI_ASSOC);

    if (!$apps) {
        echo json_encode(["status" => "error", "message" => "No tienes apps asignadas"]);
        exit;
    }

    /* =========================
       CREAR SESIÓN
    ========================= */
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["user_name"] = $user["name"];
    $_SESSION["role"] = $user["roles"];
    $_SESSION["institucion"] = $user["institucion"];
    $_SESSION["ultimo_acceso"] = time();



 /* =========================
       REDIRECCIONAR SEGÚN ROL
    ========================= */
    $redirect = "onesignal_panel.php";  

    if ($user["roles"] === "root") {
        $redirect = "usuarios_admin.php";
    } elseif ($user["roles"] === "administrator") {
        $redirect = "onesignal_panel.php";
    } elseif ($user["roles"] === "author") {
        $redirect = "onesignal_panel.php";
    }

    echo json_encode(["status" => "ok", "redirect" => $redirect]);
    exit;
    
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Iniciar Sesión</title>
<style>
body { 
    font-family: Arial; 
    background: #f4f4f4; 
    display:flex; 
    justify-content:center; 
    align-items:center; 
    height:100vh; 
    margin:0; 
}
.login-box { 
    background:#fff; 
    padding:25px; 
    border-radius:8px; 
    width:320px; 
    box-shadow:0 0 10px rgba(0,0,0,.15); 
}
input, button { 
    width:100%; 
    padding:12px; 
    margin-top:10px; 
    border-radius:6px; 
    border:1px solid #ccc; 
    box-sizing: border-box; 
}
button { 
    background:#00696B; 
    color:white; 
    border:none; 
    cursor:pointer; 
    font-weight:bold; 
}
#msg { 
    text-align:center; 
    margin-top:12px; 
    color: red; 
}
</style>
</head>
<body>

<div class="login-box">
    <h2>Iniciar Sesión</h2>
    <input type="email" id="email" placeholder="Correo">
    <input type="password" id="password" placeholder="Contraseña">
    <button onclick="login()">Entrar</button>
    <p id="msg"></p>
</div>

<script>
async function login() {

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const msg = document.getElementById("msg");
    msg.textContent = "";

    try {
        const res = await fetch("login_notifications.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, password })
        });

        const data = await res.json();
if (data.status !== "ok") {
    msg.textContent = data.message;
    return;
}

msg.style.color = "green";
msg.textContent = "Login correcto";

setTimeout(() => {
    window.location.href = data.redirect;
}, 800);

    } catch (e) {
        msg.textContent = "Error inesperado";
    }
}
</script>

</body>
</html>