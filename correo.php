<?php
    header('Content-Type: application/json');

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error !== null) {
            http_response_code(500);
            echo json_encode([
                'status' => 'fatal',
                'msg'    => $error['message'],
                'file'   => basename($error['file']),
                'line'   => $error['line']
            ]);
        }
    });
    
    Class Correo
    {
        
        
        private $mail   = null;
        public  $error  = null;
        public  $asunto = null;
        public  $cuerpo = null;

        public function __construct()
        {
            //Import PHPMailer classes into the global namespace
            //These must be at the top of your script, not inside a function
            require 'PHPMailer/src/Exception.php';
            require 'PHPMailer/src/PHPMailer.php';
            require 'PHPMailer/src/SMTP.php';
            
            //Create an instance; passing `true` enables exceptions
            $this->mail = new PHPMailer(true);
            
            $this->mail->isSMTP();
            $this->mail->Host       = getenv('MAIL_HOST');
            $this->mail->Port       = getenv('MAIL_PORT');
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = getenv('MAIL_USER');
            $this->mail->Password   = getenv('MAIL_PASS');
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mail->CharSet    = PHPMailer::CHARSET_UTF8;

            $this->mail->setLanguage('es');
            $this->mail->setFrom(getenv('MAIL_USER'), 'Noreply');
            $this->mail->isHTML(true);                                  //Set email format to HTML
        }
        public function destinatario($correo, $nombre=null) {
            $this->mail->addAddress($correo, $nombre);
        }

        public function esHTML($bol) {
            $this->mail->isHTML($bol);                                  //Set email format to HTML
        }

        public function verbose() {
            $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
        }

        public function destinatarioCC($correo, $nombre) {
            $this->mail->addCC($correo, $nombre);
        }

        public function destinatarioCO($correo, $nombre) {
            $this->mail->addBCC($correo, $nombre);
        }

        public function adjunto ($origen, $nombre) {
            $this->mail->addAttachment($origen, $nombre);
        }

        public function mandaMail($asunto = '', $cuerpo = '') {
            try {
                if (isset($asunto) && $asunto != "") $this->asunto = $asunto;
                if (isset($cuerpo) && $cuerpo != "") $this->cuerpo = $cuerpo;
                $this->mail->Subject = $this->asunto;
                $this->mail->Body    = $this->cuerpo;
            
                $this->mail->send();
                return true;
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }
        
        public function __destruct() {
            unset($this->mail);
        }
    }


    class Database {
        private $pdo;
        private $config;
        
        public function __construct() {
            $this->config = [ 'host' => getenv('DB_HOST'), 'user' => getenv('DB_USER'), 'pass' => getenv('DB_PASS'), 'name' => getenv('DB_APLI') ];
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['name']};charset=utf8mb4";

            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }

        public function insertLog($data) {
            $sql = "INSERT INTO mail_log 
                (destinatario, copia, copia_oculta, asunto, cuerpo, adjuntos, estado, mensaje_error, ip_origen, aplicacion)
                VALUES 
                (:destinatario, :copia, :copia_oculta, :asunto, :cuerpo, :adjuntos, :estado, :mensaje_error, :ip_origen, :aplicacion)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
        }
    } 



    $db     = new Database();
    $correo = new Correo  ();

    try {
        $input_raw = file_get_contents('php://input'); 
        $input = json_decode($input_raw, true);

        $to     = $input['to']      ?? null;
        $cc     = $input['cc']      ?? null;
        $bcc    = $input['bcc']     ?? null;
        $asunto = $input['subject'] ?? '';
        $cuerpo = $input['body']    ?? '';
        $app    = $input['app']     ?? 'no informada';

        if (!$to) { throw new Exception("Campo 'to' obligatorio y no recibido"); }
    
        $correo->destinatario($to);
        if ($cc)  $correo->destinatarioCC($cc);
        if ($bcc) $correo->destinatarioCO($bcc);
        
        if (!$correo->mandaMail($asunto, $cuerpo)) { throw new Exception($correo->error); }

        $db->insertLog([
            'destinatario' => $to,
            'copia' => $cc,
            'copia_oculta' => $bcc,
            'asunto' => $asunto,
            'cuerpo' => $cuerpo,
            'adjuntos' => null,
            'estado' => 'OK',
            'mensaje_error' => null,
            'ip_origen' => $_SERVER['REMOTE_ADDR'] ?? null,
            'aplicacion' => $app
        ]);

        http_response_code(200);
        echo json_encode(['status' => 'ok']);

    } catch (Exception $e) {

        try {    
            $db->insertLog([
                    'destinatario' => $to,
                    'copia' => $cc,
                    'copia_oculta' => $bcc,
                    'asunto' => $asunto,
                    'cuerpo' => $cuerpo,
                    'adjuntos' => null,
                    'estado' => 'ERROR',
                    'mensaje_error' => $e->getMessage(),
                    'ip_origen' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'aplicacion' => $app
                ]);
        } catch (Exception $ignored) { 
            // Si falla el log, no rompemos la respuesta 
        }
        http_response_code(400);
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }


    
?>