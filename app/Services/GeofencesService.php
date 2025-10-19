<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;

class GeofencesService
{
    use Curl;

    public function allGeofences($request)
    {
        $id = session('tc_user_id');
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $geofences = [];
        $deviceIds = (!empty($request->user()->geofence_ids)) ? json_decode($request->user()->geofence_ids, true) : [];
        if (!empty($deviceIds)) {
            $getDDD = '';
            foreach ($deviceIds as $key => $value) {
                $getDDD .= "id=$value&";
            }
            if ($getDDD !== '') {
                $data = static::curl('/api/geofences?' . $getDDD, 'GET', $sessionId, '', array());
                if ($data->responseCode == 200) {
                    $user_data = json_decode($data->response);
                    foreach ($user_data as $key => $geofence) {
                        $geofences[$key]['id'] = $geofence->id;
                        $geofences[$key]['name'] = $geofence->name;
                        $geofences[$key]['description'] = $geofence->description;
                        $geofences[$key]['area'] = $geofence->area;
                        $geofences[$key]['attributes'] = $geofence->attributes;
                    }
                }
            }
        }
        return $geofences;
    }

    public function deviceGeofences($request, $deviceId)
    {
        $id = session('tc_user_id');
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $geofences = [];
        if ($deviceId) {
            $data = static::curl('/api/geofences?deviceId=' . $deviceId, 'GET', $sessionId, '', array());
            if ($data->responseCode == 200) {
                $user_data = json_decode($data->response);
                foreach ($user_data as $key => $geofence) {
                    $geofences[$key]['id'] = $geofence->id;
                    $geofences[$key]['name'] = $geofence->name;
                    $geofences[$key]['description'] = $geofence->description;
                    $geofences[$key]['area'] = $geofence->area;
                    $geofences[$key]['attributes'] = $geofence->attributes;
                }
            }
        }
        return $geofences;
    }




    public function viewGeofence($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $id = $request->geifenceId;
        $data = 'id=' . $request->geifenceId;
        $data = static::curl('/api/geofences?' . $data, 'GET', $sessionId, '', array());
        $data = json_decode($data->response);
        return $data;
        // $response = [];
        // foreach ($data as $key => $value) {
        //     if ($value->id == $id) {
        //         $response['id'] = $value->id;
        //         $response['name'] = $value->name;
        //         $response['description'] = $value->description;
        //         $response['area'] = $value->area;
        //         $response['attributes'] = $value->attributes;
        //     }
        // }


        // $arr1 = str_split($response['area']);
        // $type = true;
        // $long = false;
        // $lat = false;
        // $rad = false;
        // $round_bracket = 0;
        // $latlng = "";
        // $latlngArrayIndex = 0;
        // $response['type'] = "";
        // $response['radius'] = "";
        // $response['latitude'] = "";
        // $response['longitude'] = "";
        // $response['latLongs'] = [];
        // foreach ($arr1 as $key => $value) {

        //     if ($arr1[0] == 'C') {
        //         if ($value == ")")
        //             break;

        //         if ($rad == true)
        //             $response['radius'] = $response['radius'] . $value;
        //         if ($value == " ")
        //             $type = false;


        //         if ($value == "-") {
        //             $long = true;
        //             $lat == false;
        //         }
        //         if ($long == true)
        //             $response['longitude'] = $response['longitude'] . $value;
        //         if ($lat == true)
        //             $response['latitude'] = $response['latitude'] . $value;
        //         if ($type == true)
        //             $response['type'] = $response['type'] . $value;

        //         if ($value == "(")
        //             $lat = true;
        //         if ($value == ",") {
        //             $long = false;
        //             $rad = true;
        //         }
        //     } else if ($arr1[0] == 'P') {
        //         if ($value >= 'A' && $value <= 'Z') {
        //             $response['type'] = $response['type'] . $value;

        //         } else {
        //             if ($value != '(' && $value != ')') {

        //                 if ($lat == true && $round_bracket == 2) {

        //                     if ($value == ' ' && $arr1[$key - 1] != ',') {
        //                         $lat = false;
        //                         $long = true;
        //                         $response['latitude'] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][0] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][0] = (float) $response['latLongs'][$latlngArrayIndex][0];
        //                         $latlng = '';

        //                     } else {
        //                         $latlng = $latlng . $value;
        //                     }
        //                 } elseif ($long == true && $round_bracket == 2) {

        //                     if ($value == ',') {
        //                         $lat = true;
        //                         $long = false;
        //                         $response['longitude'] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][1] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][1] = (float) $response['latLongs'][$latlngArrayIndex][1];
        //                         $latlng = '';
        //                         $latlngArrayIndex++;
        //                     } else {
        //                         $latlng = $latlng . $value;
        //                     }
        //                 }

        //             } else if ($value == '(' || $value == ')') {
        //                 $round_bracket++;
        //                 if ($value == ')') {
        //                     $response['longitude'] = $latlng;
        //                     $response['latLongs'][$latlngArrayIndex][1] = $latlng;
        //                     $response['latLongs'][$latlngArrayIndex][1] = (float) $response['latLongs'][$latlngArrayIndex][1];
        //                 }
        //                 if ($round_bracket == 2) {
        //                     $lat = true;
        //                 }
        //             }
        //         }
        //     } elseif ($arr1[0] == "L") {
        //         if ($value >= 'A' && $value <= 'Z') {
        //             $response['type'] = $response['type'] . $value;

        //         } else {
        //             if ($value != '(' && $value != ')') {

        //                 if ($lat == true) {

        //                     if ($value == ' ' && $arr1[$key - 1] != ',') {
        //                         $lat = false;
        //                         $long = true;
        //                         $response['latitude'] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][0] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][0] = (float) $response['latLongs'][$latlngArrayIndex][0];
        //                         $latlng = '';

        //                     } else {
        //                         $latlng = $latlng . $value;
        //                     }
        //                 } elseif ($long == true) {

        //                     if ($value == ',') {
        //                         $lat = true;
        //                         $long = false;
        //                         $response['longitude'] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][1] = $latlng;
        //                         $response['latLongs'][$latlngArrayIndex][1] = (float) $response['latLongs'][$latlngArrayIndex][1];
        //                         $latlng = '';
        //                         $latlngArrayIndex++;
        //                     } else {
        //                         $latlng = $latlng . $value;
        //                     }
        //                 }

        //             } else if ($value == '(' || $value == ')') {
        //                 if ($value == '(')
        //                     $lat = true;
        //                 if ($value == ')') {
        //                     $response['longitude'] = $latlng;
        //                     $response['latLongs'][$latlngArrayIndex][1] = $latlng;
        //                     $response['latLongs'][$latlngArrayIndex][1] = (float) $response['latLongs'][$latlngArrayIndex][1];
        //                 }

        //             }
        //         }
        //     }
        // }

        // $response['radius'] = (float) $response['radius'];
        // $response['longitude'] = (float) $response['longitude'];
        // $response['latitude'] = (float) $response['latitude'];
        // // print_r($response);die();
        // return $response;
    }

    public function addGeofence($request)
    {
        // Retrieve session ID from header or session
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        // dd($request->all());
        // Common fields
        $name    = $request->name;
        $address = $request->address;
        $type    = $request->type; // Expected values: "circle", "rectangle", "polygon", "route"
        $userId  = $request->user()->id;

        $wkt = "";
        $attributes = [
            "user_id" => $userId,
            "type"    => $type
        ];

        $lat    = $request->lat;
        $lon    = $request->lng;
        $radius = $request->radius; // in meters

        // Add circle-specific attributes.
        $attributes["lat"] = $lat;
        $attributes["long"] = $lon;
        $attributes["radius"] = $radius;
        $attributes["coordinates"] = $request->coordinates;

        if ($type === "circle") {
            // For a circle, use center and radius.
            // Create a polygon approximation of the circle as a valid WKT.
            $wkt = $this->createCircleWKT($lat, $lon, $radius);

        } elseif ($type === "rectangle") {
            // For rectangle, expect two coordinates: two opposite corners.
            $coords = $request->coordinates; // e.g. [ [lat1, lng1], [lat2, lng2] ]
            if (count($coords) < 2) {
                throw new \Exception("Not enough coordinates for rectangle");
            }
            $lat1 = $coords[0][0];
            $lon1 = $coords[0][1];
            $lat2 = $coords[1][0];
            $lon2 = $coords[1][1];

            // Construct the rectangle polygon in WKT format.
            $wkt = "POLYGON((" .
                   "$lon1 $lat1, " .
                   "$lon2 $lat1, " .
                   "$lon2 $lat2, " .
                   "$lon1 $lat2, " .
                   "$lon1 $lat1" .
                   "))";

        } elseif ($type === "polygon") {
            // For a polygon, expect at least three coordinates.
            $coords = $request->coordinates; // array of [lat, lng] points
            if (count($coords) < 3) {
                throw new \Exception("Not enough coordinates for polygon");
            }
            $wktCoords = [];
            foreach ($coords as $point) {
                // Note: WKT order is "longitude latitude"
                $wktCoords[] = $point[1] . " " . $point[0];
            }
            // Ensure the polygon is closed by repeating the first coordinate if needed.
            $first = $wktCoords[0];
            $last  = end($wktCoords);
            if ($first !== $last) {
                $wktCoords[] = $first;
            }
            $wkt = "POLYGON((" . implode(", ", $wktCoords) . "))";

        } elseif ($type === "route") {
            // For a route, expect at least two points.
            $coords = $request->coordinates; // array of [lat, lng] points
            if (count($coords) < 2) {
                throw new \Exception("Not enough coordinates for route");
            }
            $wktCoords = [];
            foreach ($coords as $point) {
                // Note: WKT order is "longitude latitude"
                $wktCoords[] = $point[1] . " " . $point[0];
            }
            // Generate a WKT with "ROUTE" as the geometry type.
            $wkt = "ROUTE(" . implode(", ", $wktCoords) . ")";

        } else {
            throw new \Exception("Unknown geofence type: $type");
        }

        // Build the final data object.
        $dataArray = [
            "id"          => "-1",
            "name"        => $name,
            "description" => $address,
            "area"        => $wkt,
            "attributes"  => $attributes
        ];

        $data = json_encode($dataArray);

        // Make the API call using the static curl method.
        $response = static::curl(
            '/api/geofences',
            'POST',
            $sessionId,
            $data,
            [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        );

        return $response;
    }

    /**
     * Helper function to create a circle approximation in WKT format.
     *
     * @param float $lat Center latitude
     * @param float $lon Center longitude
     * @param float $radius Radius in meters
     * @param int   $numPoints Number of points to approximate the circle
     * @return string WKT polygon string representing the circle
     */
    private function createCircleWKT($lat, $lon, $radius, $numPoints = 32)
    {
        $earthRadius = 6378137; // Earth's radius in meters
        $points = [];
        for ($i = 0; $i < $numPoints; $i++) {
            $angle = deg2rad($i * 360 / $numPoints);
            // Calculate offset in meters.
            $dx = $radius * cos($angle);
            $dy = $radius * sin($angle);
            // Convert offset from meters to degrees.
            $dLat = ($dy / $earthRadius) * (180 / pi());
            $dLon = ($dx / ($earthRadius * cos(deg2rad($lat)))) * (180 / pi());
            $pointLat = $lat + $dLat;
            $pointLon = $lon + $dLon;
            $points[] = $pointLon . " " . $pointLat; // WKT expects "lon lat"
        }
        // Close the polygon by repeating the first point.
        $points[] = $points[0];
        return "POLYGON((" . implode(", ", $points) . "))";
    }



    public function deleteGeofence($request)
    {
        $id = $request->geofenceId;
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $response = static::curl('/api/geofences/' . $id, 'DELETE', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        return $response;
    }

    public function enableParking($id, $name, $request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data = "deviceId=" . $id;
        $response = static::curl('/api/positions?' . $data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $response = json_decode($response->response);
        $lat = $response[0]->latitude;
        $long = $response[0]->longitude;
        $area = "CIRCLE (" . $lat . " -" . $long . ", 50)";
        $name = "High Alert " . $name;
        return $this->addGeofence($area, $name);
    }


}
?>
