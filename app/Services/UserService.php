<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;

class UserService
{
    use Curl;

    public function allUsers()
    {
        $id = session('tc_user_id');
        $sessionId = session('cookie');

        if ($id != '') {
            if ($id == 1) {
                $data = 'id=' . $id;
            } else {
                $data = 'userId=' . $id;
            }
        }
        $data = static::curl('/api/users?' . $data, 'GET', $sessionId, '', array());

        $sessionId = session('cookie');
        $user_data = json_decode($data->response);
        foreach ($user_data as $key => $user) {
            $users[$key]['user_id'] = $user->id;
            $users[$key]['name'] = $user->name;
            $users[$key]['email'] = $user->email;
            $users[$key]['phone'] = $user->phone;
        }
        return $users;
    }

    public function userEdit($id)
    {
        $sessionId = session('cookie');
        $data = static::curl('/api/users/' . $id, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $userResponse = json_decode($data->response);
        $users['user_id'] = $userResponse->id;
        $users['name'] = $userResponse->name;
        $users['email'] = $userResponse->email;
        $users['phone'] = $userResponse->phone;
        $users['deviceReadonly'] = $userResponse->deviceReadonly;
        $users['administrator'] = $userResponse->administrator;
        $users['readonly'] = $userResponse->readonly;
        $users['userLimit'] = $userResponse->userLimit;
        $users['deviceLimit'] = $userResponse->deviceLimit;
        return $users;
    }


    public function userUpdate($data, $userData = null)
    {
        $userData = json_decode($userData, true);
        $sessionId = session('cookie');
        $id = $data['hidden_id'];
        $name = $data['name'];
        $email = $data['email'];
        $phone = $data['phone'];
        $data = static::curl('/api/users/' . $id, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $userResponse = json_decode($data->response);
        $readOnly = $userData['readonly'];
        $administrator = $userData['administrator'];
        $map = $userResponse->map;
        $latitude = $userResponse->latitude;
        $longitude = $userResponse->longitude;
        $zoom = $userResponse->zoom;
        $twelveHourFormat = $userResponse->twelveHourFormat;
        $coordinateFormat = $userResponse->coordinateFormat;
        $disabled = ""; //setType($disabled,'boolean');
        $expirationTime = $userResponse->expirationTime;
        $deviceLimit = $userData['deviceLimit'];
        $userLimit = $userData['userLimit'];
        $deviceReadonly = $userData['deviceReadonly'];
        $limitCommands = ""; //setType($limitCommands,'boolean');
        $token = $userResponse->token;
        $login = $userResponse->login;
        $poiLayer = $userResponse->poiLayer;

        $user = '{"id":' . $id . ',"name":"' . $name . '","readonly":' . $readOnly . ',"deviceReadonly":' . $deviceReadonly . ',"administrator":' . $administrator . ',"email":"' . $email . '","coordinateFormat":"' . $coordinateFormat . '","deviceLimit":"' . $deviceLimit . '","expirationTime":"' . $expirationTime . '","latitude":"' . $latitude . '","login":"' . $login . '","longitude":"' . $longitude . '","map":"' . $map . '","phone":"' . $phone . '","poiLayer":"' . $poiLayer . '","token":"' . $token . '","twelveHourFormat":"' . $twelveHourFormat . '","userLimit":"' . $userLimit . '","zoom":"' . $zoom . '"}';
        $sessionId = session('cookie');
        return self::curl('/api/users/' . $id, 'PUT', $sessionId, $user, array('Content-Type: application/json', 'Accept: application/json'));
    }


    public function userCreate($data)
    {
        $id = session('user_id');
        $token = '';
        $data = $data->all();
        unset($data['confirmed']);
        $currentDate = date('Y-m-d H:i:s');
        $futureDate = date('Y-m-d H:i:s', strtotime("+8 year", strtotime($currentDate)));
        $jsonString = '{"admin_id": "1"}';
        $attributes = json_decode($jsonString, true);
        $data2 = array(
            "readonly"=> false,
            "administrator"=> true,
            "map"=> "1",
            "latitude"=> 0,
            "longitude"=> 0,
            "zoom"=> 0,
            "coordinateFormat"=> "0",
            "disabled"=> false,
            "expirationTime"=> '',
            "deviceLimit"=> 0,
            "userLimit"=> 0,
            "deviceReadonly"=> true,
            "limitCommands"=> false,
            "fixedEmail"=> true,
            "poiLayer"=> "string",
            "attributes"=> $attributes,
        );
        $userResponse = array_merge($data,$data2);
        $data = json_encode($userResponse);
        $sessionId = session('cookie');
        return self::curl('/api/users', 'POST', $sessionId, $data, array('Content-Type: application/json', 'Accept: application/json'));
    }


    public static function userDelete($id)
    {
        // {$id='id='.$id;}
        $sessionId = session('cookie');
        return self::curl('/api/users/' . $id, 'DELETE', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
    }


    public function userDevices($id)
    {
        $userId = $id;
        $id = session('tc_user_id');
        $sessionId = session('cookie');
        if ($id != '') {
            $data = 'id=' . $id;
        }
        $data = static::curl('/api/devices?all=true' . $data, 'GET', $sessionId, '', array());
        $sessionId = session('cookie');
        $veh_data = json_decode($data->response);
        foreach ($veh_data as $key => $device) {

            $data = static::curl('/api/devices?userId=' . $userId, 'GET', $sessionId, '', array());
        $sessionId = session('cookie');
            $veh_data22 = json_decode($data->response, true);
            $devices[$key]['user'] = false;
            foreach ($veh_data22 as $ukey => $uvalue) {
                if ($uvalue['id'] == $device->id) {
                    $devices[$key]['user'] = true;
                }
            }
            $devices[$key]['veh_id'] = $device->id;
            $devices[$key]['name'] = $device->name;
        }
        return $devices;
    }
}

?>
