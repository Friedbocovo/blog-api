<?php

namespace Tests\Unit\Models;

use App\Models\AboutPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_about_page_as_table_name(): void
    {
        $about = new AboutPage();
        $this->assertEquals('about_page', $about->getTable());
    }

    /** @test */
    public function it_has_timestamps_disabled(): void
    {
        $about = new AboutPage();
        $this->assertFalse($about->timestamps);
    }

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $about = new AboutPage();

        $this->assertContains('bio', $about->getFillable());
        $this->assertContains('links', $about->getFillable());
        $this->assertContains('extra_sections', $about->getFillable());
        $this->assertContains('profile_photo', $about->getFillable());
    }

    /** @test */
    public function it_casts_links_to_array(): void
    {
        $casts = (new AboutPage())->getCasts();
        $this->assertArrayHasKey('links', $casts);
        $this->assertEquals('array', $casts['links']);
    }

    /** @test */
    public function it_casts_extra_sections_to_array(): void
    {
        $casts = (new AboutPage())->getCasts();
        $this->assertArrayHasKey('extra_sections', $casts);
        $this->assertEquals('array', $casts['extra_sections']);
    }

    /** @test */
    public function singleton_returns_new_instance_when_table_is_empty(): void
    {
        $about = AboutPage::singleton();

        $this->assertInstanceOf(AboutPage::class, $about);
        $this->assertFalse($about->exists);
    }

    /** @test */
    public function singleton_returns_existing_instance_when_record_exists(): void
    {
        $existing = new AboutPage([
            'bio' => 'I am a developer.',
            'updated_at' => now(),
        ]);
        $existing->save();

        $about = AboutPage::singleton();

        $this->assertTrue($about->exists);
        $this->assertEquals('I am a developer.', $about->bio);
    }

    /** @test */
    public function singleton_always_returns_same_single_record(): void
    {
        $existing = new AboutPage(['bio' => 'First bio', 'updated_at' => now()]);
        $existing->save();

        $first = AboutPage::singleton();
        $second = AboutPage::singleton();

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AboutPage::count());
    }
}
