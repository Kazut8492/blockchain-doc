<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fiscal_year',
        'fiscal_period',
        'document_type',
        'creator',
        'last_modifier',
        'status',
    ];

    /**
     * Get the user that owns the fiscal entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the documents for the fiscal entry.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}