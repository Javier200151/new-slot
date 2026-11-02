<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\NotificationRuleCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NotificationRule extends Model
{
    use Auditable;

    protected $table = 'notification_rules';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['enabled', 'max_age_value', 'max_age_unit'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'max_age_value' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $rule) => Cache::forget('sqa.notification-rule.' . $rule->getKey()));
    }

    public function getLabelAttribute(): string
    {
        return NotificationRuleCatalog::TYPES[$this->key]['label'] ?? $this->key;
    }

    public function getCategoryAttribute(): string
    {
        return NotificationRuleCatalog::TYPES[$this->key]['category'] ?? 'Otros';
    }

    public function getAllowsAgeAttribute(): bool
    {
        return (bool) (NotificationRuleCatalog::TYPES[$this->key]['age'] ?? false);
    }
}
