<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'project_id',
        'category_id',
        'transaction_date',
        'pelaksana',
        'payment_method',
        'title',
        'qty',
        'unit',
        'unit_price',
        'total_amount',
        'vendor',
        'proof_number',
        'validation_status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date:Y-m-d',
            'qty' => 'float',
            'unit_price' => 'float',
            'total_amount' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
