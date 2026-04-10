<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class UserProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_update_user_profile_name()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'phone' => '11999999999'
        ]);

        $this->actingAs($user);

        $payload = [
            'name' => 'Updated Name'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('message', 'Perfil atualizado com sucesso');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com'
        ]);
    }

    public function test_it_can_update_user_profile_email()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com'
        ]);

        $this->actingAs($user);

        $payload = [
            'email' => 'new@example.com'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com'
        ]);
    }

    public function test_it_can_update_user_profile_phone()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create([
            'phone' => '11999999999'
        ]);

        $this->actingAs($user);

        $payload = [
            'phone' => '21988888888'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.phone', '21988888888');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '21988888888'
        ]);
    }

    public function test_it_can_update_user_pix_key()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'pix_key' => 'user@example.com'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.pix_key', 'user@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pix_key' => 'user@example.com'
        ]);
    }

    public function test_it_can_update_user_pix_key_with_camelcase()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'pixKey' => 'user.pix@example.com'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.pix_key', 'user.pix@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pix_key' => 'user.pix@example.com'
        ]);
    }

    public function test_it_can_update_multiple_profile_fields()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create([
            'name' => 'Original',
            'email' => 'old@example.com',
            'phone' => '11999999999'
        ]);

        $this->actingAs($user);

        $payload = [
            'name' => 'Updated Name',
            'email' => 'new@example.com',
            'phone' => '21988888888',
            'pix_key' => '123456789'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.email', 'new@example.com');
        $response->assertJsonPath('data.phone', '21988888888');
        $response->assertJsonPath('data.pix_key', '123456789');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'new@example.com',
            'phone' => '21988888888',
            'pix_key' => '123456789'
        ]);
    }

    public function test_it_fails_if_email_already_exists()
    {
        // Arrange
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $this->actingAs($user2);

        $payload = [
            'email' => 'user1@example.com'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_it_fails_if_email_is_invalid()
    {
        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'email' => 'invalid-email'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_it_fails_if_name_is_too_short()
    {
        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'name' => 'ab'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_it_fails_if_name_is_too_long()
    {
        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'name' => str_repeat('a', 256)
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_it_fails_if_phone_is_too_short()
    {
        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'phone' => '123456789' // 9 digits
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_it_fails_if_phone_is_too_long()
    {
        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'phone' => str_repeat('1', 21)
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_it_fails_if_pix_key_is_too_long()
    {
        // Arrange
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = [
            'pix_key' => str_repeat('a', 256)
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('pix_key');
    }

    public function test_it_allows_null_pix_key()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create([
            'pix_key' => 'old@example.com'
        ]);

        $this->actingAs($user);

        $payload = [
            'pix_key' => null
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.pix_key', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pix_key' => null
        ]);
    }

    public function test_it_requires_authentication()
    {
        // Act
        $response = $this->putJson('/api/v1/update-me', [
            'name' => 'Updated Name'
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_it_does_not_update_password()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($user);

        $payload = [
            'name' => 'Updated Name',
            'password' => 'newpassword123'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);

        // Verify password wasn't changed
        $user->refresh();
        $this->assertEquals($originalPassword, $user->password);
    }

    public function test_it_does_not_update_fcm_token()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create(['fcm_token' => 'original-token']);
        $this->actingAs($user);

        $payload = [
            'name' => 'Updated Name',
            'fcm_token' => 'new-token'
        ];

        // Act
        $response = $this->putJson('/api/v1/update-me', $payload);

        // Assert
        $response->assertStatus(200);

        // Verify fcm_token wasn't changed
        $user->refresh();
        $this->assertEquals('original-token', $user->fcm_token);
    }
}
