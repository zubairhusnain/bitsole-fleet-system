<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;

class DriverService
{
    use Curl;

    public function allDrivers()
    {
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        // if ($id != '') {
        //     $data = 'id=' . $id;
        // }
        $data = static::curl('/api/drivers?', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));

        $user_data = json_decode($data->response);
        // print_r($users);die();
        $drivers = array();
        foreach ($user_data as $key => $driver) {
            $drivers[$key]['id'] = $driver->id;
            $drivers[$key]['name'] = $driver->name;
            $drivers[$key]['uniqueId'] = $driver->uniqueId;
        }
        return $drivers;
    }

    public function getDriverByDevice($device_id)
    {
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        if ($id != '') {
            $data = '?deviceId=' . $device_id;
        }
        $data = static::curl('/api/drivers' . $data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));

        $user_data = json_decode($data->response);
        // print_r($users);die();
        $drivers = array();
        foreach ($user_data as $key => $driver) {
            $drivers[$key]['id'] = $driver->id;
            $drivers[$key]['name'] = $driver->name;
            $drivers[$key]['uniqueId'] = $driver->uniqueId;
        }

        return $drivers;
    }

    public static function driverAdd($request)
    {
        $sessionId = $request->user()->traccarSession ?? session(key: 'cookie');

        // Normalize and whitelist payload to Traccar-supported fields
        $input = $request->all();
        if (isset($input['attributes']) && is_string($input['attributes'])) {
            $decoded = json_decode($input['attributes'], true);
            $input['attributes'] = is_array($decoded) ? $decoded : [];
        }

        $allowed = ['id', 'name', 'uniqueId', 'attributes'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = $input[$key];
            }
        }
        // Defaults per required shape
        if (!isset($data['id'])) {
            $data['id'] = 0; // Traccar will assign a real id
        }
        if (!isset($data['attributes']) || !is_array($data['attributes'])) {
            $data['attributes'] = [];
        }

        $payload = json_encode($data);
        return self::curl('/api/drivers', 'POST', $sessionId, $payload, ['Content-Type: application/json', 'Accept: application/json']);
    }

    // public static function assigndriver($sessionId,$userId,$driverId){

    //     $id='id='.$id;
    //     $data='{"userId":"'.$userId.'","driverId":'.$driverId.'}';

    //     return self::curl('/api/permissions','POST',$sessionId,$data,array('Content-Type: application/json', 'Accept: application/json'));
    // }

    public function updateDriver($request, $id)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');

        // Normalize and whitelist payload to Traccar-supported fields
        $input = $request->all();
        if (isset($input['attributes']) && is_string($input['attributes'])) {
            $decoded = json_decode($input['attributes'], true);
            $input['attributes'] = is_array($decoded) ? $decoded : [];
        }

        $allowed = ['id', 'name', 'uniqueId', 'attributes'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = $input[$key];
            }
        }
        // Ensure path id aligns
        $data['id'] = $id;
        if (!isset($data['attributes']) || !is_array($data['attributes'])) {
            $data['attributes'] = [];
        }

        $payload = json_encode($data);
        return self::curl('/api/drivers/' . $id, 'PUT', $sessionId, $payload, ['Content-Type: application/json', 'Accept: application/json']);
    }

    public static function driverDelete($request, $id)
    {
        // {$id='id='.$id;}
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        return self::curl('/api/drivers/' . $id, 'DELETE', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
    }
}
