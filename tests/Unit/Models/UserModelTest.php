<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\User;

/**
 * Unit tests for the User model.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Unit\Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 * @covers     \App\Models\User
 */
class UserModelTest extends TestCase
{
    /** @var User */
    private User $user;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../config/app.php';
        $this->user = new User();
    }

    /**
     * Test that the user model can find a user by email.
     *
     * @return void
     */
    public function testFindByEmail(): void
    {
        $result = $this->user->findByEmail('email4johnson@gmail.com');
        $this->assertNotNull($result, 'Admin user should exist');
        $this->assertEquals('admin', $result['username']);
        $this->assertEquals('email4johnson@gmail.com', $result['email']);
    }

    /**
     * Test that findByEmail returns null for non-existent user.
     *
     * @return void
     */
    public function testFindByEmailReturnsNullForMissing(): void
    {
        $result = $this->user->findByEmail('nonexistent@example.com');
        $this->assertNull($result);
    }

    /**
     * Test authentication with valid credentials.
     *
     * @return void
     */
    public function testAuthenticateWithValidCredentials(): void
    {
        $result = $this->user->authenticate('email4johnson@gmail.com', '24AdaPlace');
        $this->assertNotNull($result, 'Valid credentials should authenticate');
        $this->assertEquals('admin', $result['username']);
    }

    /**
     * Test authentication with invalid password.
     *
     * @return void
     */
    public function testAuthenticateWithInvalidPassword(): void
    {
        $result = $this->user->authenticate('email4johnson@gmail.com', 'wrongpassword');
        $this->assertNull($result);
    }

    /**
     * Test authentication with non-existent email.
     *
     * @return void
     */
    public function testAuthenticateWithNonExistentEmail(): void
    {
        $result = $this->user->authenticate('fake@example.com', '24AdaPlace');
        $this->assertNull($result);
    }

    /**
     * Test that all() returns an array.
     *
     * @return void
     */
    public function testAllReturnsArray(): void
    {
        $result = $this->user->all();
        $this->assertIsArray($result);
        $this->assertNotEmpty($result, 'Should have at least the admin user');
    }

    /**
     * Test that find returns a user by ID.
     *
     * @return void
     */
    public function testFindById(): void
    {
        $all = $this->user->all();
        $id = $all[0]['id'];
        $result = $this->user->find($id);
        $this->assertNotNull($result);
        $this->assertEquals($id, $result['id']);
    }
}
