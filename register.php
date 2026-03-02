<?php
session_start();

/* =========================
   VALIDACIÓN DE SESIÓN
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login_notifications.php");
    exit;
}

/* =========================
   DATOS DEL USUARIO
========================= */
$role = $_SESSION['role'];

/* =========================
   VALIDACIÓN DE ROL (SOLO ROOT PARA CREAR USUARIOS)
========================= */
if ($role !== 'root') {
    header("Location: login_notifications.php?acceso_denegado=1");
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

    case 'author': $menu_vistas = [
            'Mensajes' => 'onesignal_panel.php',
        ];
    case 'editor':
    case 'contributor':
       
        break;
}

/* =========================
   CONTROL DE INACTIVIDAD
========================= */
$tiempo_limite = 1800; // 30 min
if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $tiempo_limite)) {
    session_unset();
    session_destroy();
    header("Location: login_notifications.php?expirado=1");
    exit;
}
$_SESSION['ultimo_acceso'] = time();

/* =========================
   BASE DE DATOS
========================= */
require_once 'db.php';
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name  = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $rol = $_POST["roles"] ?? "author";
    $institucion_nombre = trim($_POST["Instituciones"] ?? "");
    $apps = $_POST["apps"] ?? []; // array de apps seleccionadas

    if (empty($name) || empty($email) || empty($password)) {
        $msg = "Todos los campos son obligatorios";
    } else {
        // Verificar si el correo ya existe
        $check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "El correo ya existe";
        } else {
            // Obtener ID de institución
            $stmtInst = $mysqli->prepare("SELECT id FROM instituciones WHERE nombre = ?");
            $stmtInst->bind_param("s", $institucion_nombre);
            $stmtInst->execute();
            $resultInst = $stmtInst->get_result();
            $inst = $resultInst->fetch_assoc();

            if (!$inst) {
                $msg = "Institución no válida";
            } else {
                $institucion_id = (int)$inst["id"];
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Insertar usuario
                $stmt = $mysqli->prepare(
                    "INSERT INTO users (name, email, password, roles, institucion_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("ssssi", $name, $email, $hash, $rol, $institucion_id);
                $stmt->execute();
                $user_id = $stmt->insert_id;

                // Insertar apps en tabla intermedia
                if (!empty($apps)) {
                    $stmtApp = $mysqli->prepare(
                        "INSERT INTO user_apps (user_id, app_id) VALUES (?, ?)"
                    );
                    foreach ($apps as $app_id) {
                        $app_id = (int)$app_id;
                        $stmtApp->bind_param("ii", $user_id, $app_id);
                        $stmtApp->execute();
                    }
                }

                $_SESSION["success"] = "Usuario creado correctamente";
                header("Location: login_notifications.php");
                exit;
            }
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
body { font-family: Arial; background:#f4f4f4; margin:0; padding:0; }
.box { background:#fff; padding:30px; border-radius:10px; width:350px; margin:40px auto; box-shadow:0 8px 20px rgba(0,0,0,.12);}
h2 { text-align:center; margin-bottom:15px; }
input, button, select { width:100%; padding:10px; margin-top:10px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box;}
input:focus, select:focus { border-color:#00696B; outline:none; box-shadow:0 0 0 2px rgba(0,105,107,0.15);}
button { background:#00696B; color:white; border:none; cursor:pointer; font-weight:bold; transition:0.3s;}
button:hover { background:#004d4f; }
label { font-size:14px; color:#333; margin-top:10px; display:block;}
.logout-btn { background:#dc3545; width:auto; padding:8px 12px; font-size:14px; margin-bottom:10px; cursor:pointer; border:none; border-radius:4px; color:white;}
.logout-btn:hover { background:#b02a37;}
.checkbox-group label { display:block; margin-top:5px;}
.checkbox-item { display:flex; align-items:center; margin-top:5px; cursor:pointer; font-size:14px; position:relative;}
.checkbox-item input[type="checkbox"] { appearance:none; width:18px; height:18px; border:2px solid #00696B; border-radius:4px; margin-right:10px; cursor:pointer; position:relative; transition:all 0.2s ease;}
.checkbox-item input[type="checkbox"]:hover { background-color: rgba(0,105,107,0.1);}
.checkbox-item input[type="checkbox"]:checked { background-color:#00696B; border-color:#00696B;}
.checkbox-item input[type="checkbox"]:checked::after { content:"✓"; position:absolute; color:white; font-size:12px; font-weight:bold; left:50%; top:50%; transform:translate(-50%, -55%);}
.select-wrapper { position:relative; margin-top:5px;}
.select-wrapper select { appearance:none; width:100%; padding:12px 40px 12px 12px; border-radius:6px; border:1px solid #ccc; background-color:#f9f9f9; font-size:14px; cursor:pointer; transition:all 0.3s ease;}
.select-wrapper::after { content:"▼"; position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:12px; color:#00696B; pointer-events:none;}
.msg { margin-top:10px; text-align:center; color:red; }
.top-menu { display:flex; align-items:center; gap:15px; padding:10px 20px; background:#00696B; color:white; flex-wrap:wrap; margin-bottom:20px; }
.top-menu .menu-link { color:white; text-decoration:none; font-weight:bold; padding:6px 12px; border-radius:4px; transition:0.2s; }
.top-menu .menu-link:hover { background:#004d4f; }
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

<div class="box">
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

    <label>Roles de usuario:</label>
    <div class="select-wrapper">
        <select name="roles" required>
            <option value="">Seleccionar Rol</option>
            <option value="root">Root</option>
            <option value="administrator">Administrador</option>
            <option value="editor">Editor</option>
            <option value="author">Autor</option>
            <option value="contributor">Colaborador</option>
            <option value="subscriber">Suscriptor</option>
        </select>
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
let tiempoLimite = 1800000; // 30 min
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