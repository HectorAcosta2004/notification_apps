<?php
session_start();
require_once 'db.php';

/* =========================
   VALIDAR SESIÓN Y ROL
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login_notifications.php");
    exit;
}

$role = $_SESSION['role'];

if ($role !== 'root') {
    header("Location: login_notifications.php?acceso_denegado=1");
    exit;
}

/* =========================
   MENU DINÁMICO POR ROL
========================= */
$menu_vistas = [
    'Registro' => 'register.php',
    'Usuarios' => 'usuarios_admin.php',
    'Reportes' => 'reportes_mensajes.php',
    'Mensajes' => 'onesignal_panel.php',
];

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
   OBTENER INSTITUCIONES Y APPS
========================= */
$instituciones_res = $mysqli->query("SELECT * FROM instituciones ORDER BY nombre ASC");
$instituciones = $instituciones_res ? $instituciones_res->fetch_all(MYSQLI_ASSOC) : [];

$apps_res = $mysqli->query("SELECT * FROM apps ORDER BY nombre ASC");
$apps = $apps_res ? $apps_res->fetch_all(MYSQLI_ASSOC) : [];

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
    $institucion_id = intval($_POST['institucion_id']);
    $apps_post = $_POST['apps'] ?? [];

    // Actualizar tabla users
    $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, roles=?, institucion_id=? WHERE id=?");
    $stmt->bind_param("sssii", $name, $email, $roles, $institucion_id, $id);
    $stmt->execute();

    // Actualizar tabla user_apps
    $mysqli->query("DELETE FROM user_apps WHERE user_id=$id");
    if (!empty($apps_post)) {
        $stmt_apps = $mysqli->prepare("INSERT INTO user_apps(user_id, app_id) VALUES (?, ?)");
        foreach ($apps_post as $app_id) {
            $app_id = (int)$app_id;
            $stmt_apps->bind_param("ii", $id, $app_id);
            $stmt_apps->execute();
        }
    }

    header("Location: usuarios_admin.php");
    exit;
}

/* =========================
   OBTENER USUARIOS
========================= */
$result = $mysqli->query("SELECT * FROM users ORDER BY id DESC");
if (!$result) die("Error al obtener usuarios: " . $mysqli->error);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Usuarios</title>
<style>
body { font-family: Arial; background:#f4f4f4; margin:0; padding:0; }
.top-menu { display:flex; align-items:center; gap:15px; padding:10px 20px; background:#00696B; color:white; flex-wrap:wrap;}
.top-menu .menu-link { color:white; text-decoration:none; font-weight:bold; padding:6px 12px; border-radius:4px; transition:0.2s;}
.top-menu .menu-link:hover { background:#004d4f; }
.logout-btn { background:#dc3545; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; margin-left:auto;}
.logout-btn:hover { background:#b02a37;}
.box { background:#fff; padding:30px; border-radius:10px; width:95%; max-width:1100px; margin:20px auto; box-shadow:0 8px 20px rgba(0,0,0,.12);}
h2 { text-align:center; margin-bottom:20px;}
.top-bar { display:flex; justify-content:space-between; margin-bottom:20px;}
button { padding:8px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; transition:0.3s;}
.btn-green { background:#28a745; color:white; } .btn-green:hover { background:#1e7e34; }
.btn-update { background:#00696B; color:white; } .btn-update:hover { background:#004d4f; }
.btn-delete { background:#dc3545; color:white; } .btn-delete:hover { background:#b02a37; }
table { width:100%; border-collapse: collapse; margin-top:10px;}
th { background:#00696B; color:white; padding:10px;}
td { padding:8px; border-bottom:1px solid #ddd; text-align:center;}
input, select { width:100%; padding:6px; border-radius:6px; border:1px solid #ccc;}
input:focus, select:focus { border-color:#00696B; outline:none; box-shadow:0 0 0 2px rgba(0,105,107,0.15);}
.actions { display:flex; gap:5px; justify-content:center;}
.checkbox-group { margin-top:5px; }
.checkbox-item { display:flex; align-items:center; margin-top:6px; cursor:pointer; font-size:13px; position:relative; }
.checkbox-item input[type="checkbox"] { appearance:none; width:18px; height:18px; border:2px solid #00696B; border-radius:4px; margin-right:8px; cursor:pointer; position:relative; transition: all 0.2s ease;}
.checkbox-item input[type="checkbox"]:hover { background-color: rgba(0,105,107,0.1);}
.checkbox-item input[type="checkbox"]:checked { background-color:#00696B; border-color:#00696B;}
.checkbox-item input[type="checkbox"]:checked::after { content:"✓"; position:absolute; color:white; font-size:12px; font-weight:bold; left:50%; top:50%; transform:translate(-50%, -55%);}
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

<?php while($row = $result->fetch_assoc()): ?>
<?php
// Obtener apps del usuario actual
$apps_usuario = [];
$app_query = $mysqli->query("SELECT app_id FROM user_apps WHERE user_id=".$row['id']);
while($ua = $app_query->fetch_assoc()) $apps_usuario[] = $ua['app_id'];
?>
<tr>
<form method="POST">
    <td><input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>"></td>
    <td><input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>"></td>
    <td>
        <select name="roles">
            <?php
            $roles_opciones = ['root'=>'Root','administrator'=>'Administrador','editor'=>'Editor','author'=>'Autor','contributor'=>'Colaborador','subscriber'=>'Suscriptor'];
            foreach($roles_opciones as $r_val=>$r_nombre):
            ?>
            <option value="<?= $r_val ?>" <?= $row['roles']==$r_val?'selected':'' ?>><?= $r_nombre ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td>
        <select name="institucion_id">
            <?php foreach($instituciones as $inst): ?>
                <option value="<?= $inst['id'] ?>" <?= $row['institucion_id']==$inst['id']?'selected':'' ?>>
                    <?= htmlspecialchars($inst['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>
    <td>
        <div class="checkbox-group">
            <?php foreach($apps as $app): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="apps[]" value="<?= $app['id'] ?>" <?= in_array($app['id'],$apps_usuario)?'checked':'' ?>>
                    <?= htmlspecialchars($app['nombre']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </td>
    <td>
        <div class="actions">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="submit" name="update" class="btn-update" onclick="return confirm('¿Actualizar usuario?');">Actualizar</button>
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
let tiempoLimite = 1800000;
let temporizador;
function reiniciar() {
    clearTimeout(temporizador);
    temporizador = setTimeout(()=>{ window.location.href="logout.php"; }, tiempoLimite);
}
window.onload = reiniciar;
document.onmousemove = reiniciar;
document.onkeypress = reiniciar;
document.onclick = reiniciar;
document.onscroll = reiniciar;
</script>

</body>
</html>