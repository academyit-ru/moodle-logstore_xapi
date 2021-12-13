<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace src\loader\moodle_curl_lrs;
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (!isset($CFG)) {
    $CFG = (object) [ 'libdir' => 'utils' ];
}
require_once($CFG->libdir . '/filelib.php');

use src\loader\utils as utils;

function load(array $config, array $events) {
    $sendhttpstatements = function (array $config, array $statements) {
        $endpoint = $config['lrs_endpoint'];
        $token = $config['lrs_token'];
        $curlsettings = [];
        $proxyendpoint = $config['lrs_proxy_endpoint'] ?? null;
        if (isset($proxyendpoint)) {
            $curlsettings['proxy'] = true;
            $curlsettings['proxy_host'] = $proxyendpoint;
        }
        $noop =  function () {};
        $logerror = $config['log_error'] ?? $noop;
        $loginfo = $config['log_info'] ?? $noop;

        $url = utils\correct_endpoint($endpoint).'/statements';
        $postdata = json_encode($statements);

        $curlsettingscfg = get_config('logstore_xapi', 'curlsettings');
        $curlsettingscfg = json_decode($curlsettingscfg, true);
        if (is_array($curlsettingscfg)) {
            $curlsettings = array_merge($curlsettings, $curlsettingscfg);
        }
        $request = new \curl($curlsettings);

        $responsetext = $request->post($url, $postdata, [
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Basic '.$token,
                'X-Experience-API-Version: 1.0.3',
                'Content-Type: application/json; charset=utf-8',
            ],
        ]);

        $responsecode = (int) $request->info['http_code'];

        if ($responsecode !== 200 || $request->error) {
            $errcontext = json_encode([
                'curl response' => $responsetext,
                'curl error' => $request->error,
                'curl info' => $request->info
            ]);
            $localerrorid = 'IT-ERR-' . sha1($errcontext);
            $errmessage = sprintf('[LOADER:MOODLE_CURL_LRS][ERROR] localErrorId: %s, Context: %s', $errcontext);
            call_user_func($logerror, $errmessage);

            $erronousrequestinfo = json_encode([
                'curl info' => $request->info
            ]);
            $erronousrequestline = sprintf(
                '[LOADER:MOODLE_CURL_LRS][ERROR][ERRONOUS_REQUEST_INFO] localErrorId: %s Request info: %s, Request body: %s',
                $localerrorid,
                $erronousrequestinfo,
                $postdata
            );
            call_user_func($logerror, $erronousrequestline);

            throw new \Exception($responsetext);
        }
        call_user_func($loginfo, sprintf('[LOADER:MOODLE_CURL_LRS][INFO] Context %s', json_encode([
            'curl response' => $responsetext,
            'curl info' => $request->info
        ])));
    };
    return utils\load_in_batches($config, $events, $sendhttpstatements);
}