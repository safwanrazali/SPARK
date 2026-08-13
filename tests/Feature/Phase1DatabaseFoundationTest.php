<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalisDraftHistory;
use App\Models\AnalisisInventori;
use App\Models\ApprovalLog;
use App\Models\EntitasAssignment;
use App\Models\User;
use App\Models\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Entiti Assignment Table and Model
     */
    public function test_entitas_assignment_can_be_created(): void
    {
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);

        $assignment = EntitasAssignment::create([
            'agency_code' => 'A010101',
            'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $analyst->id,
            'assigned_by_user_id' => $coordinator->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => 'A010101',
            'assigned_to_user_id' => $analyst->id,
        ]);

        $this->assertEquals('A010101', $assignment->agency_code);
        $this->assertEquals('active', $assignment->status);
    }

    public function test_entitas_assignment_relationships(): void
    {
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);

        $assignment = EntitasAssignment::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $analyst->id,
            'assigned_by_user_id' => $coordinator->id,
            'status' => 'active',
        ]);

        $this->assertTrue($assignment->assignedTo()->exists());
        $this->assertTrue($assignment->assignedBy()->exists());
        $this->assertEquals($analyst->id, $assignment->assignedTo->id);
        $this->assertEquals($coordinator->id, $assignment->assignedBy->id);
    }

    public function test_analyst_can_retrieve_assigned_entities(): void
    {
        $analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);

        EntitasAssignment::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $analyst->id,
            'assigned_by_user_id' => User::factory()->create(['role' => User::ROLE_COORDINATOR])->id,
            'status' => 'active',
        ]);

        $this->assertCount(1, $analyst->assignedEntities);
    }

    /**
     * Test 2: Workflow Status Table and Model
     */
    public function test_workflow_status_can_be_created(): void
    {
        $workflow = WorkflowStatus::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'current_stage' => 1,
            'stage_name' => 'Penerimaan & Pendaftaran Data',
        ]);

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => 'A010101',
            'current_stage' => 1,
        ]);

        $this->assertEquals('Penerimaan & Pendaftaran Data', $workflow->stage_name);
    }

    public function test_workflow_stage_transitions(): void
    {
        $workflow = WorkflowStatus::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'current_stage' => 1,
            'stage_name' => WorkflowStatus::WORKFLOW_STAGES[1],
        ]);

        $nextStage = $workflow->getNextStage();
        $this->assertEquals(2, $nextStage);

        $workflow->update([
            'current_stage' => 2,
            'stage_name' => WorkflowStatus::WORKFLOW_STAGES[2],
        ]);

        $this->assertEquals(2, $workflow->current_stage);
        $this->assertEquals('Semakan Awal Data', $workflow->stage_name);
    }

    public function test_workflow_completion_check(): void
    {
        $workflow = WorkflowStatus::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'current_stage' => 7,
            'stage_name' => WorkflowStatus::WORKFLOW_STAGES[7],
        ]);

        $this->assertTrue($workflow->isComplete());
    }

    /**
     * Test 3: Activity Log Table and Model
     */
    public function test_activity_log_can_be_created(): void
    {
        $user = User::factory()->create();

        $activity = ActivityLog::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'action' => 'workflow_stage_changed',
            'old_value' => '1',
            'new_value' => '2',
            'changed_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'agency_code' => 'A010101',
            'action' => 'workflow_stage_changed',
        ]);

        $this->assertEquals('Peringkat Workflow Berubah', $activity->getActionLabel());
    }

    public function test_activity_log_with_metadata(): void
    {
        $user = User::factory()->create();

        $activity = ActivityLog::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'action' => 'workflow_stage_changed',
            'old_value' => '1',
            'new_value' => '2',
            'changed_by_user_id' => $user->id,
            'metadata' => [
                'reason' => 'Data validation complete',
                'duration_days' => 5,
            ],
        ]);

        $this->assertArrayHasKey('reason', $activity->metadata);
        $this->assertEquals('Data validation complete', $activity->metadata['reason']);
    }

    /**
     * Test 4: Analisis Draft History Table and Model
     */
    public function test_analisis_draft_history_can_be_created(): void
    {
        $analisis = AnalisisInventori::factory()->create();

        $draft = AnalisDraftHistory::create([
            'analisis_inventori_id' => $analisis->id,
            'version' => 1,
            'section_name' => 'profil',
            'section_data' => ['kategori' => ['Sistem/Aplikasi' => 5]],
            'completed' => false,
            'saved_by_user_id' => $analisis->user_id,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('analisis_draft_history', [
            'analisis_inventori_id' => $analisis->id,
            'version' => 1,
        ]);

        $this->assertTrue($draft->is_current);
    }

    public function test_analisis_draft_history_relationship(): void
    {
        $analisis = AnalisisInventori::factory()->create();
        $user = $analisis->user;

        AnalisDraftHistory::create([
            'analisis_inventori_id' => $analisis->id,
            'version' => 1,
            'section_data' => ['test' => 'data'],
            'saved_by_user_id' => $user->id,
            'is_current' => true,
        ]);

        $this->assertCount(1, $analisis->draftHistories);
        $this->assertTrue($analisis->getCurrentDraft() !== null);
    }

    public function test_draft_version_tracking(): void
    {
        $analisis = AnalisisInventori::factory()->create();
        $user = $analisis->user;

        $draft1 = AnalisDraftHistory::create([
            'analisis_inventori_id' => $analisis->id,
            'version' => 1,
            'section_data' => ['version' => 1],
            'saved_by_user_id' => $user->id,
            'is_current' => true,
        ]);

        $draft2 = AnalisDraftHistory::create([
            'analisis_inventori_id' => $analisis->id,
            'version' => 2,
            'section_data' => ['version' => 2],
            'saved_by_user_id' => $user->id,
            'is_current' => true,
        ]);

        // Mark draft1 as no longer current
        $draft1->update(['is_current' => false]);

        $currentDraft = $analisis->getCurrentDraft();
        $this->assertEquals(2, $currentDraft->version);
    }

    /**
     * Test 5: Approval Log Table and Model
     */
    public function test_approval_log_can_be_created(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $approval = ApprovalLog::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'report_type' => 'inventori',
            'status_before' => 'Dalam Proses',
            'status_after' => 'Siap',
            'approved_by_user_id' => $user->id,
            'comments' => 'Laporan disahkan sempurna',
        ]);

        $this->assertDatabaseHas('approval_logs', [
            'agency_code' => 'A010101',
            'report_type' => 'inventori',
        ]);

        $this->assertEquals('Laporan Inventori Kriptografi', $approval->getReportTypeLabel());
    }

    /**
     * Test 6: User Model Relationships
     */
    public function test_user_has_many_assigned_entities(): void
    {
        $analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        EntitasAssignment::factory()->count(3)->create([
            'assigned_to_user_id' => $analyst->id,
            'assigned_by_user_id' => $coordinator->id,
        ]);

        $this->assertCount(3, $analyst->assignedEntities);
    }

    public function test_user_can_view_accessible_entities(): void
    {
        $analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        // Analyst should have accessible entities list
        EntitasAssignment::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $analyst->id,
            'assigned_by_user_id' => $coordinator->id,
            'status' => 'active',
        ]);

        $accessible = $analyst->getAccessibleEntities();
        $this->assertIsArray($accessible);
        $this->assertContains('A010101', $accessible);

        // Coordinator should have no restrictions
        $coordinatorAccessible = $coordinator->getAccessibleEntities();
        $this->assertNull($coordinatorAccessible); // Null means no filtering needed
    }

    /**
     * Test 7: Cross-Model Relationships
     */
    public function test_complete_workflow_scenario(): void
    {
        // Setup
        $analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        // 1. Coordinator assigns entity to analyst
        $assignment = EntitasAssignment::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $analyst->id,
            'assigned_by_user_id' => $coordinator->id,
            'status' => 'active',
        ]);

        // Log the assignment
        ActivityLog::create([
            'agency_code' => 'A010101',
            'action' => 'assignment_created',
            'old_value' => null,
            'new_value' => $analyst->name,
            'changed_by_user_id' => $coordinator->id,
        ]);

        // 2. Create workflow status
        $workflow = WorkflowStatus::create([
            'agency_code' => 'A010101',
            'agency_name' => 'SPR',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'current_stage' => 1,
            'stage_name' => WorkflowStatus::WORKFLOW_STAGES[1],
            'updated_by_user_id' => $coordinator->id,
        ]);

        // 3. Analyst creates analysis and saves draft
        $analisis = AnalisisInventori::factory()->create([
            'agency_code' => 'A010101',
            'user_id' => $analyst->id,
        ]);

        $draft = AnalisDraftHistory::create([
            'analisis_inventori_id' => $analisis->id,
            'version' => 1,
            'section_data' => ['profil' => []],
            'saved_by_user_id' => $analyst->id,
            'is_current' => true,
        ]);

        // Verify all relationships
        $this->assertDatabaseHas('entiti_assignment', ['agency_code' => 'A010101']);
        $this->assertDatabaseHas('workflow_status', ['agency_code' => 'A010101']);
        $this->assertDatabaseHas('activity_log', ['agency_code' => 'A010101']);
        $this->assertDatabaseHas('analisis_draft_history', ['analisis_inventori_id' => $analisis->id]);

        // Verify relationships work
        $this->assertTrue($analyst->assignedEntities()->exists());
        $this->assertEquals('A010101', $analyst->assignedEntities->first()->agency_code);
    }
}
