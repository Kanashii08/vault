<?php

/**
 * BookingController
 * 
 * Handles booking operations: list, create, update, delete.
 */

class BookingController
{
    /**
     * List all bookings (admin/staff only)
     */
    public function index()
    {
        $stmt = db()->query('
            SELECT b.*, 
                   c.name AS cubicle_name,
                   CONCAT(u.first_name, " ", u.last_name) AS user_name
            FROM bookings b
            JOIN cubicles c ON c.id = b.cubicle_id
            JOIN users u ON u.id = b.user_id
            ORDER BY FIELD(b.status, "pending", "confirmed", "cancelled"), b.start_time DESC
        ');
        $bookings = $stmt->fetchAll();

        $this->formatBookings($bookings);
        response(['bookings' => $bookings]);
    }

    /**
     * Get current user's bookings
     */
    public function mine()
    {
        $user = auth();

        $stmt = db()->prepare('
            SELECT b.*, 
                   c.name AS cubicle_name,
                   CONCAT(u.first_name, " ", u.last_name) AS user_name
            FROM bookings b
            JOIN cubicles c ON c.id = b.cubicle_id
            JOIN users u ON u.id = b.user_id
            WHERE b.user_id = :user_id
            ORDER BY FIELD(b.status, "pending", "confirmed", "cancelled"), b.start_time DESC
        ');
        $stmt->execute(['user_id' => $user['user_id']]);
        $bookings = $stmt->fetchAll();

        $this->formatBookings($bookings);
        response(['bookings' => $bookings]);
    }

    /**
     * Get today's bookings (admin/staff only)
     */
    public function today()
    {
        $stmt = db()->query('
            SELECT b.*, 
                   c.name AS cubicle_name,
                   CONCAT(u.first_name, " ", u.last_name) AS user_name
            FROM bookings b
            JOIN cubicles c ON c.id = b.cubicle_id
            JOIN users u ON u.id = b.user_id
            WHERE DATE(b.start_time) = CURDATE()
            ORDER BY FIELD(b.status, "pending", "confirmed", "cancelled"), b.start_time
        ');
        $bookings = $stmt->fetchAll();

        $this->formatBookings($bookings);
        response(['bookings' => $bookings]);
    }

    /**
     * Lookup bookings by email (admin/staff only)
     */
    public function lookup()
    {
        $data = request();
        $email = $data['email'] ?? '';

        if (!$email) {
            response(['error' => true, 'message' => 'Email is required'], 400);
        }

        $stmt = db()->prepare('
            SELECT b.*, 
                   c.name AS cubicle_name,
                   CONCAT(u.first_name, " ", u.last_name) AS user_name
            FROM bookings b
            JOIN cubicles c ON c.id = b.cubicle_id
            JOIN users u ON u.id = b.user_id
            WHERE u.email = :email
            ORDER BY b.start_time DESC
        ');
        $stmt->execute(['email' => strtolower($email)]);
        $bookings = $stmt->fetchAll();

        $this->formatBookings($bookings);
        response(['bookings' => $bookings]);
    }

    /**
     * Create a new booking
     */
    public function store()
    {
        $data = validate([
            'cubicle_id' => 'required',
            'start_time' => 'required',
            'duration'   => 'required',
        ]);

        $user = auth();
        $cubicleId = (int) $data['cubicle_id'];
        $startTime = $data['start_time'];
        // Duration between 1 and 24 hours
        $duration = max(1, min(24, (int) $data['duration']));

        // Calculate end time and validate start time
        $start = new DateTime($startTime);
        $now = new DateTime();
        $tomorrow8am = (clone $now)->setTime(0, 0, 0)->modify('+1 day')->setTime(8, 0, 0);

        // Bookings must start from tomorrow 8:00 AM onwards (no past / no today)
        if ($start < $tomorrow8am) {
            response(['error' => true, 'message' => 'Bookings must start from tomorrow 8:00 AM onwards'], 400);
        }

        // Also ensure bookings are at 8:00 AM onwards for the selected day
        if ((int) $start->format('H') < 8) {
            response(['error' => true, 'message' => 'Bookings must be scheduled at 8:00 AM onwards'], 400);
        }
        $end = clone $start;
        $end->modify("+{$duration} hours");

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        // Check for overlapping CONFIRMED bookings on this cubicle
        $stmt = db()->prepare('
            SELECT COUNT(*) as cnt FROM bookings
            WHERE cubicle_id = :cubicle_id
              AND status = "confirmed"
              AND NOT (:end_time <= start_time OR :start_time >= end_time)
        ');
        $stmt->execute([
            'cubicle_id' => $cubicleId,
            'start_time' => $startStr,
            'end_time'   => $endStr,
        ]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            response(['error' => true, 'message' => 'Time slot is not available'], 400);
        }

        // Prevent the same user from booking any overlapping time range if they already have pending/confirmed booking
        $stmt = db()->prepare('
            SELECT COUNT(*) as cnt FROM bookings
            WHERE user_id = :user_id
              AND status IN ("pending", "confirmed")
              AND NOT (:end_time <= start_time OR :start_time >= end_time)
        ');
        $stmt->execute([
            'user_id'    => $user['user_id'],
            'start_time' => $startStr,
            'end_time'   => $endStr,
        ]);
        $userOverlap = (int) $stmt->fetchColumn();

        if ($userOverlap > 0) {
            response(['error' => true, 'message' => 'You already have a booking at this time'], 400);
        }

        // Create booking
        $stmt = db()->prepare('
            INSERT INTO bookings (user_id, cubicle_id, start_time, end_time, status, created_at, updated_at)
            VALUES (:user_id, :cubicle_id, :start_time, :end_time, :status, NOW(), NOW())
        ');
        $stmt->execute([
            'user_id'    => $user['user_id'],
            'cubicle_id' => $cubicleId,
            'start_time' => $startStr,
            'end_time'   => $endStr,
            'status'     => 'pending',
        ]);

        $id = db()->lastInsertId();

        response(['success' => true, 'id' => (int) $id], 201);
    }

    /**
     * Show a single booking
     */
    public function show($id)
    {
        $user = auth();

        $stmt = db()->prepare('
            SELECT b.*, 
                   c.name AS cubicle_name,
                   CONCAT(u.first_name, " ", u.last_name) AS user_name
            FROM bookings b
            JOIN cubicles c ON c.id = b.cubicle_id
            JOIN users u ON u.id = b.user_id
            WHERE b.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            response(['error' => true, 'message' => 'Booking not found'], 404);
        }

        // Users can only see their own bookings
        if ($user['role'] === 'user' && $booking['user_id'] != $user['user_id']) {
            response(['error' => true, 'message' => 'Forbidden'], 403);
        }

        $this->formatBooking($booking);
        response(['booking' => $booking]);
    }

    /**
     * Update a booking
     */
    public function update($id)
    {
        $user = auth();
        $data = request();

        $stmt = db()->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            response(['error' => true, 'message' => 'Booking not found'], 404);
        }

        // Users can only update their own bookings
        if ($user['role'] === 'user' && $booking['user_id'] != $user['user_id']) {
            response(['error' => true, 'message' => 'Forbidden'], 403);
        }

        $newStatus = $data['status'] ?? $booking['status'];

        // Validate allowed statuses
        $allowedStatuses = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            response(['error' => true, 'message' => 'Invalid status'], 400);
        }

        // Normal users can only cancel their own booking, and only if not confirmed
        if ($user['role'] === 'user') {
            if ($newStatus !== 'cancelled') {
                response(['error' => true, 'message' => 'You can only cancel your own bookings'], 403);
            }
            if ($booking['status'] === 'confirmed') {
                response(['error' => true, 'message' => 'Confirmed bookings cannot be cancelled by users'], 403);
            }
        }

        $stmt = db()->prepare('
            UPDATE bookings 
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id'     => $id,
            'status' => $newStatus,
        ]);

        // If this booking is now confirmed, auto-cancel other pending bookings for the same cubicle and overlapping time range
        if ($newStatus === 'confirmed') {
            // Do not allow confirmation if it overlaps an already confirmed booking on the same cubicle
            $stmt = db()->prepare('
                SELECT COUNT(*) as cnt FROM bookings
                WHERE id != :id
                  AND cubicle_id = :cubicle_id
                  AND status = "confirmed"
                  AND NOT (:end_time <= start_time OR :start_time >= end_time)
            ');
            $stmt->execute([
                'id'         => $id,
                'cubicle_id' => $booking['cubicle_id'],
                'start_time' => $booking['start_time'],
                'end_time'   => $booking['end_time'],
            ]);
            $confirmedOverlap = (int) $stmt->fetchColumn();
            if ($confirmedOverlap > 0) {
                response(['error' => true, 'message' => 'Cannot confirm: time slot overlaps an existing confirmed booking'], 400);
            }

            $stmt = db()->prepare('
                UPDATE bookings
                SET status = "cancelled", updated_at = NOW()
                WHERE id != :id
                  AND cubicle_id = :cubicle_id
                  AND status = "pending"
                  AND NOT (:end_time <= start_time OR :start_time >= end_time)
            ');
            $stmt->execute([
                'id'         => $id,
                'cubicle_id' => $booking['cubicle_id'],
                'start_time' => $booking['start_time'],
                'end_time'   => $booking['end_time'],
            ]);
        }

        response(['success' => true]);
    }

    /**
     * Delete a booking
     */
    public function destroy($id)
    {
        $user = auth();

        $stmt = db()->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            response(['error' => true, 'message' => 'Booking not found'], 404);
        }

        // Users can only delete their own bookings
        if ($user['role'] === 'user' && $booking['user_id'] != $user['user_id']) {
            response(['error' => true, 'message' => 'Forbidden'], 403);
        }

        $stmt = db()->prepare('DELETE FROM bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);

        response(['success' => true]);
    }

    /**
     * Format booking fields
     */
    private function formatBooking(&$booking)
    {
        $booking['id'] = (int) $booking['id'];
        $booking['user_id'] = (int) $booking['user_id'];
        $booking['cubicle_id'] = (int) $booking['cubicle_id'];
    }

    /**
     * Format multiple bookings
     */
    private function formatBookings(&$bookings)
    {
        foreach ($bookings as &$b) {
            $this->formatBooking($b);
        }
    }
}
