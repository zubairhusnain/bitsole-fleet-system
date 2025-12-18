<?php

namespace App\Services;
use App\Helpers\Curl;
use Illuminate\Http\Request;



class PermissionService
{
        use Curl;
        public function assignDriver($request,$device_id,$driver_id){
            $sessionId = $request->user()->traccarSession ?? session('cookie');
            $userId = session('tc_user_id');
            $data='{"deviceId":"'.$device_id.'","driverId":"'.$driver_id.'"}';
            $devices = static::curl('/api/permissions', 'POST',$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
            return $devices->response;
        }

        public function assignUser($device_id, $user_id, $type, $request = null){
            $sessionId = session('cookie');
            $userId = session('tc_user_id');
            $data='{"userId":"'.$user_id.'","deviceId":"'.$device_id.'"}';
            $method = "POST";
            if($type=='false'){
                $method = "DELETE";
            }
            $devices = static::curl('/api/permissions', $method,$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
            return $devices->response;
        }

        public function assignGeofence($request,$device_id,$geo_id,$type){
            $sessionId = $request->user()->traccarSession ?? session('cookie');
            $data = json_encode(['deviceId' => (int)$device_id, 'geofenceId' => (int)$geo_id]);
            $devices = static::curl('/api/permissions', $type, $sessionId, $data,array('Content-Type: application/json', 'Accept: application/json'));
            return $devices;
        }


        public function assignGroup($request,$device_id,$groupId){
            $sessionId = $request->user()->traccarSession ?? session('cookie');
            $data='{"deviceId":"'.$device_id.'","groupId":'.$groupId.'}';
            $devices = static::curl('/api/permissions', 'POST', $sessionId, $data,array('Content-Type: application/json', 'Accept: application/json'));
            return $devices->response;
        }

        public function assignNotification($request,$deviceId,$notificationId){
        if($deviceId !==null && $notificationId !==null){
            $sessionId = $request->user()->traccarSession ?? session('cookie');
            $data='{"deviceId":"'.$deviceId.'","notificationId":"'.$notificationId.'"}';
            $method = "POST";

            // Use strict boolean check on input
            $alreadyExist = filter_var($request->input('already_xist'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            // If explicitly false, then DELETE
            if ($alreadyExist === false) {
                $method = "DELETE";
            }

            $devices = static::curl('/api/permissions', $method,$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
            return $devices->response;
        }
    }

        public function unassignDriver($request,$device_id,$driver_id){
            $sessionId = $request->user()->traccarSession ?? session('cookie');
            $data='{"deviceId":"'.$device_id.'","driverId":"'.$driver_id.'"}';
            $devices = static::curl('/api/permissions', 'DELETE',$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
            return $devices->response;
        }

        public function assignComputedAttributesToAllDevices(Request $request){
            $sessionId = '';
            if ($request instanceof \Illuminate\Http\Request) {
                try {
                    $u = $request->user();
                    if ($u && isset($u->traccarSession)) {
                        $sessionId = $u->traccarSession ?? '';
                    }
                } catch (\Throwable $e) {}
            }
            if ($sessionId === '' || $sessionId === null) {
                $sessionId = session('cookie');
            }
            if (empty($sessionId)) {
                try {
                    $data = 'email=' . \Illuminate\Support\Facades\Config::get('constants.Constants.adminEmail') . '&password=' . \Illuminate\Support\Facades\Config::get('constants.Constants.adminPassword');
                    static::curl('/api/session', 'POST', '', $data, [\Illuminate\Support\Facades\Config::get('constants.Constants.urlEncoded')]);
                    $sessionId = session('cookie');
                } catch (\Throwable $e) {}
            }
            $headers = array('Content-Type: application/json', 'Accept: application/json');
            $devResp = static::curl('/api/devices', 'GET', $sessionId, '', $headers);
            $devices = json_decode($devResp->response ?? '[]', true) ?? [];
            $attrs = [];
            $attrResp = static::curl('/api/attributes/computed', 'GET', $sessionId, '', $headers);
            if ((int)($attrResp->responseCode ?? 0) >= 200 && (int)($attrResp->responseCode ?? 0) < 300) {
                $attrs = json_decode($attrResp->response ?? '[]', true) ?? [];
            }
            if (empty($attrs)) {
                $attrResp2 = static::curl('/api/computedattributes', 'GET', $sessionId, '', $headers);
                if ((int)($attrResp2->responseCode ?? 0) >= 200 && (int)($attrResp2->responseCode ?? 0) < 300) {
                    $attrs = json_decode($attrResp2->response ?? '[]', true) ?? [];
                }
            }
            $ok = 0; $fail = 0; $errors = [];
            foreach ($devices as $d) {
                $did = isset($d['id']) ? (int)$d['id'] : 0;
                if ($did <= 0) { continue; }
                foreach ($attrs as $a) {
                    $aid = isset($a['id']) ? (int)$a['id'] : 0;
                    if ($aid <= 0) { continue; }
                    $data = json_encode([ 'deviceId' => $did, 'attributeId' => $aid ]);
                    try {
                        $resp = static::curl('/api/permissions', 'POST', $sessionId, $data, $headers);
                        $code = (int)($resp->responseCode ?? 0);
                        if ($code >= 200 && $code < 300) { $ok++; } else { $fail++; }
                    } catch (\Throwable $e) {
                        $fail++; $errors[] = $e->getMessage();
                    }
                }
            }
            return [ 'assigned' => $ok, 'failed' => $fail, 'errors' => $errors, 'deviceCount' => count($devices), 'attributeCount' => count($attrs) ];
        }
}
