<?php

/**
 * Booking Model
 * 
 * Represents a cubicle booking in the Book Cafe.
 */

class Booking
{
    protected $table = 'bookings';

    protected $fillable = [
        'user_id',
        'cubicle_id',
        'start_time',
        'end_time',
        'status',
    ];

    public static function find($id)
    {
        $stmt = db()->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function forUser($userId)
    {
        $stmt = db()->prepare('SELECT * FROM bookings WHERE user_id = :user_id ORDER BY start_time DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
