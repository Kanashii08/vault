<?php

/**
 * Legacy auth.php - DEPRECATED
 * 
 * This file is kept for backwards compatibility.
 * The new Laravel-style backend uses:
 *   - app/Http/Controllers/AuthController.php
 *   - Routes: POST /api/auth/login, POST /api/auth/register
 * 
 * All requests should go through index.php which routes to controllers.
 */

// For backwards compatibility, redirect to new structure
header('Location: /backend/api/auth/login');
exit;
