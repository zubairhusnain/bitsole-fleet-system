<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleModel;
use App\Helpers\Curl;
use Illuminate\Support\Facades\Config;

class VehicleModelController extends Controller
{
    use Curl;

    public function options(Request $request)
    {
        $list = VehicleModel::query()->orderBy('modelname')->pluck('modelname')->all();
        return response()->json(['options' => $list]);
    }
    public function index(Request $request)
    {
        $rows = VehicleModel::query()->orderByDesc('id')->get();
        return response()->json(['models' => $rows]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'modelname' => ['required','string','max:190'],
            'odmeter_ioid' => ['nullable','string','max:190'],
            'fuel_ioid' => ['nullable','string','max:190'],
            'attributes' => ['nullable'],
        ]);
        $row = new VehicleModel();
        $row->modelname = $data['modelname'];
        $row->odmeter_ioid = isset($data['odmeter_ioid']) && strlen(trim($data['odmeter_ioid'])) ? trim($data['odmeter_ioid']) : null;
        $row->fuel_ioid = isset($data['fuel_ioid']) && strlen(trim($data['fuel_ioid'])) ? trim($data['fuel_ioid']) : null;

        $attrs = $data['attributes'] ?? null;
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        $row->attributes = is_array($attrs) ? $attrs : null;

        $row->created_by = $user?->id ?? null;
        $row->save();

        $this->syncComputedAttributes($row);

        return response()->json(['message' => 'Model added', 'model' => $row]);
    }

    public function destroy(Request $request, int $id)
    {
        $row = VehicleModel::query()->find($id);
        if (!$row) { return response()->json(['message' => 'Not found'], 404); }
        $row->delete();
        return response()->json(['message' => 'Model deleted']);
    }

    public function update(Request $request, int $id)
    {
        $row = VehicleModel::query()->find($id);
        if (!$row) { return response()->json(['message' => 'Not found'], 404); }
        $data = $request->validate([
            'modelname' => ['required','string','max:190'],
            'odmeter_ioid' => ['nullable','string','max:190'],
            'fuel_ioid' => ['nullable','string','max:190'],
            'attributes' => ['nullable'],
        ]);
        $row->modelname = $data['modelname'];
        $row->odmeter_ioid = isset($data['odmeter_ioid']) && strlen(trim($data['odmeter_ioid'])) ? trim($data['odmeter_ioid']) : null;
        $row->fuel_ioid = isset($data['fuel_ioid']) && strlen(trim($data['fuel_ioid'])) ? trim($data['fuel_ioid']) : null;

        $attrs = $data['attributes'] ?? null;
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        $row->attributes = is_array($attrs) ? $attrs : null;

        $row->save();

        $this->syncComputedAttributes($row);

        return response()->json(['message' => 'Model updated', 'model' => $row]);
    }

    private function syncComputedAttributes($model)
    {
        $attrs = $model->attributes;
        // $model->attributes is already cast to array by Model, but let's be safe
        if (is_string($attrs)) $attrs = json_decode($attrs, true);
        if (!is_array($attrs)) return;

        $all = [];
        if (isset($attrs['odometer']) && is_array($attrs['odometer'])) {
            foreach($attrs['odometer'] as $a) $all[] = $a;
        }
        if (isset($attrs['fuel']) && is_array($attrs['fuel'])) {
            foreach($attrs['fuel'] as $a) $all[] = $a;
        }

        if (empty($all)) return;

        // Login logic
        $sessionId = session('cookie');
        if (empty($sessionId)) {
            try {
                $email = Config::get('constants.Constants.adminEmail');
                $password = Config::get('constants.Constants.adminPassword');
                $data = 'email=' . urlencode($email) . '&password=' . urlencode($password);
                $resp = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);
                $sessionId = $resp->cookieData ?? null;
            } catch (\Throwable $e) {}
        }

        if (!$sessionId) return;

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $resp = static::curl('/api/attributes/computed', 'GET', $sessionId, '', $headers);
        $existing = json_decode($resp->response ?? '[]', true) ?? [];

        foreach ($all as $item) {
            $name = trim($item['name'] ?? '');
            $key = trim($item['key'] ?? '');
            if (!$name || !$key) continue;

            $description = $model->modelname . ' - ' . $name;
            $expression = "io$key ?: -1";
            $attribute = $name;

            $match = null;
            foreach ($existing as $ex) {
                if (($ex['description'] ?? '') === $description) {
                    $match = $ex;
                    break;
                }
            }

            $payload = [
                'description' => $description,
                'attribute' => $attribute,
                'expression' => $expression,
                'type' => 'number'
            ];

            if ($match) {
                static::curl("/api/attributes/computed/{$match['id']}", 'PUT', $sessionId, json_encode($payload), $headers);
            } else {
                static::curl("/api/attributes/computed", 'POST', $sessionId, json_encode($payload), $headers);
            }
        }
    }
}
