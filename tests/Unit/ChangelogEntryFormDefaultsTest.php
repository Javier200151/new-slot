<?php

namespace Tests\Unit;

use App\Filament\Resources\ChangelogEntries\Schemas\ChangelogEntryForm;
use PHPUnit\Framework\TestCase;

class ChangelogEntryFormDefaultsTest extends TestCase
{
    public function test_new_changelog_starts_with_six_changes_in_the_requested_order(): void
    {
        $changes = ChangelogEntryForm::defaultChanges();

        $this->assertCount(6, $changes);
        $this->assertSame([
            ['frontend', 'new'],
            ['frontend', 'improvement'],
            ['frontend', 'fix'],
            ['filament', 'new'],
            ['filament', 'improvement'],
            ['filament', 'fix'],
        ], array_map(
            static fn (array $change): array => [$change['area'], $change['type']],
            $changes,
        ));
    }

    public function test_default_changes_have_empty_editable_content(): void
    {
        foreach (ChangelogEntryForm::defaultChanges() as $change) {
            $this->assertSame('', $change['description']);
            $this->assertSame('', $change['url']);
            $this->assertSame(['area', 'type', 'description', 'url'], array_keys($change));
        }
    }

    public function test_default_changes_return_independent_values(): void
    {
        $changes = ChangelogEntryForm::defaultChanges();
        $changes[0]['description'] = 'Cambio de prueba';
        unset($changes[1]);

        $freshChanges = ChangelogEntryForm::defaultChanges();

        $this->assertCount(6, $freshChanges);
        $this->assertSame('', $freshChanges[0]['description']);
    }
}
