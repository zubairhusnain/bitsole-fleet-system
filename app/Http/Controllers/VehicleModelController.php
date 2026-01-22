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
        $models = VehicleModel::query()->orderBy('modelname')->get();
        $list = $models->pluck('modelname')->all();
        return response()->json([
            'options' => $list,
            'models' => $models,
        ]);
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
        $row->attributes = is_array($attrs) ? $attrs : null;

        $row->save();

        $this->syncComputedAttributes($row);

        return response()->json(['message' => 'Model updated', 'model' => $row]);
    }

    private function getTraccarSession()
    {
        $user = request()->user();
        return $user->traccarSession ?? session('cookie');
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
                $expression = "($key ?: -1)";
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
                // Note: The frontend may pass 'is_analog' (boolean), but we currently treat all fuel attributes
                // with the same expression pattern. If analog logic requires different handling (e.g. scaling),
                // it can be implemented here based on that flag.
                $expression = "($key ?: -1)";
                $attribute = $name;
                $desired[$description] = [
                    'description' => $description,
                    'attribute' => $attribute,
                    'expression' => $expression,
                    'type' => 'number'
                ];
            }
        }
        if (isset($attrs['speed']) && is_array($attrs['speed'])) {
            foreach ($attrs['speed'] as $a) {
                $name = trim($a['name'] ?? '');
                $key = trim($a['key'] ?? '');
                if (!$name || !$key) {
                    continue;
                }
                $description = $model->modelname . ' - ' . $name;
                $expression = "($key ?: -1)";
                $attribute = $name;
                $desired[$description] = [
                    'description' => $description,
                    'attribute' => $attribute,
                    'expression' => $expression,
                    'type' => 'number'
                ];
            }
        }

        $sessionId = $this->getTraccarSession();

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
        $sessionId = $this->getTraccarSession();

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
        $groups = ['odometer', 'fuel', 'speed'];
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
}
