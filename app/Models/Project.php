<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = [
        'name',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order')->orderBy('id');
    }

    public function categorySetting(): HasOne
    {
        return $this->hasOne(ProjectCategorySetting::class);
    }

    public function investorFunds(): HasMany
    {
        return $this->hasMany(InvestorFund::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    public function kasbonTransactions(): HasMany
    {
        return $this->hasMany(KasbonTransaction::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }
}
