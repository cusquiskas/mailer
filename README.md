<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Microservicio MAILER</title>
<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
    code { background: #f4f4f4; padding: 3px 6px; border-radius: 4px; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 6px; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    table, th, td { border: 1px solid #ccc; }
    th, td { padding: 8px; text-align: left; }
    h1, h2, h3 { color: #333; }
</style>
</head>
<body>

<h1>📬 Microservicio MAILER</h1>
<p>Servicio interno para el envío de correos electrónicos mediante PHPMailer, con registro automático en base de datos.</p>

<hr>

<h2>🧩 Descripción general</h2>
<p>
Este microservicio expone un único endpoint HTTP (<code>/correo.php</code>) que permite enviar correos electrónicos desde cualquier otro contenedor o desde el host.
El servicio:
</p>

<ul>
    <li>Recibe una petición JSON</li>
    <li>Envía el correo usando PHPMailer</li>
    <li>Registra el envío (o error) en la tabla <code>mail_log</code></li>
    <li>Devuelve una respuesta JSON simple</li>
</ul>

<p>Está diseñado para ser ligero, autónomo y fácil de integrar.</p>

<hr>

<h2>🚀 Endpoint disponible</h2>

<h3><code>POST /correo.php</code></h3>
<p>Envía un correo electrónico y registra el resultado.</p>

<h3>📥 Parámetros esperados (JSON)</h3>

<table>
<tr><th>Campo</th><th>Tipo</th><th>Obligatorio</th><th>Descripción</th></tr>
<tr><td>to</td><td>string</td><td>Sí</td><td>Dirección del destinatario</td></tr>
<tr><td>cc</td><td>string</td><td>No</td><td>Dirección en copia</td></tr>
<tr><td>bcc</td><td>string</td><td>No</td><td>Dirección en copia oculta</td></tr>
<tr><td>subject</td><td>string</td><td>No</td><td>Asunto del correo</td></tr>
<tr><td>body</td><td>string</td><td>No</td><td>Cuerpo del correo (HTML permitido)</td></tr>
<tr><td>app</td><td>string</td><td>No</td><td>Nombre de la aplicación que envía el correo</td></tr>
</table>

<hr>

<h2>📤 Ejemplos de uso</h2>

<h3>1. Desde otro contenedor (PHP)</h3>
<pre><code>
$ch = curl_init('http://serv-mailer/correo.php');

$data = [
    'to'      => 'usuario@dominio.com',
    'subject' => 'Prueba desde contenedor',
    'body'    => '&lt;h1&gt;Hola!&lt;/h1&gt;&lt;p&gt;Esto es una prueba.&lt;/p&gt;',
    'app'     => 'comunidad'
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
</code></pre>

<h3>2. Desde otro contenedor (curl en bash)</h3>
<pre><code>
curl -X POST http://serv-mailer/correo.php \
  -H "Content-Type: application/json" \
  -d '{
        "to": "usuario@dominio.com",
        "subject": "Prueba desde bash",
        "body": "&lt;p&gt;Correo enviado desde un contenedor.&lt;/p&gt;",
        "app": "script-cron"
      }'
</code></pre>

<h3>3. Desde el host (si el puerto está expuesto)</h3>
<pre><code>
curl -X POST http://localhost:9092/correo.php \
  -H "Content-Type: application/json" \
  -d '{
        "to": "usuario@dominio.com",
        "cc": "otro@dominio.com",
        "bcc": "oculto@dominio.com",
        "subject": "Prueba desde host",
        "body": "&lt;b&gt;Hola desde el host&lt;/b&gt;",
        "app": "host-test"
      }'
</code></pre>

<hr>

<h2>📦 Respuestas del servicio</h2>

<h3>✔️ Éxito</h3>
<pre><code>{
  "status": "ok"
}
</code></pre>

<h3>❌ Error</h3>
<pre><code>{
  "status": "error",
  "msg": "Descripción del error"
}
</code></pre>

<hr>

<h2>🗄️ Registro en base de datos</h2>

<p>Cada envío (correcto o fallido) se guarda en la tabla <code>mail_log</code>:</p>

<table>
<tr><th>Campo</th><th>Descripción</th></tr>
<tr><td>destinatario</td><td>Email destino</td></tr>
<tr><td>copia</td><td>CC</td></tr>
<tr><td>copia_oculta</td><td>BCC</td></tr>
<tr><td>asunto</td><td>Asunto enviado</td></tr>
<tr><td>cuerpo</td><td>Cuerpo del correo</td></tr>
<tr><td>adjuntos</td><td>(no implementado aún)</td></tr>
<tr><td>estado</td><td>OK o ERROR</td></tr>
<tr><td>mensaje_error</td><td>Error devuelto por PHPMailer</td></tr>
<tr><td>ip_origen</td><td>IP del cliente</td></tr>
<tr><td>aplicacion</td><td>Nombre de la app que llamó al servicio</td></tr>
</table>

<hr>

<h2>⚙️ Variables de entorno necesarias</h2>

<pre><code>
MAIL_HOST=smtp.dominio.com
MAIL_PORT=465
MAIL_USER=noreply@dominio.com
MAIL_PASS=*************
DB_HOST=serv-mariadb
DB_USER=mailer
DB_PASS=*********
DB_APLI=mailer
</code></pre>

<hr>

<h2>🧱 Estructura del microservicio</h2>

<pre><code>
correo.php
PHPMailer/
</code></pre>

<hr>

<h2>🧠 Notas finales</h2>

<ul>
    <li>PHPMailer lanza excepción si falta destinatario.</li>
    <li>El microservicio es interno, no requiere autenticación.</li>
    <li>La estructura permite añadir adjuntos fácilmente.</li>
    <li>Puede ampliarse a sistema de colas si se desea.</li>
</ul>

</body>
</html>
