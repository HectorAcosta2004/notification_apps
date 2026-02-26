<?php
session_start();

// Configuración de base de datos
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "Reavivados";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json; charset=UTF-8");

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!$data || empty($data["email"]) || empty($data["password"])) {
        echo json_encode(["status" => "error", "message" => "Campos obligatorios"]);
        exit;
    }

    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        echo json_encode(["status" => "error", "message" => "Error de conexión"]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id, name, password, app FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $data["email"]);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
        exit;
    }

    $user = $res->fetch_assoc();
    if (!password_verify($data["password"], $user["password"])) {
        echo json_encode(["status" => "error", "message" => "Contraseña incorrecta"]);
        exit;
    }

    // Guardamos la sesión
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["app"] = $user["app"];

    echo json_encode([
        "status" => "ok",
        "user" => ["id" => $user["id"], "name" => $user["name"]]
    ]);
    exit; 
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; display:flex; justify-content:center; align-items:center; height:100vh; margin:0; }
        .login-box { background:#fff; padding:25px; border-radius:8px; width:320px; box-shadow:0 0 10px rgba(0,0,0,.15); }
        input, button { width:100%; padding:12px; margin-top:10px; border-radius:6px; border:1px solid #ccc; box-sizing: border-box; }
        button { background:#00696B; color:white; border:none; cursor:pointer; font-weight:bold; }
        #msg { text-align:center; margin-top:12px; color: red; }
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
            window.location.href = "register.php";
        }, 1000);

    } catch (e) {
        msg.textContent = "Error inesperado";
    }
}
</script>
</body>
</html>