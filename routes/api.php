<?php

/**
 * API Routes
 * 
 * Define all API routes here in Laravel-style format.
 * Each route has: method, path, action (Controller@method), and optional middleware.
 */

return [
    // Auth routes (public)
    [
        'method' => 'POST',
        'path' => '/api/auth/register',
        'action' => 'AuthController@register',
        'middleware' => [],
    ],
    [
        'method' => 'POST',
        'path' => '/api/auth/login',
        'action' => 'AuthController@login',
        'middleware' => [],
    ],
    [
        'method' => 'GET',
        'path' => '/api/auth/verify-email',
        'action' => 'AuthController@verifyEmail',
        'middleware' => [],
    ],
    [
        'method' => 'POST',
        'path' => '/api/auth/logout',
        'action' => 'AuthController@logout',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/user',
        'action' => 'AuthController@user',
        'middleware' => ['auth'],
    ],

    // Cubicle routes
    [
        'method' => 'GET',
        'path' => '/api/cubicles',
        'action' => 'CubicleController@index',
        'middleware' => [],
    ],
    [
        'method' => 'POST',
        'path' => '/api/cubicles',
        'action' => 'CubicleController@store',
        'middleware' => ['auth', 'role:admin,staff'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/cubicles/{id}',
        'action' => 'CubicleController@show',
        'middleware' => [],
    ],
    [
        'method' => 'PUT',
        'path' => '/api/cubicles/{id}',
        'action' => 'CubicleController@update',
        'middleware' => ['auth', 'role:admin,staff'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/cubicles/{id}',
        'action' => 'CubicleController@destroy',
        'middleware' => ['auth', 'role:admin'],
    ],

    // Booking routes
    [
        'method' => 'GET',
        'path' => '/api/bookings',
        'action' => 'BookingController@index',
        'middleware' => ['auth', 'role:admin,staff'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/bookings/mine',
        'action' => 'BookingController@mine',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/bookings/today',
        'action' => 'BookingController@today',
        'middleware' => ['auth', 'role:admin,staff'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/bookings/lookup',
        'action' => 'BookingController@lookup',
        'middleware' => ['auth', 'role:admin,staff'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/bookings',
        'action' => 'BookingController@store',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/bookings/{id}',
        'action' => 'BookingController@show',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'PUT',
        'path' => '/api/bookings/{id}',
        'action' => 'BookingController@update',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/bookings/{id}',
        'action' => 'BookingController@destroy',
        'middleware' => ['auth'],
    ],

    // User management routes (admin only)
    [
        'method' => 'GET',
        'path' => '/api/users',
        'action' => 'UserController@index',
        'middleware' => ['auth', 'role:admin'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/users',
        'action' => 'UserController@store',
        'middleware' => ['auth', 'role:admin'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{id}',
        'action' => 'UserController@show',
        'middleware' => ['auth', 'role:admin'],
    ],
    [
        'method' => 'PUT',
        'path' => '/api/users/{id}',
        'action' => 'UserController@update',
        'middleware' => ['auth', 'role:admin'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/users/{id}',
        'action' => 'UserController@destroy',
        'middleware' => ['auth', 'role:admin'],
    ],

    // Authenticated user profile update
    [
        'method' => 'PUT',
        'path' => '/api/profile',
        'action' => 'UserController@updateSelf',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/profile/avatar',
        'action' => 'UserController@uploadAvatar',
        'middleware' => ['auth'],
    ],
];
