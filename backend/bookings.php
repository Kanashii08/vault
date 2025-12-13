<?php

/**
 * Legacy bookings.php - DEPRECATED
 * 
 * This file is kept for backwards compatibility.
 * The new Laravel-style backend uses:
 *   - app/Http/Controllers/BookingController.php
 *   - Routes: GET /api/bookings, POST /api/bookings, etc.
 * 
 * All requests should go through index.php which routes to controllers.
 */

// For backwards compatibility, redirect to new structure
header('Location: /backend/api/bookings');
exit;
