<?php

/**
 * Cubicle Model
 * 
 * Represents a bookable cubicle in the Book Cafe.
 */

class Cubicle
{
    protected $table = 'cubicles';

    protected $fillable = [
        'name',
        'description',
        'hourly_rate',
        'has_beer',
    ];

    public static function all()
    {
        $stmt = db()->query('SELECT * FROM cubicles ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $stmt = db()->prepare('SELECT * FROM cubicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
