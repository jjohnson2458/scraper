<?php

namespace App\Models;

use App\Core\BaseModel;

/**
 * User Model
 *
 * Represents an admin user account in the system.
 *
 * @package    ClaudeScraper
 * @subpackage Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class User extends BaseModel
{
    /** @var string The database table */
    protected string $table = 'users';

    /** @var array<string> Mass-assignable columns */
    protected array $fillable = ['username', 'email', 'password', 'role', 'last_login'];

    /**
     * Find a user by email address.
     *
     * @param string $email The email address.
     * @return array|null The user record or null.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Authenticate a user by email and password.
     *
     * @param string $email    The email address.
     * @param string $password The plain text password.
     * @return array|null The user record if authenticated, null otherwise.
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            $this->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
            return $user;
        }
        return null;
    }
}
