<?php

namespace Tests\Unit;

use App\Filament\Resources\CommunityRaffles\Schemas\CommunityRaffleForm;
use App\Models\CommunityRaffle;
use App\Models\CommunityRafflePrize;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class CommunityRaffleFormTest extends TestCase
{
    public function test_new_raffle_and_existing_draft_prize_are_editable(): void
    {
        $draft = new CommunityRaffle(['status' => 'draft']);
        $prize = new CommunityRafflePrize(['name' => 'Premio de prueba']);
        $prize->setRelation('raffle', $draft);

        $this->assertTrue(CommunityRaffleForm::canEditPrizes(null));
        $this->assertTrue(CommunityRaffleForm::canEditPrizes($draft));
        $this->assertTrue(CommunityRaffleForm::canEditPrizes($prize));
    }

    public function test_active_and_closed_raffle_prizes_cannot_be_edited(): void
    {
        foreach (['active', 'closed'] as $status) {
            $raffle = new CommunityRaffle(['status' => $status]);
            $prize = new CommunityRafflePrize(['name' => 'Premio existente']);
            $prize->setRelation('raffle', $raffle);

            $this->assertFalse(CommunityRaffleForm::canEditPrizes($raffle));
            $this->assertFalse(CommunityRaffleForm::canEditPrizes($prize));
        }
    }

    public function test_orphan_prizes_and_unexpected_models_are_not_editable(): void
    {
        $prize = new CommunityRafflePrize(['name' => 'Sin sorteo']);

        $this->assertFalse(CommunityRaffleForm::canEditPrizes($prize));
        $this->assertFalse(CommunityRaffleForm::canEditPrizes(new class extends Model {}));
    }

    public function test_all_seven_filament_repeater_callbacks_accept_the_nested_model(): void
    {
        // Prevent regressions where a closure is typed CommunityRaffle but
        // Filament passes a CommunityRafflePrize when rendering the edit form.
        $source = file_get_contents(
            app_path('Filament/Resources/CommunityRaffles/Schemas/CommunityRaffleForm.php')
        );

        $this->assertIsString($source);
        $this->assertSame(
            7,
            substr_count($source, 'fn (?Model $record): bool => '),
            'All prize field/action editability callbacks must accept Model.',
        );
        $this->assertStringNotContainsString('fn (?CommunityRaffle $record)', $source);
    }
}
