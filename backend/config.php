<?php

/**
 * Legacy config.php - DEPRECATED
 * 
 * This file is kept for backwards compatibility.
 * The new Laravel-style backend uses:
 *   - config/database.php for database settings
 *   - bootstrap/app.php for initialization
 *   - routes/api.php for routing
 *   - app/Http/Controllers/ for controllers
 * 
 * Please update config/database.php with your MySQL credentials.
 */

// Redirect to the new structure
require_once __DIR__ . '/bootstrap/app.php';
