<?php
/**
 * Pendik Yüzyıl Hastanesi - Lead Formu Mail Gönderim Betiği
 */

// Hata raporlama ve güvenlik
error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Istanbul');

// CORS ve Yanıt Başlıkları
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} else {
    header('Access-Control-Allow-Origin: *');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Girdi Verisini Al (JSON veya FormData/POST desteği)
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $data = $jsonData;
} else {
    $data = $_POST;
}

// Honeypot spam kontrolü
if (!empty($data['website'])) {
    echo json_encode(['success' => true, 'message' => 'Talebiniz alındı.']);
    exit;
}

function clean_input($val) {
    return trim(htmlspecialchars(strip_tags((string)$val), ENT_QUOTES, 'UTF-8'));
}

$name     = clean_input($data['name'] ?? $data['ad_soyad'] ?? '');
$phone    = clean_input($data['phone'] ?? $data['tel'] ?? $data['telefon'] ?? '');
$email    = clean_input($data['email'] ?? $data['eposta'] ?? '');
$message  = clean_input($data['message'] ?? $data['mesaj'] ?? '');
$source   = clean_input($data['source'] ?? $data['type'] ?? 'Web Formu');
$pageUrl  = clean_input($data['url'] ?? $data['page'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
$dateStr  = date('d/m/Y H:i');
$ipAddr   = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';

// UTM Parametreleri
$utmSource   = clean_input($data['utm_source'] ?? ($data['utm']['utm_source'] ?? ''));
$utmMedium   = clean_input($data['utm_medium'] ?? ($data['utm']['utm_medium'] ?? ''));
$utmCampaign = clean_input($data['utm_campaign'] ?? ($data['utm']['utm_campaign'] ?? ''));

// Zorunlu alan kontrolü
if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Lütfen adınızı ve telefon numaranızı girin.']);
    exit;
}

// PHPMailer Yükleme
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
} elseif (file_exists(__DIR__ . '/../PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/../PHPMailer/Exception.php';
    require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/SMTP.php';
}

$mail = new PHPMailer;
$mail->isSMTP();
$mail->Host = 'mail.pendikyuzyilhastanesi.com';
$mail->Port = 587;
$mail->SMTPOptions = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
$mail->SMTPSecure = 'tls';
$mail->SMTPAuth = true;
$mail->CharSet = 'UTF-8';
$mail->Username = 'info@pendikyuzyilhastanesi.com';
$mail->Password = 'Pbu4s321#';

$mail->setFrom('info@pendikyuzyilhastanesi.com', 'Yüzyıl Hastanesi İletişim');
$mail->addAddress('info@pendikyuzyilhastanesi.com', 'Yüzyıl Hastanesi İletişim');

$mail->Subject = 'Pendik Yüzyıl Hastanesi Formu Dolduruldu - ' . $name . ' (' . $dateStr . ')';
$mail->isHTML(true);

$mailHtml = '
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<style type="text/css">
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; }
  .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
  .header { background-color: #318dde; color: #ffffff; padding: 25px 20px; text-align: center; }
  .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
  .header p { margin: 6px 0 0 0; font-size: 14px; opacity: 0.9; }
  .content { padding: 30px; }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
  .info-table td.lbl { width: 140px; font-weight: bold; color: #64748b; font-size: 14px; }
  .info-table td.val { color: #0f172a; font-size: 15px; }
  .phone-link { display: inline-block; background: #eff6ff; color: #1d4ed8; padding: 4px 10px; border-radius: 6px; font-weight: bold; text-decoration: none; font-size: 16px; }
  .message-box { background: #f8fafc; border-left: 4px solid #318dde; padding: 12px; margin-top: 6px; border-radius: 4px; color: #334155; font-size: 14px; }
  .footer { background: #f8fafc; padding: 15px 30px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>Yeni İletişim / Randevu Talebi</h1>
      <p>Pendik Yüzyıl Hastanesi Landing Page</p>
    </div>
    <div class="content">
      <table class="info-table">
        <tr>
          <td class="lbl">İsim Soyisim:</td>
          <td class="val"><strong>' . $name . '</strong></td>
        </tr>
        <tr>
          <td class="lbl">Telefon Numarası:</td>
          <td class="val"><a href="tel:' . $phone . '" class="phone-link">' . $phone . '</a></td>
        </tr>';

if (!empty($email)) {
    $mailHtml .= '
        <tr>
          <td class="lbl">E-posta:</td>
          <td class="val">' . $email . '</td>
        </tr>';
}

if (!empty($message)) {
    $mailHtml .= '
        <tr>
          <td class="lbl" style="vertical-align: top;">Mesaj:</td>
          <td class="val"><div class="message-box">' . nl2br($message) . '</div></td>
        </tr>';
}

$mailHtml .= '
        <tr>
          <td class="lbl">Form Konumu:</td>
          <td class="val">' . $source . '</td>
        </tr>
        <tr>
          <td class="lbl">Sayfa URL:</td>
          <td class="val"><a href="' . $pageUrl . '" target="_blank">' . $pageUrl . '</a></td>
        </tr>
        <tr>
          <td class="lbl">Tarih & Saat:</td>
          <td class="val">' . $dateStr . '</td>
        </tr>
        <tr>
          <td class="lbl">IP Adresi:</td>
          <td class="val">' . $ipAddr . '</td>
        </tr>';

if (!empty($utmSource) || !empty($utmCampaign)) {
    $mailHtml .= '
        <tr>
          <td class="lbl">Kampanya (UTM):</td>
          <td class="val">Kaynak: ' . $utmSource . ' | Kampanya: ' . $utmCampaign . ' | Mecra: ' . $utmMedium . '</td>
        </tr>';
}

$mailHtml .= '
      </table>
    </div>
    <div class="footer">
      Bu bildirim web sitesi formundan otomatik olarak gönderilmiştir.
    </div>
  </div>
</body>
</html>';

$mail->MsgHTML($mailHtml);

if (!$mail->send()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Mailer Error: ' . $mail->ErrorInfo
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Talebiniz başarıyla iletildi. En kısa sürede sizinle iletişime geçilecektir.'
    ]);
}
