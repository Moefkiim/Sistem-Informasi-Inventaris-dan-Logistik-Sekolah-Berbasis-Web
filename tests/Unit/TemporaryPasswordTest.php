<?php

namespace Tests\Unit;

use App\Support\TemporaryPassword;
use Tests\TestCase;

class TemporaryPasswordTest extends TestCase
{
    public function test_generates_password_with_minimum_length(): void
    {
        $this->assertGreaterThanOrEqual(8, strlen(TemporaryPassword::generate()));
        $this->assertSame(12, strlen(TemporaryPassword::generate(12)));
    }

    public function test_never_uses_easily_confused_characters(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = TemporaryPassword::generate();
            $this->assertDoesNotMatchRegularExpression('/[0O1lo]/', $password, "Kata sandi '{$password}' memuat karakter yang mudah tertukar.");
        }
    }

    public function test_always_contains_uppercase_lowercase_and_digit(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = TemporaryPassword::generate();
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
    }
}
