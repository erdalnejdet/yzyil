<?php
/**
 * Pendik Yüzyıl Hastanesi - Lead Formu SMTP İletim Betiği
 * 
 * Sunucu: mail.pendikyuzyilhastanesi.com:587 (STARTTLS)
 * Kullanıcı: info@pendikyuzyilhastanesi.com
 */

// Hata raporlama ve güvenlik
error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Istanbul');

// CORS ve JSON Başlıkları
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Girdi Verisini Al (JSON veya Form POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}

// Honeypot spam kontrolü (website alanı gizli bot tuzağıdır)
if (!empty($data['website'])) {
    // Botlara başarılı gibi dön
    echo json_encode(['success' => true, 'message' => 'Talebiniz alındı.']);
    exit;
}

// Alanları ayıkla ve temizle
function sanitize_string($val) {
    return trim(htmlspecialchars(strip_tags((string)$val), ENT_QUOTES, 'UTF-8'));
}

$name       = sanitize_string($data['name'] ?? '');
$phone      = sanitize_string($data['phone'] ?? '');
$message    = sanitize_string($data['message'] ?? '');
$source     = sanitize_string($data['source'] ?? 'Genel Form');
$pageUrl    = filter_var($data['url'] ?? '', FILTER_SANITIZE_URL);
$ipAddress  = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
$dateStr    = date('d.m.Y H:i:s');

// UTM Parametreleri
$utmSource   = sanitize_string($data['utm_source'] ?? '');
$utmMedium   = sanitize_string($data['utm_medium'] ?? '');
$utmCampaign = sanitize_string($data['utm_campaign'] ?? '');

// Doğrulama
if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ad ve telefon alanları zorunludur.']);
    exit;
}

// E-posta Ayarları
$smtpConfig = [
    'host'     => 'mail.pendikyuzyilhastanesi.com',
    'port'     => 587,
    'username' => 'info@pendikyuzyilhastanesi.com',
    'password' => 'Pendik1923!',
    'fromName' => 'Pendik Yüzyıl Hastanesi Web',
    'toEmail'  => 'erdalnejdet1@gmail.com'
];

// E-posta Konusu
$subject = "Yeni Randevu / Bilgi Talebi: " . $name;

// HTML E-posta Şablonu
$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; }
    .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
    .header { background: linear-gradient(135deg, #1b1e24 0%, #d70827 100%); color: #ffffff; padding: 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: 0.5px; }
    .header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.85; }
    .content { padding: 28px; }
    .row { display: flex; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .label { width: 140px; font-weight: 600; color: #64748b; font-size: 14px; }
    .value { flex: 1; color: #0f172a; font-size: 15px; font-weight: 500; }
    .phone-badge { display: inline-block; background: #fdebec; color: #d70827; padding: 4px 12px; border-radius: 6px; font-weight: 700; text-decoration: none; font-size: 16px; }
    .message-box { background: #f8fafc; border-left: 4px solid #d70827; padding: 14px; margin-top: 8px; border-radius: 4px; font-style: italic; color: #334155; }
    .footer { background: #f8fafc; padding: 16px 28px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    .badge { display: inline-block; background: #fdebec; color: #aa041e; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>Yeni İletişim & Randevu Talebi</h1>
      <p>Pendik Yüzyıl Hastanesi Web Sitesi</p>
    </div>
    <div class="content">
      <table style="width: 100%; border-collapse: collapse;">
        <tr>
          <td style="padding: 10px 0; width: 130px; font-weight: bold; color: #64748b; font-size: 14px;">Ad Soyad:</td>
          <td style="padding: 10px 0; font-size: 16px; font-weight: 600; color: #0f172a;">{$name}</td>
        </tr>
        <tr>
          <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px;">Telefon:</td>
          <td style="padding: 10px 0;">
            <a href="tel:{$phone}" style="display: inline-block; background: #eff6ff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; font-weight: bold; text-decoration: none; font-size: 16px;">
              {$phone}
            </a>
          </td>
        </tr>
        <tr>
          <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px;">Form Konumu:</td>
          <td style="padding: 10px 0; font-size: 14px; color: #334155;">
            <span style="background: #e0e7ff; color: #3730a3; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;">{$source}</span>
          </td>
        </tr>
        <tr>
          <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px;">Tarih & Saat:</td>
          <td style="padding: 10px 0; font-size: 14px; color: #334155;">{$dateStr}</td>
        </tr>
HTML;

if (!empty($message)) {
    $htmlBody .= <<<HTML
        <tr>
          <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px; vertical-align: top;">Kullanıcı Notu:</td>
          <td style="padding: 10px 0;">
            <div style="background: #f8fafc; border-left: 4px solid #3b82f6; padding: 12px; border-radius: 4px; color: #334155; font-size: 14px;">
              {$message}
            </div>
          </td>
        </tr>
HTML;
}

if (!empty($utmSource) || !empty($utmCampaign)) {
    $htmlBody .= <<<HTML
        <tr>
          <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px;">Kampanya (UTM):</td>
          <td style="padding: 10px 0; font-size: 13px; color: #64748b;">
            Kaynak: {$utmSource} | Kampanya: {$utmCampaign} | Mecra: {$utmMedium}
          </td>
        </tr>
HTML;
}

$htmlBody .= <<<HTML
      </table>
    </div>
    <div class="footer">
     Bu mesaj web sitesindeki form aracılığıyla otomatik oluşturulmuştur.
    </div>
  </div>
</body>
</html>
HTML;

// Düz metin versiyonu
$textBody  = "YENİ İLETİŞİM & BİLGİ TALEBİ\n\n";
$textBody .= "Ad Soyad : " . $name . "\n";
$textBody .= "Telefon  : " . $phone . "\n";
if (!empty($message)) {
    $textBody .= "Mesaj    : " . $message . "\n";
}
$textBody .= "Form Yeri: " . $source . "\n";
$textBody .= "Tarih    : " . $dateStr . "\n";
$textBody .= "IP       : " . $ipAddress . "\n";

/**
 * Harici bağımlılık gerektirmeyen, doğrudan PHP soketleri ile çalışan
 * Güvenli STARTTLS SMTP Gönderim Sınıfı
 */
class DirectSMTP {
    private $socket;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $timeout = 15;
    public  $error = '';

    public function __construct($host, $port, $user, $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    private function getResponse() {
        $response = '';
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function sendCommand($cmd, $expectedCode = null) {
        fputs($this->socket, $cmd . "\r\n");
        $res = $this->getResponse();
        if ($expectedCode !== null) {
            $code = substr($res, 0, 3);
            if ($code !== (string)$expectedCode) {
                $this->error = "Beklenmeyen SMTP cevabi ($code, beklenen $expectedCode): $res";
                return false;
            }
        }
        return $res;
    }

    public function send($fromEmail, $fromName, $toEmail, $subject, $htmlContent, $textContent) {
        // 1. Soket bağlantısı kur
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $this->error = "SMTP Baglanti hatasi: $errstr ($errno)";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        $greeting = $this->getResponse();
        if (substr($greeting, 0, 3) !== '220') {
            $this->error = "Sunucu 220 vermedi: $greeting";
            fclose($this->socket);
            return false;
        }

        // 2. EHLO
        if (!$this->sendCommand('EHLO ' . gethostname(), 250)) {
            fclose($this->socket);
            return false;
        }

        // 3. STARTTLS
        if (!$this->sendCommand('STARTTLS', 220)) {
            fclose($this->socket);
            return false;
        }

        // TLS el sıkışması
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        $cryptoOk = stream_socket_enable_crypto($this->socket, true, $cryptoMethod);
        if (!$cryptoOk) {
            $this->error = "TLS el sikismasi basarisiz oldu.";
            fclose($this->socket);
            return false;
        }

        // 4. TLS sonrası EHLO
        if (!$this->sendCommand('EHLO ' . gethostname(), 250)) {
            fclose($this->socket);
            return false;
        }

        // 5. Kimlik Doğrulama (AUTH LOGIN)
        if (!$this->sendCommand('AUTH LOGIN', 334)) {
            fclose($this->socket);
            return false;
        }

        if (!$this->sendCommand(base64_encode($this->user), 334)) {
            fclose($this->socket);
            return false;
        }

        if (!$this->sendCommand(base64_encode($this->pass), 235)) {
            fclose($this->socket);
            return false;
        }

        // 6. Gönderen ve Alıcı
        if (!$this->sendCommand("MAIL FROM:<{$fromEmail}>", 250)) {
            fclose($this->socket);
            return false;
        }

        if (!$this->sendCommand("RCPT TO:<{$toEmail}>", 250)) {
            fclose($this->socket);
            return false;
        }

        // 7. DATA
        if (!$this->sendCommand('DATA', 354)) {
            fclose($this->socket);
            return false;
        }

        // 8. E-posta İçeriği & Başlıkları
        $boundary = '=_boundary_' . md5(uniqid(time(), true));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $headers = [
            "From: {$encodedFromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "To: <{$toEmail}>",
            "Subject: {$encodedSubject}",
            "Date: " . date('r'),
            "Message-ID: <" . md5(uniqid(time(), true)) . "@{$this->host}>",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: PHP/" . phpversion()
        ];

        $mimeMsg  = implode("\r\n", $headers) . "\r\n\r\n";
        
        // Düz metin parçası
        $mimeMsg .= "--{$boundary}\r\n";
        $mimeMsg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mimeMsg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mimeMsg .= chunk_split(base64_encode($textContent)) . "\r\n";

        // HTML parçası
        $mimeMsg .= "--{$boundary}\r\n";
        $mimeMsg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mimeMsg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mimeMsg .= chunk_split(base64_encode($htmlContent)) . "\r\n";

        // Bitiş sınırı
        $mimeMsg .= "--{$boundary}--\r\n";

        // DATA sonu: CRLF . CRLF
        $this->sendCommand($mimeMsg . "\r\n.");

        // 9. QUIT
        $this->sendCommand('QUIT');
        fclose($this->socket);

        return true;
    }
}

// SMTP Gönderimini Başlat
$smtp = new DirectSMTP(
    $smtpConfig['host'],
    $smtpConfig['port'],
    $smtpConfig['username'],
    $smtpConfig['password']
);

$sent = $smtp->send(
    $smtpConfig['username'],
    $smtpConfig['fromName'],
    $smtpConfig['toEmail'],
    $subject,
    $htmlBody,
    $textBody
);

if ($sent) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Talebiniz başarıyla iletildi. Uzman ekibimiz en kısa sürede sizinle iletişime geçecektir.'
    ]);
} else {
    // E-posta gönderilemediğinde istemciye hata dön (istemci JS otomatik olarak WhatsApp yedeğini devreye sokabilir)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Mesaj iletilemedi.',
        'detail' => $smtp->error
    ]);
}
