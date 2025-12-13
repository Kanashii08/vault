<?php

/**
 * UserController
 * 
 * Handles user management by admin:
 * - List all users
 * - View user details
 * - Update user role (admin, staff, user)
 * - Delete users (except the primary admin with id=1)
 */

class UserController
{
    private function isSuperAdmin($auth)
    {
        $email = strtolower(trim($auth['email'] ?? ''));
        return $email === 'admin@bc.com';
    }

    /**
     * List all users (admin only)
     */
    public function index()
    {
        // Do not include the primary admin (id = 1) in the listing
        $stmt = db()->query('
            SELECT id, first_name, last_name, email, role, avatar_url, created_at
            FROM users
            WHERE id != 1
            ORDER BY id ASC
        ');
        $users = $stmt->fetchAll();

        // Format users
        foreach ($users as &$u) {
            $u['id'] = (int) $u['id'];
        }

        response(['users' => $users]);
    }

    /**
     * Create a new user (super admin only)
     */
    public function store()
    {
        $auth = auth();
        if (!$this->isSuperAdmin($auth)) {
            response(['error' => true, 'message' => 'Forbidden'], 403);
        }

        $data = validate([
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required|email',
            'password'   => 'required|min:6',
        ]);

        $email = strtolower(trim($data['email']));

        // Check if email already exists
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            response(['error' => true, 'message' => 'Email already registered'], 400);
        }

        $allowedRoles = ['admin', 'staff', 'user'];
        $role = strtolower(trim($data['role'] ?? 'user'));
        if (!in_array($role, $allowedRoles, true)) {
            response(['error' => true, 'message' => 'Invalid role. Must be: admin, staff, or user'], 400);
        }

        $avatarUrl = $data['avatar_url'] ?? 'https://moodleaands.muccs.site/backend/storage/avatars/default-user.png';

        $stmt = db()->prepare('
            INSERT INTO users (first_name, last_name, email, password, role, avatar_url, created_at, updated_at)
            VALUES (:first_name, :last_name, :email, :password, :role, :avatar_url, NOW(), NOW())
        ');
        $stmt->execute([
            'first_name' => trim($data['first_name']),
            'last_name'  => trim($data['last_name']),
            'email'      => $email,
            // NOTE: storing plain text password for demo/school purposes only
            'password'   => trim($data['password']),
            'role'       => $role,
            'avatar_url' => trim($avatarUrl),
        ]);

        response(['success' => true, 'message' => 'User created successfully', 'id' => (int) db()->lastInsertId()], 201);
    }

    /**
     * Show a single user
     */
    public function show($id)
    {
        $stmt = db()->prepare('
            SELECT id, first_name, last_name, email, role, avatar_url, created_at
            FROM users WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            response(['error' => true, 'message' => 'User not found'], 404);
        }

        $user['id'] = (int) $user['id'];

        response(['user' => $user]);
    }

    /**
     * Update a user (admin can change role, name, etc.)
     */
    public function update($id)
    {
        $auth = auth();
        $authUserId = (int) ($auth['user_id'] ?? 0);
        $data = request();

        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            response(['error' => true, 'message' => 'User not found'], 404);
        }

        // Validate role if provided
        $allowedRoles = ['admin', 'staff', 'user'];
        $newRole = $data['role'] ?? $user['role'];
        if (!in_array($newRole, $allowedRoles)) {
            response(['error' => true, 'message' => 'Invalid role. Must be: admin, staff, or user'], 400);
        }

        // Admin role-change restrictions:
        // - Cannot change their own role
        // - Cannot change role of other admins (except super admin)
        if ($newRole !== $user['role']) {
            if ($authUserId === (int) $id) {
                response(['error' => true, 'message' => 'You cannot change your own role'], 403);
            }
            if (($user['role'] ?? '') === 'admin' && !$this->isSuperAdmin($auth)) {
                response(['error' => true, 'message' => 'You cannot change the role of an admin account'], 403);
            }
        }

        // Update user
        $stmt = db()->prepare('
            UPDATE users 
            SET first_name = :first_name, 
                last_name = :last_name, 
                role = :role,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id'         => $id,
            'first_name' => trim($data['first_name'] ?? $user['first_name']),
            'last_name'  => trim($data['last_name'] ?? $user['last_name']),
            'role'       => $newRole,
        ]);

        response(['success' => true, 'message' => 'User updated successfully']);
    }

    /**
     * Delete a user (admin only)
     * Note: The primary admin (id=1) cannot be deleted
     */
    public function destroy($id)
    {
        $id = (int) $id;

        $auth = auth();
        $authUserId = (int) ($auth['user_id'] ?? 0);

        // Admin cannot delete themselves
        if ($authUserId === $id) {
            response(['error' => true, 'message' => 'You cannot delete your own account'], 403);
        }

        // Protect the primary admin (id=1)
        if ($id === 1) {
            response(['error' => true, 'message' => 'The primary admin account cannot be deleted'], 403);
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            response(['error' => true, 'message' => 'User not found'], 404);
        }

        // Delete user's tokens first
        $stmt = db()->prepare('DELETE FROM personal_access_tokens WHERE tokenable_id = :id');
        $stmt->execute(['id' => $id]);

        // Delete user
        $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        response(['success' => true, 'message' => 'User deleted successfully']);
    }

    /**
     * Update currently authenticated user's own profile
     * Allows changing first_name, last_name, email, password, and avatar_url.
     */
    public function updateSelf()
    {
        $auth = auth();
        if (!$auth || !isset($auth['user_id'])) {
            response(['error' => true, 'message' => 'Unauthenticated'], 401);
        }

        $userId = (int) $auth['user_id'];
        $data = request();

        // Load current user
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            response(['error' => true, 'message' => 'User not found'], 404);
        }

        // If email is being changed, ensure it is unique
        $newEmail = isset($data['email']) ? strtolower(trim($data['email'])) : $user['email'];
        if ($newEmail !== $user['email']) {
            $check = db()->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
            $check->execute(['email' => $newEmail, 'id' => $userId]);
            if ($check->fetch()) {
                response(['error' => true, 'message' => 'Email already taken'], 400);
            }
        }

        // Build update fields
        $firstName  = isset($data['first_name']) ? trim($data['first_name']) : $user['first_name'];
        $lastName   = isset($data['last_name'])  ? trim($data['last_name'])  : $user['last_name'];
        $avatarUrl  = isset($data['avatar_url']) ? trim($data['avatar_url']) : $user['avatar_url'];
        $password   = isset($data['password']) && $data['password'] !== ''
            ? trim($data['password'])
            : $user['password'];

        $stmt = db()->prepare('
            UPDATE users
            SET first_name = :first_name,
                last_name  = :last_name,
                email      = :email,
                password   = :password,
                avatar_url = :avatar_url,
                updated_at = NOW()
            WHERE id = :id
        ');

        $stmt->execute([
            'id'         => $userId,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $newEmail,
            'password'   => $password,
            'avatar_url' => $avatarUrl,
        ]);

        response(['success' => true, 'message' => 'Profile updated successfully']);
    }

    /**
     * Upload avatar image for the authenticated user.
     * Expects a multipart/form-data request with field name 'avatar'.
     * Saves file into storage/avatars and updates user's avatar_url.
     */
    public function uploadAvatar()
    {
        $auth = auth();
        if (!$auth || !isset($auth['user_id'])) {
            response(['error' => true, 'message' => 'Unauthenticated'], 401);
        }

        $userId = (int) $auth['user_id'];

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            response(['error' => true, 'message' => 'No avatar file uploaded'], 400);
        }

        $file = $_FILES['avatar'];

        // Basic validation
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            response(['error' => true, 'message' => 'Invalid image type'], 400);
        }

        // Ensure directory exists
        $storageDir = __DIR__ . '/../../../storage/avatars';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $targetPath = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            response(['error' => true, 'message' => 'Failed to save uploaded file'], 500);
        }

        // Build public URL (assumes backend is served from /backend)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = '/backend';
        $avatarUrl = $scheme . '://' . $host . $basePath . '/storage/avatars/' . $filename;

        // Update user record
        $stmt = db()->prepare('UPDATE users SET avatar_url = :avatar_url, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'avatar_url' => $avatarUrl,
            'id' => $userId,
        ]);

        response(['success' => true, 'avatar_url' => $avatarUrl]);
    }
}
