<?php

namespace App\Services;
use App\Helpers\Helpers;
use App\Helpers\Curl;
use Illuminate\Support\Facades\DB;
use DateTime;
use DateTimeZone;

class LiveLocationService  {
    use Curl;

    public function getDevicePosition($device_id="")
    {
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        $data='id='.$device_id;
        $device_data = static::curl('/api/devices?' . $data, 'GET', $sessionId, '', array());
        $device_data=json_decode($device_data->response);
        $data = 'id=' . $device_data[0]->positionId;
        $sessionId = session('cookie');
        $veh = static::curl('/api/positions?'.$data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $veh=json_decode($veh->response);
        // $veh=print_r($device_data[0]->name);die();
        // print_r($veh);die();
        $from=date('Y-m-d\T', strtotime('-1 day'));
        $to=date('Y-m-d\TH:i');
        $from=$from."23:59:59Z";
        $to=$to.":00Z";
        $data='deviceId='.$device_id.'&from='.$from.'&to='.$to;
        $summary=static::curl('/api/reports/summary?'.$data,'GET',$sessionId,'',array('Content-Type: application/json', 'Accept: application/json'));
        $summary=json_decode($summary->response);

        $position = static::curl('/api/positions', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $position=json_decode($position->response);
        // print_r($position);die();
        foreach($position as $one) {
            if($one->deviceId==$device_id){
                $veh_details['odometer'] = isset($one->attributes->totalDistance ) ? $one->attributes->totalDistance : '-';
                $veh_details['distance'] = isset($one->attributes->distance) ? $one->attributes->distance : '';
                break;
            }
        }

        $veh_details['reg_no'] = isset($device_data[0]->name) ? $device_data[0]->name : '';
        $veh_details['category'] = isset($device_data[0]->category) ? $device_data[0]->category : '';
        $veh_details['address'] = isset($veh[0]->address) ? $veh[0]->address : '';
        $veh_details['latitude'] = isset($veh[0]->latitude) ? $veh[0]->latitude : '';
        $veh_details['longitude'] = isset($veh[0]->longitude) ? $veh[0]->longitude : '';
        $veh_details['speed'] = isset($veh[0]->speed) ? number_format($veh[0]->speed * 1.85, 2, '.', '') : '';
        $veh_details['serverTime'] = isset($device_data[0]->serverTime) ? Helpers::servertime($device_data[0]->serverTime) : '';
        $veh_details['ignition'] = isset($veh[0]->attributes->ignition) ? $veh[0]->attributes->ignition : '';
        $veh_details['battery'] = isset($veh[0]->attributes->batteryLevel) ? $veh[0]->attributes->batteryLevel : '';
        $veh_details['blocked'] = isset($veh[0]->attributes->blocked) ? $veh[0]->attributes->blocked : '';
        $veh_details['sat'] = isset($veh[0]->attributes->sat) ? $veh[0]->attributes->sat : '';
        $veh_details['course'] = isset($veh[0]->course) ? $veh[0]->course : '';

        $veh_details['engineHours'] = isset($summary[0]->engineHours) ? $summary[0]->engineHours: '';
        $veh_details['todayDistance'] = isset($summary[0]->distance) ? $summary[0]->distance: '';
        if (isset($veh[0]->attributes->motion) && $veh[0]->attributes->motion == 1  && (strtotime($veh[0]->serverTime) >= strtotime('-1 hour')))
        $veh_details['status']="moving";
        else if (isset($veh[0]->attributes->ignition) && $veh[0]->attributes->ignition == 1 && $veh[0]->attributes->motion == 0  && (strtotime($veh[0]->serverTime) >= strtotime('-1 hour')))
        $veh_details['status']="idle";
        else if (isset($veh[0]->attributes->motion) && $veh[0]->attributes->motion == 0 && isset($veh[0]->attributes->ignition) && $veh[0]->attributes->ignition == 0  && (strtotime($veh[0]->serverTime) >= strtotime('-1 hour')))
        $veh_details['status']="stopped";
        else if (isset($veh[0]->attributes->alarm) && $veh[0]->attributes->alarm == "overspeed" && $veh[0]->attributes->motion == 1 && (strtotime($veh[0]->serverTime) <= strtotime('-1 hour')))
        $veh_details['status']="overSpeed";
        else if (strtotime($veh[0]->serverTime) <= strtotime('-1 hour'))
        $veh_details['status']="inActive";
        else
        $veh_details['status']="noData";
        // print_r($veh_details);die();


        return $veh_details;

    }
    public function livelocationPosition()
    {

        $sessionId = session('cookie');
        $position_response = static::curl('/api/positions?'  , 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $position_response = json_decode($position_response->response);
        $status_icon=[];
        $status_icon['longitude']=$position_response[0]->longitude;
        $status_icon['latitude']=$position_response[0]->latitude;
        $status_icon['status']="";
        foreach($position_response as $key=>$value){
            if (isset($value->attributes->motion) && $value->attributes->motion == 1  && (strtotime($value->serverTime) >= strtotime('-1 hour')))
            $position_response[$key]->status="moving";
            else if ($value->attributes->ignition == 1 && $value->attributes->motion == 0  && (strtotime($value->serverTime) >= strtotime('-1 hour')))
            $position_response[$key]->status="idle";
            else if ($value->attributes->motion == 0 && $value->attributes->ignition == 0  && (strtotime($value->serverTime) >= strtotime('-1 hour')))
            $position_response[$key]->status="stopped";
            else if (isset($value->attributes->alarm) && $value->attributes->alarm == "overspeed" && $value->attributes->motion == 1 && (strtotime($value->serverTime) <= strtotime('-1 hour')))
            $position_response[$key]->status="overSpeed";
            else if (strtotime($value->serverTime) <= strtotime('-1 hour'))
            $position_response[$key]->status="inActive";
            else
            $position_response[$key]->status="noData";
        }

        // print_r($position_response);die();


        return  $position_response;


    }

    public function livePosition($device_id)
    {
        $sessionId = session('cookie');
        $data = 'id='.$device_id;
        $response = static::curl('/api/positions?'.$data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $positions = json_decode($response->response);

        $veh_details = [
            'latitude' => '',
            'longitude' => ''
        ];

        if (is_array($positions) && !empty($positions) && is_object($positions[0])) {
            $veh_details['latitude'] = isset($positions[0]->latitude) ? $positions[0]->latitude : '';
            $veh_details['longitude'] = isset($positions[0]->longitude) ? $positions[0]->longitude : '';
        }

        return $veh_details;


    }




}
