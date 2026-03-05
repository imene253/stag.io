<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Application;
use App\Models\InternshipOffer;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // =====================
    // Role Helpers
    // =====================
    public function isStudent(): bool 
    { 
        return $this->role === 'student'; 
    }

    public function isCompany(): bool 
    { 
        return $this->role === 'company'; 
    }

    public function isAdmin(): bool   
    { 
        return $this->role === 'admin'; 
    }

    // =====================
    // Relationships
    // =====================

    // Student profile
    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    // Company profile
    public function companyProfile()
    {
        return $this->hasOne(CompanyProfile::class);
    }

    // Student → Applications
    public function applications()
    {
        return $this->hasMany(Application::class, 'student_id');
    }

    // Company → Internship Offers
    public function offers()
    {
        return $this->hasMany(InternshipOffer::class, 'user_id');
    }
}