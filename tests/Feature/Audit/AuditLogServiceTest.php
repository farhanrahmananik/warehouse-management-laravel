<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_record_a_basic_audit_log_with_event_module_and_description(): void
    {
        $auditLog = app(AuditLogService::class)->record(
            event: 'created',
            module: 'products',
            description: 'Product was created.',
        );

        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'event' => 'created',
            'module' => 'products',
            'description' => 'Product was created.',
            'user_id' => null,
            'auditable_type' => null,
            'auditable_id' => null,
        ]);
    }

    public function test_it_records_authenticated_user_when_user_is_not_explicitly_provided(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $auditLog = app(AuditLogService::class)->record(
            event: 'updated',
            module: 'users',
            description: 'User profile was updated.',
        );

        $this->assertSame($user->id, $auditLog->user_id);
    }

    public function test_it_records_auditable_model_type_and_id(): void
    {
        $auditable = User::factory()->create();

        $auditLog = app(AuditLogService::class)->record(
            event: 'viewed',
            module: 'users',
            auditable: $auditable,
        );

        $this->assertSame($auditable->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($auditable->id, $auditLog->auditable_id);
    }

    public function test_it_sanitizes_sensitive_values_from_old_values_new_values_and_metadata(): void
    {
        $auditLog = app(AuditLogService::class)->record(
            event: 'updated',
            module: 'auth',
            oldValues: [
                'name' => 'Original Name',
                'password' => 'secret',
                'profile' => [
                    'api_token' => 'hidden',
                    'safe' => 'kept',
                    'nested' => [
                        'Token' => 'hidden',
                        'visible' => 'kept',
                    ],
                ],
            ],
            newValues: [
                'name' => 'Updated Name',
                'Password_Confirmation' => 'secret',
                'settings' => [
                    'current_password' => 'hidden',
                    'timezone' => 'UTC',
                ],
            ],
            metadata: [
                '_token' => 'hidden',
                'csrf_token' => 'hidden',
                'safe' => [
                    'key' => 'hidden',
                    'value' => 'kept',
                ],
            ],
        );

        $this->assertSame([
            'name' => 'Original Name',
            'profile' => [
                'safe' => 'kept',
                'nested' => [
                    'visible' => 'kept',
                ],
            ],
        ], $auditLog->old_values);

        $this->assertSame([
            'name' => 'Updated Name',
            'settings' => [
                'timezone' => 'UTC',
            ],
        ], $auditLog->new_values);

        $this->assertSame([
            'safe' => [
                'value' => 'kept',
            ],
        ], $auditLog->metadata);
    }

    public function test_it_records_request_context_when_available(): void
    {
        Route::get('/audit-log-service-context-test', function (AuditLogService $auditLogService) {
            $auditLog = $auditLogService->record(
                event: 'viewed',
                module: 'audit',
                description: 'Request context test.',
            );

            return response()->json([
                'id' => $auditLog->id,
            ]);
        });

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_USER_AGENT' => 'AuditLogServiceTest/1.0',
            ])
            ->get('/audit-log-service-context-test?source=feature');

        $response->assertOk();

        $auditLog = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertSame('GET', $auditLog->method);
        $this->assertSame('AuditLogServiceTest/1.0', $auditLog->user_agent);
        $this->assertStringContainsString('/audit-log-service-context-test?source=feature', (string) $auditLog->url);
        $this->assertNotNull($auditLog->ip_address);
    }
}
