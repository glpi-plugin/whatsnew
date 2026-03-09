<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * PluginWhatsnewAnnouncement - ORM class for announcements.
 */
class PluginWhatsnewAnnouncement extends CommonDBTM {

    public static $rightname = 'plugin_whatsnew_announcement';

    // Profile type constants — single source of truth used everywhere
    const PROFILE_CENTRAL  = 'central';
    const PROFILE_HELPDESK = 'helpdesk';
    const PROFILE_ALL      = 'all';

    static function getTypeName($nb = 0) {
        return _n("What's New Announcement", "What's New Announcements", $nb, 'whatsnew');
    }

    /**
     * Build the canonical version hash for an announcement.
     * Mixing in profile_type ensures identical content for different
     * audiences produces distinct hashes, preventing cross-profile
     * dismissal bleed.
     */
    static function buildHash(string $content, string $profile_type): string {
        return hash('sha256', $content . $profile_type);
    }

    /**
     * Return the active announcement for a given profile type.
     * Falls back to PROFILE_ALL if no profile-specific row is found.
     * Returns null if nothing is active.
     */
    static function getActiveForProfile(string $profile_type): ?array {
        global $DB;

        // Reject invalid profile types early
        $allowed = [self::PROFILE_CENTRAL, self::PROFILE_HELPDESK, self::PROFILE_ALL];
        if (!in_array($profile_type, $allowed, true)) {
            return null;
        }

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_whatsnew_announcements',
            'WHERE' => ['is_active' => 1, 'profile_type' => $profile_type],
            'ORDER' => 'date_mod DESC',
            'LIMIT' => 1,
        ]);

        if ($result->count() > 0) {
            return $result->current();
        }

        // Fall back to 'all' if no profile-specific row exists
        if ($profile_type !== self::PROFILE_ALL) {
            $fallback = $DB->request([
                'FROM'  => 'glpi_plugin_whatsnew_announcements',
                'WHERE' => ['is_active' => 1, 'profile_type' => self::PROFILE_ALL],
                'ORDER' => 'date_mod DESC',
                'LIMIT' => 1,
            ]);

            if ($fallback->count() > 0) {
                return $fallback->current();
            }
        }

        return null;
    }

    /**
     * Fetch the single active announcement for a specific profile_type
     * with no fallback — used by the config page to load each editor card.
     */
    static function getByProfileType(string $profile_type): ?array {
        global $DB;

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_whatsnew_announcements',
            'WHERE' => ['is_active' => 1, 'profile_type' => $profile_type],
            'ORDER' => 'date_mod DESC',
            'LIMIT' => 1,
        ]);

        if ($result->count() === 0) {
            return null;
        }

        $row = $result->current();

        // Strict ownership guard — never return a row that doesn't belong to this type
        if ($row['profile_type'] !== $profile_type) {
            return null;
        }

        return $row;
    }

    /**
     * Check whether a user has already dismissed a specific version hash.
     */
    static function isUserDismissed(int $users_id, string $version_hash): bool {
        global $DB;

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_whatsnew_user_dismissals',
            'WHERE' => ['users_id' => $users_id, 'version_hash' => $version_hash],
            'LIMIT' => 1,
        ]);

        return $result->count() > 0;
    }

    /**
     * Validate that a version_hash corresponds to a real announcement.
     * Used by dismiss.php to reject arbitrary/crafted hashes.
     */
    static function hashExists(string $version_hash): bool {
        global $DB;

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_whatsnew_announcements',
            'WHERE' => ['version_hash' => $version_hash],
            'LIMIT' => 1,
        ]);

        return $result->count() > 0;
    }

    /**
     * Record that a user dismissed a given version hash.
     * Safe to call multiple times — silently ignores duplicates.
     */
    static function dismissForUser(int $users_id, string $version_hash): void {
        global $DB;

        if (self::isUserDismissed($users_id, $version_hash)) {
            return;
        }

        $DB->insert('glpi_plugin_whatsnew_user_dismissals', [
            'users_id'       => $users_id,
            'version_hash'   => $version_hash,
            'date_dismissal' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Save (insert or update) an announcement for a given profile type.
     * Recalculates the version hash so all users see the modal again.
     * Also saves to history and purges stale dismissal records.
     *
     * @return string  The new version hash.
     */
    static function save(int $id, string $title, string $content, string $profile_type): string {
        global $DB;

        $now  = date('Y-m-d H:i:s');
        $hash = self::buildHash($content, $profile_type);

        if ($id > 0) {
            $DB->update('glpi_plugin_whatsnew_announcements', [
                'title'        => $title,
                'content'      => $content,
                'profile_type' => $profile_type,
                'version_hash' => $hash,
                'date_mod'     => $now,
            ], ['id' => $id, 'profile_type' => $profile_type]);
        } else {
            $DB->insert('glpi_plugin_whatsnew_announcements', [
                'title'         => $title,
                'content'       => $content,
                'profile_type'  => $profile_type,
                'version_hash'  => $hash,
                'is_active'     => 1,
                'date_creation' => $now,
                'date_mod'      => $now,
            ]);
        }

        // Record in history
        $DB->insert('glpi_plugin_whatsnew_history', [
            'profile_type' => $profile_type,
            'title'        => $title,
            'content'      => $content,
            'saved_by'     => (int) Session::getLoginUserID(),
            'date_save'    => $now,
        ]);

        // Purge dismissal records that no longer correspond to any active announcement.
        // This keeps the dismissals table lean over time.
        self::purgeOrphanedDismissals();

        return $hash;
    }

    /**
     * Delete dismissal records whose version_hash no longer matches
     * any row in the announcements table.
     */
    static function purgeOrphanedDismissals(): void {
        global $DB;

        // Collect all hashes that still exist in announcements
        $active_hashes = [];
        $iter = $DB->request(['SELECT' => ['version_hash'], 'FROM' => 'glpi_plugin_whatsnew_announcements']);
        foreach ($iter as $row) {
            $active_hashes[] = $row['version_hash'];
        }

        if (empty($active_hashes)) {
            // No announcements at all — clear everything
            $DB->query("TRUNCATE TABLE `glpi_plugin_whatsnew_user_dismissals`");
            return;
        }

        // Delete dismissals that reference a hash no longer in the table
        $DB->delete('glpi_plugin_whatsnew_user_dismissals', [
            'NOT' => ['version_hash' => $active_hashes],
        ]);
    }

    /**
     * Return all valid profile types as a key => label array.
     */
    static function getProfileTypes(): array {
        return [
            self::PROFILE_CENTRAL  => __('Technicians (Central interface)', 'whatsnew'),
            self::PROFILE_HELPDESK => __('Self-Service (Helpdesk interface)', 'whatsnew'),
            self::PROFILE_ALL      => __('All users (fallback)', 'whatsnew'),
        ];
    }
}
