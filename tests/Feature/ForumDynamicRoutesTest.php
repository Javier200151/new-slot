<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use Tests\TestCase;

class ForumDynamicRoutesTest extends TestCase
{
    public function test_category_and_thread_urls_use_the_category_slug_directly(): void
    {
        $this->assertSame(
            url('/area/foro/general'),
            route('community.forum.category', 'general'),
        );

        $this->assertSame(
            url('/area/foro/general/6'),
            route('community.forum.show', ['general', 6]),
        );
    }

    public function test_old_category_url_is_kept_only_as_a_legacy_redirect_route(): void
    {
        $this->assertSame(
            url('/area/foro/categoria/general'),
            route('community.forum.category.legacy', 'general'),
        );

        $this->assertSame(
            url('/area/foro/categoria/general'),
            route('community.forum.category.store.legacy', 'general'),
        );
    }

    public function test_internal_forum_paths_cannot_be_reused_as_dynamic_category_slugs(): void
    {
        $this->assertContains('diario', ForumCategory::RESERVED_SLUGS);
        $this->assertContains('categoria', ForumCategory::RESERVED_SLUGS);
        $this->assertContains('changelog', ForumCategory::RESERVED_SLUGS);
        $this->assertContains('nuevos-mensajes', ForumCategory::RESERVED_SLUGS);
        $this->assertContains('personal', ForumCategory::RESERVED_SLUGS);
    }
}
