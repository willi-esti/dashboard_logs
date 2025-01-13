<?php

require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

function genToken($username)
{
    $payload = [
        'iss' => $_ENV['JWT_ISSUER'],
        'iat' => time(),
        'exp' => time() + 3600, // Token expires in 1h
        'sub' => $username
    ];
    return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
}

function genNewToken($username)
{
    $token = genToken($username);
    header('Authorization: Bearer ' . $token);
}


function verifyToken()
{
    $headers = getallheaders();
    if (empty($headers['Authorization'])) {
        jsonResponse(['error' => 'Unauthorized', 'message' => 'Authorization header is missing'], 401);
    }

    $authHeader = explode(' ', $headers['Authorization']);
    $jwt = $authHeader[1];

    try {
        $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET'], 'HS256'));
        $currentTime = time();
        $remainingTime = $decoded->exp - $currentTime;

        $newJwt = null;
        // If the token is about to expire in less than 5 minutes, issue a new token
        if ($remainingTime < 300) {
            genNewToken($decoded->sub);
            //header('Authorization: Bearer ' . $newJwt);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
    }
}


function getUsernameFromAuthHeader()
{
    $authHeader = explode(' ', getallheaders()['Authorization']);
    return explode(':', base64_decode($authHeader[1]))[0];
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200, $log = true)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    if ($log) {
        logError(json_encode($data));
    }
    exit;
}

function logError($message)
{
    $LOG_DIR = $_ENV['LOG_DIR'];
    if (!file_exists($LOG_DIR)) {
        mkdir($LOG_DIR);
    }
    error_log("[" . date('Y-m-d H:i:s') . "] $message\n", 3, $LOG_DIR . '/server-dashboard.log');
}

function isWhitelistedService($service)
{
    $whitelistedServices = ['nginx.service', 'mysql.service', 'apache2.service'];
    return in_array($service, $whitelistedServices, true);
}

function isValidAction($action)
{
    $validActions = ['start', 'stop', 'restart'];
    return in_array($action, $validActions, true);
}

function isSELinuxActive() {
    $selinuxStatusFile = '/sys/fs/selinux/enforce';

    // Check if the SELinux status file exists
    if (file_exists($selinuxStatusFile)) {
        $status = file_get_contents($selinuxStatusFile);
        if ($status === false) {
            logError('Unable to read SELinux status file.');
            return 0;
        }
        return $status;
    } else {
       return 0;
    }
}

function getToken($url, $token)
{    
    $serverStatus = getServersStatus($url, $token);
    if ($serverStatus == 1) {
        logError(['error' => 'Server error', 'message' => 'Server is not running']);
        return 1;
    }
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($data, 0, $header_size);
    $body = substr($data, $header_size);
    $headers = explode("\n", $header);
    $accessToken = '';
    foreach ($headers as $header) {
        if (strpos($header, 'Authorization: Bearer ') !== false) {
            $accessToken = str_replace('Authorization: Bearer ', '', $header);
        }
    }
    return ['status' => 'running', 'token' => $accessToken];   
}

function getServersStatus($url, $token, $skip_redirects = 0)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true); // get the header
    //curl_setopt($ch, CURLOPT_NOBODY, true); // and *only* the header
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL: self-signed certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // SSL: cn verification
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
    $data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpcode == 301 || $httpcode == 302 && $skip_redirects == 0) {
        $new_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        if ($new_url) {
            return getServersStatus($new_url, $token, 1);
        }
    }
    if (curl_errno($ch)) {
        logError(['error' => 'Curl error', 'message' => curl_error($ch)]);
        return 1;
    }
    curl_close($ch);
    if ($httpcode == 200) {
        return ['status' => 'running', 'data' => $data];
    } else {
        return 1;
    }
}
?>
