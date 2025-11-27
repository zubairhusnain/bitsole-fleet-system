<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleModel;

class VehicleModelController extends Controller
{
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
        ]);
        $row = new VehicleModel();
        $row->modelname = $data['modelname'];
        $row->odmeter_ioid = isset($data['odmeter_ioid']) && strlen(trim($data['odmeter_ioid'])) ? trim($data['odmeter_ioid']) : null;
        $row->fuel_ioid = isset($data['fuel_ioid']) && strlen(trim($data['fuel_ioid'])) ? trim($data['fuel_ioid']) : null;
        $row->created_by = $user?->id ?? null;
        $row->save();
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
        ]);
        $row->modelname = $data['modelname'];
        $row->odmeter_ioid = isset($data['odmeter_ioid']) && strlen(trim($data['odmeter_ioid'])) ? trim($data['odmeter_ioid']) : null;
        $row->fuel_ioid = isset($data['fuel_ioid']) && strlen(trim($data['fuel_ioid'])) ? trim($data['fuel_ioid']) : null;
        $row->save();
        return response()->json(['message' => 'Model updated', 'model' => $row]);
    }
}
