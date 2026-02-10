<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\VehicleModel;

class TcDevice extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'tc_devices';
    // Tracking server tables typically don't have Laravel timestamps
    public $timestamps = false;

    // protected $with = ['vehicleModel'];

    // Position relation via positionid
    public function position(): BelongsTo
    {
        return $this->belongsTo(TcPosition::class, 'positionid', 'id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model', 'modelname');
    }

    public function getAttributesAttribute($value)
    {
        if (is_null($value)) {
            return '{}';
        }

        $attributes = json_decode($value, true);
        if (!is_array($attributes)) {
            return $value;
        }
        if(isset($attributes['odometerAttr'])){
            $attributes['odometerAttr_key']="";
        }

        if(isset($attributes['fuelAttr'])){
            $attributes['fuelAttr_key']="";
        }

        $trackerModelName = $attributes['trackerModel'] ?? null;

        if ($trackerModelName) {
            // Use static cache to avoid N+1 queries during loop
            static $vehicleModelsCache = [];

            if (!array_key_exists($trackerModelName, $vehicleModelsCache)) {
                $vehicleModelsCache[$trackerModelName] = VehicleModel::where('modelname', $trackerModelName)->first();
            }

            $vehicleModel = $vehicleModelsCache[$trackerModelName];

            // Note: The column name 'attributes' conflicts with Eloquent's internal attributes property.
            // We must access it carefully. Using getAttribute() or accessing as array should work,
            // but fetching raw and decoding is safest to avoid ambiguity.
            $vmAttrsRaw = $vehicleModel ? $vehicleModel->getAttribute('attributes') : null;

            // If the cast works, it's an array. If not (due to conflict), it might be a string.
            if (is_string($vmAttrsRaw)) {
                $vmAttrs = json_decode($vmAttrsRaw, true);
            } else {
                $vmAttrs = $vmAttrsRaw;
            }

            if (!empty($vmAttrs)) {
                // Merge Odometer Key
                $odoAttr = $attributes['odometerAttr'] ?? null;
                if ($odoAttr && !empty($vmAttrs['odometer']) && is_array($vmAttrs['odometer'])) {
                    foreach ($vmAttrs['odometer'] as $item) {
                        if (($item['name'] ?? '') === $odoAttr) {
                            $attributes['odometerAttr_key'] = $item['key'] ?? null;
                            break;
                        }
                    }
                }


                // Merge Fuel Key
                $fuelAttr = $attributes['fuelAttr'] ?? null;
                if ($fuelAttr && !empty($vmAttrs['fuel']) && is_array($vmAttrs['fuel'])) {
                    foreach ($vmAttrs['fuel'] as $item) {
                        if (($item['name'] ?? '') === $fuelAttr) {
                            $attributes['fuelAttr_key'] = $item['key'] ?? null;
                            break;
                        }
                    }
                }
            }
        }

        return json_encode($attributes);
    }

    public function notifications()
    {
        return $this->belongsToMany(TcNotification::class, 'tc_device_notification', 'deviceid', 'notificationid');
    }
}
