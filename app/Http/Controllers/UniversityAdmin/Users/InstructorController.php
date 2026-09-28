<?php

namespace App\Http\Controllers\UniversityAdmin\Users;

use App\Http\Controllers\Controller;
use App\Mail\InstructorAccountMail;
use App\Models\Instructor;
use App\Models\UniversityAdministrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InstructorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    private const INSTRUCTOR_ROLE =
        'instructor';

    private const INSTRUCTOR_GUARD =
        'instructor';


    /*
    |--------------------------------------------------------------------------
    | Instructor List
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this->ensureInstructorRoleExists();


        $components =
            $this->getUniversityComponents(
                $admin
            );


        $instructors =
            Instructor::query()
                ->with(
                    'managedComponents:id,instructor_id,component'
                )
                ->where(
                    'university_id',
                    $admin->university_id
                )
                ->orderBy(
                    'created_at',
                    'desc'
                )
                ->get([
                    'id',
                    'university_id',
                    'full_name',
                    'username',
                    'email',
                    'phone_number',
                    'profile_photo',
                    'component',
                    'status',
                    'created_at',
                    'updated_at',
                ])
                ->map(
                    function (
                        Instructor $instructor
                    ): Instructor {

                        $componentCodes =
                            $instructor
                                ->componentCodes();


                        $instructor
                            ->setAttribute(
                                'component_codes',
                                $componentCodes
                            );


                        $instructor
                            ->setAttribute(
                                'components_display',
                                implode(
                                    ', ',
                                    $componentCodes
                                )
                            );


                        return $instructor;

                    }
                );


        return Inertia::render(
            'UniversityAdmin/Users/Instructor/instructor',
            [
                'instructors' =>
                    $instructors,

                'components' =>
                    $components,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Add Instructor Page
    |--------------------------------------------------------------------------
    */

    public function create(): Response
    {
        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this->ensureInstructorRoleExists();


        return Inertia::render(
            'UniversityAdmin/Users/Instructor/AddInstructorPage',
            [
                'mode' =>
                    'create',

                'instructor' =>
                    null,

                'components' =>
                    $this->getUniversityComponents(
                        $admin
                    ),
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Store Instructor
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): RedirectResponse {

        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $instructorRole =
            $this->ensureInstructorRoleExists();


        $components =
            $this->getUniversityComponents(
                $admin
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        |
        | components:
        |
        | One, two, or all three components may be assigned.
        |
        */

        $validated =
            $request->validate([

                'full_name' => [
                    'required',
                    'string',
                    'max:255',
                ],


                'username' => [
                    'required',
                    'string',
                    'max:100',

                    Rule::unique(
                        'instructors',
                        'username'
                    ),
                ],


                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',

                    Rule::unique(
                        'instructors',
                        'email'
                    ),
                ],


                'phone_number' => [
                    'required',
                    'string',
                    'max:30',
                ],


                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:255',
                ],


                /*
                |--------------------------------------------------------------------------
                | Multiple Components
                |--------------------------------------------------------------------------
                */

                'components' => [
                    'required',
                    'array',
                    'min:1',
                    'max:3',
                ],


                'components.*' => [
                    'required',
                    'string',
                    'distinct',

                    Rule::in(
                        $components
                    ),
                ],


                /*
                |--------------------------------------------------------------------------
                | Legacy Component Field
                |--------------------------------------------------------------------------
                */

                'component' => [
                    'nullable',
                    'string',

                    Rule::in(
                        $components
                    ),
                ],


                'status' => [
                    'required',

                    Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],
            ]);


        /*
        |--------------------------------------------------------------------------
        | Normalize Components
        |--------------------------------------------------------------------------
        */

        $selectedComponents =
            array_values(
                array_unique(
                    array_map(
                        static fn (
                            $component
                        ) =>
                            strtoupper(
                                trim(
                                    (string)
                                    $component
                                )
                            ),

                        $validated[
                            'components'
                        ]
                    )
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Temporary Plain Password
        |--------------------------------------------------------------------------
        */

        $plainPassword =
            $validated[
                'password'
            ];


        /*
        |--------------------------------------------------------------------------
        | Create Instructor
        |--------------------------------------------------------------------------
        */

        $instructor =
            DB::transaction(
                function () use (
                    $admin,
                    $validated,
                    $selectedComponents,
                    $instructorRole
                ): Instructor {

                    $instructor =
                        new Instructor();


                    /*
                    |--------------------------------------------------------------------------
                    | Basic Information
                    |--------------------------------------------------------------------------
                    */

                    $instructor->university_id =
                        $admin->university_id;

                    $instructor->full_name =
                        $validated[
                            'full_name'
                        ];

                    $instructor->username =
                        $validated[
                            'username'
                        ];

                    $instructor->email =
                        $validated[
                            'email'
                        ];

                    $instructor->phone_number =
                        $validated[
                            'phone_number'
                        ];

                    $instructor->password =
                        $validated[
                            'password'
                        ];


                    /*
                    |--------------------------------------------------------------------------
                    | Primary / Legacy Component
                    |--------------------------------------------------------------------------
                    |
                    | Existing areas of the system still use instructors.component.
                    |
                    | We keep the first selected component there.
                    |
                    */

                    $instructor->component =
                        $selectedComponents[0];


                    $instructor->status =
                        $validated[
                            'status'
                        ];


                    $instructor->save();


                    /*
                    |--------------------------------------------------------------------------
                    | Store ALL Selected Components
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $selectedComponents
                        as
                        $component
                    ) {

                        $instructor
                            ->managedComponents()
                            ->create([
                                'component' =>
                                    $component,
                            ]);

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Instructor Role
                    |--------------------------------------------------------------------------
                    */

                    $instructor
                        ->assignRole(
                            $instructorRole
                        );


                    return $instructor;

                }
            );


        /*
        |--------------------------------------------------------------------------
        | Temporary Password Session
        |--------------------------------------------------------------------------
        */

        session()->put(
            'new_instructor_passwords.'
            .
            $instructor->id,

            $plainPassword
        );


        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route(
                'university-admin.instructors.new',
                [
                    'instructor' =>
                        $instructor->id,
                ]
            )
            ->with(
                'success',
                'Instructor created successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Newly Created Instructor
    |--------------------------------------------------------------------------
    */

    public function showNewInstructor(
        Instructor $instructor
    ): Response {

        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this
            ->ensureInstructorBelongsToUniversity(
                $instructor,
                $admin
            );


        /*
        |--------------------------------------------------------------------------
        | Ensure Role
        |--------------------------------------------------------------------------
        */

        $role =
            $this->ensureInstructorRoleExists();


        if (
            !$instructor->hasRole(
                $role
            )
        ) {

            $instructor
                ->syncRoles([
                    $role,
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Temporary Password
        |--------------------------------------------------------------------------
        */

        $plainPassword =
            session()->get(
                'new_instructor_passwords.'
                .
                $instructor->id,

                ''
            );


        /*
        |--------------------------------------------------------------------------
        | University Access Code
        |--------------------------------------------------------------------------
        */

        $universityAccessCode =
            $admin
                ->university
                ->access_code;


        /*
        |--------------------------------------------------------------------------
        | Load ALL Instructor Components
        |--------------------------------------------------------------------------
        */

        $instructor
            ->loadMissing(
                'managedComponents'
            );


        $assignedComponents =
            $instructor
                ->componentCodes();


        /*
        |--------------------------------------------------------------------------
        | Render Page
        |--------------------------------------------------------------------------
        */

        return Inertia::render(
            'UniversityAdmin/Users/Instructor/NewInstructorPage',
            [
                'instructor' => [

                    'id' =>
                        $instructor->id,

                    'university_id' =>
                        $instructor->university_id,

                    'full_name' =>
                        $instructor->full_name,

                    'username' =>
                        $instructor->username,

                    'email' =>
                        $instructor->email,

                    'password' =>
                        $plainPassword,

                    'phone_number' =>
                        $instructor->phone_number,

                    'profile_photo' =>
                        $instructor->profile_photo,


                    /*
                    |--------------------------------------------------------------------------
                    | Primary Component
                    |--------------------------------------------------------------------------
                    */

                    'component' =>
                        $instructor
                            ->primaryComponent(),


                    /*
                    |--------------------------------------------------------------------------
                    | All Components
                    |--------------------------------------------------------------------------
                    */

                    'components' =>
                        $assignedComponents,

                    'component_codes' =>
                        $assignedComponents,

                    'components_display' =>
                        implode(
                            ', ',
                            $assignedComponents
                        ),


                    /*
                    |--------------------------------------------------------------------------
                    | Full Component Names
                    |--------------------------------------------------------------------------
                    */

                    'component_name' =>
                        implode(
                            ', ',
                            array_map(
                                fn (
                                    $component
                                ) =>
                                    $this
                                        ->getComponentName(
                                            $component
                                        ),

                                $assignedComponents
                            )
                        ),


                    'status' =>
                        $instructor->status,

                    'access_code' =>
                        $universityAccessCode,

                    'role' =>
                        self::INSTRUCTOR_ROLE,
                ],


                'university' => [

                    'id' =>
                        $admin
                            ->university
                            ->id,

                    'name' =>
                        $admin
                            ->university
                            ->name,

                    'acronym' =>
                        $admin
                            ->university
                            ->acronym,
                ],
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Edit Instructor
    |--------------------------------------------------------------------------
    */

    public function edit(
        Instructor $instructor
    ): Response {

        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this
            ->ensureInstructorBelongsToUniversity(
                $instructor,
                $admin
            );


        /*
        |--------------------------------------------------------------------------
        | Ensure Instructor Role
        |--------------------------------------------------------------------------
        */

        $role =
            $this->ensureInstructorRoleExists();


        if (
            !$instructor->hasRole(
                $role
            )
        ) {

            $instructor
                ->syncRoles([
                    $role,
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | University Components
        |--------------------------------------------------------------------------
        */

        $components =
            $this->getUniversityComponents(
                $admin
            );


        /*
        |--------------------------------------------------------------------------
        | Instructor Components
        |--------------------------------------------------------------------------
        */

        $instructor
            ->loadMissing(
                'managedComponents'
            );


        $assignedComponents =
            $instructor
                ->componentCodes();


        /*
        |--------------------------------------------------------------------------
        | Render
        |--------------------------------------------------------------------------
        */

        return Inertia::render(
            'UniversityAdmin/Users/Instructor/EditInstructorPage',
            [
                'instructor' => [

                    'id' =>
                        $instructor->id,

                    'university_id' =>
                        $instructor->university_id,

                    'full_name' =>
                        $instructor->full_name,

                    'username' =>
                        $instructor->username,

                    'email' =>
                        $instructor->email,

                    'phone_number' =>
                        $instructor->phone_number,

                    'profile_photo' =>
                        $instructor->profile_photo,

                    'component' =>
                        $instructor
                            ->primaryComponent(),

                    'components' =>
                        $assignedComponents,

                    'component_codes' =>
                        $assignedComponents,

                    'status' =>
                        $instructor->status,

                    'role' =>
                        self::INSTRUCTOR_ROLE,
                ],


                'components' =>
                    $components,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Instructor
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Instructor $instructor
    ): RedirectResponse {

        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this
            ->ensureInstructorBelongsToUniversity(
                $instructor,
                $admin
            );


        $instructorRole =
            $this->ensureInstructorRoleExists();


        $components =
            $this->getUniversityComponents(
                $admin
            );


        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([

                'full_name' => [
                    'required',
                    'string',
                    'max:255',
                ],


                'username' => [
                    'required',
                    'string',
                    'max:100',

                    Rule::unique(
                        'instructors',
                        'username'
                    )
                        ->ignore(
                            $instructor->id
                        ),
                ],


                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',

                    Rule::unique(
                        'instructors',
                        'email'
                    )
                        ->ignore(
                            $instructor->id
                        ),
                ],


                'phone_number' => [
                    'required',
                    'string',
                    'max:30',
                ],


                'password' => [
                    'nullable',
                    'string',
                    'min:8',
                    'max:255',
                ],


                /*
                |--------------------------------------------------------------------------
                | Multiple Components
                |--------------------------------------------------------------------------
                */

                'components' => [
                    'required',
                    'array',
                    'min:1',
                    'max:3',
                ],


                'components.*' => [
                    'required',
                    'string',
                    'distinct',

                    Rule::in(
                        $components
                    ),
                ],


                'component' => [
                    'nullable',
                    'string',

                    Rule::in(
                        $components
                    ),
                ],


                'status' => [
                    'required',

                    Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],
            ]);


        /*
        |--------------------------------------------------------------------------
        | Normalize Components
        |--------------------------------------------------------------------------
        */

        $selectedComponents =
            array_values(
                array_unique(
                    array_map(
                        static fn (
                            $component
                        ) =>
                            strtoupper(
                                trim(
                                    (string)
                                    $component
                                )
                            ),

                        $validated[
                            'components'
                        ]
                    )
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $instructor,
                $validated,
                $selectedComponents,
                $instructorRole
            ): void {

                $instructor->full_name =
                    $validated[
                        'full_name'
                    ];

                $instructor->username =
                    $validated[
                        'username'
                    ];

                $instructor->email =
                    $validated[
                        'email'
                    ];

                $instructor->phone_number =
                    $validated[
                        'phone_number'
                    ];


                /*
                |--------------------------------------------------------------------------
                | Default Component
                |--------------------------------------------------------------------------
                */

                $instructor->component =
                    $selectedComponents[0];


                $instructor->status =
                    $validated[
                        'status'
                    ];


                /*
                |--------------------------------------------------------------------------
                | Optional Password
                |--------------------------------------------------------------------------
                */

                if (
                    !empty(
                        $validated[
                            'password'
                        ]
                        ??
                        null
                    )
                ) {

                    $instructor->password =
                        $validated[
                            'password'
                        ];

                }


                $instructor->save();


                /*
                |--------------------------------------------------------------------------
                | Remove Old Component Assignments
                |--------------------------------------------------------------------------
                */

                $instructor
                    ->managedComponents()
                    ->delete();


                /*
                |--------------------------------------------------------------------------
                | Save New Component Assignments
                |--------------------------------------------------------------------------
                */

                foreach (
                    $selectedComponents
                    as
                    $component
                ) {

                    $instructor
                        ->managedComponents()
                        ->create([
                            'component' =>
                                $component,
                        ]);

                }


                /*
                |--------------------------------------------------------------------------
                | Ensure Instructor Role
                |--------------------------------------------------------------------------
                */

                $instructor
                    ->syncRoles([
                        $instructorRole,
                    ]);

            }
        );


        return redirect()
            ->route(
                'university-admin.instructors'
            )
            ->with(
                'success',
                'Instructor updated successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Instructor
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Instructor $instructor
    ): RedirectResponse {

        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this
            ->ensureInstructorBelongsToUniversity(
                $instructor,
                $admin
            );


        $instructorName =
            $instructor->full_name;


        /*
        |--------------------------------------------------------------------------
        | Remove Temporary Password
        |--------------------------------------------------------------------------
        */

        session()->forget(
            'new_instructor_passwords.'
            .
            $instructor->id
        );


        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $instructor
            ): void {

                $instructor
                    ->syncRoles([]);


                $instructor
                    ->delete();

            }
        );


        return redirect()
            ->route(
                'university-admin.instructors'
            )
            ->with(
                'success',
                $instructorName
                .
                ' was deleted successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Send Instructor Email
    |--------------------------------------------------------------------------
    */

    public function sendEmail(
        Instructor $instructor
    ): RedirectResponse {

        $admin =
            $this->getAuthenticatedUniversityAdmin();


        $this
            ->ensureInstructorBelongsToUniversity(
                $instructor,
                $admin
            );


        /*
        |--------------------------------------------------------------------------
        | Email Required
        |--------------------------------------------------------------------------
        */

        if (
            empty(
                $instructor->email
            )
        ) {

            return back()
                ->withErrors([
                    'email' =>
                        'The instructor does not have an email address.',
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Ensure Role
        |--------------------------------------------------------------------------
        */

        $role =
            $this->ensureInstructorRoleExists();


        if (
            !$instructor->hasRole(
                $role
            )
        ) {

            $instructor
                ->syncRoles([
                    $role,
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Temporary Password
        |--------------------------------------------------------------------------
        */

        $plainPassword =
            session()->get(
                'new_instructor_passwords.'
                .
                $instructor->id
            );


        if (
            empty(
                $plainPassword
            )
        ) {

            return back()
                ->withErrors([
                    'email' =>
                        'The temporary instructor password is no longer available. Please reset the instructor password before sending account information.',
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | University
        |--------------------------------------------------------------------------
        */

        $university =
            $admin->university;


        if (
            !$university
        ) {

            return back()
                ->withErrors([
                    'email' =>
                        'The university information could not be found.',
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | University Access Code
        |--------------------------------------------------------------------------
        */

        $accessCode =
            $university
                ->access_code;


        if (
            empty(
                $accessCode
            )
        ) {

            return back()
                ->withErrors([
                    'email' =>
                        'The university access code could not be found.',
                ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Login URL
        |--------------------------------------------------------------------------
        */

        $loginUrl =
            url(
                '/instructor-coordinator/access-code'
            );


        /*
        |--------------------------------------------------------------------------
        | Send Email
        |--------------------------------------------------------------------------
        */

        try {

            Mail::to(
                $instructor->email,
                $instructor->full_name
            )
                ->send(
                    new InstructorAccountMail(
                        instructor:
                            $instructor,

                        university:
                            $university,

                        temporaryPassword:
                            $plainPassword,

                        accessCode:
                            $accessCode,

                        loginUrl:
                            $loginUrl,
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | Remove Temporary Password After Successful Email
            |--------------------------------------------------------------------------
            */

            session()->forget(
                'new_instructor_passwords.'
                .
                $instructor->id
            );


            return back()
                ->with(
                    'success',

                    'Instructor account information was successfully sent to '
                    .
                    $instructor->email
                    .
                    '.'
                );

        } catch (
            \Throwable $exception
        ) {

            report(
                $exception
            );


            return back()
                ->withErrors([
                    'email' =>
                        app()->environment(
                            'local'
                        )
                            ? 'Unable to send email: '
                                .
                                $exception
                                    ->getMessage()

                            : 'Unable to send the instructor email. Please try again.',
                ]);

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Ensure Instructor Role Exists
    |--------------------------------------------------------------------------
    */

    private function ensureInstructorRoleExists():
        Role
    {
        app(
            PermissionRegistrar::class
        )
            ->forgetCachedPermissions();


        $role =
            Role::firstOrCreate([
                'name' =>
                    self::INSTRUCTOR_ROLE,

                'guard_name' =>
                    self::INSTRUCTOR_GUARD,
            ]);


        app(
            PermissionRegistrar::class
        )
            ->forgetCachedPermissions();


        return $role;
    }


    /*
    |--------------------------------------------------------------------------
    | Get Authenticated University Administrator
    |--------------------------------------------------------------------------
    */

    private function getAuthenticatedUniversityAdmin():
        UniversityAdministrator
    {
        $admin =
            Auth::guard(
                'university_admin'
            )
                ->user();


        if (
            !$admin
        ) {

            abort(
                401,
                'You must be logged in as a University Administrator.'
            );

        }


        if (
            !(
                $admin instanceof
                UniversityAdministrator
            )
        ) {

            abort(
                403,
                'Invalid University Administrator account.'
            );

        }


        $admin
            ->loadMissing(
                'university'
            );


        if (
            !$admin->university_id
            ||
            !$admin->university
        ) {

            abort(
                403,
                'University Administrator is not connected to a university.'
            );

        }


        return $admin;
    }


    /*
    |--------------------------------------------------------------------------
    | University Components
    |--------------------------------------------------------------------------
    */

    private function getUniversityComponents(
        UniversityAdministrator $admin
    ): array {

        $components =
            $admin
                ->university
                ->components;


        if (
            !is_array(
                $components
            )
        ) {

            return [];

        }


        /*
        |--------------------------------------------------------------------------
        | Normalize
        |--------------------------------------------------------------------------
        */

        $components =
            array_map(
                static fn (
                    $component
                ) =>
                    strtoupper(
                        trim(
                            (string)
                            $component
                        )
                    ),

                $components
            );


        /*
        |--------------------------------------------------------------------------
        | Allow Only Valid NSTP Components
        |--------------------------------------------------------------------------
        */

        return array_values(
            array_unique(
                array_filter(
                    $components,

                    static fn (
                        $component
                    ) =>
                        in_array(
                            $component,
                            [
                                'LTS',
                                'CWTS',
                                'ROTC',
                            ],
                            true
                        )
                )
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Component Full Name
    |--------------------------------------------------------------------------
    */

    private function getComponentName(
        ?string $component
    ): string {

        return match (
            strtoupper(
                $component
                ??
                ''
            )
        ) {

            'CWTS' =>
                'CWTS - Civic Welfare Training Service',

            'LTS' =>
                'LTS - Literacy Training Service',

            'ROTC' =>
                "ROTC - Reserve Officers' Training Corps",

            default =>
                $component
                ??
                '',
        };
    }


    /*
    |--------------------------------------------------------------------------
    | Instructor University Security
    |--------------------------------------------------------------------------
    */

    private function ensureInstructorBelongsToUniversity(
        Instructor $instructor,
        UniversityAdministrator $admin
    ): void {

        if (
            (int)
            $instructor->university_id
            !==
            (int)
            $admin->university_id
        ) {

            abort(
                403,
                'You are not authorized to access this instructor.'
            );

        }
    }
}