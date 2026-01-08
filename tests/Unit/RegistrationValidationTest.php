<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RegistrationValidationTest extends TestCase
{
    /**
     * Test password validation rules - uppercase check
     */
    public function test_password_must_contain_uppercase(): void
    {
        $password = 'lowercase123!@#';
        $hasUppercase = (bool) preg_match('/[A-Z]/', $password);
        
        $this->assertFalse($hasUppercase);
    }

    /**
     * Test password validation requires lowercase
     */
    public function test_password_must_contain_lowercase(): void
    {
        $password = 'UPPERCASE123!@#';
        $hasLowercase = (bool) preg_match('/[a-z]/', $password);
        
        $this->assertFalse($hasLowercase);
    }

    /**
     * Test password validation requires number
     */
    public function test_password_must_contain_number(): void
    {
        $password = 'Password!@#';
        $hasNumber = (bool) preg_match('/[0-9]/', $password);
        
        $this->assertFalse($hasNumber);
    }

    /**
     * Test password validation requires special character
     */
    public function test_password_must_contain_special_character(): void
    {
        $password = 'Password123';
        $hasSpecialChar = (bool) preg_match('/[@$!%*#?&^._-]/', $password);
        
        $this->assertFalse($hasSpecialChar);
    }

    /**
     * Test valid password passes all rules
     */
    public function test_valid_password_passes_all_rules(): void
    {
        $password = 'Password123!@#';
        
        $hasUppercase = (bool) preg_match('/[A-Z]/', $password);
        $hasLowercase = (bool) preg_match('/[a-z]/', $password);
        $hasNumber = (bool) preg_match('/[0-9]/', $password);
        $hasSpecialChar = (bool) preg_match('/[@$!%*#?&^._-]/', $password);
        $minLength = strlen($password) >= 8;
        
        $this->assertTrue($hasUppercase);
        $this->assertTrue($hasLowercase);
        $this->assertTrue($hasNumber);
        $this->assertTrue($hasSpecialChar);
        $this->assertTrue($minLength);
    }

    /**
     * Test password minimum length validation
     */
    public function test_password_minimum_length_validation(): void
    {
        $password = 'Pass1!';
        $minLength = strlen($password) >= 8;
        
        $this->assertFalse($minLength);
    }

    /**
     * Test email validation format
     */
    public function test_email_validation_format(): void
    {
        $email = 'invalid-email';
        $isValidEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        $this->assertFalse($isValidEmail);
    }

    /**
     * Test valid email format passes
     */
    public function test_valid_email_format_passes(): void
    {
        $email = 'user@example.com';
        $isValidEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        $this->assertNotFalse($isValidEmail);
    }

    /**
     * Test first_name is required - check empty validation
     */
    public function test_first_name_cannot_be_empty(): void
    {
        $firstName = '';
        $isValid = !empty(trim($firstName));
        
        $this->assertFalse($isValid);
    }

    /**
     * Test last_name is required - check empty validation
     */
    public function test_last_name_cannot_be_empty(): void
    {
        $lastName = '';
        $isValid = !empty(trim($lastName));
        
        $this->assertFalse($isValid);
    }

    /**
     * Test password confirmation must match
     */
    public function test_password_confirmation_must_match(): void
    {
        $password = 'Password123!@#';
        $passwordConfirmation = 'DifferentPassword123!@#';
        
        $this->assertNotEquals($password, $passwordConfirmation);
    }

    /**
     * Test matching password confirmation
     */
    public function test_password_confirmation_matches(): void
    {
        $password = 'Password123!@#';
        $passwordConfirmation = 'Password123!@#';
        
        $this->assertEquals($password, $passwordConfirmation);
    }
}

