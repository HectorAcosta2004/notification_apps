<?php
session_start();

$db = new mysqli("localhost", "root", "", "Reavivados");

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $name = trim($_POST["name"] ?? "");

    if (!$email || !$password || !$name) {
        $msg = "Todos los campos son obligatorios";
    } else {

        // verificar si ya existe
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "El correo ya existe";
        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                "INSERT INTO users (email, password, name) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $email, $hash, $name);
            $stmt->execute();

            // OPCIONAL: mensaje para login
            $_SESSION['success'] = "Usuario creado correctamente, inicia sesión";

            // REDIRECCIÓN
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
body{
    font-family: Arial;
    background:#f4f4f4;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.box{
    background:#fff;
    padding:25px;
    border-radius:8px;
    width:320px;
    box-shadow:0 0 10px rgba(0,0,0,.15);
}
input,button{
    width:100%;
    padding:10px;
    margin-top:10px;
}
button{
    background:#00696B;
    color:white;
    border:none;
    cursor:pointer;
}
.msg{
    margin-top:10px;
    text-align:center;
    color:red;
}
</style>
</head>

<body>

<div class="box">
    <h2>Crear usuario</h2>

    <form method="POST">
        <input type="text" name="name" placeholder="Nombre" required>
        <input type="email" name="email" placeholder="Correo" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Crear</button>
    </form>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
</div>

</body>
</html>
