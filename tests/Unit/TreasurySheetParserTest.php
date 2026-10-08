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

}
