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

    public function test_active_raffle_allows_prize_details_but_not_inventory_changes(): void
    {
        $active = new CommunityRaffle(['status' => 'active']);
        $prize = new CommunityRafflePrize(['name' => 'Premio publicado']);
        $prize->setRelation('raffle', $active);

        $this->assertTrue(CommunityRaffleForm::canEditPrizeDetails($active));
        $this->assertTrue(CommunityRaffleForm::canEditPrizeDetails($prize));
        $this->assertFalse(CommunityRaffleForm::canEditPrizes($active));
        $this->assertFalse(CommunityRaffleForm::canEditPrizes($prize));
    }

    public function test_closed_raffle_keeps_both_prize_details_and_inventory_locked(): void
    {
        $closed = new CommunityRaffle(['status' => 'closed']);
        $prize = new CommunityRafflePrize(['name' => 'Premio cerrado']);
        $prize->setRelation('raffle', $closed);

        $this->assertFalse(CommunityRaffleForm::canEditPrizeDetails($closed));
        $this->assertFalse(CommunityRaffleForm::canEditPrizeDetails($prize));
        $this->assertFalse(CommunityRaffleForm::canEditPrizes($closed));
        $this->assertFalse(CommunityRaffleForm::canEditPrizes($prize));
    }

    public function test_draft_and_new_prizes_allow_editing_all_details(): void
    {
        $draft = new CommunityRaffle(['status' => 'draft']);
        $prize = new CommunityRafflePrize(['name' => 'Premio nuevo']);
        $prize->setRelation('raffle', $draft);

        $this->assertTrue(CommunityRaffleForm::canEditPrizeDetails(null));
        $this->assertTrue(CommunityRaffleForm::canEditPrizeDetails($draft));
        $this->assertTrue(CommunityRaffleForm::canEditPrizeDetails($prize));
    }

    public function test_orphan_prizes_and_unexpected_models_are_not_editable(): void
    {
        $prize = new CommunityRafflePrize(['name' => 'Sin sorteo']);

        $this->assertFalse(CommunityRaffleForm::canEditPrizes($prize));
        $this->assertFalse(CommunityRaffleForm::canEditPrizeDetails($prize));
        $this->assertFalse(CommunityRaffleForm::canEditPrizes(new class extends Model {}));
        $this->assertFalse(CommunityRaffleForm::canEditPrizeDetails(new class extends Model {}));
    }

    public function test_all_eight_filament_repeater_callbacks_accept_the_nested_model(): void
    {
        // Prevent regressions where a closure is typed CommunityRaffle but
        // Filament passes a CommunityRafflePrize when rendering the edit form.
        $source = file_get_contents(
            app_path('Filament/Resources/CommunityRaffles/Schemas/CommunityRaffleForm.php')
        );

        $this->assertIsString($source);
        $this->assertSame(
            8,
            substr_count($source, 'fn (?Model $record): bool => '),
            'All prize field/action editability callbacks must accept Model.',
        );
        $this->assertSame(3,
            substr_count($source, '->disabled(fn (?Model $record): bool => ! self::canEditPrizeDetails($record))'),
            'Name, description and photo must be editable after opening the raffle.',
        );
        $this->assertSame(2,
            substr_count($source, '->disabled(fn (?Model $record): bool => ! self::canEditPrizes($record))'),
            'Quantity and order must remain protected after opening the raffle.',
        );
        $this->assertStringNotContainsString('fn (?CommunityRaffle $record)', $source);
    }
}
