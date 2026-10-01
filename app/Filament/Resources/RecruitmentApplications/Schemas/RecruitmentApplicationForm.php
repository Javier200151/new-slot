<?php

namespace App\Filament\Resources\RecruitmentApplications\Schemas;

use App\Models\ContactSubmission;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class RecruitmentApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Estado del alistamiento')
                ->schema([
                    Placeholder::make('workflow_status')
                        ->label('Estado')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::statusBadge($record)),
                    Placeholder::make('matched_user')
                        ->label('Usuario coincidente')
                        ->content(fn (?ContactSubmission $record): string => $record?->recruitmentMatchedUser?->nick
                            ? $record->recruitmentMatchedUser->nick . ' · ' . $record->recruitmentMatchedUser->email
                            : 'No encontrado por email'),
                    Placeholder::make('interviewer')
                        ->label('Entrevistador')
                        ->content(fn (?ContactSubmission $record): string => $record?->recruitmentInterviewer?->nick ?? 'Sin asignar'),
                    Placeholder::make('recruited_at_display')
                        ->label('Entrada en reclutamiento')
                        ->content(fn (?ContactSubmission $record): string => $record?->recruited_at?->format('d/m/Y H:i') ?? 'Todavía no ha entrado'),
                    Placeholder::make('tier_ratings')
                        ->label('Valoraciones TIER del equipo')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::tierRatings($record))
                        ->columnSpanFull(),
                ])
                ->columns(4)
                ->columnSpanFull(),

            Section::make('Solicitud')
                ->schema([
                    TextInput::make('nickname')->label('Nick')->disabled(),
                    TextInput::make('email')->label('Email')->disabled(),
                    TextInput::make('full_name')->label('Nombre y apellidos reales')->disabled(),
                    TextInput::make('birth_date_display')
                        ->label('Fecha de nacimiento')
                        ->formatStateUsing(fn (?ContactSubmission $record): string => $record?->birth_date?->format('d/m/Y') ?? 'No indicado')
                        ->disabled(),
                    TextInput::make('residence')->label('Lugar de residencia')->disabled(),
                    TextInput::make('phone_whatsapp')->label('Teléfono / WhatsApp')->disabled(),
                    TextInput::make('discord_profile')->label('Discord')->disabled()->placeholder('No indicado'),
                    TextInput::make('created_at_display')
                        ->label('Recibida')
                        ->formatStateUsing(fn (?ContactSubmission $record): string => $record?->created_at?->format('d/m/Y H:i') ?? '—')
                        ->disabled(),
                    Textarea::make('message')->label('Mensaje')->rows(5)->disabled()->columnSpanFull(),
                    Textarea::make('how_heard_us')->label('Cómo nos conoció')->rows(3)->disabled()->columnSpanFull(),
                    Textarea::make('experience_summary')->label('Experiencia en simulación militar / Arma 3')->rows(4)->disabled()->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Requisitos declarados')
                ->schema([
                    Placeholder::make('accepted_rules_display')
                        ->label('Normativa')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->accepted_rules)),
                    Placeholder::make('is_adult_display')
                        ->label('Mayor de edad')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->is_adult)),
                    Placeholder::make('accepts_contributions_display')
                        ->label('Aportaciones económicas')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->accepts_contributions)),
                    Placeholder::make('has_required_game_content_display')
                        ->label('Contenido de juego requerido')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->has_required_game_content)),
                    Placeholder::make('tuesday_available_display')
                        ->label('Disponible martes')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->tuesday_available)),
                    Placeholder::make('friday_available_display')
                        ->label('Disponible viernes')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->friday_available)),
                    Placeholder::make('has_previous_experience_display')
                        ->label('Experiencia previa')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->has_previous_experience)),
                ])
                ->columns(4)
                ->columnSpanFull(),

            Section::make('Consentimientos')
                ->schema([
                    Placeholder::make('accepted_privacy_display')
                        ->label('Política de privacidad')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->accepted_privacy)),
                    Placeholder::make('accepted_contact_display')
                        ->label('Consentimiento de contacto')
                        ->content(fn (?ContactSubmission $record): HtmlString => self::booleanIndicator((bool) $record?->accepted_contact)),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    private static function booleanIndicator(bool $value): HtmlString
    {
        if ($value) {
            return new HtmlString('<span style="display:inline-flex;align-items:center;gap:.45rem;font-weight:700;color:#4ade80;"><span style="display:inline-flex;align-items:center;justify-content:center;width:1.35rem;height:1.35rem;border-radius:999px;background:rgba(22,163,74,.16);border:1px solid rgba(22,163,74,.38);">✓</span>Sí</span>');
        }

        return new HtmlString('<span style="display:inline-flex;align-items:center;gap:.45rem;font-weight:700;color:#fb7185;"><span style="display:inline-flex;align-items:center;justify-content:center;width:1.35rem;height:1.35rem;border-radius:999px;background:rgba(225,29,72,.14);border:1px solid rgba(225,29,72,.35);">×</span>No</span>');
    }

    private static function statusBadge(?ContactSubmission $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('—');
        }

        [$background, $border, $color] = match ($record->recruitment_review_status) {
            ContactSubmission::REVIEW_APPROVED => ['rgba(22,163,74,.15)', 'rgba(22,163,74,.35)', '#4ade80'],
            ContactSubmission::REVIEW_DISCARDED => ['rgba(225,29,72,.14)', 'rgba(225,29,72,.35)', '#fb7185'],
            default => ['rgba(100,116,139,.16)', 'rgba(148,163,184,.28)', '#cbd5e1'],
        };

        $label = e($record->recruitmentWorkflowLabel());

        return new HtmlString("<span style=\"display:inline-flex;align-items:center;border-radius:999px;padding:.3rem .65rem;font-size:.78rem;font-weight:800;background:{$background};border:1px solid {$border};color:{$color};\">{$label}</span>");
    }

    private static function tierRatings(?ContactSubmission $record): HtmlString
    {
        if (! $record || $record->recruitmentTierRatingsSummary()->isEmpty()) {
            return new HtmlString('<span style="color:#94a3b8;">Todavía no hay valoraciones TIER.</span>');
        }

        $rows = $record->recruitmentTierRatingsSummary()
            ->map(function (array $rating): string {
                $tierNumber = (int) str_replace('TIER ', '', $rating['tier']);
                [$background, $border, $color] = match ($tierNumber) {
                    1 => ['rgba(22,163,74,.15)', 'rgba(22,163,74,.36)', '#4ade80'],
                    2 => ['rgba(234,179,8,.16)', 'rgba(234,179,8,.38)', '#fde047'],
                    3 => ['rgba(249,115,22,.16)', 'rgba(249,115,22,.38)', '#fb923c'],
                    default => ['rgba(100,116,139,.16)', 'rgba(148,163,184,.28)', '#cbd5e1'],
                };

                $nick = e($rating['nick']);
                $tier = e($rating['tier']);
                $reason = filled($rating['reason'])
                    ? e($rating['reason'])
                    : '<span style="color:#94a3b8;">Sin comentario</span>';

                return "<div style=\"display:flex;align-items:flex-start;gap:.7rem;padding:.7rem .8rem;border-radius:.75rem;background:rgba(15,23,42,.36);border:1px solid rgba(148,163,184,.14);\"><span style=\"flex:0 0 auto;display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.72rem;font-weight:800;background:{$background};border:1px solid {$border};color:{$color};\">{$tier}</span><div style=\"min-width:0;\"><div style=\"font-weight:800;color:#e5e7eb;\">{$nick}</div><div style=\"margin-top:.15rem;color:#cbd5e1;line-height:1.45;\">{$reason}</div></div></div>";
            })
            ->implode('');

        return new HtmlString('<div style="display:grid;gap:.55rem;">' . $rows . '</div>');
    }
}
