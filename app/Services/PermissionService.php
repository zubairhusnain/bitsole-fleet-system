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
            $data='{"deviceId":"'.$device_id.'","geofenceId":'.$geo_id.'}';
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
                if(isset($request->already_xist) && $request->already_xist== false){
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
}
