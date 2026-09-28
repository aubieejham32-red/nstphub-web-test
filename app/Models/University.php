<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'acronym',
        'type',
        'campus_type',
        'email',
        'contact_number',
        'website',
        'logo',

        'region',
        'province',
        'city',
        'barangay',
        'zip_code',
        'complete_address',

        'academic_year',
        'semester',
        'components',
        'max_students',

        'access_code',
        'status',
    ];

    protected $casts = [
        'components' => 'array',
    ];

    /**
     * One University has one Administrator.
     */
    public function administrator()
    {
        return $this->hasOne(
            UniversityAdministrator::class,
            'university_id',
            'id'
        );
    }

    /**
     * One University can have many Instructors.
     */
    public function instructors()
    {
        return $this->hasMany(
            Instructor::class,
            'university_id',
            'id'
        );
    }
}