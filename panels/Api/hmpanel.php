<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../request.php';
ini_set('error_log', 'error_log');

function hmpanel_request($panel, $method, $path, $data = null)
{
    $base = rtrim($panel['url_panel'], '/');
    $url = $base . '/api' . $path;

    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );

    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    if (!empty($panel['password_panel'])) {
        $req->setBearerToken($panel['password_panel']);
    }

    if (strtoupper($method) === 'GET') {
        $response = $req->get();
        return $response;
    } elseif (strtoupper($method) === 'DELETE') {
        if ($data === null) {
            $response = $req->delete();
        } else {
            $response = $req->delete(json_encode($data));
        }
        return $response;
    } else {
        if ($data === null) {
            $response = $req->post(array());
        } else {
            $response = $req->post(json_encode($data));
        }
        return $response;
    }
}

function hmpanel_get_inbounds($panel)
{
    return hmpanel_request($panel, 'GET', '/v1/inbounds');
}

function hmpanel_get_client_by_id($panel, $clientId)
{
    return hmpanel_request($panel, 'GET', '/v1/clients/' . rawurlencode($clientId));
}

function hmpanel_lookup_client($panel, $key)
{
    return hmpanel_request($panel, 'GET', '/v1/clients/lookup?key=' . rawurlencode($key));
}

function hmpanel_create_client($panel, $email, $inboundIds = array(), $totalGb = null, $expiryTimeMs = null, $remark = '', $limitIp = null)
{
    $body = array();
    if ($email !== '' && $email !== null) {
        $body['email'] = $email;
    }
    if (is_array($inboundIds) && !empty($inboundIds)) {
        $body['inboundIds'] = array_values($inboundIds);
    }
    if ($totalGb === null || $totalGb === '') {
        $body['total'] = 0;
    } else {
        $body['total'] = intval($totalGb) * 1024 * 1024 * 1024;
    }
    if ($expiryTimeMs !== null && $expiryTimeMs !== '') {
        $body['expiryTime'] = intval($expiryTimeMs);
    }
    if ($remark !== '' && $remark !== null) {
        $body['remark'] = $remark;
    }
    if ($limitIp !== null && $limitIp !== '') {
        $body['limitIp'] = intval($limitIp);
    }
    return hmpanel_request($panel, 'POST', '/v1/clients', $body);
}

function hmpanel_update_client($panel, $clientId, $data = array())
{
    return hmpanel_request($panel, 'PATCH', '/v1/clients/' . rawurlencode($clientId), $data);
}

function hmpanel_delete_client($panel, $clientId)
{
    return hmpanel_request($panel, 'DELETE', '/v1/clients/' . rawurlencode($clientId));
}

function hmpanel_renew_client($panel, $clientId, $days = 0, $totalGb = null, $resetUsage = false)
{
    $body = array();
    if ($days !== null && $days !== '') {
        $body['days'] = intval($days);
    }
    if ($totalGb !== null && $totalGb !== '') {
        $body['totalGb'] = intval($totalGb);
    }
    if ($resetUsage) {
        $body['resetUsage'] = true;
    }
    return hmpanel_request($panel, 'POST', '/v1/clients/' . rawurlencode($clientId) . '/renew', $body);
}

function hmpanel_reset_usage($panel, $clientId)
{
    return hmpanel_request($panel, 'POST', '/v1/clients/' . rawurlencode($clientId) . '/reset-usage', array());
}
