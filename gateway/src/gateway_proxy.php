<?php
declare(strict_types=1);

require_once __DIR__ . '/gateway_helpers.php';
require_once __DIR__ . '/gateway_logger.php';

function buildForwardHeaders(array $headers): array
{
    $forwardHeaders = [
        'Accept: application/json',
    ];

    foreach ($headers as $name => $value) {
        $normalized = strtolower((string) $name);

        if ($normalized === 'host' || $normalized === 'content-length') {
            continue;
        }

        if ($normalized === 'content-type' || $normalized === 'authorization' || $normalized === 'accept') {
            $forwardHeaders[] = $name . ': ' . $value;
        }
    }

    return $forwardHeaders;
}

function appendCurrentQueryString(string $targetUrl): string
{
    $query = gatewayGetCurrentQueryString();

    if ($query === '') {
        return $targetUrl;
    }

    return $targetUrl . (str_contains($targetUrl, '?') ? '&' : '?') . $query;
}

function forwardRequest(string $targetUrl): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $body = file_get_contents('php://input');

    $finalUrl = appendCurrentQueryString($targetUrl);

    $ch = curl_init($finalUrl);
    if ($ch === false) {
        gatewayLogError('Falha ao inicializar cURL', [
            'method' => $method,
            'target_url' => $finalUrl,
        ]);

        gatewayJsonResponse(502, [
            'success' => false,
            'message' => 'Falha ao encaminhar requisição.',
            'errors' => ['gateway' => 'Não foi possível inicializar o cliente HTTP.'],
        ]);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, buildForwardHeaders($headers));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body !== false ? $body : '');
    }

    $responseBody = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/json; charset=utf-8';
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($responseBody === false) {
        gatewayLogError('Falha ao encaminhar requisição', [
            'method' => $method,
            'target_url' => $finalUrl,
            'curl_error' => $curlError,
        ]);

        gatewayJsonResponse(502, [
            'success' => false,
            'message' => 'Falha ao encaminhar requisição.',
            'errors' => ['gateway' => $curlError],
        ]);
    }

    gatewayLogInfo('Requisição encaminhada', [
        'method' => $method,
        'target_url' => $finalUrl,
        'status_code' => $statusCode,
    ]);

    http_response_code($statusCode);
    header('Content-Type: ' . $contentType);
    echo $responseBody;
    exit;
}