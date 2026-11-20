<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatronVerificationCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'patron_id',
        'cardnumber',
        'patron_name',
        'action',
        'barcode',
        'photo_path',
        'face_detected',
        'match_status',
        'match_score',
        'matched_at',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'transaction_items',
    ];

    protected function casts(): array
    {
        return [
            'face_detected' => 'boolean',
        ];
    }
}