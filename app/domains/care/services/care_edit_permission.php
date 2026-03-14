<?php
/**
 * app/domains/care/services/care_edit_permission.php
 *
 * Service to check if a care record can be edited/deleted
 */
declare(strict_types=1);

namespace App\Domains\Care\Services;

class CareEditPermission
{
    // Edit deadline: within 3 days
    private const EDIT_DAYS = 3;

    // Delete deadline: within 2 days
    private const DELETE_DAYS = 2;

    /**
     * Check if can edit a record
     */
    public static function can_edit(string $recorded_at, ?string $override_pass = null): bool
    {
        // If has override password, allow
        if (!empty($override_pass) && self::verify_password($override_pass)) {
            return true;
        }

        // Check if within edit deadline
        $recorded = strtotime($recorded_at);
        $now = time();
        $diff_days = ($now - $recorded) / 86400;

        return $diff_days <= self::EDIT_DAYS;
    }

    /**
     * Check if can delete a record
     */
    public static function can_delete(string $recorded_at, ?string $override_pass = null): bool
    {
        // If has override password, allow
        if (!empty($override_pass) && self::verify_password($override_pass)) {
            return true;
        }

        // Check if within delete deadline
        $recorded = strtotime($recorded_at);
        $now = time();
        $diff_days = ($now - $recorded) / 86400;

        return $diff_days <= self::DELETE_DAYS;
    }

    /**
     * Get delete deadline text
     */
    public static function delete_deadline(string $recorded_at): string
    {
        $recorded = strtotime($recorded_at);
        $deadline = $recorded + (self::DELETE_DAYS * 86400);
        return date('d/m/Y', $deadline);
    }

    /**
     * Get edit deadline text
     */
    public static function edit_deadline(string $recorded_at): string
    {
        $recorded = strtotime($recorded_at);
        $deadline = $recorded + (self::EDIT_DAYS * 86400);
        return date('d/m/Y', $deadline);
    }

    /**
     * Verify override password
     * In production, this should compare against a hashed password
     * For now, using a simple check (should be improved)
     */
    private static function verify_password(string $pass): bool
    {
        // Simple hardcoded password for override
        // In production, should check against stored hashed password
        return $pass === 'admin123';
    }
}
