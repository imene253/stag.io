
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'industry',
        'location',
        'website_url',
        'company_size',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}