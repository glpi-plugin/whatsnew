<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginWhatsnewProfile extends CommonDBTM {

    public static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return __("What's New", 'whatsnew');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item instanceof Profile) {
            return __("What's New", 'whatsnew');
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item instanceof Profile) {
            self::showProfileForm($item);
        }
        return true;
    }

    static function showProfileForm(Profile $profile) {
        $rights  = self::getAllRights();
        $canedit = Session::haveRight('config', UPDATE);
        $ID      = $profile->getID();

        echo '<div class="card mt-3">';
        echo '<div class="card-header"><h5>' . __("What's New - Permissions", 'whatsnew') . '</h5></div>';
        echo '<div class="card-body">';

        if ($canedit) {
            echo '<form method="POST" action="' . Profile::getFormURL() . '">';
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        }

        $profile->displayRightsChoiceMatrix($rights, [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __("What's New plugin", 'whatsnew'),
        ]);

        if ($canedit) {
            echo '<div class="mt-3 text-center">';
            echo '<button type="submit" name="update" class="btn btn-primary">' . __('Save') . '</button>';
            echo '</div>';
            echo '</form>';
        }

        echo '</div></div>';
    }

    static function getAllRights($all = false) {
        return [
            [
                'itemtype' => 'PluginWhatsnewAnnouncement',
                'label'    => __('Manage announcements', 'whatsnew'),
                'field'    => 'plugin_whatsnew_announcement',
                'rights'   => [
                    READ   => __('Read'),
                    UPDATE => __('Update'),
                ],
            ],
        ];
    }

    /**
     * Called on plugin install.
     * Grants READ|UPDATE to the super-admin profile using the canonical
     * Profile::getSuperAdminID() rather than a fragile name-pattern match.
     */
    static function addDefaultProfileRights() {
        global $DB;

        ProfileRight::addProfileRights(['plugin_whatsnew_announcement']);

        $super_admin_id = Profile::getSuperAdminID();
        if (!$super_admin_id) {
            return;
        }

        $existing = $DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => ['profiles_id' => $super_admin_id, 'name' => 'plugin_whatsnew_announcement'],
        ]);

        if ($existing->count() > 0) {
            $DB->update('glpi_profilerights', [
                'rights' => READ | UPDATE,
            ], [
                'profiles_id' => $super_admin_id,
                'name'        => 'plugin_whatsnew_announcement',
            ]);
        }
    }

    static function removeRights() {
        ProfileRight::deleteProfileRights(['plugin_whatsnew_announcement']);
    }
}
