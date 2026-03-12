<?php
session_start();
require_once "db.php";

/* =========================
   VALIDAR SESIÓN Y ROL
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login_notifications.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$rolesPermitidos = ['root','administrator'];
if (!in_array($role, $rolesPermitidos)) {
    session_destroy();
    header("Location: login_notifications.php");
    exit;
}

/* =========================
   CONTROL DE INACTIVIDAD
========================= */
$tiempo_limite = 180;
if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $tiempo_limite)) {
    session_destroy();
    header("Location: login_notifications.php?expirado=1");
    exit;
}
$_SESSION['ultimo_acceso'] = time();

/* =========================
   DATOS DE USUARIO
========================= */
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$busqueda = $_GET['buscar'] ?? '';

/* =========================
   MENU DINÁMICO POR ROL
========================= */
$menu_vistas = [];
switch ($role) {
    case 'root':
        $menu_vistas = [
            'Registro' => 'register.php',
            'Usuarios' => 'usuarios_admin.php',
            'Reportes' => 'reportes_mensajes.php',
            'Mensajes' => 'onesignal_panel.php',
            'Correo a Unión' => 'cambio_correo.php',
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

        break;
}

/* =========================
   OBTENER APPS DEL USUARIO
========================= */
$apps_usuario = [];
$res_apps = $mysqli->query("SELECT app_id FROM user_apps WHERE user_id = $user_id");
while ($row = $res_apps->fetch_assoc()) {
    $apps_usuario[] = $row['app_id'];
}

/* =========================
   QUERY PRINCIPAL
========================= */
$query = "
SELECT r.titulo,
       r.descripcion,
       r.fecha_envio,
       a.nombre AS app_nombre,
       u.name AS autor_nombre,
       u.email AS autor_email,
       u.roles AS autor_role,
       i.nombre AS institucion_nombre
FROM reportes_mensajes r
INNER JOIN apps a ON r.app_id = a.id
INNER JOIN users u ON r.user_id = u.id
INNER JOIN instituciones i ON u.institucion_id = i.id
";

$condiciones = [];
$parametros = [];
$tipos = "";

/* =========================
   CONTROL POR ROL
========================= */
if ($role === 'administrator' || $role === 'author') {
    if (!empty($apps_usuario)) {
        $placeholders = implode(',', array_fill(0, count($apps_usuario), '?'));
        $condiciones[] = "r.app_id IN ($placeholders)";
        $tipos .= str_repeat("i", count($apps_usuario));
        $parametros = array_merge($parametros, $apps_usuario);
    } else {
        $condiciones[] = "1=0"; // si no tiene apps asignadas, no mostrar nada
    }
}

/* =========================
   BUSCADOR
========================= */
if (!empty($busqueda)) {
    $condiciones[] = "(
        a.nombre LIKE ?
        OR u.name LIKE ?
        OR u.email LIKE ?
        OR r.titulo LIKE ?
        OR i.nombre LIKE ?
        OR DATE(r.fecha_envio) LIKE ?
    )";

    for ($i = 0; $i < 6; $i++) {
        $parametros[] = "%$busqueda%";
        $tipos .= "s";
    }
}

if (count($condiciones) > 0) {
    $query .= " WHERE " . implode(" AND ", $condiciones);
}

$query .= " ORDER BY r.fecha_envio DESC";

$stmt = $mysqli->prepare($query);
if (!$stmt) die("Error en prepare: " . $mysqli->error);

if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();
$result = $stmt->get_result();
$mensajes = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Mensajes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        /* =========================
   MENU SUPERIOR
========================= */
        .top-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 20px;
            background: #00696B;
            color: white;
            flex-wrap: wrap;
        }

        .top-menu .menu-link {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 6px 12px;
            border-radius: 4px;
            transition: 0.2s;
        }

        .top-menu .menu-link:hover {
            background: #004d4f;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .search-bar {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .search-bar input {
            width: 60%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px 0 0 5px;
            outline: none;
        }

        .search-bar button {
            padding: 10px 15px;
            border-radius: 0 5px 5px 0;
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #00696B;
            color: white;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .no-data {
            text-align: center;
            margin-top: 20px;
            color: #888;
        }
    </style>
</head>

<body>
    <div class="top-menu">
        <?php foreach ($menu_vistas as $nombre => $url): ?>
            <a href="<?= $url ?>" class="menu-link"><?= $nombre ?></a>
        <?php endforeach; ?>
        <form action="logout.php" method="POST" style="display:inline-block; margin-left:auto;">
            <button type="submit" class="btn-logout">Cerrar sesión</button>
        </form>
    </div>

    <div class="container">
        <h2>Reporte de Notificaciones</h2>

        <!-- BUSCADOR -->
        <form method="GET" class="search-bar">
            <input type="text" name="buscar"
                placeholder="Buscar"
                value="<?= htmlspecialchars($busqueda) ?>">
            <button type="submit">Buscar</button>
        </form>

        <?php if (count($mensajes) > 0): ?>
            <table>
                <tr>
                    <th>App</th>
                    <th>Institución</th>
                    <th>Correo</th>
                    <th>Título</th>
                    <th>Mensaje</th>
                    <th>Fecha</th>
                </tr>
                <?php foreach ($mensajes as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['app_nombre']) ?></td>
                        <td><?= htmlspecialchars($m['institucion_nombre']) ?></td>
                        <td><?= htmlspecialchars($m['autor_email']) ?></td>
                        <td><?= htmlspecialchars($m['titulo']) ?></td>
                        <td><?= htmlspecialchars($m['descripcion']) ?></td>
                        <td><?= htmlspecialchars($m['fecha_envio']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="no-data">No se encontraron resultados.</div>
        <?php endif; ?>

    </div>
</body>

</html>