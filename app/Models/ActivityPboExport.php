<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Export ownership and last verified checksums; never adopt arbitrary Windows files. */
class ActivityPboExport extends Model
{
    protected $fillable = [
        'activity_id', 'filename', 'source_path', 'pbo_sha256', 'html_sha256',
        'status', 'last_error', 'synced_at',
    ];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
