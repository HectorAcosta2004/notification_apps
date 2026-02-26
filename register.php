<?php
session_start();

/* =========================
   VALIDACIÓN DE SESIÓN
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
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
        header("Location: login_register.php?expirado=1");
        exit;
    }
}

$_SESSION['ultimo_acceso'] = time();

/* =========================
   BASE DE DATOS
========================= */
$db = new mysqli("localhost", "root", "", "Reavivados");

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $name = trim($_POST["name"] ?? "");
    $apps = $_POST["apps"] ?? [];

    // Validación general
    if (!$email || !$password || !$name || empty($apps)) {
        $msg = "Todos los campos son obligatorios y debes seleccionar al menos una aplicación";
    } else {

        // Convertir array de apps a string
        $app_string = implode(",", $apps);

        // Verificar si el correo ya existe
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "El correo ya existe";
        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                "INSERT INTO users (email, password, name, app) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $email, $hash, $name, $app_string);
            $stmt->execute();

            $_SESSION['success'] = "Usuario creado correctamente";
            header("Location: login_notifications.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear usuario</title>
<style>
body {
    font-family: Arial;
    background: #f4f4f4;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}
.box {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    width: 320px;
    box-shadow: 0 0 10px rgba(0,0,0,.15);
}
input, button {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    box-sizing: border-box;
    border-radius: 4px;
    border: 1px solid #ccc;
}
button {
    background: #00696B;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: bold;
}
button:hover {
    background: #004d4f;
}
.msg {
    margin-top: 10px;
    text-align: center;
    color: red;
}
label {
    font-size: 14px;
    color: #333;
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
.logout-btn:hover {
    background:#b02a37;
}
.checkbox-group {
    margin-top: 10px;
}
.checkbox-group label {
    display: block;
    margin-top: 5px;
}
input[type="checkbox"] {
    margin-right: 6px;
}
</style>
</head>
<body>

<div class="box">

<form action="logout_2.php" method="POST" style="text-align:right;">
    <button type="submit" class="logout-btn">Cerrar sesión</button>
</form>

<h2>Crear usuario</h2>

<form method="POST">

    <input type="text" name="name" placeholder="Nombre" required>
    <input type="email" name="email" placeholder="Correo" required>
    <input type="password" name="password" placeholder="Contraseña" required>
    
    <label>Vincular a aplicación:</label>

    <div class="checkbox-group">
        <label>
            <input type="checkbox" name="apps[]" value="rpsp">
            Reavivados por su Palabra (RPSP)
        </label>

        <label>
            <input type="checkbox" name="apps[]" value="radio">
            Esperanza México Radio
        </label>
    </div>

    <button type="submit">Crear</button>

</form>

<?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

</div>

</body>

<script>
let tiempoLimite = 180000; 
let temporizador;

function reiniciar() {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        window.location.href = "logout_2.php";
    }, tiempoLimite);
}

window.onload = reiniciar;
document.onmousemove = reiniciar;
document.onkeypress = reiniciar;
document.onclick = reiniciar;
document.onscroll = reiniciar;
</script>

</html>