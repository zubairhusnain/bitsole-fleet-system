<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;
use App\Models\DeviceGroup;
use App\Services\PermissionService;
use App\Jobs\AssignNotificationToDevices;
use Illuminate\Support\Facades\DB;
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
        $removeValue = array('media','textMessage','maintenance','commandResult','alarm');
        $alarmTypes = array('general','sos','lowBattery','powerOff','vibration','accident','geoFenceEnter','geoFenceExit','overSpeed');
        if(!empty($types)){
            foreach($types as $key=>$value){
                if ($this->stringExistsInNestedArray($value['type'], $removeValue)){
                    unset($value['type']);
                }
                if(isset($value['type'])){
                    $exist = false;
                    $id = 0;
                    if ($this->stringExistsInNestedArray($value['type'], $notification)){
                        $exist = true;
                    }
                    if($this->findArrayIndex($value['type'], $notification)  !== null ){
                        $id = $this->findArrayIndex($value['type'], $notification);
                    }
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
                    $exist = false;
                    $id = 0;
                    if ($this->stringExistsInNestedArray($value, $notification)){
                        $exist = true;
                    }
                    if($this->findArrayIndex($value, $notification) !== null ){
                        $id = $this->findArrayIndex($value, $notification);
                    }
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
        $removeValue = array('media','textMessage','maintenance','commandResult','alarm');
        $alarmTypes = array('general','sos','lowBattery','powerOff','vibration','accident','geoFenceEnter','geoFenceExit','overSpeed');
        $idsData = [];

        if(!empty($types)){
            foreach($types as $key=>$value){
                if ($this->stringExistsInNestedArray($value['type'], $removeValue)){
                    unset($value['type']);
                }
                if(isset($value['type'])){
                    $id = 0;
                    if($this->findArrayIndex($value['type'], $notification)  !== null ){
                        $id = $this->findArrayIndex($value['type'], $notification);
                    }
                    if($id > 0){
                        $exist = true;
                        $deviceNotifications = DB::connection('mysqlTraccar')
                        ->table('tc_device_notification')
                        ->where('deviceid', $request->device_detail_id)
                        ->where('notificationid', $id)
                        ->first(); // Returns a collection
                        if(empty($deviceNotifications)){
                            $exist = false;
                            array_push($idsData,array($deviceNotifications,$exist));
                        }
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
                    $id = 0;
                    if($this->findArrayIndex($value, $notification) !== null ){
                        $id = $this->findArrayIndex($value, $notification);
                    }
                    if($id > 0){
                        $exist = true;
                        $deviceNotifications = DB::connection('mysqlTraccar')
                        ->table('tc_device_notification')
                        ->where('deviceid', $request->device_detail_id)
                        ->where('notificationid', $id)
                        ->first(); // Returns a collection
                        if(empty($deviceNotifications)){
                            $exist = false;
                            array_push($idsData,array($deviceNotifications,$exist));
                        }
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
                $value['notificators'] = $notificators;
                $attributes = [];
                $attributes = (!empty($value['attributes'])) ? json_encode($value['attributes']): json_encode([]);

                $data = '{"id": '.$value['id'].',"type": "'.$value['type'].'","always": true,"calendarId": 0,"attributes": '.$attributes.',"notificators": "'.$notificators.'"}';
                if(empty($value['attributes'])){
                    $data = '{"id": '.$value['id'].',"type": "'.$value['type'].'","always": true,"calendarId": 0,"attributes":{},"notificators": "'.$notificators.'"}';
                }
                if(isset($value['id']) && $value['id'] !==0 && $already_xist == false){
                    $type = "DELETE";
                    $id = '/'.$value['id'];
                    $data = '';
                }

                $response = static::curl('/api/notifications'.$id, $type, $sessionId, $data, array('Content-Type: application/json', 'Accept: application/json'));
                if($response->responseCode!==200 && $type == 'POST'){
                    array_push($error,$response);
                }else if($type == 'DELETE' && $response->responseCode!==204){
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
