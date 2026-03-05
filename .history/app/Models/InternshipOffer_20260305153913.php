<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InternshipOffer extends Model
{
    use HasFactory;

 
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'domain',
        'location',
        'type',
        'duration_unit',
        'duration_value',
        'required_skills',
        'status',
        'deadline',
    ];

    
    protected $casts = [
        'required_skills' => 'array',
        'deadline'        => 'date',
    ];


    // The company that created the internship offer
    public function company()
    {
        return $this->belongsTo(User::class, 'user_id')
                    ->with('companyProfile');
    }

    // Applications submitted for this offer
    public function applications()
    {
        return $this->hasMany(Application::class, 'offer_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    // Only open offers
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    // Filter by location (Wilaya)
    public function scopeByWilaya($query, $wilaya)
    {
        return $query->where('location', $wilaya);
    }

    // Filter by domain
    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', 'like', "%{$domain}%");
    }

    // Filter by type (remote, onsite, hybrid)
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Filter by required skill (JSON column)
    public function scopeBySkill($query, $skill)
    {
        return $query->whereJsonContains('required_skills', $skill);
    }
}