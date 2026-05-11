<?php
/**
 * AJAX: Record a dismissal for the current user.
 * The CSRF token is submitted from JS alongside the version_hash.
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json');

$hash    = isset($_POST['version_hash']) ? trim($_POST['version_hash']) : '';
$user_id = (int) Session::getLoginUserID();

if (empty($hash)) {
    echo json_encode(['success' => false, 'error' => 'Missing version_hash']);
    exit;
}

// Security: reject hashes that don't correspond to a real announcement.
// Prevents a logged-in user from writing arbitrary strings into the dismissals table.
if (!PluginWhatsnewAnnouncement::hashExists($hash)) {
    echo json_encode(['success' => false, 'error' => 'Invalid version_hash']);
    exit;
}

$never = !isset($_POST['never']) || $_POST['never'] === '1';

if ($never) {
    try {
        PluginWhatsnewAnnouncement::dismissForUser($user_id, $hash);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Log internally but never expose DB internals to the client
        Toolbox::logError('[whatsnew] dismiss failed for user ' . $user_id . ': ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
} else {
    // Session-only dismissal: hides for this login session but reappears after logout.
    if (!isset($_SESSION['plugin_whatsnew_session_dismissed'])) {
        $_SESSION['plugin_whatsnew_session_dismissed'] = [];
    }
    $_SESSION['plugin_whatsnew_session_dismissed'][$hash] = true;
    echo json_encode(['success' => true]);
}
