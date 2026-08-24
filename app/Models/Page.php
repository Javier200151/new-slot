<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\Auditable;

class Page extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Page $page): void {
            if (Auth::check()) {
                $page->created_by = Auth::id();
                $page->updated_by = Auth::id();
            }
        });

        static::updating(function (Page $page): void {
            if (Auth::check()) {
                $page->updated_by = Auth::id();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
