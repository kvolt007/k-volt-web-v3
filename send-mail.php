<?php
declare(strict_types=1);

$isAjax =
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respond(bool $success, string $message, bool $isAjax): void {
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($success ? 200 : 422);
        echo json_encode([
            'success' => $success,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Location: ./?contact=' . ($success ? 'ok' : 'error') . '#contacto', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ./#contacto', true, 303);
    exit;
}

function clean(string $value): string {
    $value = trim($value);
    $value = str_replace(["\r", "\0"], '', $value);
    return $value;
}

$honeypot = clean($_POST['empresa_web'] ?? '');
if ($honeypot !== '') {
    // Respuesta neutra para bots; no revelar la protección.
    respond(true, 'Consulta recibida.', $isAjax);
}

$nombre   = clean($_POST['nombre'] ?? '');
$telefono = clean($_POST['telefono'] ?? '');
$email    = clean($_POST['email'] ?? '');
$servicio = clean($_POST['servicio'] ?? '');
$mensaje  = trim((string)($_POST['mensaje'] ?? ''));

$allowedServices = [
    'Asistencia para mi hogar',
    'Obra nueva / ampliación',
    'Asistencia para mi comercio o empresa',
    'Urgencia eléctrica 24 horas',
    'Climatización / aire acondicionado',
    'Otro'
];

$valid =
    $nombre !== '' &&
    $telefono !== '' &&
    filter_var($email, FILTER_VALIDATE_EMAIL) &&
    in_array($servicio, $allowedServices, true) &&
    $mensaje !== '' &&
    mb_strlen($nombre) <= 80 &&
    mb_strlen($telefono) <= 40 &&
    mb_strlen($email) <= 120 &&
    mb_strlen($mensaje) <= 2000;

if (!$valid) {
    respond(false, 'Revisá los datos ingresados.', $isAjax);
}

$to = 'contacto.kvolt@gmail.com';
$subject = 'K-VOLT | Nueva consulta - ' . $servicio;

$body = "Nueva consulta desde la web K-Volt\n\n";
$body .= "Nombre: {$nombre}\n";
$body .= "Teléfono / WhatsApp: {$telefono}\n";
$body .= "Email: {$email}\n";
$body .= "Servicio: {$servicio}\n\n";
$body .= "Consulta:\n{$mensaje}\n";

$host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$fromDomain = $host && strpos($host, '.') !== false ? $host : 'localhost';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: K-Volt Web <no-reply@' . $fromDomain . '>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion()
];

$sent = @mail($to, $subject, $body, implode("\r\n", $headers));

respond(
    $sent,
    $sent ? 'Consulta enviada correctamente.' : 'No pudimos enviar la consulta.',
    $isAjax
);
