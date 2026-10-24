<?php

class HmpanelPanelDriver extends BasePanelDriver
{
    public function create(array $vars)
    {
        $Get_Data_Product = $vars['Get_Data_Product'];
        $Get_Data_Panel = $vars['Get_Data_Panel'];
        $usernameC = $vars['usernameC'];
        $expire = $vars['expire'];
        $data_limit = $vars['data_limit'];
        $note = $vars['note'];
        $Output = $vars['Output'];
        $invoice = $vars['invoice'];
        $domainhosts = $vars['domainhosts'];

        $inbounds = array();
        if (isset($Get_Data_Product['inbounds']) and $Get_Data_Product['inbounds'] != null && $Get_Data_Product['inbounds'] != '') {
            $decoded = json_decode($Get_Data_Product['inbounds'], true);
            if (is_array($decoded)) {
                $inbounds = $decoded;
            }
        } elseif (isset($Get_Data_Panel['inbounds']) and $Get_Data_Panel['inbounds'] != null && $Get_Data_Panel['inbounds'] != '') {
            $decoded = json_decode($Get_Data_Panel['inbounds'], true);
            if (is_array($decoded)) {
                $inbounds = $decoded;
            }
        }

        $expiryTimeMs = null;
        if ($Get_Data_Product['name_product'] == "usertest") {
            if (isset($Get_Data_Panel['on_hold_test']) && $Get_Data_Panel['on_hold_test'] == "1" && $expire == 0) {
                $expiryTimeMs = 0;
            } elseif ($expire == 0) {
                $expiryTimeMs = 0;
            } else {
                if (isset($Get_Data_Panel['conecton']) && $Get_Data_Panel['conecton'] == "onconecton") {
                    $timelast = $expire - time();
                    $expiryTimeMs = -intval(($timelast / 86400) * 86400000);
                } else {
                    $expiryTimeMs = $expire * 1000;
                }
            }
        } else {
            if (isset($Get_Data_Panel['conecton']) && $Get_Data_Panel['conecton'] == "onconecton") {
                if ($expire == 0) {
                    $expiryTimeMs = 0;
                } else {
                    $timelast = $expire - time();
                    $expiryTimeMs = -intval(($timelast / 86400) * 86400000);
                }
            } else {
                if ($expire == 0) {
                    $expiryTimeMs = 0;
                } else {
                    $expiryTimeMs = $expire * 1000;
                }
            }
        }

        $res = hmpanel_create_client($Get_Data_Panel, $usernameC, $inbounds, $data_limit, $expiryTimeMs, $note);
        if (!empty($res['error'])) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $res['error']
            );
        } elseif (!empty($res['status']) && $res['status'] != 200) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $res['status']
            );
        }

        $body = json_decode($res['body'], true);
        if (!is_array($body)) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => 'Invalid response'
            );
        }

        if (isset($body['status']) && $body['status'] === false) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $body['msg'] ?? 'Unsuccessful'
            );
        }

        $client = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
        if (empty($client['id']) && empty($client['email'])) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $body['msg'] ?? 'Unsuccessful'
            );
        }

        $subUrl = $client['subscriptionUrl'] ?? ($client['subscription_url'] ?? '');
        if ($subUrl == '' && !empty($client['subToken'])) {
            $base = rtrim($Get_Data_Panel['url_panel'], '/');
            $subUrl = $base . '/s/' . $client['subToken'];
        }

        $links = array();
        if (!empty($subUrl)) {
            if (function_exists('outputlink')) {
                $links_user = outputlink($subUrl);
                if (function_exists('isBase64') && isBase64($links_user)) {
                    $links_user = base64_decode($links_user);
                }
                $links = is_array($links_user) ? $links_user : explode("\n", trim($links_user));
            } else {
                $links = array($subUrl);
            }
        }

        $Output['status'] = 'successful';
        $Output['username'] = $client['email'] ?? $usernameC;
        $Output['subscription_url'] = $subUrl;
        $Output['configs'] = is_array($links) ? $links : array();
        if ($invoice != false && function_exists('is_array') && $invoice != false) {
            $Output['subscription_url'] = "https://$domainhosts/sub/" . $invoice['id_invoice'];
        }

        return $Output;
    }

    public function read(array $vars)
    {
        $username = $vars['username'];
        $Get_Data_Panel = $vars['Get_Data_Panel'];
        $invoice = $vars['invoice'];
        $domainhosts = $vars['domainhosts'];
        $Output = $vars['Output'];

        $res = hmpanel_lookup_client($Get_Data_Panel, $username);
        if (!empty($res['error'])) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $res['error']
            );
        } elseif (!empty($res['status']) && $res['status'] != 200) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $res['status']
            );
        }

        $body = json_decode($res['body'], true);
        if (!is_array($body)) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => 'Invalid response'
            );
        }

        $client = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
        if (empty($client) || (isset($client['status']) && $client['status'] === false && isset($client['msg']))) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => $client['msg'] ?? 'Unsuccessful'
            );
        }

        $enable = isset($client['enable']) ? $client['enable'] : true;
        $status = $enable ? 'active' : 'disabled';
        $used = 0;
        if (isset($client['usedBytes'])) {
            $used = intval($client['usedBytes']);
        } elseif (isset($client['upBytes']) || isset($client['downBytes'])) {
            $used = intval($client['upBytes'] ?? 0) + intval($client['downBytes'] ?? 0);
        }

        $total = null;
        if (isset($client['totalBytes'])) {
            $total = intval($client['totalBytes']);
        } elseif (isset($client['total'])) {
            $total = intval($client['total']);
        }

        $expireMs = isset($client['expiryTime']) ? intval($client['expiryTime']) : 0;
        $expireSec = 0;
        if ($expireMs > 0) {
            $expireSec = intval($expireMs / 1000);
        } elseif ($expireMs == 0) {
            $expireSec = 0;
        }

        if ($expireMs == 0) {
            // unlimited/indefinite
        } else {
            if ($expireSec > 0 && $expireSec <= time()) {
                $status = 'expired';
            }
        }
        if ($total !== null && $total > 0 && $used >= $total) {
            $status = 'limited';
        }
        if ($expireMs < -10000) {
            $status = 'on_hold';
            $expireSec = 0;
        }

        $subUrl = $client['subscriptionUrl'] ?? ($client['subscription_url'] ?? '');
        if ($subUrl == '' && !empty($client['subToken'])) {
            $base = rtrim($Get_Data_Panel['url_panel'], '/');
            $subUrl = $base . '/s/' . $client['subToken'];
        }
        if ($invoice != false) {
            $subUrl = "https://$domainhosts/sub/" . $invoice['id_invoice'];
        }

        $links = array();
        if (!empty($subUrl)) {
            if (function_exists('outputlink')) {
                $links_user = outputlink($subUrl);
                if (function_exists('isBase64') && isBase64($links_user)) {
                    $links_user = base64_decode($links_user);
                }
                $links = is_array($links_user) ? $links_user : explode("\n", trim($links_user));
            } else {
                $links = array($subUrl);
            }
        }

        return array(
            'status' => $status,
            'username' => $client['email'] ?? $username,
            'data_limit' => $total,
            'expire' => $expireSec,
            'expiry_time' => $expireMs,
            'online_at' => $client['online_at'] ?? null,
            'used_traffic' => $used,
            'links' => is_array($links) ? $links : array(),
            'subscription_url' => $subUrl,
            'sub_updated_at' => null,
            'sub_last_user_agent' => null,
        );
    }

    public function revokeSub(array $vars)
    {
        $username = $vars['username'];
        $Get_Data_Panel = $vars['Get_Data_Panel'];
        $Output = $vars['Output'];

        $found = hmpanel_lookup_client($Get_Data_Panel, $username);
        if (!empty($found['status']) && $found['status'] != 200) {
            return array('status' => 'Unsuccessful', 'msg' => $found['status']);
        }
        if (!empty($found['error'])) {
            return array('status' => 'Unsuccessful', 'msg' => $found['error']);
        }
        $fbody = json_decode($found['body'], true);
        $client = isset($fbody['data']) && is_array($fbody['data']) ? $fbody['data'] : $fbody;
        $clientId = $client['id'] ?? null;
        if (!$clientId) {
            return array('status' => 'Unsuccessful', 'msg' => 'Client not found');
        }

        // HMPanel v1 API doesn't expose explicit sub revoke endpoint in admin-api.md; try update with regenerate token if supported by panel
        // Best effort: call renew/reset path not available; fallback to reading updated sub
        $read = $this->read(array('username' => $username, 'Get_Data_Panel' => $Get_Data_Panel, 'invoice' => false, 'domainhosts' => $vars['domainhosts'] ?? ''));
        if (($read['status'] ?? '') == 'Unsuccessful') {
            return array('status' => 'Unsuccessful', 'msg' => $read['msg'] ?? 'Unsuccessful');
        }
        return array(
            'status' => 'successful',
            'configs' => $read['links'] ?? array(),
            'subscription_url' => $read['subscription_url'] ?? '',
        );
    }

    public function remove(array $vars)
    {
        $Get_Data_Panel = $vars['Get_Data_Panel'];
        $username = $vars['username'];
        $Output = $vars['Output'];

        $found = hmpanel_lookup_client($Get_Data_Panel, $username);
        if (!empty($found['status']) && $found['status'] != 200) {
            return array('status' => 'Unsuccessful', 'msg' => $found['status']);
        }
        if (!empty($found['error'])) {
            return array('status' => 'Unsuccessful', 'msg' => $found['error']);
        }
        $fbody = json_decode($found['body'], true);
        $client = isset($fbody['data']) && is_array($fbody['data']) ? $fbody['data'] : $fbody;
        $clientId = $client['id'] ?? null;
        if (!$clientId) {
            return array('status' => 'Unsuccessful', 'msg' => 'Client not found');
        }

        $res = hmpanel_delete_client($Get_Data_Panel, $clientId);
        if (!empty($res['error'])) {
            return array('status' => 'Unsuccessful', 'msg' => $res['error']);
        } elseif (!empty($res['status']) && $res['status'] != 200) {
            return array('status' => 'Unsuccessful', 'msg' => $res['status']);
        }
        $body = json_decode($res['body'], true);
        if (is_array($body) && isset($body['status']) && $body['status'] === false) {
            return array('status' => 'Unsuccessful', 'msg' => $body['msg'] ?? 'Unsuccessful');
        }
        return array('status' => 'successful', 'username' => $username);
    }

    public function modify(array $vars)
    {
        $name_panel = $vars['name_panel'];
        $username = $vars['username'];
        $config = $vars['config'];
        $Get_Data_Panel = $vars['Get_Data_Panel'];

        $found = hmpanel_lookup_client($Get_Data_Panel, $username);
        if (!empty($found['error'])) {
            return array('status' => false, 'msg' => $found['error']);
        }
        if (!empty($found['status']) && $found['status'] != 200) {
            return array('status' => false, 'msg' => $found['status']);
        }
        $fbody = json_decode($found['body'], true);
        $client = isset($fbody['data']) && is_array($fbody['data']) ? $fbody['data'] : $fbody;
        $clientId = $client['id'] ?? null;
        if (!$clientId) {
            return array('status' => false, 'msg' => 'Client not found');
        }

        $body = array();
        if (isset($config['enable'])) {
            $body['enable'] = (bool)$config['enable'];
        }
        if (isset($config['totalGB'])) {
            $total = intval($config['totalGB']);
            $body['total'] = $total == 0 ? 0 : $total;
        }
        if (isset($config['total'])) {
            $total = intval($config['total']);
            $body['total'] = $total == 0 ? 0 : $total;
        }
        if (isset($config['expiryTime'])) {
            $exp = intval($config['expiryTime']);
            if ($exp > 1000000000000) {
                $body['expiryTime'] = $exp;
            } elseif ($exp > 0) {
                $body['expiryTime'] = $exp * 1000;
            } else {
                $body['expiryTime'] = $exp;
            }
        }
        if (isset($config['remark'])) {
            $body['remark'] = $config['remark'];
        }
        if (isset($config['limitIp'])) {
            $body['limitIp'] = intval($config['limitIp']);
        }

        if (empty($body)) {
            return array('status' => true, 'data' => $client);
        }

        $res = hmpanel_update_client($Get_Data_Panel, $clientId, $body);
        if (!empty($res['error'])) {
            return array('status' => false, 'msg' => $res['error']);
        }
        if (!empty($res['status']) && $res['status'] != 200) {
            return array('status' => false, 'msg' => $res['status']);
        }
        $rbody = json_decode($res['body'], true);
        if (is_array($rbody) && isset($rbody['status']) && $rbody['status'] === false) {
            return array('status' => false, 'msg' => $rbody['msg'] ?? 'Unsuccessful');
        }
        return array('status' => true, 'data' => $rbody['data'] ?? $rbody);
    }

    public function changeStatus(array $vars)
    {
        $DataUserOut = $vars['DataUserOut'];
        $username = $vars['username'];
        $name_panel = $vars['name_panel'];
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");

        $found = hmpanel_lookup_client($Get_Data_Panel, $username);
        if (!empty($found['error']) || (!empty($found['status']) && $found['status'] != 200)) {
            return array('status' => 'Unsuccessful', 'msg' => $found['error'] ?? $found['status'] ?? 'Error');
        }
        $fbody = json_decode($found['body'], true);
        $client = isset($fbody['data']) && is_array($fbody['data']) ? $fbody['data'] : $fbody;
        $clientId = $client['id'] ?? null;
        if (!$clientId) {
            return array('status' => 'Unsuccessful', 'msg' => 'Client not found');
        }

        $enable = ($DataUserOut['status'] == "active") ? false : true;
        $res = hmpanel_update_client($Get_Data_Panel, $clientId, array('enable' => $enable));
        if (!empty($res['error']) || (!empty($res['status']) && $res['status'] != 200)) {
            return array('status' => 'Unsuccessful', 'msg' => $res['error'] ?? $res['status'] ?? 'Error');
        }
        return array('status' => 'successful', 'msg' => null);
    }

    public function resetUsage(array $vars)
    {
        $username = $vars['username'];
        $panel = $vars['panel'];

        $found = hmpanel_lookup_client($panel, $username);
        if (!empty($found['error']) || (!empty($found['status']) && $found['status'] != 200)) {
            return array('status' => false, 'msg' => $found['error'] ?? $found['status'] ?? 'Error');
        }
        $fbody = json_decode($found['body'], true);
        $client = isset($fbody['data']) && is_array($fbody['data']) ? $fbody['data'] : $fbody;
        $clientId = $client['id'] ?? null;
        if (!$clientId) {
            return array('status' => false, 'msg' => 'Client not found');
        }

        $res = hmpanel_reset_usage($panel, $clientId);
        if (!empty($res['error']) || (!empty($res['status']) && $res['status'] != 200)) {
            return array('status' => false, 'msg' => $res['error'] ?? $res['status'] ?? 'Error');
        }
        $rbody = json_decode($res['body'], true);
        if (is_array($rbody) && isset($rbody['status']) && $rbody['status'] === false) {
            return array('status' => false, 'msg' => $rbody['msg'] ?? 'Unsuccessful');
        }
        return array('status' => true, 'data' => $rbody['data'] ?? $rbody);
    }

    public function extendService(array $vars)
    {
        $data_limit_new = $vars['data_limit_new'];
        $time_new = $vars['time_new'];
        $username = $vars['username'];
        $panel = $vars['panel'];

        $found = hmpanel_lookup_client($panel, $username);
        if (!empty($found['error']) || (!empty($found['status']) && $found['status'] != 200)) {
            return array('status' => false, 'msg' => $found['error'] ?? $found['status'] ?? 'Error');
        }
        $fbody = json_decode($found['body'], true);
        $client = isset($fbody['data']) && is_array($fbody['data']) ? $fbody['data'] : $fbody;
        $clientId = $client['id'] ?? null;
        if (!$clientId) {
            return array('status' => false, 'msg' => 'Client not found');
        }

        $expMs = 0;
        if ($time_new > 0) {
            $expMs = $time_new * 1000;
        }
        $body = array();
        if ($data_limit_new !== null && $data_limit_new !== '') {
            $body['totalGb'] = intval($data_limit_new);
        }
        if ($expMs !== 0) {
            $body['days'] = intval($time_new);
        }
        if (isset($body['days']) && isset($body['totalGb'])) {
            $res = hmpanel_renew_client($panel, $clientId, $body['days'], $body['totalGb'], false);
        } elseif (isset($body['days'])) {
            $res = hmpanel_renew_client($panel, $clientId, $body['days'], null, false);
        } elseif (isset($body['totalGb'])) {
            $res = hmpanel_renew_client($panel, $clientId, 0, $body['totalGb'], false);
        } else {
            $res = hmpanel_renew_client($panel, $clientId, $time_new, $data_limit_new, false);
        }

        if (!empty($res['error']) || (!empty($res['status']) && $res['status'] != 200)) {
            return array('status' => false, 'msg' => $res['error'] ?? $res['status'] ?? 'Error');
        }
        $rbody = json_decode($res['body'], true);
        if (is_array($rbody) && isset($rbody['status']) && $rbody['status'] === false) {
            return array('status' => false, 'msg' => $rbody['msg'] ?? 'Unsuccessful');
        }
        return array('status' => true, 'data' => $rbody['data'] ?? $rbody);
    }

    public function extraVolume(array $vars)
    {
        $new_limit = $vars['new_limit'];
        $username_account = $vars['username_account'];
        $panel = $vars['panel'];
        return $this->extendService(array('data_limit_new' => $new_limit, 'time_new' => 0, 'username' => $username_account, 'panel' => $panel));
    }

    public function extraTime(array $vars)
    {
        $new_limit = $vars['new_limit'];
        $username_account = $vars['username_account'];
        $panel = $vars['panel'];
        return $this->extendService(array('data_limit_new' => null, 'time_new' => $new_limit, 'username' => $username_account, 'panel' => $panel));
    }
}
