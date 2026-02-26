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
    $institucion = trim($_POST["Instituciones"] ?? "");

    if (!$email || !$password || !$name || !$institucion || empty($apps)) {
        $msg = "Todos los campos son obligatorios y debes seleccionar al menos una aplicación";
    } else {

        $app_string = implode(",", $apps);

        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "El correo ya existe";
        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                "INSERT INTO users (email, password, name, app, Instituciones) 
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param("sssss", $email, $hash, $name, $app_string, $institucion);
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
    padding: 30px;
    border-radius: 10px;
    width: 350px;
    box-shadow: 0 8px 20px rgba(0,0,0,.12);
}

h2 {
    text-align: center;
    margin-bottom: 15px;
}

input, button {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    box-sizing: border-box;
    border-radius: 6px;
    border: 1px solid #ccc;
}

input:focus {
    border-color: #00696B;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,105,107,0.15);
}

button {
    background: #00696B;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
}

button:hover {
    background: #004d4f;
}

label {
    font-size: 14px;
    color: #333;
    margin-top: 10px;
    display: block;
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

.checkbox-group label {
    display: block;
    margin-top: 5px;
}

input[type="checkbox"] {
    margin-right: 6px;
}

.select-wrapper {
    position: relative;
    margin-top: 5px;
}

.select-wrapper select {
    appearance: none;
    width: 100%;
    padding: 12px 40px 12px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    background-color: #f9f9f9;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.select-wrapper select:focus {
    border-color: #00696B;
    box-shadow: 0 0 0 2px rgba(0, 105, 107, 0.2);
    background-color: #fff;
    outline: none;
}

.select-wrapper::after {
    content: "▼";
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #00696B;
    pointer-events: none;
}

.msg {
    margin-top: 10px;
    text-align: center;
    color: red;
}
.checkbox-group {
    margin-top: 8px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    margin-top: 8px;
    cursor: pointer;
    font-size: 14px;
    position: relative;
}

.checkbox-item input[type="checkbox"] {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid #00696B;
    border-radius: 4px;
    margin-right: 10px;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}

.checkbox-item input[type="checkbox"]:hover {
    background-color: rgba(0,105,107,0.1);
}

.checkbox-item input[type="checkbox"]:checked {
    background-color: #00696B;
    border-color: #00696B;
}

.checkbox-item input[type="checkbox"]:checked::after {
    content: "✓";
    position: absolute;
    color: white;
    font-size: 12px;
    font-weight: bold;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -55%);
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

    <label class="checkbox-item">
        <input type="checkbox" name="apps[]" value="rpsp">
        Reavivados por su Palabra (RPSP)
    </label>

    <label class="checkbox-item">
        <input type="checkbox" name="apps[]" value="radio">
        Esperanza México Radio
    </label>

</div>

    <label>Institución:</label>

    <div class="select-wrapper">
        <select name="Instituciones" required>
            <option value="">Seleccionar institución</option>
            <option value="Unión Mexicana del Norte">Unión Mexicana del Norte</option>
            <option value="Unión Mexicana Central">Unión Mexicana Central</option>
            <option value="Unión Mexicana Interoceánica">Unión Mexicana Interoceánica</option>
            <option value="Unión Mexicana del Sureste">Unión Mexicana del Sureste</option>
        </select>
    </div>

    <button type="submit">Crear</button>

</form>

<?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

</div>

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

</body>
</html>