<?php

namespace Tests\Feature;

use App\Models\AgendaDiary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_soft_delete_and_restore_a_diary_entry(): void
    {
        $user = User::factory()->create();
        $entry = AgendaDiary::create([
            'user_id' => $user->id,
            'date' => Carbon::parse('2026-02-15'),
            'content' => '<p>Primeira anotação</p>',
        ]);

        $this->actingAs($user);

        $this->delete(route('diary.entries.destroy', $entry))
            ->assertRedirect();

        $this->assertSoftDeleted('agenda_diaries', ['id' => $entry->id]);

        $this->post(route('diary.entries.restore', $entry->id))
            ->assertRedirect();

        $this->assertDatabaseHas('agenda_diaries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_cannot_delete_or_restore_entries_from_other_users(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $entry = AgendaDiary::create([
            'user_id' => $owner->id,
            'date' => Carbon::parse('2026-02-16'),
            'content' => '<p>Registro privado</p>',
        ]);

        $this->actingAs($intruder);

        $this->delete(route('diary.entries.destroy', $entry))
            ->assertForbidden();

        $this->post(route('diary.entries.restore', $entry->id))
            ->assertNotFound();

        $this->assertDatabaseHas('agenda_diaries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
    }

    public function test_upsert_with_empty_content_soft_deletes_existing_entry(): void
    {
        $user = User::factory()->create();
        $entry = AgendaDiary::create([
            'user_id' => $user->id,
            'date' => Carbon::parse('2026-02-17'),
            'content' => '<p>Para apagar</p>',
        ]);

        $this->actingAs($user);

        $this->putJson(route('agenda.diary.upsert'), [
            'date' => $entry->date->format('Y-m-d'),
            'content' => '',
        ])->assertOk();

        $this->assertSoftDeleted('agenda_diaries', ['id' => $entry->id]);
    }

    public function test_upsert_restores_soft_deleted_entry_when_new_content_is_sent(): void
    {
        $user = User::factory()->create();
        $entry = AgendaDiary::create([
            'user_id' => $user->id,
            'date' => Carbon::parse('2026-02-18'),
            'content' => '<p>Texto antigo</p>',
        ]);

        $entry->delete();
        $this->assertSoftDeleted('agenda_diaries', ['id' => $entry->id]);

        $this->actingAs($user);

        $newContent = '<p>Texto restaurado</p>';

        $this->putJson(route('agenda.diary.upsert'), [
            'date' => $entry->date->format('Y-m-d'),
            'content' => $newContent,
        ])->assertOk()
            ->assertJsonPath('entry.content', $newContent);

        $this->assertDatabaseHas('agenda_diaries', [
            'id' => $entry->id,
            'deleted_at' => null,
            'content' => $newContent,
        ]);
    }

    public function test_show_endpoint_hides_deleted_content_unless_flag_is_passed(): void
    {
        $user = User::factory()->create();
        $entry = AgendaDiary::create([
            'user_id' => $user->id,
            'date' => Carbon::parse('2026-02-19'),
            'content' => '<p>Conteúdo sigiloso</p>',
        ]);

        $entry->delete();

        $this->actingAs($user);

        $this->getJson(route('agenda.diary.show', [
            'date' => $entry->date->format('Y-m-d'),
        ]))
            ->assertOk()
            ->assertJsonPath('entry.content', '')
            ->assertJsonPath('entry.deleted', true);

        $this->getJson(route('agenda.diary.show', [
            'date' => $entry->date->format('Y-m-d'),
            'include_deleted_content' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('entry.content', $entry->content)
            ->assertJsonPath('entry.deleted', true);
    }
}

