<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecapVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_accepts_an_mp4_and_stores_it_under_recaps(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.videos.store'), [
                'video' => UploadedFile::fake()->create('recap.mp4', 2048, 'video/mp4'),
            ]);

        $response->assertOk()->assertJsonStructure(['path', 'url']);

        $path = $response->json('path');
        $this->assertStringStartsWith('recaps/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_rejects_a_non_video_file(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.videos.store'), [
                'video' => UploadedFile::fake()->image('not-a-video.png'),
            ])
            ->assertSessionHasErrors('video');

        $this->assertEmpty(Storage::disk('public')->allFiles('recaps'));
    }

    public function test_upload_requires_authentication(): void
    {
        $this->post(route('admin.videos.store'), [
            'video' => UploadedFile::fake()->create('recap.mp4', 512, 'video/mp4'),
        ])->assertRedirect(route('login'));
    }

    public function test_post_form_persists_recap_path_and_duration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.posts.store'), [
            'title' => 'Recap Admin Drop',
            'slug' => 'recap-admin-drop',
            'body' => 'Body text.',
            'status' => 'draft',
            'recap_video_path' => 'recaps/some-render.mp4',
            'recap_video_duration' => 31,
        ])->assertRedirect(route('admin.posts.index'));

        $this->assertDatabaseHas('posts', [
            'title' => 'Recap Admin Drop',
            'recap_video_path' => 'recaps/some-render.mp4',
            'recap_video_duration' => 31,
        ]);
    }
}
