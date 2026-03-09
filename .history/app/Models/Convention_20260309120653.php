<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Convention extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'file_path',
        'convention_number',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class)->with([
            'student.studentProfile',
            'offer.company.companyProfile',
        ]);
    }
}