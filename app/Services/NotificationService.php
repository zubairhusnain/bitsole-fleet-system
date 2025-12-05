<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;
use App\Models\DeviceGroup;
use App\Services\PermissionService;
use App\Jobs\AssignNotificationToDevices;
use Illuminate\Support\Facades\DB;
use App\Models\TcDeviceNotification;
class NotificationService
{
    use Curl;
    public function allnotification($request)
    {
        if(isset($request->device_detail_id)){
            return $this->deviceNotification($request);
        }
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $type = 'GET';
        $notification = [];
        $response = static::curl('/api/notifications', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $result = json_decode($response->response,true);
        if(!empty($result)){
            $notification = $result;
        }
        $notificationType = static::curl('/api/notifications/types', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $types = json_decode($notificationType->response,true);
        $notificationTypeList = [];
        $alarmdata = [];
        // Example usage
        $removeValue = array('media','textMessage','maintenance','commandResult');
        $alarmTypes = array('general','sos','lowBattery','powerOff','vibration','accident','geoFenceEnter','geoFenceExit','overSpeed');
        if(!empty($types)){
            foreach($types as $key=>$value){
                if ($this->stringExistsInNestedArray($value['type'], $removeValue)){
                    unset($value['type']);
                }
                if(isset($value['type'])){
                    $id = $this->notificationIdForType($notification, $value['type']);
                    $exist = $id > 0;
                    $data = array(
                        "id"=> $id,
                        "type"=> $value['type'],
                        "always"=> true,
                        "web"=> true,
                        "mail"=> true,
                        "sms"=> true,
                        "calendarId"=> 0,
                        "attributes"=> [],
                        "already_xist"=>$exist,
                    );
                    array_push($notificationTypeList,$data);
                }
            }
        }
        $alarmTypeList = [];
        if(!empty($alarmTypes)){
            foreach($alarmTypes as $key=>$value){

                if(isset($value)){
                    $id = $this->notificationIdForType($notification, 'alarm', $value);
                    $exist = $id > 0;
                    $data2 = array(
                        "id"=> $id,
                        "type"=> 'alarm',
                        "always"=> true,
                        "web"=> true,
                        "mail"=> true,
                        "sms"=> true,
                        "calendarId"=> 0,
                        "attributes"=> array('alarms'=>$value),
                        "already_xist"=>$exist,
                    );
                    array_push($alarmTypeList,$data2);
                }
            }
        }

        $alarmdata['alarmType'] = $alarmTypeList;
        $alarmdata['notificationType'] = $notificationTypeList;
        return $alarmdata;
    }

    public function deviceNotification($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $type = 'GET';
        $notification = [];
        $deviceAlarm = (isset($request->device_detail_id)) ? $request->device_detail_id:0;
        $response = static::curl('/api/notifications', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $result = json_decode($response->response,true);

        if(!empty($result)){
            $notification = $result;
        }
        $notificationType = static::curl('/api/notifications/types', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $types = json_decode($notificationType->response,true);
        $notificationTypeList = [];
        $alarmdata = [];
        // Example usage
        $removeValue = array();
        $alarmTypes = array('general','sos','lowBattery','powerOff','vibration','accident','geoFenceEnter','geoFenceExit','overSpeed');
        $idsData = [];
        $deviceId = (int) ($request->device_detail_id ?? 0);
        $deviceNotificationIds = $deviceId > 0
            ? TcDeviceNotification::query()
                ->where('deviceid', $deviceId)
                ->pluck('notificationid')
                ->all()
            : [];

        if(!empty($types)){
            foreach($types as $key=>$value){
                if ($this->stringExistsInNestedArray($value['type'], $removeValue)){
                    unset($value['type']);
                }
                if(isset($value['type'])){
                    $id = $this->notificationIdForType($notification, $value['type']);
                    if($id > 0){
                        $exist = in_array($id, $deviceNotificationIds, true);
                        $data = array(
                            "id"=> $id,
                            "type"=> $value['type'],
                            "always"=> true,
                            "web"=> true,
                            "mail"=> true,
                            "sms"=> true,
                            "calendarId"=> 0,
                            "attributes"=> [],
                            "already_xist"=>$exist,
                        );
                        array_push($notificationTypeList,$data);
                    }
                }
            }
        }
        $alarmTypeList = [];
        if(!empty($alarmTypes)){
            foreach($alarmTypes as $key=>$value){

                if(isset($value)){
                    $id = $this->notificationIdForType($notification, 'alarm', $value);
                    if($id > 0){
                        $exist = in_array($id, $deviceNotificationIds, true);
                        $data2 = array(
                            "id"=> $id,
                            "type"=> 'alarm',
                            "always"=> true,
                            "web"=> true,
                            "mail"=> true,
                            "sms"=> true,
                            "calendarId"=> 0,
                            "attributes"=> array('alarms'=>$value),
                            "already_xist"=>$exist,
                        );
                        array_push($alarmTypeList,$data2);
                    }
                }
            }
        }

        $alarmdata['alarmType'] = $alarmTypeList;
        $alarmdata['notificationType'] = $notificationTypeList;
        return $alarmdata;
    }

    public function findArrayIndex($needle, $haystack) {
        foreach ($haystack as $key => $value) {
            if (is_array($value)) {
                // Recursively search in the child array
                if ($this->findArrayIndex($needle, $value) !== null) {
                    return $key; // Return the parent key
                }
            } elseif ($value === $needle) {
                return $key; // Return the key of the parent array
            }
        }
        return null; // String not found
    }

    public function stringExistsInNestedArray($needle, $haystack) {
        foreach ($haystack as $value) {
            if (is_array($value)) {
                // Recursively check nested arrays
                if ($this->stringExistsInNestedArray($needle, $value)) {
                    return true;
                }
            } elseif ($value === $needle) {
                // String found
                return true;
            }
        }
        return false;
    }

    private function notificationIdForType(array $notifications, string $type, ?string $alarmName = null): int
    {
        foreach ($notifications as $n) {
            if (!is_array($n)) { continue; }
            $nid = isset($n['id']) ? (int)$n['id'] : 0;
            $ntype = isset($n['type']) ? (string)$n['type'] : '';
            if ($type === 'alarm') {
                if ($ntype === 'alarm') {
                    $attrs = isset($n['attributes']) && is_array($n['attributes']) ? $n['attributes'] : [];
                    $alarms = isset($attrs['alarms']) ? (string)$attrs['alarms'] : '';
                    if ($alarmName !== null && $alarms === $alarmName) { return $nid; }
                }
            } else {
                if ($ntype === $type) { return $nid; }
            }
        }
        return 0;
    }

    public function addNotification($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data = $request->all();
        // dd($data);
        $error = [];
        $devices = static::curl('/api/devices', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $userDevices = json_decode($devices->response);
        if(!empty($data)){
            foreach($data as $key=>$value){
                $type = 'POST';
                // dd($value);
                $id = '';
                $already_xist = $value['already_xist'] ? true : false;
                $notificators = $value['web'] ? "web,":"";
                $notificators.= $value['mail'] ? "mail,":"";
                $notificators.= $value['sms'] ? "firebase":"";
                $notificators = rtrim($notificators, ',');
                $value['notificators'] = $notificators;
                $attributes = [];
                $attributes = (!empty($value['attributes'])) ? json_encode($value['attributes']): json_encode(new \stdClass());

                $data = '{"id": '.$value['id'].',"type": "'.$value['type'].'","always": true,"calendarId": 0,"attributes": '.$attributes.',"notificators": "'.$notificators.'"}';

                if(isset($value['id']) && $value['id'] !==0 && $already_xist == false){
                    $type = "DELETE";
                    $id = '/'.$value['id'];
                    $data = '';
                }

                $response = static::curl('/api/notifications'.$id, $type, $sessionId, $data, array('Content-Type: application/json', 'Accept: application/json'));
                if(($type == 'POST' || $type == 'PUT') && ($response->responseCode < 200 || $response->responseCode >= 300)){
                    array_push($error,$response);
                }else if($type == 'DELETE' && $response->responseCode!==204){
                    // Traccar returns 204 for successful delete
                    array_push($error,['error'=>$response->error,'data'=>$data]);
                }
            }
        }
        return $error;
    }

    public function deleteGroup($request)
    {
        $id = $request->groupId;
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $response = static::curl('/api/groups/' . $id, 'DELETE', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        return $response;
    }

    public function resetNotification(){
        $sessionId = session('cookie');
        $type = 'GET';
        $notification = [];

        $response = static::curl('/api/notifications', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $result = json_decode($response->response,true);
        if(!empty($result)){
            $notification = $result;
            foreach($notification as $key=>$value){
                $type = "DELETE";
                $id = '/'.$value['id'];
                $response = static::curl('/api/notifications'.$id, $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
            }
            dd($notification);
        }
    }

    public function getAllNotification($session){
        $sessionId = $session;
        $type = 'GET';
        $response = static::curl('/api/notifications', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $result = json_decode($response->response,true);
        return $result;
    }
}
?>
