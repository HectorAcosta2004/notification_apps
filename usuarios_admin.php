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
   OBTENER USUARIOS
========================= */

$result = $mysqli->query("
SELECT users.*, union_iasd.nombre AS union_nombre
FROM users
LEFT JOIN union_iasd ON users.union_id = union_iasd.id
ORDER BY users.id DESC
");

if (!$result) die("Error usuarios: " . $mysqli->error);

/* =========================
   OBTENER INSTITUCIONES
========================= */
$instituciones_res = $mysqli->query("SELECT * FROM instituciones ORDER BY nombre ASC");
$instituciones = $instituciones_res->fetch_all(MYSQLI_ASSOC);

/* =========================
   OBTENER UNIONES
========================= */
$uniones_res = $mysqli->query("SELECT * FROM union_iasd ORDER BY nombre ASC");
$uniones = $uniones_res->fetch_all(MYSQLI_ASSOC);

/* =========================
   OBTENER APPS
========================= */

$apps_res = $mysqli->query("SELECT * FROM apps ORDER BY nombre ASC");
$apps = $apps_res->fetch_all(MYSQLI_ASSOC);

/* =========================
   ELIMINAR USUARIO
========================= */

if (isset($_GET['delete'])) {

   $id = intval($_GET['delete']);

   $stmt = $mysqli->prepare("DELETE FROM users WHERE id=?");
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
   $union_id = intval($_POST['union_id']);

   $apps_post = $_POST['apps'] ?? [];
   $password = trim($_POST['password'] ?? '');

   if (!empty($password)) {

      $password_hash = password_hash($password, PASSWORD_DEFAULT);

      $stmt = $mysqli->prepare("
        UPDATE users 
        SET name=?, email=?, roles=?, union_id=?, institucion_id=?, password=? 
        WHERE id=?
        ");

      $stmt->bind_param(
         "sssissi",
         $name,
         $email,
         $roles,
         $union_id,
         $institucion_id,
         $password_hash,
         $id
      );
   } else {

      $stmt = $mysqli->prepare("
        UPDATE users 
        SET name=?, email=?, roles=?, union_id=?, institucion_id=? 
        WHERE id=?
        ");

      $stmt->bind_param(
         "sssiii",
         $name,
         $email,
         $roles,
         $union_id,
         $institucion_id,
         $id
      );
   }

   $stmt->execute();

   /* actualizar apps */

   $mysqli->query("DELETE FROM user_apps WHERE user_id=$id");

   if (!empty($apps_post)) {

      $stmt_apps = $mysqli->prepare("
        INSERT INTO user_apps(user_id, app_id) VALUES (?,?)
        ");

      foreach ($apps_post as $app_id) {

         $app_id = (int)$app_id;

         $stmt_apps->bind_param("ii", $id, $app_id);
         $stmt_apps->execute();
      }
   }

   header("Location: usuarios_admin.php");
   exit;
}
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
         margin: 0;
         padding: 0;
      }

      .top-menu {
         display: flex;
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

      .box {
         background: #fff;
         padding: 30px;
         border-radius: 10px;
         width: 95%;
         max-width: 1100px;
         margin: 20px auto;
         box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
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
         padding: 8px;
         border-bottom: 1px solid #ddd;
         text-align: center;
      }

      input,
      select {
         width: 100%;
         padding: 6px;
         border-radius: 6px;
         border: 1px solid #ccc;
      }

      .actions {
         display: flex;
         gap: 5px;
         justify-content: center;
      }

      .btn-update {
         background: #00696B;
         color: white;
         border-radius: 5px;
      }

      .btn-delete {
         background: #dc3545;
         color: white;
         border-radius: 5px;
      }

      .checkbox-item {
         display: flex;
         align-items: center;
         font-size: 13px;
      }
   </style>
</head>

<body>

   <div class="top-menu">

      <?php foreach ($menu_vistas as $nombre => $url): ?>
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
            <th>Unión</th>
            <th>Nueva Contraseña</th>
            <th>Apps</th>
            <th>Acciones</th>
         </tr>

         <?php while ($row = $result->fetch_assoc()): ?>

            <?php
            $apps_usuario = [];

            $app_query = $mysqli->query(
               "SELECT app_id FROM user_apps 
                     WHERE user_id=" . $row['id']
            );

            while ($ua = $app_query->fetch_assoc()) {
               $apps_usuario[] = $ua['app_id'];
            }
            ?>
            <tr>
               <form method="POST">
                  <td>
                     <input type="text" name="name"
                        value="<?= htmlspecialchars($row['name']) ?>">
                  </td>

                  <td>
                     <input type="email" name="email"
                        value="<?= htmlspecialchars($row['email']) ?>">
                  </td>

                  <td>
                     <select name="roles">
                        <?php
                        $roles_opciones = [
                           'root' => 'Root',
                           'administrator' => 'Administrador',
                           'editor' => 'Editor',
                           'author' => 'Autor',
                           'contributor' => 'Colaborador',
                           'subscriber' => 'Suscriptor'
                        ];

                        foreach ($roles_opciones as $r_val => $r_nombre):
                        ?>
                           <option value="<?= $r_val ?>"
                              <?= $row['roles'] == $r_val ? 'selected' : '' ?>>
                              <?= $r_nombre ?>
                           </option>
                        <?php endforeach; ?>
                     </select>
                  </td>
                  <td>
                     <select name="institucion_id">
                        <?php foreach ($instituciones as $inst): ?>
                           <option value="<?= $inst['id'] ?>"
                              <?= $row['institucion_id'] == $inst['id'] ? 'selected' : '' ?>>
                              <?= htmlspecialchars($inst['nombre']) ?>
                           </option>
                        <?php endforeach; ?>
                     </select>
                  </td>
                  <td>
                     <select name="union_id">
                        <?php foreach ($uniones as $u): ?>
                           <option value="<?= $u['id'] ?>"
                              <?= $row['union_id'] == $u['id'] ? 'selected' : '' ?>>
                              <?= htmlspecialchars($u['nombre']) ?>
                           </option>
                        <?php endforeach; ?>
                     </select>
                  </td>
                  <td>
                     <input type="password" name="password"
                        placeholder="Nueva contraseña">
                  </td>
                  <td>
                     <?php foreach ($apps as $app): ?>
                        <label class="checkbox-item">
                           <input type="checkbox"
                              name="apps[]"
                              value="<?= $app['id'] ?>"
                              <?= in_array($app['id'], $apps_usuario) ? 'checked' : '' ?>>
                           <?= htmlspecialchars($app['nombre']) ?>
                        </label>
                     <?php endforeach; ?>
                  </td>
                  <td>
                     <div class="actions">

                        <input type="hidden" name="id"
                           value="<?= $row['id'] ?>">

                        <button type="submit"
                           name="update"
                           class="btn-update"
                           onclick="return confirm('¿Deseas actualizar este usuario?')">
                           Actualizar
                        </button>

                        <a href="?delete=<?= $row['id'] ?>"
                           onclick="return confirm('¿Eliminar usuario?')">

                           <button type="button"
                              class="btn-delete">
                              Eliminar
                           </button>

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