<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fiscal_entry_id', // New field
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'path',
        'hash',
        'blockchain_status',
        'transaction_hash',
        'blockchain_network',
        'blockchain_timestamp'
    ];

    protected $casts = [
        'blockchain_timestamp' => 'datetime',
        'size' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function fiscalEntry()
    {
        return $this->belongsTo(FiscalEntry::class);
    }
}