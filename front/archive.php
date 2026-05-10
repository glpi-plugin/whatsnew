<?php
use Glpi\RichText\RichText;

include('../../../inc/includes.php');

global $DB;

Session::checkLoginUser();

if (!$DB->tableExists('glpi_plugin_whatsnew_history')) {
    Html::displayNotFound();
}

$interface = Session::getCurrentInterface();
$entries   = PluginWhatsnewAnnouncement::getHistoryForInterface($interface);

Html::header(
    __("What's New – Past Announcements", 'whatsnew'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'plugins'
);

echo '<div class="container-fluid mt-4" style="max-width:860px">';

echo '<div class="d-flex align-items-center mb-4 gap-3">';
echo '<div style="width:4px;height:2rem;background:var(--glpi-mainmenu-bg,var(--bs-primary));border-radius:2px"></div>';
echo '<h2 class="mb-0">' . __('Past Announcements', 'whatsnew') . '</h2>';
echo '</div>';

if (empty($entries)) {
    echo '<div class="alert alert-info">' . __('No past announcements found.', 'whatsnew') . '</div>';
    echo '</div>';
    Html::footer();
    exit;
}

$type_labels = PluginWhatsnewAnnouncement::getProfileTypes();

echo '<style>
.wn-accordion-item{border:1px solid #dee2e6;border-radius:8px!important;margin-bottom:.6rem;overflow:hidden}
.wn-accordion-item .accordion-button{background:#f0f4f8;color:#1a2332;font-weight:600;border-radius:0}
.wn-accordion-item .accordion-button:not(.collapsed){background:var(--glpi-mainmenu-bg,var(--bs-primary));color:var(--glpi-mainmenu-fg,#fff);box-shadow:none}
.wn-accordion-item .accordion-button:not(.collapsed) .text-muted{color:rgba(255,255,255,.7)!important}
.wn-accordion-item .accordion-button:not(.collapsed) .badge{background:rgba(255,255,255,.25)!important;color:#fff}
.wn-accordion-item .accordion-button::after{filter:none}
.wn-accordion-item .accordion-button:not(.collapsed)::after{filter:brightness(0) invert(1)}
.wn-accordion-item .accordion-body{background:#fff;padding:1.5rem;font-size:.97rem;line-height:1.75;color:#333;border-top:1px solid #dee2e6}
.wn-meta{display:flex;align-items:center;gap:.5rem;margin-left:auto;flex-shrink:0}
</style>';

echo '<div class="accordion" id="whatsnew-archive">';

foreach ($entries as $i => $entry) {
    $entry_id  = 'arc_' . $i;
    $date      = htmlspecialchars(Html::convDateTime($entry['date_save']), ENT_QUOTES, 'UTF-8');
    $title     = htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8');
    $content   = RichText::getSafeHtml($entry['content']);
    $type_lbl  = htmlspecialchars($type_labels[$entry['profile_type']] ?? $entry['profile_type'], ENT_QUOTES, 'UTF-8');
    $expanded  = $i === 0 ? ' show' : '';
    $collapsed = $i === 0 ? '' : ' collapsed';

    echo <<<HTML
<div class="accordion-item wn-accordion-item">
  <h2 class="accordion-header">
    <button class="accordion-button{$collapsed}" type="button"
            data-bs-toggle="collapse" data-bs-target="#{$entry_id}"
            aria-expanded="{$expanded}" aria-controls="{$entry_id}">
      <span class="me-2">{$title}</span>
      <span class="wn-meta">
        <small class="text-muted">{$date}</small>
        <span class="badge bg-secondary">{$type_lbl}</span>
      </span>
    </button>
  </h2>
  <div id="{$entry_id}" class="accordion-collapse collapse{$expanded}" data-bs-parent="#whatsnew-archive">
    <div class="accordion-body">{$content}</div>
  </div>
</div>
HTML;
}

echo '</div></div>';
Html::footer();
