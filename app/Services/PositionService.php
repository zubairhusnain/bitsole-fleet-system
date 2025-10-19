<?php

namespace App\Services;

use DateTime;
use DateTimeZone;
use App\Helpers\Curl;
use Carbon\Carbon;
use App\Helpers\Helpers;
class PositionService
{
    use Curl;
    public function getAllDevicesStatus()
    {
        $sessionId = session('cookie');
        $position_response = static::curl('/api/positions', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $position_response = json_decode($position_response->response);
        $count['inActive'] = 0;
        $count['moving'] = 0;
        $count['idle'] = 0;
        $count['stopped'] = 0;
        $count['overspeed'] = 0;
        foreach ($position_response as $key => $value) {
            if (strtotime($value->serverTime) <= strtotime('-1 hour')) {
                $count['inActive']++;
            } else {
                if ($value->attributes->motion == 1) {
                    $count['moving']++;
                }
                if (isset($value->attributes->ignition)&&$value->attributes->ignition == 1 && $value->attributes->motion == 0  && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $count['idle']++;
                }
                if (isset($value->attributes->motion) && $value->attributes->motion == 0 && isset( $value->attributes->ignition) && $value->attributes->ignition == 0  && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $count['stopped']++;
                }
                if (isset($value->attributes->alarm) && $value->attributes->alarm == "overspeed" && $value->attributes->motion == 1  && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $count['overspeed']++;
                }
            }
        }


        return $count;
    }


    public function getDevicesByStatus($filter)
    {
        $sessionId = session('cookie');
        $position_response = static::curl('/api/positions', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $position_response = json_decode($position_response->response);

        $id = session('tc_user_id');
        $data = 'id=' . $id;
        $devices = static::curl('/api/devices?all=true' . $data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $devices = json_decode($devices->response);
        $i = 0;
        $check = 0;
        $response = [];

        foreach ($position_response as $key => $value) {

            if ($filter == "moving") {
                if (isset($value->attributes->motion) && $value->attributes->motion == 1  && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $check = 1;
                    $response[$i]['speed'] = isset($value->speed) ? $value->speed : null;
                    $response[$i]['address'] =  isset($value->address) ? $value->address : null;
                    $response[$i]['battery'] = isset($value->attributes->batteryLevel) ? $value->attributes->batteryLevel : "-";
                    $response[$i]['status'] = "moving";
                    $response[$i]['ignition'] = isset($value->attributes->ignition) ? $value->attributes->ignition : null;
                    $response[$i]['gpsStatus'] = isset($value->attributes->sat) ? $value->attributes->sat : "-";
                    $response[$i]['serverTime'] = isset($value->serverTime) ? Helpers::servertime($value->serverTime): '';
                    $response[$i]['device_status'] = isset($value->status) ? $value->status : "-";

                }
                    // print_r($devices);die();
            } elseif ($filter == "idle") {
                if (isset($value->attributes->ignition)&&$value->attributes->ignition == 1 && $value->attributes->motion == 0  && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $check = 1;
                    $response[$i]['speed'] = isset($value->speed) ? $value->speed : null;
                    $response[$i]['address'] = isset($value->address) ? $value->address : null;
                    $response[$i]['battery'] = isset($value->attributes->batteryLevel) ? $value->attributes->batteryLevel : "-";
                    $response[$i]['status'] = "idle";
                    $response[$i]['ignition'] = isset($value->attributes->ignition) ? $value->attributes->ignition : null;
                    $response[$i]['gpsStatus'] = isset($value->attributes->sat) ? $value->attributes->sat : "-";
                    $response[$i]['serverTime'] = isset($value->serverTime) ? Helpers::servertime($value->serverTime):'';
                    $response[$i]['device_status'] = isset($value->status) ? $value->status : "-";


                }
            } elseif ($filter == "stopped") {
                if (isset($value->attributes->motion)&&$value->attributes->motion == 0 && isset($value->attributes->ignition) && $value->attributes->ignition == 0  && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $check = 1;
                    $response[$i]['speed'] = isset($value->speed) ? $value->speed : null;
                    $response[$i]['address'] = isset($value->address) ? $value->address : null;
                    $response[$i]['battery'] = isset($value->attributes->batteryLevel) ? $value->attributes->batteryLevel : "-";
                    $response[$i]['status'] = "stopped";
                    $response[$i]['ignition'] = isset($value->attributes->ignition) ? $value->attributes->ignition : null;
                    $response[$i]['gpsStatus'] = isset($value->attributes->sat) ? $value->attributes->sat : "-";
                    $response[$i]['serverTime'] = isset($value->serverTime) ? Helpers::servertime($value->serverTime) : "-";
                    $response[$i]['device_status'] = isset($value->status) ? $value->status : "-";


                }
            } elseif ($filter == "overSpeed") {
                if (isset($value->attributes->alarm) && $value->attributes->alarm == "overspeed" && $value->attributes->motion == 1 && (strtotime($value->serverTime) >= strtotime('-1 hour'))) {
                    $check = 1;
                    $response[$i]['speed'] = isset($value->speed) ? $value->speed : null;
                    $response[$i]['address'] = isset($value->address) ? $value->address : null;
                    $response[$i]['battery'] = isset($value->attributes->batteryLevel) ? $value->attributes->batteryLevel : "-";
                    $response[$i]['status'] = "overSpeed";
                    $response[$i]['ignition'] = isset($value->attributes->ignition) ? $value->attributes->ignition : null;
                    $response[$i]['gpsStatus'] = isset($value->attributes->sat) ? $value->attributes->sat : "-";
                    $response[$i]['serverTime'] = isset($value->serverTime) ? Helpers::servertime($value->serverTime) : "-";
                    $response[$i]['device_status'] = isset($value->status) ? $value->status : "-";


                }
            }
            elseif ($filter == "inActive") {
                if (strtotime($value->serverTime) <= strtotime('-1 hour')) {
                    $check = 1;
                    $response[$i]['speed'] = isset($value->speed) ? $value->speed : null;
                    $response[$i]['address'] = isset($value->address) ? $value->address : null;
                    $response[$i]['gpsStatus'] = isset($value->attributes->sat) ? $value->attributes->sat : "-";
                    $response[$i]['ignition'] = isset($value->attributes->ignition) ? $value->attributes->ignition : null;
                    $response[$i]['battery'] = isset($value->attributes->batteryLevel) ? $value->attributes->batteryLevel : "-";
                    $response[$i]['status'] = "inActive";
                    $response[$i]['serverTime'] = isset($value->serverTime) ? Helpers::servertime($value->serverTime) : "-";
                    $response[$i]['device_status'] = isset($value->status) ? $value->status : "-";

                }
            }

            if ($check == 1) {
                foreach ($devices as $key => $device) {
                    if ($device->id == $value->deviceId) {
                        $response[$i]['id'] = $device->id;
                        $response[$i]['name'] =  $device->name ?? isset($device->name)? $device->name : null;
                        $response[$i]['category'] = $device->category;
                        $response[$i]['lastUpdate'] = isset($device->lastUpdate) ? Helpers::servertime($device->lastUpdate): "-";
                        // $engin = json_decode(json_encode($device->attributes),true);
                        // $response[$i]['Engine'] =(explode('/',$engin['Engine/Chasis'])[0]);
                        // $chasis = json_decode(json_encode($device->attributes),true);
                        // $response[$i]['Chasis'] =(explode('/',$chasis['Engine/Chasis'])[1]);
                        $startDate = new DateTime($device->lastUpdate);
                        $endDate   = new DateTime();

                        $response[$i]['since'] = ($startDate->diff($endDate)->days);
                    }
                }
                $response[$i]['device_detail'] = $device->id;
                $check = 0;
                $i++;
            }

        }
            // print_r($devices);die();
            // print_r($position_response);die();

        return $response;
    }


    public function getAllPositions($positionId,$user)
    {
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        if ($id != '') {
            $data = 'id=' . $id;
        }
        $param = '';
        if($positionId!==''){
            $param = '/?id='.$positionId;
        }
        $data = static::curl('/api/positions'.$param, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $veh_data = json_decode($data->response);
        // $total = 0;
        $veh_details = [];
        // $veh_ids;
        // $vnames;
        $veh_detail = [];
        if (!empty($veh_data)) {
            foreach ($veh_data as $key2 => $position) {
                if (!empty($position)) {
        $sessionId = session('cookie');
                    $position_response = static::curl('/api/devices?id=' . $position->deviceId, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
                    $veh = json_decode($position_response->response);
                    $position->data = $veh;
                    $veh_data = [];
                    foreach ($veh as $key => $value) {
                        if (!empty($value)) {
                            $veh_data['category'] = isset($value->category) ? $value->category : '';
                            $veh_data['deviceId'] = isset($value->id) ? $value->id : '';
                            $veh_data['course'] = isset($position->course) ? $position->course : '';
                            $veh_data['device_name'] = isset($value->name) ? $value->name : '';
                            $veh_data['latitude'] = isset($position->latitude) ? $position->latitude : '';
                            $veh_data['longitude'] = isset($position->longitude) ? $position->longitude : '';
                            $veh_data['speed'] = isset($position->speed) ? number_format($position->speed * 1.85, 2, '.', '') : '';
                            $veh_data['serverTime'] = isset($position->serverTime) ? date('Y-m-d h:i',strtotime(Helpers::servertime($position->serverTime,$user->id))) : '';
                            $veh_data['ignition'] = isset($position->attributes->ignition) ? $position->attributes->ignition : false;
                            $veh_data['fuel'] = isset($position->attributes->fuel) ? $position->attributes->fuel : '';
                            $veh_data['lastUpdate'] = isset($value->lastUpdate) ? date('M d g:i a',strtotime(Helpers::servertime($value->lastUpdate,$user->id))) : ' ';
                            $veh_data['device_status'] = isset($value->status) ? $value->status : "-";
                            $veh_data['battery'] = isset($position->attributes->batteryLevel) ? $position->attributes->batteryLevel : "-";
                            $veh_data['status'] = isset($value->status) ? $value->status : "-";
                            $veh_data['positionData'] = $position;
                            array_push($veh_details, $veh_data);
                        }
                    }
                }
            }
        }
        return $veh_details;
    }
}
