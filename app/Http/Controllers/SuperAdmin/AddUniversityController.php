<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\UniversityCredentialsMail;
use App\Models\University;
use App\Models\UniversityAdministrator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AddUniversityController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Store New University
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ) {
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validator = Validator::make(
            $request->all(),
            [
                /*
                |--------------------------------------------------------------------------
                | Institution Information
                |--------------------------------------------------------------------------
                */

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'acronym' => [
                    'required',
                    'string',
                    'max:30',
                ],

                'type' => [
                    'required',
                    'in:Public,Private',
                ],

                'campus_type' => [
                    'required',
                    'in:Main Campus,Extension',
                ],

                'email' => [
                    'required',
                    'email',
                    'unique:universities,email',
                ],

                'contact_number' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'website' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],


                /*
                |--------------------------------------------------------------------------
                | Address
                |--------------------------------------------------------------------------
                */

                'region' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'province' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'city' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'barangay' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'zip_code' => [
                    'required',
                    'string',
                    'max:10',
                ],

                'complete_address' => [
                    'required',
                    'string',
                ],


                /*
                |--------------------------------------------------------------------------
                | NSTP Configuration
                |--------------------------------------------------------------------------
                */

                'academic_year' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'semester' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'components' => [
                    'required',
                ],

                'max_students' => [
                    'required',
                    'integer',
                    'min:1',
                ],


                /*
                |--------------------------------------------------------------------------
                | University Administrator
                |--------------------------------------------------------------------------
                */

                'admin_first_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'admin_middle_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'admin_last_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'admin_email' => [
                    'required',
                    'email',
                    'unique:university_administrators,email',
                ],

                'admin_phone' => [
                    'required',
                    'string',
                    'max:30',
                ],

                'admin_username' => [
                    'required',
                    'string',
                    'max:100',
                    'unique:university_administrators,username',
                ],
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Validation Failed
        |--------------------------------------------------------------------------
        */

        if (
            $validator->fails()
        ) {
            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Validation failed.',

                    'errors' =>
                        $validator->errors(),
                ],
                422
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Logo Path
        |--------------------------------------------------------------------------
        */

        $logoPath = null;


        /*
        |--------------------------------------------------------------------------
        | Begin Database Transaction
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();


        try {
            /*
            |--------------------------------------------------------------------------
            | Generate University Access Code
            |--------------------------------------------------------------------------
            */

            $accessCode =
                $this->generateAccessCode();


            /*
            |--------------------------------------------------------------------------
            | Generate Temporary University Administrator Password
            |--------------------------------------------------------------------------
            */

            $generatedPassword =
                Str::password(
                    length: 12
                );


            /*
            |--------------------------------------------------------------------------
            | Upload University Logo
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile(
                    'logo'
                )
            ) {
                $logoPath =
                    $request
                        ->file(
                            'logo'
                        )
                        ->store(
                            'universities/logos',
                            'public'
                        );
            }


            /*
            |--------------------------------------------------------------------------
            | Prepare University Components
            |--------------------------------------------------------------------------
            */

            $components =
                $request->components;


            if (
                !is_array(
                    $components
                )
            ) {
                $components =
                    json_decode(
                        $components,
                        true
                    );
            }


            if (
                !is_array(
                    $components
                )
            ) {
                $components = [];
            }


            /*
            |--------------------------------------------------------------------------
            | Create University
            |--------------------------------------------------------------------------
            */

            $university =
                University::create([
                    /*
                    |--------------------------------------------------------------------------
                    | Basic Information
                    |--------------------------------------------------------------------------
                    */

                    'name' =>
                        trim(
                            $request->name
                        ),

                    'acronym' =>
                        trim(
                            $request->acronym
                        ),

                    'type' =>
                        $request->type,

                    'campus_type' =>
                        $request->campus_type,


                    /*
                    |--------------------------------------------------------------------------
                    | Contact Information
                    |--------------------------------------------------------------------------
                    */

                    'email' =>
                        strtolower(
                            trim(
                                $request->email
                            )
                        ),

                    'contact_number' =>
                        $request->contact_number,

                    'website' =>
                        $request->website,

                    'logo' =>
                        $logoPath,


                    /*
                    |--------------------------------------------------------------------------
                    | Address
                    |--------------------------------------------------------------------------
                    */

                    'region' =>
                        $request->region,

                    'province' =>
                        $request->province,

                    'city' =>
                        $request->city,

                    'barangay' =>
                        $request->barangay,

                    'zip_code' =>
                        $request->zip_code,

                    'complete_address' =>
                        $request->complete_address,


                    /*
                    |--------------------------------------------------------------------------
                    | NSTP Configuration
                    |--------------------------------------------------------------------------
                    */

                    'academic_year' =>
                        $request->academic_year,

                    'semester' =>
                        $request->semester,

                    'components' =>
                        $components,

                    'max_students' =>
                        $request->max_students,


                    /*
                    |--------------------------------------------------------------------------
                    | University Access
                    |--------------------------------------------------------------------------
                    */

                    'access_code' =>
                        $accessCode,

                    'status' =>
                        'ACTIVE',
                ]);


            /*
            |--------------------------------------------------------------------------
            | Create University Administrator
            |--------------------------------------------------------------------------
            */

            $administrator =
                UniversityAdministrator::create([
                    /*
                    |--------------------------------------------------------------------------
                    | University
                    |--------------------------------------------------------------------------
                    */

                    'university_id' =>
                        $university->id,


                    /*
                    |--------------------------------------------------------------------------
                    | Administrator Name
                    |--------------------------------------------------------------------------
                    */

                    'first_name' =>
                        trim(
                            $request
                                ->admin_first_name
                        ),

                    'middle_name' =>
                        $request
                            ->admin_middle_name
                            ? trim(
                                $request
                                    ->admin_middle_name
                            )
                            : null,

                    'last_name' =>
                        trim(
                            $request
                                ->admin_last_name
                        ),


                    /*
                    |--------------------------------------------------------------------------
                    | Administrator Contact
                    |--------------------------------------------------------------------------
                    */

                    'email' =>
                        strtolower(
                            trim(
                                $request
                                    ->admin_email
                            )
                        ),

                    'phone' =>
                        trim(
                            $request
                                ->admin_phone
                        ),


                    /*
                    |--------------------------------------------------------------------------
                    | Username
                    |--------------------------------------------------------------------------
                    */

                    'username' =>
                        trim(
                            $request
                                ->admin_username
                        ),


                    /*
                    |--------------------------------------------------------------------------
                    | Login Password
                    |--------------------------------------------------------------------------
                    |
                    | UniversityAdministrator model has:
                    |
                    | 'password' => 'hashed'
                    |
                    */

                    'password' =>
                        $generatedPassword,


                    /*
                    |--------------------------------------------------------------------------
                    | Temporary Password
                    |--------------------------------------------------------------------------
                    |
                    | UniversityAdministrator model has:
                    |
                    | 'temporary_password' => 'encrypted'
                    |
                    */

                    'temporary_password' =>
                        $generatedPassword,


                    /*
                    |--------------------------------------------------------------------------
                    | Password Status
                    |--------------------------------------------------------------------------
                    */

                    'must_change_password' =>
                        true,
                ]);


            /*
            |--------------------------------------------------------------------------
            | Assign University Administrator Role
            |--------------------------------------------------------------------------
            */

            if (
                !$administrator->hasRole(
                    'university-admin'
                )
            ) {
                $administrator->assignRole(
                    'university-admin'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | University Administrator Login Portal
            |--------------------------------------------------------------------------
            */

            $portalUrl =
                route(
                    'university-admin.login'
                );


            /*
            |--------------------------------------------------------------------------
            | Generated Credentials
            |--------------------------------------------------------------------------
            */

            $generatedCredentials = [

                /*
                |--------------------------------------------------------------------------
                | University
                |--------------------------------------------------------------------------
                */

                'university' =>
                    $university->name,


                /*
                |--------------------------------------------------------------------------
                | Access Code
                |--------------------------------------------------------------------------
                */

                'access_code' =>
                    $accessCode,


                /*
                |--------------------------------------------------------------------------
                | Login Portal
                |--------------------------------------------------------------------------
                */

                'portal_url' =>
                    $portalUrl,


                /*
                |--------------------------------------------------------------------------
                | University Administrator Email
                |--------------------------------------------------------------------------
                */

                'email' =>
                    $administrator->email,


                /*
                |--------------------------------------------------------------------------
                | Username
                |--------------------------------------------------------------------------
                */

                'username' =>
                    $administrator->username,


                /*
                |--------------------------------------------------------------------------
                | Temporary Password
                |--------------------------------------------------------------------------
                */

                'password' =>
                    $generatedPassword,


                /*
                |--------------------------------------------------------------------------
                | Administrator Name
                |--------------------------------------------------------------------------
                */

                'administrator' =>
                    $administrator
                        ->full_name,
            ];


            /*
            |--------------------------------------------------------------------------
            | Send Credential Email
            |--------------------------------------------------------------------------
            */

            $this
                ->sendInvitationEmail(
                    $administrator,
                    $generatedCredentials
                );


            /*
            |--------------------------------------------------------------------------
            | Generate Credentials PDF URL
            |--------------------------------------------------------------------------
            */

            $pdfUrl =
                route(
                    'superadmin.university.pdf',
                    [
                        'university' =>
                            $university->id,
                    ]
                );


            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | Successful Response
            |--------------------------------------------------------------------------
            */

            return response()->json(
                [
                    'success' =>
                        true,

                    'message' =>
                        'University created successfully.',


                    /*
                    |--------------------------------------------------------------------------
                    | University
                    |--------------------------------------------------------------------------
                    */

                    'university_id' =>
                        $university->id,

                    'university' =>
                        $university,


                    /*
                    |--------------------------------------------------------------------------
                    | Access Information
                    |--------------------------------------------------------------------------
                    */

                    'access_code' =>
                        $accessCode,

                    'portal_url' =>
                        $portalUrl,

                    'pdf_url' =>
                        $pdfUrl,


                    /*
                    |--------------------------------------------------------------------------
                    | Administrator
                    |--------------------------------------------------------------------------
                    */

                    'administrator' =>
                        $administrator,
                ],
                201
            );

        } catch (
            \Throwable $e
        ) {
            /*
            |--------------------------------------------------------------------------
            | Rollback Database
            |--------------------------------------------------------------------------
            */

            DB::rollBack();


            /*
            |--------------------------------------------------------------------------
            | Remove Uploaded Logo
            |--------------------------------------------------------------------------
            */

            if (
                !empty(
                    $logoPath
                )
            ) {
                Storage::disk(
                    'public'
                )->delete(
                    $logoPath
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Report Error
            |--------------------------------------------------------------------------
            */

            report(
                $e
            );


            /*
            |--------------------------------------------------------------------------
            | Error Response
            |--------------------------------------------------------------------------
            */

            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Unable to create university.',

                    'error' =>
                        app()->environment(
                            'local'
                        )
                            ? $e->getMessage()
                            : 'An unexpected error occurred.',
                ],
                500
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Download University Credentials PDF
    |--------------------------------------------------------------------------
    |
    | LEGAL SIZE:
    |
    | 8.5 x 14 inches
    |
    | This method generates the PDF on demand and returns a real
    | Laravel download response.
    |
    */

    public function downloadPdf(
        University $university
    ) {
        /*
        |--------------------------------------------------------------------------
        | Find University Administrator
        |--------------------------------------------------------------------------
        */

        $administrator =
            UniversityAdministrator::query()
                ->where(
                    'university_id',
                    $university->id
                )
                ->first();


        /*
        |--------------------------------------------------------------------------
        | Administrator Not Found
        |--------------------------------------------------------------------------
        */

        if (
            !$administrator
        ) {
            abort(
                404,
                'University administrator not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Get Temporary Password
        |--------------------------------------------------------------------------
        */

        $temporaryPassword =
            $administrator
                ->temporary_password;


        /*
        |--------------------------------------------------------------------------
        | Temporary Password No Longer Available
        |--------------------------------------------------------------------------
        */

        if (
            empty(
                $temporaryPassword
            )
        ) {
            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'The temporary password is no longer available. '
                        . 'The administrator may already have changed the password. '
                        . 'A credentials PDF containing the original temporary '
                        . 'password can no longer be generated.',
                ],
                422
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Build Credentials
        |--------------------------------------------------------------------------
        */

        $credentials = [

            'university' =>
                $university->name,

            'access_code' =>
                $university->access_code,

            'portal_url' =>
                route(
                    'university-admin.login'
                ),

            'email' =>
                $administrator->email,

            'username' =>
                $administrator->username,

            'password' =>
                $temporaryPassword,

            'administrator' =>
                $administrator->full_name,
        ];


        /*
        |--------------------------------------------------------------------------
        | Generate LEGAL Portrait PDF
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | This must match:
        |
        | resources/views/pdf/university_credentials.blade.php
        |
        | @page {
        |     size: legal portrait;
        | }
        |
        */

        $pdf =
            Pdf::loadView(
                'pdf.university_credentials',
                [
                    'credentials' =>
                        $credentials,
                ]
            )
                ->setPaper(
                    'legal',
                    'portrait'
                );


        /*
        |--------------------------------------------------------------------------
        | Safe University Filename
        |--------------------------------------------------------------------------
        */

        $safeUniversity =
            Str::slug(
                (string) (
                    $university->acronym
                    ?: $university->name
                )
            );


        if (
            $safeUniversity ===
            ''
        ) {
            $safeUniversity =
                'university';
        }


        /*
        |--------------------------------------------------------------------------
        | Filename
        |--------------------------------------------------------------------------
        */

        $filename =
            'nstphub_credentials_'
            . $safeUniversity
            . '_'
            . now()->format(
                'Ymd_His'
            )
            . '.pdf';


        /*
        |--------------------------------------------------------------------------
        | Download PDF
        |--------------------------------------------------------------------------
        */

        return $pdf->download(
            $filename
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resend Credentials Email
    |--------------------------------------------------------------------------
    */

    public function sendEmail(
        University $university
    ) {
        try {
            /*
            |--------------------------------------------------------------------------
            | Find University Administrator
            |--------------------------------------------------------------------------
            */

            $administrator =
                UniversityAdministrator::query()
                    ->where(
                        'university_id',
                        $university->id
                    )
                    ->first();


            /*
            |--------------------------------------------------------------------------
            | Administrator Not Found
            |--------------------------------------------------------------------------
            */

            if (
                !$administrator
            ) {
                return response()->json(
                    [
                        'success' =>
                            false,

                        'message' =>
                            'University administrator not found.',
                    ],
                    404
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Administrator Email
            |--------------------------------------------------------------------------
            */

            if (
                empty(
                    $administrator->email
                )
            ) {
                return response()->json(
                    [
                        'success' =>
                            false,

                        'message' =>
                            'The University Administrator does not have an email address.',
                    ],
                    422
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Temporary Password
            |--------------------------------------------------------------------------
            |
            | Automatically decrypted by:
            |
            | 'temporary_password' => 'encrypted'
            |
            */

            $temporaryPassword =
                $administrator
                    ->temporary_password;


            /*
            |--------------------------------------------------------------------------
            | Temporary Password Not Available
            |--------------------------------------------------------------------------
            */

            if (
                empty(
                    $temporaryPassword
                )
            ) {
                return response()->json(
                    [
                        'success' =>
                            false,

                        'message' =>
                            'The temporary password is no longer available. '
                            . 'The University Administrator may have already '
                            . 'changed the password. Use Forgot Password if '
                            . 'account recovery is required.',
                    ],
                    422
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Build Credentials
            |--------------------------------------------------------------------------
            */

            $credentials = [

                /*
                |--------------------------------------------------------------------------
                | University
                |--------------------------------------------------------------------------
                */

                'university' =>
                    $university->name,


                /*
                |--------------------------------------------------------------------------
                | Access Code
                |--------------------------------------------------------------------------
                */

                'access_code' =>
                    $university
                        ->access_code,


                /*
                |--------------------------------------------------------------------------
                | Login Portal
                |--------------------------------------------------------------------------
                */

                'portal_url' =>
                    route(
                        'university-admin.login'
                    ),


                /*
                |--------------------------------------------------------------------------
                | University Administrator Email
                |--------------------------------------------------------------------------
                */

                'email' =>
                    $administrator
                        ->email,


                /*
                |--------------------------------------------------------------------------
                | Username
                |--------------------------------------------------------------------------
                */

                'username' =>
                    $administrator
                        ->username,


                /*
                |--------------------------------------------------------------------------
                | Temporary Password
                |--------------------------------------------------------------------------
                */

                'password' =>
                    $temporaryPassword,


                /*
                |--------------------------------------------------------------------------
                | Administrator Name
                |--------------------------------------------------------------------------
                */

                'administrator' =>
                    $administrator
                        ->full_name,
            ];


            /*
            |--------------------------------------------------------------------------
            | Send Credentials Email
            |--------------------------------------------------------------------------
            */

            Mail::to(
                $administrator->email
            )->send(
                new UniversityCredentialsMail(
                    $credentials
                )
            );


            /*
            |--------------------------------------------------------------------------
            | Successful Response
            |--------------------------------------------------------------------------
            */

            return response()->json(
                [
                    'success' =>
                        true,

                    'message' =>
                        'Credentials email sent successfully.',
                ]
            );

        } catch (
            \Throwable $e
        ) {
            /*
            |--------------------------------------------------------------------------
            | Report Error
            |--------------------------------------------------------------------------
            */

            report(
                $e
            );


            /*
            |--------------------------------------------------------------------------
            | Error Response
            |--------------------------------------------------------------------------
            */

            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Email sending failed.',

                    'error' =>
                        app()->environment(
                            'local'
                        )
                            ? $e->getMessage()
                            : 'Unable to send credentials email.',
                ],
                500
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Unique Access Code
    |--------------------------------------------------------------------------
    |
    | Format:
    |
    | 2026-123456AB
    |
    */

    private function generateAccessCode(): string
    {
        do {
            /*
            |--------------------------------------------------------------------------
            | Current Year
            |--------------------------------------------------------------------------
            */

            $year =
                now()->format(
                    'Y'
                );


            /*
            |--------------------------------------------------------------------------
            | Random 6-Digit Number
            |--------------------------------------------------------------------------
            */

            $randomNumber =
                random_int(
                    100000,
                    999999
                );


            /*
            |--------------------------------------------------------------------------
            | Random Two Letters
            |--------------------------------------------------------------------------
            */

            $randomLetters =
                strtoupper(
                    Str::random(
                        2
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | Final Access Code
            |--------------------------------------------------------------------------
            */

            $code =
                "{$year}-{$randomNumber}{$randomLetters}";

        } while (
            University::where(
                'access_code',
                $code
            )->exists()
        );


        return $code;
    }


    /*
    |--------------------------------------------------------------------------
    | Send Invitation Email
    |--------------------------------------------------------------------------
    */

    private function sendInvitationEmail(
        UniversityAdministrator $administrator,
        array $credentials
    ): void {
        Mail::to(
            $administrator->email
        )->send(
            new UniversityCredentialsMail(
                $credentials
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Credential PDF
    |--------------------------------------------------------------------------
    |
    | LEGAL SIZE:
    |
    | 8.5 x 14 inches
    |
    | This method is also explicitly configured to Legal so all generated
    | credential PDFs use exactly the same paper size.
    |
    */

    private function generateCredentialPDF(
        array $credentials
    ): ?string {
        /*
        |--------------------------------------------------------------------------
        | Generate LEGAL Portrait PDF
        |--------------------------------------------------------------------------
        */

        $pdf =
            Pdf::loadView(
                'pdf.university_credentials',
                [
                    'credentials' =>
                        $credentials,
                ]
            )
                ->setPaper(
                    'legal',
                    'portrait'
                );


        /*
        |--------------------------------------------------------------------------
        | Filename
        |--------------------------------------------------------------------------
        */

        $filename =
            'credentials_'
            . now()->format(
                'Ymd_His'
            )
            . '_'
            . Str::random(
                6
            )
            . '.pdf';


        /*
        |--------------------------------------------------------------------------
        | Store PDF
        |--------------------------------------------------------------------------
        */

        Storage::disk(
            'public'
        )->put(
            'credentials/'
            . $filename,

            $pdf->output()
        );


        /*
        |--------------------------------------------------------------------------
        | Return Public URL
        |--------------------------------------------------------------------------
        */

        return Storage::url(
            'credentials/'
            . $filename
        );
    }
}