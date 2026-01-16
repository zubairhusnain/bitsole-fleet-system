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
            'attributes' => ['nullable'],
        ]);
        $attrs = $data['attributes'] ?? null;
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        $dup = $this->getDuplicateAttributeName($attrs);
        if ($dup !== null) {
            return response()->json(['message' => 'Duplicate attribute name "'.$dup.'" for this model'], 422);
        }
        $dupRemote = $this->getTraccarDuplicateAttributeName($attrs, $data['modelname']);
        if ($dupRemote !== null) {
            return response()->json(['message' => 'Attribute name "'.$dupRemote.'" already exists in tracking server'], 422);
        }
        $row = new VehicleModel();
        $row->modelname = $data['modelname'];
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
        $this->removeComputedAttributes($row);
        $row->delete();
        return response()->json(['message' => 'Model deleted']);
    }

    public function update(Request $request, int $id)
    {
        $row = VehicleModel::query()->find($id);
        if (!$row) { return response()->json(['message' => 'Not found'], 404); }
        $data = $request->validate([
            'modelname' => ['required','string','max:190'],
            'attributes' => ['nullable'],
        ]);
        $row->modelname = $data['modelname'];
        $attrs = $data['attributes'] ?? null;
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        $dup = $this->getDuplicateAttributeName($attrs);
        if ($dup !== null) {
            return response()->json(['message' => 'Duplicate attribute name "'.$dup.'" for this model'], 422);
        }
        $dupRemote = $this->getTraccarDuplicateAttributeName($attrs, $data['modelname']);
        if ($dupRemote !== null) {
            return response()->json(['message' => 'Attribute name "'.$dupRemote.'" already exists in tracking server'], 422);
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
        if (!is_array($attrs)) $attrs = [];

        $desired = [];
        if (isset($attrs['odometer']) && is_array($attrs['odometer'])) {
            foreach ($attrs['odometer'] as $a) {
                $name = trim($a['name'] ?? '');
                $key = trim($a['key'] ?? '');
                if (!$name || !$key) {
                    continue;
                }
                $description = $model->modelname . ' - ' . $name;
                $expression = "(io$key ?: -1)";
                $attribute = $name;
                $desired[$description] = [
                    'description' => $description,
                    'attribute' => $attribute,
                    'expression' => $expression,
                    'type' => 'number'
                ];
            }
        }
        if (isset($attrs['fuel']) && is_array($attrs['fuel'])) {
            foreach ($attrs['fuel'] as $a) {
                $name = trim($a['name'] ?? '');
                $key = trim($a['key'] ?? '');
                if (!$name || !$key) {
                    continue;
                }
                $description = $model->modelname . ' - ' . $name;
                $expression = "(io$key ?: -1)";
                $attribute = $name;
                $desired[$description] = [
                    'description' => $description,
                    'attribute' => $attribute,
                    'expression' => $expression,
                    'type' => 'number'
                ];
            }
        }

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

        $prefix = $model->modelname . ' - ';
        $byDescription = [];
        foreach ($existing as $ex) {
            $desc = $ex['description'] ?? '';
            if (strpos($desc, $prefix) === 0) {
                $byDescription[$desc] = $ex;
            }
        }

        // Delete all existing attributes for this model in Traccar so we can recreate them cleanly
        foreach ($byDescription as $desc => $ex) {
            if (isset($ex['id'])) {
                static::curl("/api/attributes/computed/{$ex['id']}", 'DELETE', $sessionId, '', $headers);
            }
        }

        // Create desired attributes fresh so expression and other fields are always in sync
        foreach ($desired as $desc => $payload) {
            static::curl("/api/attributes/computed", 'POST', $sessionId, json_encode($payload), $headers);
        }
    }

    private function removeComputedAttributes($model)
    {
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

        $prefix = $model->modelname . ' - ';
        foreach ($existing as $ex) {
            $desc = $ex['description'] ?? '';
            if (strpos($desc, $prefix) === 0 && isset($ex['id'])) {
                static::curl("/api/attributes/computed/{$ex['id']}", 'DELETE', $sessionId, '', $headers);
            }
        }
    }

    private function getDuplicateAttributeName($attrs)
    {
        if (is_string($attrs)) {
            $decoded = json_decode($attrs, true);
            $attrs = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($attrs)) {
            return null;
        }
        $seen = [];
        $groups = ['odometer', 'fuel'];
        foreach ($groups as $group) {
            if (!isset($attrs[$group]) || !is_array($attrs[$group])) {
                continue;
            }
            foreach ($attrs[$group] as $item) {
                $name = isset($item['name']) ? trim((string)$item['name']) : '';
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    return $name;
                }
                $seen[$key] = true;
            }
        }
        return null;
    }

    private function getTraccarDuplicateAttributeName($attrs, $modelName)
    {
        if (is_string($attrs)) {
            $decoded = json_decode($attrs, true);
            $attrs = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($attrs)) {
            return null;
        }
        $names = [];
        $groups = ['odometer', 'fuel'];
        foreach ($groups as $group) {
            if (!isset($attrs[$group]) || !is_array($attrs[$group])) {
                continue;
            }
            foreach ($attrs[$group] as $item) {
                $name = isset($item['name']) ? trim((string)$item['name']) : '';
                if ($name === '') {
                    continue;
                }
                $names[mb_strtolower($name)] = $name;
            }
        }
        if (empty($names)) {
            return null;
        }

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

        if (!$sessionId) {
            return null;
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $resp = static::curl('/api/attributes/computed', 'GET', $sessionId, '', $headers);
        $existing = json_decode($resp->response ?? '[]', true) ?? [];

        $prefix = $modelName . ' - ';
        foreach ($existing as $ex) {
            $attrName = isset($ex['attribute']) ? trim((string)$ex['attribute']) : '';
            if ($attrName === '') {
                continue;
            }
            $key = mb_strtolower($attrName);
            if (!isset($names[$key])) {
                continue;
            }
            $desc = isset($ex['description']) ? (string)$ex['description'] : '';
            if (strpos($desc, $prefix) !== 0) {
                return $names[$key];
            }
        }

        return null;
    }
}
