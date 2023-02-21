<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use App\Models\RoleConst;
use App\Models\User;
use Tests\TestCase;
use Exception;

class TaskTest extends TestCase
{
    private $user;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->user = User::where('email', 'user@user.ru')->first();

        if (!$this->user) {
            throw new Exception('Нет пользователей в базе данных');
        }
    }

    public function test_pipelines_index()
    {
        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->getJson('/api/pipelines');

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('pipelines', 'success')
            ->where('success', true)
            ->whereType('pipelines', 'array')
        );
    }

    public function test_pipelines_store()
    {
        $pipeline = Pipeline::factory()->make();
        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->postJson('/api/pipelines', [
                'name' => $pipeline->name,
                'type' => Pipeline::getModelAlias($pipeline->type),
            ]);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('success', 'pipeline')
            ->where('success', true)
            ->whereType('pipeline', 'array')
            ->where('pipeline.name', $pipeline->name)
            ->where('pipeline.type', Pipeline::getModelAlias($pipeline->type))
        );

        $this->assertDatabaseHas('pipelines', [
            'name' => $pipeline->name,
            'type' => $pipeline->type,
        ]);
    }

    public function test_stages_index()
    {
        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->getJson('/api/stages?pipeline_id=1');

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('stages', 'success')
            ->where('success', true)
            ->whereType('stages', 'array')
        );
    }

    public function test_stages_store()
    {
        $stage = Stage::factory()->make();
        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->postJson('/api/stages', [
                'name' => $stage->name,
                'color' => $stage->color,
                'pipeline_id' => $stage->pipeline_id,
            ]);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('success', 'stage')
            ->where('success', true)
            ->whereType('stage', 'array')
            ->where('stage.name', $stage->name)
            ->where('stage.color', $stage->color)
            ->where('stage.pipeline_id', $stage->pipeline_id)
        );

        $this->assertDatabaseHas('stages', [
            'name' => $stage->name,
            'color' => $stage->color,
            'pipeline_id' => $stage->pipeline_id
        ]);
    }

    public function test_tasks_index()
    {
        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->getJson('/api/tasks');
        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('tasks', 'meta', 'links')
            ->whereType('tasks', 'array')
        );
    }

    public function test_tasks_store()
    {
        $task = Task::factory()->make();
        $pipelines = Pipeline::limit(2)->get();
        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->postJson('/api/tasks', [
                'name' => $task->name,
                'user_id' => $task->user_id,
                'position' => $task->position,
                'checkboxes' => ['checkbox1', 'checkbox2'],
                'pipelines' => [
                    [
                        'pipeline_id' => $pipelines[0]->id,
                        'stage_id' => null
                    ],
                    [
                        'pipeline_id' => $pipelines[1]->id,
                        'stage_id' => null
                    ],
                ]
            ]);
$response->dump();
        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('success', 'task')
            ->where('success', true)
            ->whereType('task', 'array')
            ->where('task.name', $task->name)
            ->where('task.user.id', $task->user_id)
            ->where('task.status', Task::STATUS_WAIT)
            ->where('task.position', $task->position)
            ->where('task.checkboxes.0.description', 'checkbox1')
            ->where('task.checkboxes.0.is_checked', false)
            ->where('task.checkboxes.1.description', 'checkbox2')
            ->where('task.checkboxes.1.is_checked', false)
        );

        $this->assertDatabaseHas('tasks', [
            'name' => $task->name,
            'user_id' => $task->user_id,
            'position' => $task->position
        ]);
    }

    private function prepareTaskCheckboxes($checkboxes, $newCheckboxes): array
    {
        $postCheckboxes = [];
        foreach ($checkboxes as $index => $checkbox) {
            if (!$index) {
                continue;
            }
            $postCheckboxes[] = [
                'id' => $checkbox->id,
                'description' => $checkbox->description,
                'is_checked' => $checkbox->is_checked
            ];
        }

        foreach ($newCheckboxes as $checkbox) {
            $postCheckboxes[] = [
                'id' => null,
                'description' => $checkbox['description'],
                'is_checked' => $checkbox['is_checked']
            ];
        }

        return $postCheckboxes;
    }

    public function test_tasks_update()
    {
        $task = Task::whereHas('checkboxes')->first();
        $newTask = Task::factory()->make();
        $newCheckboxes = [
            [
                'description' => 'new checkbox 1',
                'is_checked' => true,
            ],
            [
                'description' => 'new checkbox 2',
                'is_checked' => false,
            ],
        ];

        $postCheckboxes = $this->prepareTaskCheckboxes($task->checkboxes, $newCheckboxes);

        $response = $this->actingAs($this->user, RoleConst::GUARD_NAME)
            ->putJson('/api/tasks/'.$task->id, [
                'name' => $newTask->name,
                'user_id' => $newTask->user_id,
                'status' => $newTask->status,
                'position' => $newTask->position,
                'end_at' => Carbon::parse($newTask->end_at)->toDateString(),
                'start_at' => Carbon::parse($newTask->start_at)->toDateString(),
                'checkboxes' => $postCheckboxes,
            ]);

        $response->assertOk()->assertJson(fn (AssertableJson $json) =>
        $json->hasAll('success', 'task')
            ->where('success', true)
            ->whereType('task', 'array')
            ->where('task.name', $newTask->name)
            ->where('task.position', $newTask->position)
            ->where('task.user.id', $newTask->user_id)
            ->where('task.status', $newTask->status)
        );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => $newTask->name,
            'user_id' => $newTask->user_id,
            'status' => $newTask->status,
            'positino' => $newTask->position,
            'end_at' => Carbon::parse($newTask->end_at)->toDateString(),
            'start_at' => Carbon::parse($newTask->start_at)->toDateString()
        ]);
    }
}