<?php

function api_request($method, $path, $payload = null, $token = null)
{
    global $config;
    $curl = curl_init($config['api_base_url'] . $path);
    $headers = ['Accept: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => $config['connect_timeout'],
        CURLOPT_TIMEOUT => $config['request_timeout'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $errno = curl_errno($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || $status >= 500 || $status === 0) {
        error_log('ERP auth: endpoint=' . $path . ' status=' . $status . ' curl_code=' . $errno);
        return ['status' => 0, 'data' => null];
    }
    return ['status' => $status, 'data' => json_decode($body, true)];
}

function valid_identity($user)
{
    return is_array($user) && isset($user['id'], $user['nome'], $user['email'])
        && is_int($user['id']) && $user['id'] > 0
        && is_string($user['nome']) && $user['nome'] !== ''
        && is_string($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL);
}

function valid_expiration($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|\+00:00)$/D', $value)) {
        return false;
    }
    try {
        $date = new DateTimeImmutable($value);
        $errors = DateTimeImmutable::getLastErrors();
        return (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->getTimestamp() > time();
    } catch (Exception $exception) {
        return false;
    }
}
