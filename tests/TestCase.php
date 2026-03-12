<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\User;

abstract class TestCase extends BaseTestCase {
    /**
     * Act as a user with JWT authentication.
     * Overrides the default actingAs to work with JWT guard.
     */
    public function actingAs($user, $guard = 'api') {
        if ($guard === 'api') {
            $token = auth('api')->login($user);
            return $this->withHeader('Authorization', "Bearer {$token}");
        }

        return parent::actingAs($user, $guard);
    }
}
