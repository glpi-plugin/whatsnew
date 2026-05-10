<?php
use Glpi\RichText\RichText;

include('../../../inc/includes.php');

if (!Session::haveRight('plugin_whatsnew_announcement', UPDATE) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
}

// --- Handle form submission ---
if (isset($_POST['update'])) {
    $title       = trim($_POST['title'] ?? '');
    // Enforce the DB column limit explicitly rather than letting MySQL silently truncate
    if (mb_strlen($title) > 255) {
        Session::addMessageAfterRedirect(__('Title must be 255 characters or fewer.', 'whatsnew'), true, ERROR);
        Html::back();
    }
    $raw_content = $_POST['content'] ?? '';
    $content     = RichText::getSafeHtml($raw_content);
    $id          = (int) ($_POST['id'] ?? 0);

    // Whitelist profile_type against the class constants — never trust raw POST
    $allowed_types = array_keys(PluginWhatsnewAnnouncement::getProfileTypes());
    $profile_type  = in_array($_POST['profile_type'] ?? '', $allowed_types, true)
                     ? $_POST['profile_type']
                     : PluginWhatsnewAnnouncement::PROFILE_ALL;

    if ($title && $content) {
        PluginWhatsnewAnnouncement::save($id, $title, $content, $profile_type);

        $msg = $id > 0
            ? __('Announcement updated. All users will see the modal again.', 'whatsnew')
            : __('Announcement created.', 'whatsnew');
        Session::addMessageAfterRedirect($msg, true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Title and content are required.', 'whatsnew'), true, ERROR);
    }

    Html::back();
}

// --- Render page ---
Html::header(__("What's New – Configuration", 'whatsnew'), $_SERVER['PHP_SELF'], 'config', 'plugins');

$profile_types = PluginWhatsnewAnnouncement::getProfileTypes();

echo '<div class="container-fluid mt-3">';
echo '<h2>' . __("What's New – Announcement Editor", 'whatsnew') . '</h2>';
echo '<p class="text-muted">' . __('Each audience can have its own announcement. Saving regenerates the version hash — all matching users will see the modal again.', 'whatsnew') . '</p>';

foreach ($profile_types as $profile_type => $label) {

    $announcement = PluginWhatsnewAnnouncement::getByProfileType($profile_type);
    $editor_id    = 'content_' . $profile_type;

    echo '<div class="card mb-4">';
    echo '<div class="card-header text-white" style="background:var(--glpi-mainmenu-bg,var(--bs-primary))">';
    echo '<h5 class="mb-0">' . htmlspecialchars($label) . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';

    // Editor form
    echo '<form method="POST" action="" data-submit-once>';
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo '<input type="hidden" name="profile_type" value="' . htmlspecialchars($profile_type) . '">';
    echo '<input type="hidden" name="id" value="' . (int) ($announcement['id'] ?? 0) . '">';
    echo '<input type="hidden" name="update" value="1">';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-bold">' . __('Title', 'whatsnew') . '</label>';
    echo '<input type="text" name="title" class="form-control" value="'
        . htmlspecialchars($announcement['title'] ?? '', ENT_QUOTES, 'UTF-8') . '" required>';
    echo '</div>';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-bold">' . __('Content', 'whatsnew') . '</label>';
    Html::textarea([
        'name'              => 'content',
        'id'                => $editor_id,
        'value'             => $announcement['content'] ?? '',
        'enable_richtext'   => true,
        'enable_fileupload' => false,
        'cols'              => 100,
        'rows'              => 10,
        'editor_options'    => ['paste_data_images' => false],
    ]);
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary">' . __('Save & Notify All Users', 'whatsnew') . '</button>';
    echo '</form>';

    // History panel
    global $DB;
    $history = $DB->request([
        'FROM'  => 'glpi_plugin_whatsnew_history',
        'WHERE' => ['profile_type' => $profile_type],
        'ORDER' => 'date_save DESC',
    ]);

    if ($history->count() > 0) {
        $active_content = $announcement['content'] ?? null;

        echo '<hr>';
        echo '<h6 class="mt-3 mb-2 text-muted">'
            . __('History', 'whatsnew')
            . ' <small>(' . __('last 6 months', 'whatsnew') . ')</small></h6>';
        echo '<div class="accordion" id="history_' . $profile_type . '">';

        $i = 0;
        foreach ($history as $entry) {
            $i++;
            $entry_id = 'hist_' . $profile_type . '_' . $i;
            $user     = new User();
            $user->getFromDB($entry['saved_by']);
            $saved_by = htmlspecialchars($user->getFriendlyName() ?: __('Unknown'), ENT_QUOTES, 'UTF-8');
            $date     = htmlspecialchars(Html::convDateTime($entry['date_save']), ENT_QUOTES, 'UTF-8');

            // Badge the history entry that matches the currently active content
            $is_active = ($active_content !== null && $entry['content'] === $active_content);
            $badge     = $is_active
                ? ' <span class="badge bg-success ms-2">' . __('Active', 'whatsnew') . '</span>'
                : '';

            echo '<div class="accordion-item">';
            echo '<h2 class="accordion-header">';
            echo '<button class="accordion-button collapsed py-2" type="button"'
                . ' data-bs-toggle="collapse" data-bs-target="#' . $entry_id . '">';
            echo '<span class="me-3"><strong>'
                . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8')
                . '</strong>' . $badge . '</span>';
            echo '<small class="text-muted">' . $date . ' - ' . $saved_by . '</small>';
            echo '</button>';
            echo '</h2>';
            echo '<div id="' . $entry_id . '" class="accordion-collapse collapse"'
                . ' data-bs-parent="#history_' . $profile_type . '">';
            echo '<div class="accordion-body">';
            echo RichText::getSafeHtml($entry['content']);
            echo '</div></div></div>';
        }

        echo '</div>';
    }

    echo '</div></div>';
}

echo '</div>';
Html::footer();
