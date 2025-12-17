<?php

/**
 * User Model
 * 
 * Represents a user in the Book Cafe system.
 * Roles: admin, staff, user
 */

class User
{
    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
    ];

    public static function find($id)
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function findByEmail($email)
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);
        return $stmt->fetch();
    }
}
