<?php

/**
 * Lista de requerimientos
 * // Login
 * - Al hacer login con credenciales válidas se devuelve access_token
 * - Al hacer login con credenciales inválidas se retorna error 401
 * - Login requiere email válido (validación)
 * - Login requiere password (validación)
 *
 * // Rol del Usuario
 * - El rol del usuario (customer) está presente en /api/auth/user
 * - El rol del usuario (admin) está presente en /api/auth/user
 *
 * // Usuario Autenticado
 * - Con access_token válido, /api/auth/user devuelve datos del usuario incluyendo el rol
 * - Sin access_token, /api/auth/user retorna error 401
 * - Con token expirado, /api/auth/user retorna error 401
 *
 * // Refresh Token
 * - Con token válido, /api/auth/refresh devuelve nuevo access_token
 * - Con token inválido, /api/auth/refresh falla
 * - Con token expirado, /api/auth/refresh falla
 *
 * // Logout
 * - Al hacer logout se invalida la sesión y se devuelve mensaje
 * - Después de logout, /api/auth/refresh falla
 * - Después de logout, /api/auth/user falla
 */

use App\Models\User;

describe('JWT Authentication', function () {
    describe('User Role', function () {
        it('customer user data includes customer role', function () {
            // Arrange
            $user = User::factory()->customer()->create();
            $token = generateJWTToken($user);

            // Act
            $response = $this->getJson('/api/auth/user', [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertSuccessful();
            expect($response->json('data.role'))->toBe('customer');
        });

        it('admin user data includes admin role', function () {
            // Arrange
            $user = User::factory()->admin()->create();
            $token = generateJWTToken($user);

            // Act
            $response = $this->getJson('/api/auth/user', [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertSuccessful();
            expect($response->json('data.role'))->toBe('admin');
        });
    });

    describe('Login', function () {
        it('can login with valid credentials and receive access_token', function () {
            // Arrange
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            // Act
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

            // Assert
            $response->assertSuccessful()
                ->assertJsonStructure([
                    'message',
                    'data' => ['access_token', 'user'],
                ]);

            expect($response->json('data.access_token'))->toBeTruthy();
        });

        it('cannot login with invalid credentials', function () {
            // Arrange
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            // Act
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });

        it('requires valid email', function () {
            // Arrange
            // No hay usuario que crear

            // Act
            $response = $this->postJson('/api/auth/login', [
                'email' => '',
                'password' => 'password123',
            ]);

            // Assert
            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        });

        it('requires password', function () {
            // Arrange
            // No hay usuario que crear

            // Act
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => '',
            ]);

            // Assert
            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['password']);
        });
    });

    describe('Authenticated User', function () {
        it('can retrieve user data with valid token', function () {
            // Arrange
            $user = User::factory()->customer()->create();
            $token = generateJWTToken($user);

            // Act
            $response = $this->getJson('/api/auth/user', [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertSuccessful()
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id',
                        'email',
                        'name',
                        'role',
                    ],
                ]);

            expect($response->json('data.id'))->toBe($user->id);
            expect($response->json('data.email'))->toBe($user->email);
            expect($response->json('data.role'))->toBe('customer');
        });

        it('cannot access user endpoint without token', function () {
            // Arrange
            // No hay usuario que crear

            // Act
            $response = $this->getJson('/api/auth/user');

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });

        it('cannot access user endpoint with expired token', function () {
            // Arrange
            $user = User::factory()->create();
            $expiredToken = generateExpiredJWTToken($user);

            // Act
            $response = $this->getJson('/api/auth/user', [
                'Authorization' => "Bearer {$expiredToken}",
            ]);

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });
    });

    describe('Refresh Token', function () {
        it('can refresh token with valid token', function () {
            // Arrange
            $user = User::factory()->create();
            $token = generateJWTToken($user);

            // Act
            $response = $this->postJson('/api/auth/refresh', [], [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertSuccessful()
                ->assertJsonStructure([
                    'message',
                    'data' => ['access_token'],
                ]);

            expect($response->json('data.access_token'))->toBeTruthy();
            expect($response->json('data.access_token'))->not->toBe($token);
        });

        it('cannot refresh with invalid token', function () {
            // Arrange
            $invalidToken = 'invalid.jwt.token';

            // Act
            $response = $this->postJson('/api/auth/refresh', [], [
                'Authorization' => "Bearer {$invalidToken}",
            ]);

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });

        it('cannot refresh with expired token', function () {
            // Arrange
            $user = User::factory()->create();
            $expiredToken = generateExpiredJWTToken($user);

            // Act
            $response = $this->postJson('/api/auth/refresh', [], [
                'Authorization' => "Bearer {$expiredToken}",
            ]);

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });
    });

    describe('Logout', function () {
        it('can logout successfully and invalidate session', function () {
            // Arrange
            $user = User::factory()->create();
            $token = generateJWTToken($user);

            // Act
            $response = $this->postJson('/api/auth/logout', [], [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertSuccessful()
                ->assertJsonStructure(['message']);

            expect($response->json('message'))->toBeTruthy();
        });

        it('cannot refresh token after logout', function () {
            // Arrange
            $user = User::factory()->create();
            $token = generateJWTToken($user);

            // Logout first
            $this->postJson('/api/auth/logout', [], [
                'Authorization' => "Bearer {$token}",
            ]);

            // Act
            $response = $this->postJson('/api/auth/refresh', [], [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });

        it('cannot access user endpoint after logout', function () {
            // Arrange
            $user = User::factory()->create();
            $token = generateJWTToken($user);

            // Logout first
            $this->postJson('/api/auth/logout', [], [
                'Authorization' => "Bearer {$token}",
            ]);

            // Act
            $response = $this->getJson('/api/auth/user', [
                'Authorization' => "Bearer {$token}",
            ]);

            // Assert
            $response->assertUnauthorized()
                ->assertJsonStructure(['message']);
        });
    });
});
