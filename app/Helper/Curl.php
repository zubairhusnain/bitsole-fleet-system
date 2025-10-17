<?php

namespace App\Helpers;
use \stdClass;
use Illuminate\Support\Facades\Config;
use DateTimeZone;
use Illuminate\Support\Facades\Session;

Trait Curl
{


    public static function curl($task,$method,$cookie,$data,$header) {
        // Increase maximum execution time to 5 minutes
        set_time_limit(300); // 300 seconds
        // Avoid redundant ini_set - set_time_limit is sufficient
        $res=new stdClass();
        $res->responseCode='';
        $res->error='';
        $res->cookieData = '';
        if (!is_array($header)) {
            $header = [];
        }
        if (!empty($cookie)) {
            $header[] = "Cookie: " . $cookie;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Config::get('constants.Constants.host').$task);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        // Enable gzip/deflate for faster transfers when supported
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        // Enforce SSL verification when using HTTPS
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if($method=='POST' || $method=='PUT' || $method=='DELETE') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        $data=curl_exec($ch);

        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if ($data !== false && $size !== false) {
            $res->response = substr($data, $size);
        } else {
            $res->response = '';
        }

        if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', substr($data, 0, $size), $c) == 1){
            session(['cookie'=>$c[1]]);
        }

        if(!curl_errno($ch)) {
            $res->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        else {
            $res->responseCode=400;
            $res->error= curl_error($ch);
        }
        curl_close($ch);
        return $res;
    }








}
