<?php

function plugin_whatsnew_install() {
    global $DB;

    $migration        = new Migration(PLUGIN_WHATSNEW_VERSION);
    $is_fresh_install = false;

    if (!$DB->tableExists('glpi_plugin_whatsnew_announcements')) {
        $is_fresh_install = true;
        $migration->addPostQuery("CREATE TABLE `glpi_plugin_whatsnew_announcements` (
            `id`            int(11)      NOT NULL AUTO_INCREMENT,
            `title`         varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `content`       longtext     COLLATE utf8mb4_unicode_ci NOT NULL,
            `version_hash`  varchar(64)  COLLATE utf8mb4_unicode_ci NOT NULL,
            `profile_type`  varchar(20)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
            `is_active`     tinyint(1)   NOT NULL DEFAULT 1,
            `date_creation` datetime     NOT NULL,
            `date_mod`      datetime     NOT NULL,
            PRIMARY KEY (`id`),
            KEY `version_hash` (`version_hash`),
            KEY `profile_type` (`profile_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        if (!$DB->fieldExists('glpi_plugin_whatsnew_announcements', 'profile_type')) {
            $migration->addPostQuery("ALTER TABLE `glpi_plugin_whatsnew_announcements`
                ADD `profile_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all' AFTER `version_hash`");
        }
    }

    if (!$DB->tableExists('glpi_plugin_whatsnew_user_dismissals')) {
        $migration->addPostQuery("CREATE TABLE `glpi_plugin_whatsnew_user_dismissals` (
            `id`             int(11)     NOT NULL AUTO_INCREMENT,
            `users_id`       int(11)     NOT NULL,
            `version_hash`   varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `date_dismissal` datetime    NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_version` (`users_id`, `version_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$DB->tableExists('glpi_plugin_whatsnew_history')) {
        $migration->addPostQuery("CREATE TABLE `glpi_plugin_whatsnew_history` (
            `id`            int(11)      NOT NULL AUTO_INCREMENT,
            `profile_type`  varchar(20)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
            `title`         varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `content`       longtext     COLLATE utf8mb4_unicode_ci NOT NULL,
            `saved_by`      int(11)      NOT NULL DEFAULT 0,
            `date_save`     datetime     NOT NULL,
            PRIMARY KEY (`id`),
            KEY `profile_type` (`profile_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $migration->executeMigration();

    if ($is_fresh_install && $DB->tableExists('glpi_plugin_whatsnew_announcements')) {
        $now = date('Y-m-d H:i:s');

        // Use PluginWhatsnewAnnouncement::buildHash() so the hash formula is
        // identical to what config.php generates on every subsequent save.
        $central_content = "<p><strong>Welcome, Technician!</strong></p><ul>"
            . "<li>New dashboard widgets available</li>"
            . "<li>Ticket form updated with priority auto-assignment</li>"
            . "<li>Asset management module improved</li></ul>";

        $DB->insert('glpi_plugin_whatsnew_announcements', [
            'title'         => "What's New - Technicians",
            'content'       => $central_content,
            'version_hash'  => PluginWhatsnewAnnouncement::buildHash(
                                   $central_content,
                                   PluginWhatsnewAnnouncement::PROFILE_CENTRAL
                               ),
            'profile_type'  => PluginWhatsnewAnnouncement::PROFILE_CENTRAL,
            'is_active'     => 1,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);

        $helpdesk_content = "<p><strong>Welcome!</strong></p><ul>"
            . "<li>You can now track your tickets more easily</li>"
            . "<li>New self-service portal improvements</li></ul>";

        $DB->insert('glpi_plugin_whatsnew_announcements', [
            'title'         => "What's New - Self-Service",
            'content'       => $helpdesk_content,
            'version_hash'  => PluginWhatsnewAnnouncement::buildHash(
                                   $helpdesk_content,
                                   PluginWhatsnewAnnouncement::PROFILE_HELPDESK
                               ),
            'profile_type'  => PluginWhatsnewAnnouncement::PROFILE_HELPDESK,
            'is_active'     => 1,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }

    PluginWhatsnewProfile::addDefaultProfileRights();

    return true;
}

function plugin_whatsnew_uninstall() {
    PluginWhatsnewProfile::removeRights();

    return true;
}
