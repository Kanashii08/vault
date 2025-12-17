<?php

/**
 * CubicleController
 * 
 * Handles CRUD operations for cubicles.
 */

class CubicleController
{
    /**
     * List all cubicles
     */
    public function index()
    {
        $stmt = db()->query('SELECT * FROM cubicles ORDER BY name');
        $cubicles = $stmt->fetchAll();

        // Cast numeric fields
        foreach ($cubicles as &$c) {
            $c['id'] = (int) $c['id'];
            $c['hourly_rate'] = (float) $c['hourly_rate'];
            $c['has_beer'] = (bool) $c['has_beer'];
        }

        response(['cubicles' => $cubicles]);
    }

    /**
     * Create a new cubicle (admin only)
     */
    public function store()
    {
        $data = validate([
            'name'        => 'required',
            'hourly_rate' => 'required',
        ]);

        $stmt = db()->prepare('
            INSERT INTO cubicles (name, description, hourly_rate, has_beer, created_at, updated_at)
            VALUES (:name, :description, :hourly_rate, :has_beer, NOW(), NOW())
        ');
        $stmt->execute([
            'name'        => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'hourly_rate' => (float) $data['hourly_rate'],
            'has_beer'    => !empty($data['has_beer']) ? 1 : 0,
        ]);

        $id = db()->lastInsertId();

        response(['success' => true, 'id' => (int) $id], 201);
    }

    /**
     * Show a single cubicle
     */
    public function show($id)
    {
        $stmt = db()->prepare('SELECT * FROM cubicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $cubicle = $stmt->fetch();

        if (!$cubicle) {
            response(['error' => true, 'message' => 'Cubicle not found'], 404);
        }

        $cubicle['id'] = (int) $cubicle['id'];
        $cubicle['hourly_rate'] = (float) $cubicle['hourly_rate'];
        $cubicle['has_beer'] = (bool) $cubicle['has_beer'];

        response(['cubicle' => $cubicle]);
    }

    /**
     * Update a cubicle (admin only)
     */
    public function update($id)
    {
        $data = request();

        $stmt = db()->prepare('SELECT * FROM cubicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $cubicle = $stmt->fetch();

        if (!$cubicle) {
            response(['error' => true, 'message' => 'Cubicle not found'], 404);
        }

        $stmt = db()->prepare('
            UPDATE cubicles 
            SET name = :name, description = :description, hourly_rate = :hourly_rate, has_beer = :has_beer, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id'          => $id,
            'name'        => trim($data['name'] ?? $cubicle['name']),
            'description' => trim($data['description'] ?? $cubicle['description']),
            'hourly_rate' => (float) ($data['hourly_rate'] ?? $cubicle['hourly_rate']),
            'has_beer'    => isset($data['has_beer']) ? ($data['has_beer'] ? 1 : 0) : $cubicle['has_beer'],
        ]);

        response(['success' => true]);
    }

    /**
     * Delete a cubicle (admin only)
     */
    public function destroy($id)
    {
        $stmt = db()->prepare('DELETE FROM cubicles WHERE id = :id');
        $stmt->execute(['id' => $id]);

        response(['success' => true]);
    }
}
