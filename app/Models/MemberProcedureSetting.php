<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MemberProcedureSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'alpha_metopa_id',
        'treasury_group_id',
        'tutors_group_id',
        'google_spreadsheet_id',
        'google_general_sheet_gid',
        'armasquads_squad_id',
    ];

    public static function current(): self
    {
        $setting = static::query()->firstOrCreate([], [
            'google_spreadsheet_id' => '1hMezm3dfuvuvYSrBECzvHll0vGqXgOIH8_0FzXKnvAk',
            'google_general_sheet_gid' => '1711111556',
        ]);

        if (! $setting->alpha_metopa_id) {
            $alphaId = Metopa::query()->whereRaw('UPPER(name) = ?', ['ALPHA'])->value('id');
            if ($alphaId) {
                $setting->forceFill(['alpha_metopa_id' => $alphaId])->saveQuietly();
            }
        }

        return $setting->refresh();
    }

    public function alphaMetopa()
    {
        return $this->belongsTo(Metopa::class, 'alpha_metopa_id')->withTrashed();
    }

    public function treasuryGroup()
    {
        return $this->belongsTo(SqaGroup::class, 'treasury_group_id')->withTrashed();
    }

    public function tutorsGroup()
    {
        return $this->belongsTo(SqaGroup::class, 'tutors_group_id')->withTrashed();
    }
}
