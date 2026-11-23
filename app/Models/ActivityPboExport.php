<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Export ownership and last verified checksums; never adopt arbitrary Windows files. */
class ActivityPboExport extends Model
{
    protected $fillable = [
        'pbo_key', 'html_activity_id', 'filename', 'source_path', 'pbo_sha256', 'html_sha256',
        'status', 'last_error', 'synced_at', 'replacement_approved', 'html_overwrite_approved',
    ];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime', 'replacement_approved' => 'boolean', 'html_overwrite_approved' => 'boolean'];
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'pbo_key', 'pbo_key');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'html_activity_id');
    }
}
