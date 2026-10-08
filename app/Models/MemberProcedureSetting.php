<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MemberProcedureSetting extends Model
{
    use Auditable;

    public const DEFAULT_TREASURY_SPREADSHEET_ID = '15YANw9Dz3DpOOiAqcahRqnIQEivuiFV5';

    protected $fillable = [
        'alpha_metopa_id',
        'treasury_group_id',
        'tutors_group_id',
        'google_spreadsheet_id',
        'google_general_sheet_gid',
        'treasury_spreadsheet_id',
        'treasury_private_status_ids',
        'armasquads_squad_id',
        'discord_guild_id',
        'discord_recruit_role_id',
        'discord_alpha_role_id',
        'discord_reserve_role_id',
        'discord_invite_channel_id',
        'discord_bot_nickname',
        'discord_alpha_nickname_prefix',
        'telegram_recruit_chat_id',
        'telegram_tutors_chat_id',
        'telegram_recruit_message',
        'telegram_tutors_message',
        'telegram_network_chat_id',
        'telegram_cantina_invite_url',
        'telegram_official_invite_url',
        'telegram_network_invite_url',
        'member_welcome_email_subject',
        'member_welcome_email_body',
        'reactivation_email_subject',
        'reactivation_email_body',
        'telegram_recruit_update_template',
        'telegram_recruit_entry_endings',
        'telegram_recruit_exit_endings',
        'telegram_veterancy_template',
        'telegram_veterancy_endings',
        'telegram_weekly_template',
        'telegram_weekly_endings',
        'telegram_weekly_required_weekdays',
        'telegram_weekly_required_activity_type_ids',
        'telegram_weekly_active_event_status_id',
    ];

    protected function casts(): array
    {
        return [
            'telegram_recruit_entry_endings' => 'array',
            'telegram_recruit_exit_endings' => 'array',
            'telegram_veterancy_endings' => 'array',
            'telegram_weekly_endings' => 'array',
            'telegram_weekly_required_weekdays' => 'array',
            'telegram_weekly_required_activity_type_ids' => 'array',
            'telegram_weekly_active_event_status_id' => 'integer',
            'treasury_private_status_ids' => 'array',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->firstOrCreate([], [
            'google_spreadsheet_id' => '1hMezm3dfuvuvYSrBECzvHll0vGqXgOIH8_0FzXKnvAk',
            'google_general_sheet_gid' => '1711111556',
            'treasury_spreadsheet_id' => self::DEFAULT_TREASURY_SPREADSHEET_ID,
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

    public function stepDefinitions()
    {
        return $this->hasMany(MemberProcedureStepDefinition::class, 'member_procedure_setting_id')
            ->orderBy('procedure_type')
            ->orderBy('position')
            ->orderBy('id');
    }
}
