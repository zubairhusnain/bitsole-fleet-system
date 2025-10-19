<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;

class DriverService
{
        use Curl;

    public function allDrivers(){
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        // if ($id != '') {
        //     $data = 'id=' . $id;
        // }
        $data = static::curl('/api/drivers?', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));

        $user_data = json_decode($data->response);
        // print_r($users);die();
        $drivers=array();
        foreach ($user_data as $key=>$driver) {
            $drivers[$key]['id'] = $driver->id;
            $drivers[$key]['name'] = $driver->name;
            $drivers[$key]['uniqueId'] = $driver->uniqueId;
        }
        return $drivers;
    }

    public function getDriverByDevice($device_id){
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        if ($id != '') {
            $data = '?deviceId=' . $device_id;
        }
        $data = static::curl('/api/drivers'.$data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));

        $user_data = json_decode($data->response);
        // print_r($users);die();
        $drivers=array();
        foreach ($user_data as $key=>$driver) {
            $drivers[$key]['id'] = $driver->id;
            $drivers[$key]['name'] = $driver->name;
            $drivers[$key]['uniqueId'] = $driver->uniqueId;

        }

        return $drivers;
    }

    public static function driverAdd($request){
        $data = $request->all();
        $data['uniqueId'] = time().rand();
        $data = json_encode(value: $data);
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        return self::curl('/api/drivers','POST',$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
    }

    // public static function assigndriver($sessionId,$userId,$driverId){

    //     $id='id='.$id;
    //     $data='{"userId":"'.$userId.'","driverId":'.$driverId.'}';

    //     return self::curl('/api/permissions','POST',$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
    // }



    public function updateDriver($request,$id)
    {
        // dd($request->all());
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data=$request->all();
        $data['id'] = $id;
        $data = json_encode(value: $data);
        return self::curl('/api/drivers/'.$id,'PUT',$sessionId,$data, array('Content-Type: application/json', 'Accept: application/json'));
    }

    public static function driverDelete($request,$id){
        // {$id='id='.$id;}
        $sessionId= $request->user()->traccarSession ?? session('cookie');
        return self::curl('/api/drivers/'.$id,'DELETE',$sessionId,'',array('Content-Type: application/json', 'Accept: application/json'));
    }
}
?>
