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
    ];

    protected function casts(): array
    {
        return [
            'face_detected' => 'boolean',
        ];
    }
}