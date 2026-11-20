<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\BookTag;
use App\Models\Department;
use App\Models\PatronVerificationCapture;
use App\Models\Permission;
use App\Models\RfidCard;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relationships_and_roles_and_permissions(): void
    {
        $dept = Department::create(['name' => 'Computer Science', 'code' => 'CSE']);
        $perm = Permission::create(['name' => 'reports.view']);
        $role = Role::create(['name' => 'Librarian']);
        $role->permissions()->attach($perm->id);

        $supervisor = User::factory()->create(['name' => 'Supervisor']);
        $user = User::factory()->create([
            'name' => 'Staff Member',
            'department_id' => $dept->id,
        ]);
        $user->roles()->attach($role->id);

        $task = TaskDelegation::create([
            'task' => 'Inventory check',
            'assigned_by' => $supervisor->id,
            'assigned_to' => $user->id,
            'status' => 'pending',
        ]);

        // Relationships
        $this->assertEquals($dept->id, $user->department->id);
        $this->assertTrue($user->roles->contains($role));
        $this->assertTrue($role->permissions->contains($perm));
        $this->assertTrue($user->assignedTasks->contains($task));
        $this->assertTrue($supervisor->delegatedTasks->contains($task));
        $this->assertEquals($user->id, $task->assignedTo->id);
        $this->assertEquals($supervisor->id, $task->assignedBy->id);

        // hasRole and hasPermission (case-insensitive)
        $this->assertTrue($user->hasRole('Librarian'));
        $this->assertTrue($user->hasRole('librarian'));
        $this->assertFalse($user->hasRole('Admin'));

        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertTrue($user->hasPermission('REPORTS.VIEW'));
        $this->assertFalse($user->hasPermission('system.manage'));
    }

    public function test_rfid_card_and_book_tag_attributes(): void
    {
        $card = RfidCard::create([
            'uid' => '04AABBCC',
            'cardnumber' => 'STU999',
            'active' => 1,
        ]);

        $this->assertTrue($card->active);

        $tag = BookTag::create([
            'uid' => '04112233',
            'barcode' => 'BOOK777',
            'active' => 0,
        ]);

        $this->assertFalse($tag->active);
    }

    public function test_app_setting_read_and_write(): void
    {
        $this->assertNull(AppSetting::read('nonexistent'));

        AppSetting::write('test_key', 'test_value');
        $this->assertEquals('test_value', AppSetting::read('test_key'));

        AppSetting::write('test_key', 'updated_value');
        $this->assertEquals('updated_value', AppSetting::read('test_key'));
    }

    public function test_patron_verification_capture_attributes(): void
    {
        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'barcode' => 'BOOK001',
            'photo_path' => 'captures/1.jpg',
            'face_detected' => 1,
        ]);

        $this->assertTrue($capture->face_detected);
        $this->assertEquals('borrow', $capture->action);
    }

    public function test_activity_log_attributes(): void
    {
        $log = ActivityLog::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'status' => 'success',
            'message' => 'Book checked out',
            'barcode' => 'BOOK001',
            'item_id' => 101,
            'kiosk_id' => 'kiosk-01',
            'metadata' => ['key' => 'value'],
        ]);

        $this->assertEquals('borrow', $log->action);
        $this->assertEquals('success', $log->status);
        $this->assertEquals('kiosk-01', $log->kiosk_id);
        $this->assertEquals(['key' => 'value'], $log->metadata);
    }
}
