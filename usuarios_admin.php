<?php
session_start();
require_once 'db.php';

/* =========================
   VALIDAR ADMIN
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit;
}

if (!isset($_SESSION['roles']) || $_SESSION['roles'] !== 'administrator') {
    header("Location: login_register.php?acceso_denegado=1");
    exit;
}

/* =========================
   ELIMINAR USUARIO
========================= */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: usuarios_admin.php");
    exit;
}

/* =========================
   ACTUALIZAR USUARIO
========================= */
if (isset($_POST['update'])) {

    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $roles = $_POST['roles'];
    $instituciones = $_POST['instituciones'];

    // 🔥 RECIBIR APPS
    $apps = $_POST['apps'] ?? [];
    $app_string = implode(",", $apps);

    $stmt = $mysqli->prepare("
        UPDATE users 
        SET name=?, email=?, roles=?, instituciones=?, app=? 
        WHERE id=?
    ");

    if (!$stmt) {
        die("Error en la consulta: " . $mysqli->error);
    }

    $stmt->bind_param("sssssi", $name, $email, $roles, $instituciones, $app_string, $id);
    $stmt->execute();

    header("Location: usuarios_admin.php");
    exit;
}



$result = $mysqli->query("SELECT * FROM users ORDER BY id DESC");
/* =========================
   TIEMPO DE INACTIVIDAD
========================= */
$tiempo_maximo = 180;

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];

    if ($tiempo_transcurrido > $tiempo_maximo) {
        header("Location: logout_2.php?inactividad=1");
        exit;
    }
}

// Actualizar tiempo de último acceso
$_SESSION['ultimo_acceso'] = time();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Usuarios</title>

<style>
body {
    font-family: Arial;
    background: #f4f4f4;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px;
    margin: 0;
}

.box {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    width: 95%;
    max-width: 1100px;
    box-shadow: 0 8px 20px rgba(0,0,0,.12);
}

h2 {
    text-align: center;
    margin-bottom: 20px;
}

.top-bar {
    display:flex;
    justify-content:space-between;
    margin-bottom:20px;
}

button {
    padding:8px 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-weight:bold;
    transition:0.3s;
}

.btn-green { background:#28a745; color:white; }
.btn-green:hover { background:#1e7e34; }

.btn-gray { background:#6c757d; color:white; }
.btn-gray:hover { background:#545b62; }

.btn-update { background:#00696B; color:white; }
.btn-update:hover { background:#004d4f; }

.btn-delete { background:#dc3545; color:white; }
.btn-delete:hover { background:#b02a37; }

.logout-btn { background:#dc3545; color:white; }
.logout-btn:hover { background:#b02a37; }

table {
    width:100%;
    border-collapse: collapse;
    margin-top:10px;
}

th {
    background:#00696B;
    color:white;
    padding:10px;
}

td {
    padding:8px;
    border-bottom:1px solid #ddd;
    text-align:center;
}

input, select {
    width:100%;
    padding:6px;
    border-radius:6px;
    border:1px solid #ccc;
}

input:focus, select:focus {
    border-color:#00696B;
    outline:none;
    box-shadow:0 0 0 2px rgba(0,105,107,0.15);
}

.actions {
    display:flex;
    gap:5px;
    justify-content:center;
}
.checkbox-group {
    margin-top: 5px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    margin-top: 6px;
    cursor: pointer;
    font-size: 13px;
    position: relative;
}

.checkbox-item input[type="checkbox"] {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid #00696B;
    border-radius: 4px;
    margin-right: 8px;
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

<div class="top-bar">

    <div>
        <a href="register.php">
            <button class="btn-green">Crear nuevo usuario</button>
        </a>
    </div>

    <form action="logout_2.php" method="POST">
        <button type="submit" class="logout-btn">Cerrar sesión</button>
    </form>

</div>

<h2>Administración de Usuarios</h2>

<table>
<tr>
   
    <th>Nombre</th>
    <th>Email</th>
    <th>Rol</th>
    <th>Institución</th>
    <th>Apps</th>
    <th>Acciones</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>
<?php $apps_usuario = explode(",", $row['app'] ?? ''); ?>
<tr>
<form method="POST">
    

    <td>
        <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>">
    </td>

    <td>
        <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>">
    </td>

    <td>
        <select name="roles">
            <option value="administrator" <?= $row['roles']=="administrator"?"selected":"" ?>>Administrador</option>
            <option value="editor" <?= $row['roles']=="editor"?"selected":"" ?>>Editor</option>
            <option value="author" <?= $row['roles']=="author"?"selected":"" ?>>Autor</option>
            <option value="contributor" <?= $row['roles']=="contributor"?"selected":"" ?>>Colaborador</option>
            <option value="subscriber" <?= $row['roles']=="subscriber"?"selected":"" ?>>Suscriptor</option>
        </select>
    </td>

    <td>
    <select name="instituciones">
        <option value="Unión Mexicana del Norte" 
            <?= $row['instituciones']=="Unión Mexicana del Norte"?"selected":"" ?>>
            Unión Mexicana del Norte
        </option>

        <option value="Unión Mexicana Central" 
            <?= $row['instituciones']=="Unión Mexicana Central"?"selected":"" ?>>
            Unión Mexicana Central
        </option>

        <option value="Unión Mexicana Interoceánica" 
            <?= $row['instituciones']=="Unión Mexicana Interoceánica"?"selected":"" ?>>
            Unión Mexicana Interoceánica
        </option>

        <option value="Unión Mexicana del Sureste" 
            <?= $row['instituciones']=="Unión Mexicana del Sureste"?"selected":"" ?>>
            Unión Mexicana del Sureste
        </option>
    </select>
</td>

   <td>
    <div class="checkbox-group">

        <label class="checkbox-item">
            <input type="checkbox" name="apps[]" value="rpsp"
                <?= in_array("rpsp", $apps_usuario) ? "checked" : "" ?>>
            Reavivados por su Palabra
        </label>

        <label class="checkbox-item">
            <input type="checkbox" name="apps[]" value="radio"
                <?= in_array("radio", $apps_usuario) ? "checked" : "" ?>>
            Esperanza México Radio
        </label>

    </div>
</td>

    <td>
        <div class="actions">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button 
    type="submit" 
    name="update" 
    class="btn-update"
    onclick="return confirm('¿Estás seguro de actualizar este usuario?');"
>
    Actualizar
</button>
            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar usuario?')">
                <button type="button" class="btn-delete">Eliminar</button>
            </a>
        </div>
    </td>
</form>
</tr>
<?php endwhile; ?>
</table>

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