<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\TaskDelegation;
use Illuminate\Database\Seeder;

class TaskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tasksByRole = [
            'Admin' => [
                'assigner' => 'Admin',
                'tasks' => [
                    ['Review and approve new staff role assignments', 'pending'],
                    ['Audit which permissions each role currently holds', 'in_progress'],
                    ['Check Koha connection status weekly', 'done'],
                    ['Review failed activity logs for recurring issues', 'pending'],
                ],
            ],
            'Librarian' => [
                'assigner' => 'Admin',
                'tasks' => [
                    ["Approve this week's cataloging backlog for publication", 'pending'],
                    ['Coordinate collection weeding for outdated titles', 'in_progress'],
                    ['Review overdue task delegations and follow up', 'done'],
                    ['Oversee circulation desk coverage schedule', 'pending'],
                ],
            ],
            'Assistant Librarian' => [
                'assigner' => 'Librarian',
                'tasks' => [
                    ['Spot-check shelf accuracy, Section A', 'pending'],
                    ['Assist patrons during peak kiosk hours', 'in_progress'],
                    ['Report any kiosk hardware issues to Admin', 'done'],
                    ['Verify returned books are correctly re-shelved', 'pending'],
                ],
            ],
            'Sub-Controller' => [
                'assigner' => 'Admin',
                'tasks' => [
                    ['Process new student account batch (roll numbers 2026-XXX series)', 'in_progress'],
                    ['Verify payment status for pending registrations', 'pending'],
                    ['Issue replacement ID cards for lost/damaged cards', 'done'],
                    ['Deactivate accounts for graduated students', 'pending'],
                ],
            ],
            'Senior Cataloger' => [
                'assigner' => 'Librarian',
                'tasks' => [
                    ["Assign this week's cataloging backlog to junior catalogers", 'pending'],
                    ['Review flagged catalog entries for metadata errors', 'in_progress'],
                    ['Approve new subject headings before publication', 'done'],
                    ['Audit catalog records added in the last month', 'pending'],
                ],
            ],
            'Cataloger' => [
                'assigner' => 'Senior Cataloger',
                'tasks' => [
                    ['Enter new book records for recent acquisitions', 'in_progress'],
                    ['Update existing entries with corrected author/ISBN data', 'pending'],
                    ['Print and attach spine labels for newly cataloged items', 'done'],
                    ['Flag damaged or duplicate catalog entries to Senior Cataloger', 'pending'],
                ],
            ],
            'Attendant' => [
                'assigner' => 'Assistant Librarian',
                'tasks' => [
                    ['Shelve returned books, Section B', 'pending'],
                    ['Reorganize misplaced items in the reference section', 'in_progress'],
                    ['Conduct weekly shelf inventory count, Section C', 'done'],
                    ['Dust and tidy reading area shelving', 'pending'],
                ],
            ],
            'Circulation Staff' => [
                'assigner' => 'Librarian',
                'tasks' => [
                    ['Process overdue notices for the week', 'pending'],
                    ['Follow up on items reported lost', 'in_progress'],
                    ['Resolve disputed return/borrow at the desk', 'done'],
                    ['Record and reconcile daily fine collections', 'pending'],
                ],
            ],
        ];

        foreach ($tasksByRole as $roleName => $config) {
            $assignee = Role::where('name', $roleName)->first()?->users()->first();
            $assigner = Role::where('name', $config['assigner'])->first()?->users()->first();

            if (! $assignee || ! $assigner) {
                $this->command?->warn("Skipping {$roleName}: no linked user found for this role or its assigner ({$config['assigner']}). Run your Koha staff-linking step first.");
                continue;
            }

            foreach ($config['tasks'] as [$taskText, $status]) {
                TaskDelegation::firstOrCreate(
                    [
                        'task' => $taskText,
                        'assigned_to' => $assignee->id,
                    ],
                    [
                        'assigned_by' => $assigner->id,
                        'status' => $status,
                    ]
                );
            }

            $this->command?->info("Seeded 4 tasks for {$roleName}.");
        }
    }
}