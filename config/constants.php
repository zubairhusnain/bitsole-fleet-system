<?php

return [
    'Constants' => [
        'host' 	 => env('TRACCAR_HOST', 'http://31.220.85.115:8082'),
        'adminEmail' 		 => env('TRACCAR_ADMIN_EMAIL', 'prem_saagar@ymail.com'),
		'adminPassword'=> env('TRACCAR_ADMIN_PASSWORD', 'Prem983!@'),
		'jsonA'  => 'Accept: application/json',
		'jsonC'		 => 'Content-Type: application/json',
		'urlEncoded'  	 => 'Content-Type: application/x-www-form-urlencoded'
    ]
];
