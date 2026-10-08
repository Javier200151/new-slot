<?php

namespace Tests\Unit;

use App\Services\Treasury\TreasurySheetParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TreasurySheetParserTest extends TestCase
{
    public function test_it_parses_public_summary_and_only_last_year_expenses(): void
    {
        $parser = new TreasurySheetParser();

        $summary = $parser->summary([
            ['BANCO N26', null, null, 'PAYPAL', null, null, 'TESORERÍA TOTAL'],
            [308.24, null, null, 3428.29, null, null, 3736.53],
        ]);

        $this->assertSame(308.24, $summary['bank']);
        $this->assertSame(3428.29, $summary['paypal']);
        $this->assertSame(3736.53, $summary['total']);

        $movements = [
            ['Fecha', 'Tipo', 'Jugador (ID | Nick)', 'ID (auto)', 'Categoría', 'Cuenta origen', 'Cuenta destino', 'Importe (€)', 'Impacto saldo (auto)', 'Concepto / notas'],
            ['28/09/2026', 'Gasto', null, null, 'Servidor', 'Banco', null, 1792.25, 0, 'Hetzner'],
            ['01/10/2026', 'Pago jugador', '041 | Rylod', 41, 'Cuota', null, 'PayPal', 9, 9, 'Cuota'],
            ['01/01/2024', 'Gasto', null, null, 'Otros', 'Banco', null, 3, 0, 'Antiguo'],
        ];

        $expenses = $parser->expenses($movements, CarbonImmutable::create(2025, 10, 8));

        $this->assertCount(1, $expenses);
        $this->assertSame('Servidor', $expenses[0]['category']);
        $this->assertSame(1792.25, $expenses[0]['amount']);
        $this->assertSame('Hetzner', $expenses[0]['concept']);
    }

    public function test_it_parses_private_member_balance_debt_and_latest_payment(): void
    {
        $parser = new TreasurySheetParser();

        $players = [
            ['ID', 'Nick', 'Estado', 'Fecha alta', 'Saldo arranque 01/01 (€)', 'Remanente actual (€)', 'Notas', 'Selector'],
            [41, 'Rylod', 'Miembro', null, 9, 4, null, '041 | Rylod'],
        ];

        $dues = [
            ['JUGADOR', 'ESTADO', 'REMANENTE', 'PROX. TRIMESTRE', 'ENE', 'FEB'],
            ['Rylod', 'Miembro', 4, 'DEBE 5 €', 'X', 'X'],
        ];

        $movements = [
            ['Fecha', 'Tipo', 'Jugador (ID | Nick)', 'ID (auto)', 'Categoría', 'Cuenta origen', 'Cuenta destino', 'Importe (€)', 'Impacto saldo (auto)', 'Concepto / notas'],
            ['01/07/2026', 'Pago jugador', '041 | Rylod', 41, 'Cuota', null, 'PayPal', 9, 9, 'Tercer trimestre'],
            ['01/10/2026', 'Pago jugador', '041 | Rylod', 41, 'Cuota', null, 'PayPal', 9, 9, 'Cuarto trimestre'],
        ];

        $member = $parser->member($players, $dues, $movements, 'Rylod');

        $this->assertTrue($member['found']);
        $this->assertSame(41, $member['player_id']);
        $this->assertSame(4.0, $member['remanent']);
        $this->assertSame('DEBE 5 €', $member['next_quarter']);
        $this->assertSame('01/10/2026', $member['last_payment']['date']);
        $this->assertSame(9.0, $member['last_payment']['amount']);
    }

    public function test_private_member_is_joined_only_by_nickname(): void
    {
        $parser = new TreasurySheetParser();

        $players = [
            ['ID', 'Nick', 'Estado', 'Remanente actual (€)'],
            [999, 'Rylod', 'Miembro', 12],
        ];
        $dues = [
            ['JUGADOR', 'ESTADO', 'REMANENTE', 'PROX. TRIMESTRE'],
            ['Rylod', 'Miembro', 12, 'PAGADO'],
        ];
        $movements = [
            ['Fecha', 'Tipo', 'Jugador (ID | Nick)', 'ID (auto)', 'Importe (€)', 'Concepto / notas'],
            ['07/10/2026', 'Pago jugador', '123 | Rylod', 123, 9, 'Cuota por nick'],
            ['08/10/2026', 'Pago jugador', '41 | Otro', 41, 20, 'No debe coincidir por ID'],
        ];

        $member = $parser->member($players, $dues, $movements, 'Rylod');

        $this->assertTrue($member['found']);
        $this->assertSame(999, $member['player_id']);
        $this->assertSame('07/10/2026', $member['last_payment']['date']);
        $this->assertSame('Cuota por nick', $member['last_payment']['concept']);
    }

    public function test_monthly_control_fills_only_empty_cells_and_respects_manual_values(): void
    {
        $parser = new TreasurySheetParser();
        $rows = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, '7orres', 'Miembro', 9, 'X', 'X', 'X', 'X', 'X', 'X', 'G', 'G', 'G', '', '', ''],
            [2, 'Drums', 'Reserva', 3, '-', 'X', 'X', 'X', 'R', 'X', 'R', 'R', 'R', '', '', ''],
            [3, 'Antiguo', 'Cesado', 0, '-', '-', '-', '-', '-', '-', '-', '-', '-', '', '', ''],
            [4, 'Manual', 'Miembro', 0, 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'G', '', ''],
            [5, 'Recluta', 'Recluta', 0, '', '', '', '', '', '', '', '', '', '', '', ''],
        ];

        $control = $parser->monthlyControl($rows, CarbonImmutable::create(2026, 10, 15));

        $this->assertTrue($control['valid']);
        $this->assertSame('OCT', $control['month']);
        $this->assertSame('N', $control['month_column']);
        $this->assertSame(4, count($control['updates']));
        $this->assertSame(['X', 'R', '-', 'X'], array_column($control['updates'], 'value'));
        $this->assertSame(['N5:N5', 'N6:N6', 'N7:N7', 'N9:N9'], array_column($control['updates'], 'range'));
        $this->assertSame(1, $control['existing']);
        $this->assertSame(0, $control['ignored']);
    }

    public function test_quarterly_balance_treats_nine_euros_as_paid_before_quarter_starts(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 9, 'X', 'X', 'X', 'X', 'X', 'X', 'G', 'G', 'G', '', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 9.0, CarbonImmutable::create(2026, 10, 8));

        $this->assertSame(4, $quarter['quarter']);
        $this->assertSame(2026, $quarter['quarter_year']);
        $this->assertSame('4.º trimestre 2026', $quarter['quarter_label']);
        $this->assertSame('OCT · NOV · DIC', $quarter['quarter_period']);
        $this->assertTrue($quarter['quarter_paid']);
        $this->assertSame(0.0, $quarter['display_balance']);
        $this->assertSame(0.0, $quarter['quarter_missing']);
    }

    public function test_quarterly_balance_shows_partial_amount_and_missing_money(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 4, 'X', 'X', 'X', 'X', 'X', 'X', 'G', 'G', 'G', '', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 4.0, CarbonImmutable::create(2026, 10, 8));

        $this->assertFalse($quarter['quarter_paid']);
        $this->assertSame(4.0, $quarter['display_balance']);
        $this->assertSame(5.0, $quarter['quarter_missing']);
        $this->assertSame(4.0, $quarter['quarter_available']);
    }

    public function test_quarterly_balance_keeps_quarter_paid_after_monthly_x_is_consumed(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 6, 'X', 'X', 'X', 'X', 'X', 'X', 'G', 'G', 'G', 'X', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 6.0, CarbonImmutable::create(2026, 10, 20));

        $this->assertTrue($quarter['quarter_paid']);
        $this->assertSame(1, $quarter['consumed_months']);
        $this->assertSame(9.0, $quarter['quarter_available']);
        $this->assertSame(0.0, $quarter['display_balance']);
    }

    public function test_december_prepares_first_quarter_of_next_year(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            // DIC aún está vacío: durante diciembre se cobra el T1 del año siguiente,
            // pero primero hay que reservar los 3 € del propio mes de diciembre.
            [1, 'Rylod', 'Miembro', 18, 'X', 'X', 'X', 'X', 'X', 'X', 'G', 'G', 'G', 'X', 'X', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 18.0, CarbonImmutable::create(2026, 12, 5));

        $this->assertSame(1, $quarter['quarter']);
        $this->assertSame(2027, $quarter['quarter_year']);
        $this->assertSame('1.er trimestre 2027', $quarter['quarter_label']);
        $this->assertSame(0, $quarter['consumed_months']);
        $this->assertTrue($quarter['quarter_paid']);
        $this->assertSame(3.0, $quarter['pending_current_month']);
        $this->assertSame(6.0, $quarter['display_balance']);
    }

    public function test_december_does_not_reserve_current_month_twice_when_it_is_already_marked_x(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 18, 'X', 'X', 'X', 'X', 'X', 'X', 'G', 'G', 'G', 'X', 'X', 'X'],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 18.0, CarbonImmutable::create(2026, 12, 20));

        $this->assertSame(1, $quarter['quarter']);
        $this->assertSame(2027, $quarter['quarter_year']);
        $this->assertSame(0.0, $quarter['pending_current_month']);
        $this->assertTrue($quarter['quarter_paid']);
        $this->assertSame(9.0, $quarter['display_balance']);
    }

    public function test_players_table_uses_first_blank_row_after_last_real_nickname(): void
    {
        $parser = new TreasurySheetParser();
        $rows = [
            ['SQUAD ALPHA · JUGADORES'],
            ['Texto'],
            ['ID', 'Nick', 'Estado', 'Fecha alta', 'Saldo arranque 01/01 (€)', 'Remanente actual (€)', 'Notas', 'Selector'],
            [1, 'Alpha', 'Miembro', '01/01/2026', 0, 0, '', '001 | Alpha'],
            [2, '', '', '', 0, '', '', ''],
            [3, 'Bravo', 'Reserva', '02/01/2026', 0, 0, '', '003 | Bravo'],
            [4, '', '', '', 0, '', '', ''],
        ];

        $table = $parser->playersTable($rows);

        $this->assertTrue($table['valid']);
        $this->assertCount(2, $table['entries']);
        $this->assertSame(7, $table['next_row']);
        $this->assertSame('B', $table['nick_column']);
        $this->assertSame('C', $table['state_column']);
        $this->assertSame('D', $table['date_column']);
    }

    public function test_signal_is_only_paid_when_real_signal_movements_reach_six_euros(): void
    {
        $parser = new TreasurySheetParser();
        $rows = [
            ['Fecha', 'Tipo', 'Jugador (ID | Nick)', 'ID (auto)', 'Categoría', 'Cuenta origen', 'Cuenta destino', 'Importe (€)', 'Impacto saldo (auto)', 'Concepto / notas'],
            ['01/05/2026', 'Señal', '220 | Nuevo', 220, 'Señal', null, 'PayPal', 3, 3, 'Primera parte'],
            ['02/05/2026', 'Pago jugador', '220 | Nuevo', 220, 'Cuota', null, 'PayPal', 20, 20, 'No cuenta como señal'],
            ['03/05/2026', 'Señal', '220 | Nuevo', 220, 'Señal', null, 'PayPal', 3, 3, 'Segunda parte'],
        ];

        $signal = $parser->signalPayment($rows, 'Nuevo');

        $this->assertTrue($signal['paid']);
        $this->assertSame(6.0, $signal['total']);
        $this->assertSame(2, $signal['count']);
        $this->assertSame('2026-05-03', $signal['last_date']);
    }

    public function test_reactivation_after_day_fifteen_only_charges_remaining_months_of_current_quarter(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 0, 'X', 'X', 'X', 'X', 'X', 'X', 'R', 'R', '', '', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 0.0, CarbonImmutable::create(2026, 8, 20));

        $this->assertSame(3, $quarter['quarter']);
        $this->assertSame(3.0, $quarter['quarter_price']);
        $this->assertSame(3.0, $quarter['quarter_missing']);
        $this->assertSame(2, $quarter['excluded_months']);
    }

    public function test_billing_month_before_monthly_cutoff_reserves_current_month_before_next_quarter(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 0, 'X', 'X', 'X', 'X', 'X', 'X', 'R', 'R', '', '', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 0.0, CarbonImmutable::create(2026, 9, 10));

        $this->assertSame(4, $quarter['quarter']);
        $this->assertSame(9.0, $quarter['quarter_price']);
        $this->assertSame(3.0, $quarter['pending_current_month']);
        $this->assertSame(3.0, $quarter['arrears_due']);
        $this->assertSame(9.0, $quarter['quarter_due']);
        $this->assertSame(12.0, $quarter['quarter_missing']);
    }

    public function test_recruit_promotion_can_show_recruit_month_debt_plus_next_quarter(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Nuevo', 'Miembro', -3, 'X', 'X', 'X', '', '', '', '', '', '', '', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Nuevo', -3.0, CarbonImmutable::create(2026, 3, 20));

        $this->assertSame(2, $quarter['quarter']);
        $this->assertSame(3.0, $quarter['arrears_due']);
        $this->assertSame(9.0, $quarter['quarter_due']);
        $this->assertSame(12.0, $quarter['quarter_missing']);
    }

    public function test_gifted_month_reduces_quarter_price_and_never_consumes_balance(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 6, 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'G', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 6.0, CarbonImmutable::create(2026, 10, 20));

        $this->assertSame(1, $quarter['gifted_months']);
        $this->assertSame(2, $quarter['billable_months']);
        $this->assertSame(1, $quarter['excluded_months']);
        $this->assertSame(6.0, $quarter['quarter_price']);
        $this->assertSame(0, $quarter['consumed_months']);
        $this->assertTrue($quarter['quarter_paid']);
        $this->assertSame(0.0, $quarter['quarter_missing']);
        $this->assertSame(0.0, $quarter['display_balance']);
    }

    public function test_gifted_billing_month_does_not_create_three_euro_pending_charge(): void
    {
        $parser = new TreasurySheetParser();
        $control = [
            ['SQUAD ALPHA · CONTROL MENSUAL 2026'],
            ['', '', '', '', '2026'],
            ['ID', 'Jugador', 'Estado', 'Remanente (€)', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'],
            [],
            [1, 'Rylod', 'Miembro', 9, 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'X', 'G', '', '', ''],
        ];

        $quarter = $parser->quarterlyBalance($control, 'Rylod', 9.0, CarbonImmutable::create(2026, 9, 10));

        $this->assertSame(4, $quarter['quarter']);
        $this->assertSame(0.0, $quarter['pending_current_month']);
        $this->assertSame(9.0, $quarter['quarter_price']);
        $this->assertTrue($quarter['quarter_paid']);
    }

}
