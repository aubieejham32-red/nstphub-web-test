<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\University;
use App\Models\UniversityAdministrator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UniversityController extends Controller
{
    /**
     * Display all universities.
     */
    /**
 * Display all universities.
 */
public function index(Request $request)
{
    $query = University::with('administrator');

    if ($request->filled('search')) {

        $search = trim($request->search);

        $query->where(function ($q) use ($search) {

            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('acronym', 'LIKE', "%{$search}%")
              ->orWhere('region', 'LIKE', "%{$search}%")
              ->orWhere('province', 'LIKE', "%{$search}%")
              ->orWhere('access_code', 'LIKE', "%{$search}%")
              ->orWhereHas('administrator', function ($admin) use ($search) {

                    $admin->where('first_name', 'LIKE', "%{$search}%")
                          ->orWhere('last_name', 'LIKE', "%{$search}%")
                          ->orWhereRaw(
                              "CONCAT(first_name,' ',last_name) LIKE ?",
                              ["%{$search}%"]
                          );

              });

        });

    }

    $universities = $query
        ->latest()
        ->paginate(10);

    return response()->json($universities);
}

    /**
     * Store a newly created university.
     */
    public function store(Request $request)
    {
        $request->validate([

            // University
            'name'              => 'required|string|max:255',
            'acronym'           => 'required|string|max:30|unique:universities,acronym',
            'type'              => 'required',
            'campus_type'       => 'required',
            'email'             => 'required|email|unique:universities,email',
            'contact_number'    => 'required',
            'website'           => 'nullable|string',
            'logo'              => 'nullable|image|max:2048',

            'region'            => 'required',
            'province'          => 'required',
            'city'              => 'required',
            'barangay'          => 'required',
            'zip_code'          => 'required',
            'complete_address'  => 'required',

            'academic_year'     => 'required',
            'semester'          => 'required',
            'components'        => 'required|array',
            'max_students'      => 'required|integer',

            // Administrator
            'first_name'        => 'required',
            'middle_name'       => 'nullable',
            'last_name'         => 'required',

            'admin_email'       => 'required|email|unique:users,email',
            'phone'             => 'required',

            'username'          => 'required|unique:users,username',
            'password'          => 'required|min:8',
        ]);

        DB::beginTransaction();

        try {

            $logo = null;

            if ($request->hasFile('logo')) {
                $logo = $request->file('logo')->store('universities', 'public');
            }

            $accessCode = $this->generateAccessCode();

            $university = University::create([

                'name'              => $request->name,
                'acronym'           => strtoupper($request->acronym),
                'type'              => $request->type,
                'campus_type'       => $request->campus_type,
                'email'             => $request->email,
                'contact_number'    => $request->contact_number,
                'website'           => $request->website,
                'logo'              => $logo,

                'region'            => $request->region,
                'province'          => $request->province,
                'city'              => $request->city,
                'barangay'          => $request->barangay,
                'zip_code'          => $request->zip_code,
                'complete_address'  => $request->complete_address,

                'academic_year'     => $request->academic_year,
                'semester'          => $request->semester,
                'components'        => $request->components,
                'max_students'      => $request->max_students,

                'access_code'       => $accessCode,
                'status'            => 'ACTIVE',
            ]);

            $user = User::create([
                'name'      => $request->first_name . ' ' . $request->last_name,
                'email'     => $request->admin_email,
                'username'  => $request->username,
                'password'  => Hash::make($request->password),
            ]);

            $user->assignRole('University Admin');

            UniversityAdministrator::create([

                'university_id' => $university->id,

                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,

                'email' => $request->admin_email,
                'phone' => $request->phone,

                'username' => $request->username,
                'password' => Hash::make($request->password),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'University created successfully.',
                'accessCode' => $accessCode,
                'portalUrl' => url('/superadmin/login'),
                'data' => $university->load('administrator')
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display one university.
     */
    public function show(University $university)
    {
        return response()->json(
            $university->load('administrator')
        );
    }

    /**
     * Update a university.
     */
    public function update(Request $request, University $university)
{
    $request->validate([

        'name' => 'required|string|max:255',
        'acronym' => 'required|string|max:30|unique:universities,acronym,' . $university->id,
        'type' => 'required',
        'campus_type' => 'required',

        'email' => 'required|email|unique:universities,email,' . $university->id,
        'contact_number' => 'required',
        'website' => 'nullable',

        'logo' => 'nullable|image|max:2048',

        'region' => 'required',
        'province' => 'required',
        'city' => 'required',
        'barangay' => 'required',
        'zip_code' => 'required',
        'complete_address' => 'required',

        'academic_year' => 'required',
        'semester' => 'required',

        'components' => 'required|array',

        'max_students' => 'required|integer',

        'status' => 'required',

        'administrator.first_name' => 'required',
        'administrator.middle_name' => 'nullable',
        'administrator.last_name' => 'required',

        'administrator.email' => 'required|email',

        'administrator.phone' => 'required',

        'administrator.username' => 'required',
    ]);

    DB::beginTransaction();

    try {

        $logo = $university->logo;

        if ($request->hasFile('logo')) {

            if ($logo && Storage::disk('public')->exists($logo)) {
                Storage::disk('public')->delete($logo);
            }

            $logo = $request->file('logo')->store('universities', 'public');
        }

        $university->update([

            'name' => $request->name,
            'acronym' => strtoupper($request->acronym),

            'type' => $request->type,
            'campus_type' => $request->campus_type,

            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'website' => $request->website,

            'logo' => $logo,

            'region' => $request->region,
            'province' => $request->province,
            'city' => $request->city,
            'barangay' => $request->barangay,
            'zip_code' => $request->zip_code,
            'complete_address' => $request->complete_address,

            'academic_year' => $request->academic_year,
            'semester' => $request->semester,
            'components' => $request->components,
            'max_students' => $request->max_students,

            'status' => $request->status,
        ]);

        if ($university->administrator) {

            $admin = $university->administrator;

            $admin->update([

                'first_name' => $request->administrator['first_name'],
                'middle_name' => $request->administrator['middle_name'],
                'last_name' => $request->administrator['last_name'],

                'email' => $request->administrator['email'],
                'phone' => $request->administrator['phone'],
                'username' => $request->administrator['username'],
            ]);

            User::where('username', $admin->username)->update([

                'name' =>
                    $request->administrator['first_name']
                    . ' '
                    . $request->administrator['last_name'],

                'email' => $request->administrator['email'],
                'username' => $request->administrator['username'],
            ]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'University updated successfully.',
            'data' => $university->fresh()->load('administrator'),
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Delete a university.
     */
    public function destroy(University $university)
    {
        DB::beginTransaction();

        try {

            if ($university->logo && Storage::disk('public')->exists($university->logo)) {
                Storage::disk('public')->delete($university->logo);
            }

            $administrator = $university->administrator;

            if ($administrator) {

                User::where('username', $administrator->username)->delete();

                $administrator->delete();
            }

            $university->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'University deleted successfully.',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggleStatus(University $university)
{
    $university->status =
        $university->status === 'ACTIVE'
            ? 'INACTIVE'
            : 'ACTIVE';

    $university->save();

    return response()->json([
        'status' => $university->status
    ]);
}

    public function universityAdministrators()
{
    $administrators = UniversityAdministrator::with('university')
        ->orderBy('first_name')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $administrators,
    ]);
}
}