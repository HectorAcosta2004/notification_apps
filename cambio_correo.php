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
   MENU
========================= */

$menu_vistas = [
    'Registro' => 'register.php',
    'Usuarios' => 'usuarios_admin.php',
    'Reportes' => 'reportes_mensajes.php',
    'Mensajes' => 'onesignal_panel.php',
    'Correo a Unión' => 'cambio_correo.php'
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
   ACTUALIZAR CORREO
========================= */

if (isset($_POST['update'])) {

    $id = intval($_POST['id']);
    $correo = trim($_POST['correo']);

    $stmt = $mysqli->prepare("
        UPDATE union_iasd 
        SET correo=? 
        WHERE id=?
    ");

    $stmt->bind_param("si", $correo, $id);
    $stmt->execute();

    header("Location: cambio_correo.php");
    exit;
}

/* =========================
   OBTENER UNIONES
========================= */

$result = $mysqli->query("
SELECT * FROM union_iasd
ORDER BY nombre ASC
");

if (!$result) {
    die("Error al obtener uniones: " . $mysqli->error);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <title>Correos por Unión</title>

    <style>
        body {
            font-family: Arial;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
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

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: auto;
        }

        .logout-btn:hover {
            background: #b02a37;
        }

        /* =========================
   CONTENIDO
========================= */

        .box {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 900px;
            margin: 30px auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #00696B;
            color: white;
            padding: 10px;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }

        input {
            width: 100%;
            padding: 6px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        button {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-update {
            background: #00696B;
            color: white;
        }

        .btn-update:hover {
            background: #004d4f;
        }
    </style>

</head>

<body>

    <div class="top-menu">

        <?php foreach ($menu_vistas as $nombre => $url): ?>
            <a href="<?= $url ?>" class="menu-link"><?= $nombre ?></a>
        <?php endforeach; ?>

        <form  method="POST" style="margin-left:auto;">
            <button type="submit" class="logout-btn">Cerrar sesión</button>
        </form>

    </div>

    <div class="box">

        <h2>Correos por Unión IASD</h2>

        <table>

            <tr>
                <th>Unión</th>
                <th>Correo</th>
                <th>Acción</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>

                    <form method="POST">

                        <td>
                            <?= htmlspecialchars($row['nombre']) ?>
                        </td>

                        <td>
                            <input
                                type="email"
                                name="correo"
                                value="<?= htmlspecialchars($row['correo'] ?? '') ?>"
                                required>
                        </td>

                        <td>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $row['id'] ?>">

                            <button
                                type="submit"
                                name="update"
                                class="btn-update"
                                onclick="return confirm('¿Actualizar correo de esta unión?');">

                                Actualizar

                            </button>
                        </td>

                    </form>

                </tr>

            <?php endwhile; ?>

        </table>

    </div>

</body>

</html>