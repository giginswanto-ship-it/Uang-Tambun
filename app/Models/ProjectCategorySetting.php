<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCategorySetting extends Model
{
    protected $fillable = [
        'project_id',
        'default_category_id',
        'sort_by',
        'show_category_section',
        'budget_alert_percent',
    ];

    protected function casts(): array
    {
        return [
            'show_category_section' => 'boolean',
            'budget_alert_percent' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }
}
