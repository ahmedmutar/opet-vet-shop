<?php

namespace App\Http\Controllers;

use App\Models\OutPatient;
use DB;
use Illuminate\Http\Request;
use Validator;

class RawatJalanController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role == 'dokter') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Access is not allowed!'],
            ], 403);
        }

        $data = DB::table('out_patients')
            ->join('users', 'out_patients.user_id', '=', 'users.id')
            ->join('patients', 'out_patients.patient_id', '=', 'patients.id')
            ->join('branches', 'patients.branch_id', '=', 'branches.id')
            ->select('out_patients.id as out_patients_id', 'out_patients.id_register', 'out_patients.patient_id',
                'patients.id_member', 'patients.pet_category', 'patients.pet_name', 'patients.pet_gender',
                'patients.pet_year_age', 'patients.pet_month_age', 'patients.owner_name', 'patients.owner_address',
                'patients.owner_phone_number', 'complaint', 'registrant', 'users.fullname as created_by',
                DB::raw("DATE_FORMAT(out_patients.created_at, '%d %b %Y') as created_at"), 'users.branch_id as user_branch_id');
        //->where('users.branch_id', '=', $request->user()->branch_id);

        if ($request->user()->role == 'resepsionis') {
            $data = $data->where('users.branch_id', '=', $request->user()->branch_id);
        }

        if ($request->keyword) {

            $data = $data->where('patients.id_member', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.pet_category', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.pet_name', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.pet_gender', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.pet_year_age', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.pet_month_age', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.owner_name', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.owner_address', 'like', '%' . $request->keyword . '%')
            //->orwhere('patients.owner_phone_number', 'like', '%' . $request->keyword . '%')
                ->orwhere('branches.branch_name', 'like', '%' . $request->keyword . '%')
                ->orwhere('users.fullname', 'like', '%' . $request->keyword . '%')
                ->orwhere('out_patients.complaint', 'like', '%' . $request->keyword . '%')
                ->orwhere('out_patients.registrant', 'like', '%' . $request->keyword . '%');
        }

        if ($request->orderby) {

            $data = $data->orderBy($request->column, $request->orderby);
        }

        $data = $data->orderBy('out_patients.id', 'asc');

        $data = $data->get();

        return response()->json($data, 200);

    }

    public function create(Request $request)
    {
        if ($request->user()->role == 'dokter') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Access is not allowed!'],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|numeric',
            'keluhan' => 'required|string|min:3|max:51',
            'nama_pendaftar' => 'required|string|min:3|max:50',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return response()->json([
                'message' => 'Data yang dimasukkan tidak valid!',
                'errors' => $errors,
            ], 422);
        }

        $lasttransaction = DB::table('out_patients')
            ->join('users', 'out_patients.user_id', '=', 'users.id')
            ->join('branches', 'users.branch_id', '=', 'branches.id')
            ->where('branch_id', '=', $request->user()->branch_id)
            ->count();

        $getbranchuser = DB::table('branches')
            ->select('branch_code')
            ->where('id', '=', $request->user()->branch_id)
            ->first();

        $out_patient_number = 'BVC-RJ-' . $getbranchuser->branch_code . '-' . str_pad($lasttransaction + 1, 4, 0, STR_PAD_LEFT);

        $patient = OutPatient::create([
            'id_register' => $out_patient_number,
            'patient_id' => $request->patient_id,
            'complaint' => $request->keluhan,
            'registrant' => $request->nama_pendaftar,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(
            [
                'message' => 'Tambah Data Berhasil!',
            ], 200
        );
    }

    public function update(Request $request)
    {
        if ($request->user()->role == 'dokter') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Access is not allowed!'],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|numeric',
            'keluhan' => 'required|string|min:3|max:51',
            'nama_pendaftar' => 'required|string|min:3|max:50',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return response()->json([
                'message' => 'Data yang dimasukkan tidak valid!',
                'errors' => $errors,
            ], 422);
        }

        $out_patient = OutPatient::find($request->id);

        if (is_null($out_patient)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data not found!'],
            ], 404);
        }

        $out_patient->patient_id = $request->patient_id;
        $out_patient->complaint = $request->keluhan;
        $out_patient->registrant = $request->nama_pendaftar;
        $out_patient->user_update_id = $request->user()->id;
        $out_patient->updated_at = \Carbon\Carbon::now();
        $out_patient->save();

        return response()->json([
            'message' => 'Berhasil mengupdate Data',
        ], 200);
    }

    public function delete(Request $request)
    {
        if ($request->user()->role == 'dokter') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Access is not allowed!'],
            ], 403);
        }

        $out_patient = OutPatient::find($request->id);

        if (is_null($out_patient)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data not found!'],
            ], 404);
        }

        $out_patient->isDeleted = true;
        $out_patient->deleted_by = $request->user()->fullname;
        $out_patient->deleted_at = \Carbon\Carbon::now();
        $out_patient->save();

        $out_patient->delete();

        return response()->json([
            'message' => 'Berhasil menghapus Data',
        ], 200);
    }
}