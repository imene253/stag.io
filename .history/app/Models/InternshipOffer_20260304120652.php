// app/Models/InternshipOffer.php
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

    // ─── Relationships ─────────────────────────────────────
    public function company()
    {
        return $this->belongsTo(User::class, 'user_id')
                    ->with('companyProfile');
    }

    public function applications()
    {
        return $this->hasMany(Application::class); // used in next module
    }

    // ─── Scopes ────────────────────────────────────────────
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByWilaya($query, $wilaya)
    {
        return $query->where('location', $wilaya);
    }

    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', 'like', "%{$domain}%");
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySkill($query, $skill)
    {
        return $query->whereJsonContains('required_skills', $skill);
    }
}