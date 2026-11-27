<?php

namespace Tests\Unit;

use Tests\TestCase;

class EventOrbatMobileLayoutTest extends TestCase
{
    public function test_mobile_orbat_has_compact_full_width_preview_and_two_column_editor(): void
    {
        $css = file_get_contents(public_path('css/event-orbat-markings.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('@media (max-width:768px)', $css);
        $this->assertStringContainsString('.event-detail__orbat .event-orbat__group', $css);
        $this->assertStringContainsString('grid-template-columns:42px minmax(0,1fr)', $css);
        $this->assertStringContainsString('.is-marking-editing .orbat-marking-row', $css);
        $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr))', $css);
        $this->assertStringContainsString('.orbat-marking-mobile-team{display:none}', $css);
        $this->assertStringContainsString('.orbat-marking-color-column{', $css);
        $this->assertStringContainsString('display:none!important;', $css);
    }

    public function test_mobile_team_headings_are_derived_from_saved_colors_and_titles(): void
    {
        $js = file_get_contents(public_path('js/event-orbat-markings.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString("['red', 'blue', 'yellow', 'green', 'white']", $js);
        $this->assertStringContainsString('function renderMobileTeamHeadings(card)', $js);
        $this->assertStringContainsString('const totals = activeTeams(card, false)', $js);
        $this->assertStringContainsString('const titles = savedTeamTitles(card)', $js);
        $this->assertStringContainsString("container.insertBefore(heading, row)", $js);
        $this->assertStringContainsString('renderMobileTeamHeadings(card);', $js);
    }

    public function test_compact_comment_label_preserves_full_accessible_name(): void
    {
        $view = file_get_contents(resource_path('views/events/show.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('data-marking-comment aria-label="Comentar esta numeración"', $view);
        $this->assertStringContainsString('class="orbat-comment-mobile"', $view);
    }
}
