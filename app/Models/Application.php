<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'offer_id',
        'status',
        'cover_letter',
        'admin_note',
        'selected_at',
        'internship_starts_at',
        'internship_ends_at',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
        'internship_starts_at' => 'date',
        'internship_ends_at' => 'date',
    ];

    // ─── Relationships ─────────────────────────────────────
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')
                    ->with('studentProfile');
    }

    public function offer()
    {
        return $this->belongsTo(InternshipOffer::class)
                    ->with('company');
    }

    
    public function convention()
    {
        return $this->hasOne(Convention::class);
    }

    public static function activePlacementForStudent(int $studentId): ?self
    {
        return static::query()
            ->where('student_id', $studentId)
            ->whereIn('status', ['selected', 'validated'])
            ->whereDate('internship_ends_at', '>=', Carbon::today())
            ->first();
    }

    public function allowsApplicationToOffer(InternshipOffer $offer): bool
    {
        if (! $this->internship_ends_at || ! $offer->internship_starts_at) {
            return false;
        }

        return Carbon::parse($offer->internship_starts_at)->startOfDay()
            ->gt(Carbon::parse($this->internship_ends_at)->startOfDay());
    }

    // ─── Status Helpers ────────────────────────────────────
    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isAccepted(): bool   { return $this->status === 'accepted'; }
    public function isRefused(): bool    { return $this->status === 'refused'; }
    public function isValidated(): bool  { return $this->status === 'validated'; }
    public function isRejected(): bool   { return $this->status === 'rejected'; }
    public function isSelected(): bool   { return $this->status === 'selected'; }
}