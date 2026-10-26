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
    } elseif (strtoupper($method) === 'PATCH') {
        $response = $req->PATCH($data === null ? null : json_encode($data));
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

function hmpanel_decode_inbounds($response)
{
    if (!is_array($response)) {
        return array();
    }
    $body = json_decode($response['body'] ?? '', true);
    if (!is_array($body)) {
        return array();
    }
    $data = $body['data'] ?? ($body['obj'] ?? $body);
    if (isset($data['data']) && is_array($data['data'])) {
        $data = $data['data'];
    }
    if (isset($data['obj']) && is_array($data['obj'])) {
        $data = $data['obj'];
    }
    if (isset($data['inbounds']) && is_array($data['inbounds'])) {
        $data = $data['inbounds'];
    }
    if (!is_array($data)) {
        return array();
    }
    $list = array();
    foreach ($data as $item) {
        if (is_array($item) && isset($item['id'])) {
            $list[] = $item;
        }
    }
    return $list;
}

function hmpanel_inbound_ids($inbounds)
{
    $ids = array();
    foreach ((array)$inbounds as $inbound) {
        if (is_array($inbound) && isset($inbound['id'])) {
            $ids[] = $inbound['id'];
        }
    }
    return array_values(array_unique($ids, SORT_REGULAR));
}

function hmpanel_select_inbound_ids($inbounds, $input = '')
{
    $all = hmpanel_inbound_ids($inbounds);
    $input = trim((string)$input);
    if ($input === '' || empty($all)) {
        return $all;
    }
    $tokens = preg_split('/[\s,]+/', $input);
    $matched = array();
    foreach ($tokens as $token) {
        if ($token === '') {
            continue;
        }
        foreach ((array)$inbounds as $inbound) {
            if (!is_array($inbound) || !isset($inbound['id'])) {
                continue;
            }
            $id = (string)$inbound['id'];
            $tag = (string)($inbound['tag'] ?? '');
            $remark = (string)($inbound['remark'] ?? '');
            if ($id === $token || strcasecmp($tag, $token) === 0 || strcasecmp($remark, $token) === 0) {
                $matched[] = $inbound['id'];
                break;
            }
        }
    }
    return empty($matched) ? $all : array_values(array_unique($matched, SORT_REGULAR));
}

function hmpanel_get_client_by_id($panel, $clientId)
{
    return hmpanel_request($panel, 'GET', '/v1/clients/' . rawurlencode($clientId));
}

function hmpanel_lookup_client($panel, $key)
{
    return hmpanel_request($panel, 'GET', '/v1/clients/lookup?key=' . rawurlencode($key));
}

function hmpanel_create_client($panel, $email, $inboundIds = array(), $totalBytes = null, $expiryTimeMs = null, $remark = '', $limitIp = null)
{
    $body = array();
    if ($email !== '' && $email !== null) {
        $body['email'] = $email;
    }
    if (is_array($inboundIds) && !empty($inboundIds)) {
        $body['inboundIds'] = array_values($inboundIds);
    }
    if ($totalBytes === null || $totalBytes === '') {
        $body['total'] = 0;
    } else {
        $body['total'] = intval($totalBytes);
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
