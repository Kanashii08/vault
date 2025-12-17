<?php

/**
 * Legacy cubicles.php - DEPRECATED
 * 
 * This file is kept for backwards compatibility.
 * The new Laravel-style backend uses:
 *   - app/Http/Controllers/CubicleController.php
 *   - Routes: GET /api/cubicles, POST /api/cubicles
 * 
 * All requests should go through index.php which routes to controllers.
 */

// For backwards compatibility, redirect to new structure
header('Location: /backend/api/cubicles');
exit;
