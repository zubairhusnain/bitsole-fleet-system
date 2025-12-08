<?php

return [
    'Constants' => [
        'host' 	 => env('TRACCAR_HOST', 'http://52.77.249.227:8082'),
        'adminEmail' 		 => env('TRACCAR_ADMIN_EMAIL', 'omayerfleet@gmail.com'),
		'adminPassword'=> env('TRACCAR_ADMIN_PASSWORD', 'Lahore@2211'),
		'jsonA'  => 'Accept: application/json',
		'jsonC'		 => 'Content-Type: application/json',
		'urlEncoded'  	 => 'Content-Type: application/x-www-form-urlencoded'
    ]
];
