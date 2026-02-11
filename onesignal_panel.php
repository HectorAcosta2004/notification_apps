<?php
session_start();

/* =========================
   CONFIG MYSQL
========================= */
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "Reavivados";

/* =========================
   PETICIÓN AJAX (LOGIN)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'login') {
    header("Content-Type: application/json; charset=UTF-8");

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!$email || !$password) {
        echo json_encode(["status" => "error", "message" => "Campos obligatorios"]);
        exit;
    }

    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        echo json_encode(["status" => "error", "message" => "Error de conexión MySQL"]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id, name, password, app FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
        exit;
    }

    $user = $res->fetch_assoc();
    if (!password_verify($password, $user["password"])) {
        echo json_encode(["status" => "error", "message" => "Contraseña incorrecta"]);
        exit;
    }

    $_SESSION["user_id"] = $user["id"];
    $_SESSION["app"] = $user["app"]; // rpsp o radio

    echo json_encode([
        "status" => "ok",
        "user" => [
            "id" => $user["id"],
            "email" => $email,
            "name" => $user["name"],
            "app" => $user["app"]
        ]
    ]);
    exit;
}

/* =========================
   PANEL DE NOTIFICACIONES
========================= */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['app'])) {
    $showLogin = true;
} else {
    $showLogin = false;

    // Config OneSignal
    $configs = [
        'app1' => [
            'app_id' => '85a1d19b-6490-4c8a-9a7e-b051c0d4de49',
            'api_key' => 'os_v2_app_qwq5dg3esbgivgt6wbi4bvg6jgus5ixyeueelmnrt7h2mp6nf5tsfte2apuoliuq2i3nmyqfkqt5sucnj6feqdasan7lwxmi3omgiuq'
        ],
        'app2' => [
            'app_id' => '58318179-63da-4dc6-8ab9-cf054484b51c',
            'api_key' => 'os_v2_app_layyc6ld3jg4ncvzz4cujbfvdrbg56fzotcevdevqv4fw5cvj7kzhi5hmsq5llumnrv6n4hwwckaudxnq7fypyw2zrvxphfpkkgksqi'
        ]
    ];

    $app_map = ['rpsp' => 'app1', 'radio' => 'app2'];
    $user_app = $_SESSION['app'];
    $app_key = $app_map[$user_app] ?? null;
    $appConfig = $configs[$app_key] ?? null;

    if (!$appConfig) die("App no válida en sesión");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
        header('Content-Type: application/json; charset=utf-8');

        $title = trim($_POST['titulo'] ?? '');
        $message = trim($_POST['mensaje'] ?? '');
        $fechaEnvio = $_POST['fecha_envio'] ?? '';

        if ($title === '' || $message === '') {
            echo json_encode(['error' => true, 'message' => 'Campos obligatorios']);
            exit;
        }

        $data = [
            "app_id" => $appConfig['app_id'],
            "included_segments" => ["All"],
            "headings" => ["en"=>$title,"es"=>$title],
            "contents" => ["en"=>$message,"es"=>$message]
        ];

        if (!empty($fechaEnvio)) {
            try {
                $date = new DateTime($fechaEnvio);
                $data['send_after'] = $date->format('Y-m-d H:i:s O');
            } catch (Exception $e) {
                echo json_encode(['error'=>true,'message'=>'Formato de fecha inválido']);
                exit;
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://onesignal.com/api/v1/notifications",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json; charset=utf-8",
                "Authorization: Basic ".$appConfig['api_key']
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => false,
CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response===false || ($http_code !== 200 && $http_code !== 201)) {
            echo json_encode(['error'=>true,'message'=>'Error al enviar notificación','curl_error'=>$curl_error,'http_code'=>$http_code]);
            exit;
        }

        $json = json_decode($response,true);
        if(isset($json['id'])){
            echo json_encode(['id'=>$json['id'],'recipients'=>$json['recipients']??1]);
            exit;
        }

        echo json_encode(['error'=>true,'message'=>'Respuesta inesperada de OneSignal']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $showLogin ? "Login" : "Enviar Notificación" ?></title>
<style>
body { font-family: Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; display:flex; justify-content:center; align-items:center; height:100vh; }
.container { width:350px; padding:25px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,.2); background:<?= $showLogin?'#fff':'#041366' ?>; color:<?= $showLogin?'#000':'#fff' ?>; }
h2 { text-align:center; margin-bottom:20px; }
input,textarea,button,select { width:100%; margin-top:10px; padding:10px; border-radius:6px; border:1px solid <?= $showLogin?'#ccc':'#fff' ?>; color:<?= $showLogin?'#000':'#fff' ?>; background:<?= $showLogin?'#fff':'#1a194d' ?>; }
button { background:#007bff; color:#fff; font-weight:bold; border:none; cursor:pointer; }
button:hover { background:#0056b3; }
.error { background:#ffdddd; padding:10px; margin-top:10px; display:none; color:red; }
.success { background:#ddffdd; padding:10px; margin-top:10px; display:none; color:green; }
.logout-btn { background:#dc3545; width:auto; padding:8px 12px; font-size:14px; margin-bottom:10px; cursor:pointer; }
small { color:<?= $showLogin?'#000':'#ffcccc' ?>; }
</style>
</head>
<body>
<div class="container">

<?php if($showLogin): ?>
<h2>Iniciar Sesión</h2>
<input type="email" id="email" placeholder="Correo">
<input type="password" id="password" placeholder="Contraseña">
<button onclick="login()">Entrar</button>
<p id="msg" class="error"></p>

<script>
async function login(){
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const msg = document.getElementById("msg");
    msg.style.display="none";

    try{
        const res = await fetch("",{
            method:"POST",
            headers:{"Content-Type":"application/x-www-form-urlencoded"},
            body:new URLSearchParams({action:"login",email,password})
        });
        const data = await res.json();
        if(data.status!=="ok"){
            msg.innerText = data.message;
            msg.style.display="block";
            return;
        }
        msg.style.color="green";
        msg.innerText="Login correcto";
        msg.style.display="block";
        setTimeout(()=>{ location.reload(); },1000);
    }catch(e){
        msg.innerText="Error inesperado";
        msg.style.display="block";
    }
}
</script>

<?php else: ?>
<form action="logout.php" method="POST" style="text-align:right;"><button type="submit" class="logout-btn">Cerrar sesión</button></form>
<h2>Enviar Notificación</h2>
<form id="formNoti" method="POST">
<label>Aplicación asignada</label>
<select name="app" disabled><option><?= htmlspecialchars($user_app) ?></option></select>
<label>Título</label><input type="text" name="titulo" required>
<label>Mensaje</label><textarea name="mensaje" required></textarea>
<label>Programar envío</label><input type="datetime-local" name="fecha_envio"><small>Deja vacío para enviar inmediatamente.</small>
<button type="submit">Enviar Notificación</button>
</form>

<div id="mensajeError" class="error"></div>
<div id="mensajeExito" class="success">✅ Notificación enviada con éxito.</div>

<script>
document.getElementById("formNoti").addEventListener("submit",function(e){
    e.preventDefault();
    const form = new FormData(this);
    form.append('action','send');
    fetch("",{method:"POST",body:form})
    .then(res=>res.json())
    .then(data=>{
        if(data.id||(data.recipients&&data.recipients>0)){
            document.getElementById("mensajeExito").style.display="block";
            document.getElementById("mensajeError").style.display="none";
            this.reset();
            setTimeout(()=>{document.getElementById("mensajeExito").style.display="none";},3000);
        }else{
            document.getElementById("mensajeError").innerText=data.message||"❌ Error al enviar";
            document.getElementById("mensajeError").style.display="block";
        }
    })
    .catch(()=>{document.getElementById("mensajeError").innerText="❌ Error de conexión";document.getElementById("mensajeError").style.display="block";});
});
</script>
<?php endif; ?>

</div>
</body>
</html>
