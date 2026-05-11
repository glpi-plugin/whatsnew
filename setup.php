<?php
use Glpi\RichText\RichText;

/**
 * Plugin: What's New
 */

define('PLUGIN_WHATSNEW_VERSION', '1.0.0');

function plugin_init_whatsnew() {
    global $PLUGIN_HOOKS;

    Plugin::loadLang('whatsnew');

    $PLUGIN_HOOKS['csrf_compliant']['whatsnew']  = true;
    $PLUGIN_HOOKS['config_page']['whatsnew']     = 'front/config.php';

    // Hook both central and helpdesk display events.
    // The static $displayed guard inside plugin_whatsnew_display() prevents
    // double-rendering if both hooks fire on the same request.
    $PLUGIN_HOOKS['display_central']['whatsnew']  = 'plugin_whatsnew_display';
    $PLUGIN_HOOKS['display_helpdesk']['whatsnew'] = 'plugin_whatsnew_display';

    Plugin::registerClass('PluginWhatsnewProfile', ['addtabon' => 'Profile']);

    if (Session::haveRight('plugin_whatsnew_announcement', UPDATE) || Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['whatsnew'] = ['config' => 'PluginWhatsnewConfig'];
    }
}

function plugin_version_whatsnew() {
    return [
        'name'         => "What's New",
        'version'      => PLUGIN_WHATSNEW_VERSION,
        'author'       => 'DSI PF',
        'license'      => 'GPLv2+',
        'requirements' => [
            // No upper cap — the plugin is not inherently version-limited
            'glpi' => ['min' => '11.0.0'],
        ],
    ];
}

function plugin_whatsnew_check_prerequisites() { return true; }
function plugin_whatsnew_check_config()        { return true; }

/**
 * Injected on every GLPI page after login.
 * Fetches the active announcement for the current user's interface,
 * checks their dismissal state, then delegates HTML rendering to
 * plugin_whatsnew_render_modal() — keeping data and presentation separate.
 */
function plugin_whatsnew_display() {
    global $CFG_GLPI, $DB;

    if (!Session::getLoginUserID()) return;

    static $displayed = false;
    if ($displayed) return;
    $displayed = true;

    if (!$DB->tableExists('glpi_plugin_whatsnew_announcements')) return;

    $current_interface = Session::getCurrentInterface(); // 'central' or 'helpdesk'
    $announcement      = PluginWhatsnewAnnouncement::getActiveForProfile($current_interface);

    if ($announcement === null) return;

    $hash       = $announcement['version_hash'];
    $user_id    = (int) Session::getLoginUserID();
    $force_show = isset($_GET['whatsnew']) && $_GET['whatsnew'] === '1';
    $session_dismissed = !empty($_SESSION['plugin_whatsnew_session_dismissed'][$hash]);
    $dismissed  = PluginWhatsnewAnnouncement::isUserDismissed($user_id, $hash) || $session_dismissed;
    $show_modal = !$dismissed || $force_show;

    plugin_whatsnew_render_modal([
        'title'       => htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'),
        'content'     => RichText::getSafeHtml($announcement['content']),
        'hash_js'     => json_encode($hash),
        'dismiss_url' => json_encode($CFG_GLPI['root_doc'] . '/plugins/whatsnew/ajax/dismiss.php'),
        'force_js'    => $force_show ? 'true' : 'false',
        'csrf_token'  => json_encode(Session::getNewCSRFToken()),
        'show_modal'  => $show_modal,
        'archive_url' => htmlspecialchars(
            $CFG_GLPI['root_doc'] . '/plugins/whatsnew/front/archive.php',
            ENT_QUOTES, 'UTF-8'
        ),
    ]);
}

/**
 * Renders the modal HTML, CSS, and JS.
 * Kept separate from plugin_whatsnew_display() so the presentation layer
 * can be modified or tested independently of the data-fetching logic.
 *
 * @param array $tpl {
 *   string title, string content, string hash_js, string dismiss_url,
 *   string force_js, string csrf_token, bool show_modal
 * }
 */
function plugin_whatsnew_render_modal(array $tpl): void {
    $modal_display = $tpl['show_modal'] ? 'flex' : 'none';
    $lbl_dismiss   = htmlspecialchars(__("Don't show this again", 'whatsnew'), ENT_QUOTES, 'UTF-8');
    $lbl_got_it    = htmlspecialchars(__('Got it!', 'whatsnew'), ENT_QUOTES, 'UTF-8');
    $lbl_reopen    = htmlspecialchars(__("What's New", 'whatsnew'), ENT_QUOTES, 'UTF-8');
    $lbl_archive   = htmlspecialchars(__('Past Announcements', 'whatsnew'), ENT_QUOTES, 'UTF-8');

    $title       = $tpl['title'];
    $content     = $tpl['content'];
    $hash_js     = $tpl['hash_js'];
    $dismiss_url = $tpl['dismiss_url'];
    $force_js    = $tpl['force_js'];
    $csrf_token  = $tpl['csrf_token'];
    $archive_url = $tpl['archive_url'];

    echo <<<HTML
<div id="whatsnew-overlay" style="display:{$modal_display}" role="dialog" aria-modal="true" aria-labelledby="whatsnew-title">
  <div id="whatsnew-modal">
    <div id="whatsnew-header">
      <span id="whatsnew-title">{$title}</span>
      <button type="button" id="whatsnew-close" aria-label="Close">&times;</button>
    </div>
    <div id="whatsnew-body">{$content}</div>
    <div id="whatsnew-footer">
      <label><input type="checkbox" id="whatsnew-never"> {$lbl_dismiss}</label>
      <a href="{$archive_url}" id="whatsnew-archive-link">{$lbl_archive}</a>
      <button type="button" id="whatsnew-ok">{$lbl_got_it}</button>
    </div>
  </div>
</div>

<div id="whatsnew-fab-group">
  <button type="button" id="whatsnew-reopen-btn" onclick="whatsnewOpen()" title="{$lbl_reopen}" aria-label="{$lbl_reopen}">&#128227;</button>
</div>

<style>
#whatsnew-overlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center}
#whatsnew-modal{background:#fff;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:min(680px,92vw);max-height:85vh;display:flex;flex-direction:column;overflow:hidden}
#whatsnew-header{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;background:var(--glpi-mainmenu-bg,var(--bs-primary));color:var(--glpi-mainmenu-fg,#fff)}
#whatsnew-title{font-size:1.15rem;font-weight:700}
#whatsnew-close{background:none;border:none;color:rgba(255,255,255,.85);font-size:1.6rem;cursor:pointer;line-height:1;padding:0 4px}
#whatsnew-close:hover{color:#fff}
#whatsnew-body{padding:24px;overflow-y:auto;flex:1;font-size:.97rem;line-height:1.75;color:#333}
#whatsnew-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 24px;background:#f8f9fa;border-top:1px solid #e9ecef;gap:12px;flex-wrap:wrap}
#whatsnew-footer label{display:flex;align-items:center;gap:8px;font-size:.88rem;color:#555;cursor:pointer}
#whatsnew-ok{background:var(--glpi-mainmenu-bg,var(--bs-primary));color:var(--glpi-mainmenu-fg,#fff);border:none;border-radius:6px;padding:9px 26px;font-size:.9rem;font-weight:600;cursor:pointer}
#whatsnew-ok:hover{filter:brightness(1.15)}
#whatsnew-ok:disabled{opacity:.6;cursor:not-allowed}
#whatsnew-fab-group{position:fixed;bottom:18px;right:18px;z-index:99998;display:flex;flex-direction:column;align-items:flex-end;gap:6px}
#whatsnew-reopen-btn{background:var(--glpi-mainmenu-bg,var(--bs-primary));color:var(--glpi-mainmenu-fg,#fff);border:none;border-radius:50%;width:38px;height:38px;font-size:1.2rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center}
#whatsnew-reopen-btn:hover{filter:brightness(1.15)}
@keyframes whatsnew-jump{0%,100%{transform:translateY(0)}20%{transform:translateY(-10px)}40%{transform:translateY(-5px)}60%{transform:translateY(-8px)}80%{transform:translateY(-3px)}}
.whatsnew-jump{animation:whatsnew-jump .7s ease 3}
#whatsnew-archive-link{font-size:.85rem;color:inherit;text-decoration:underline;opacity:.7}
#whatsnew-archive-link:hover{opacity:1}
</style>

<script>
(function () {
  var overlay   = document.getElementById('whatsnew-overlay');
  var hash      = {$hash_js};
  var url       = {$dismiss_url};
  var forced    = {$force_js};
  var csrfToken = {$csrf_token};
  var dismissing = false;

  function jumpReopenBtn() {
    var btn = document.getElementById('whatsnew-reopen-btn');
    if (!btn) return;
    btn.classList.remove('whatsnew-jump');
    void btn.offsetWidth;
    btn.classList.add('whatsnew-jump');
  }

  function hideModal(never) {
    if (dismissing) return;
    dismissing = true;

    var okBtn = document.getElementById('whatsnew-ok');
    okBtn.disabled = true;

    if (!forced) {
      // Always notify the server: permanent (never=1) writes to DB,
      // temporary (never=0) writes to session so the modal won't
      // reappear on tab navigation within the same login session.
      var fd = new FormData();
      fd.append('version_hash', hash);
      fd.append('never', never ? '1' : '0');
      fd.append('_glpi_csrf_token', csrfToken);

      fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) { console.log('[whatsnew dismiss]', d); })
        .catch(function (e) { console.error('[whatsnew dismiss error]', e); })
        .finally(function () { overlay.style.display = 'none'; dismissing = false; jumpReopenBtn(); });
    } else {
      overlay.style.display = 'none';
      dismissing = false;
      jumpReopenBtn();
    }
  }

  document.getElementById('whatsnew-ok').onclick    = function () { hideModal(document.getElementById('whatsnew-never').checked); };
  document.getElementById('whatsnew-close').onclick = function () { hideModal(false); };
  overlay.onclick = function (e) { if (e.target === overlay) hideModal(false); };
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideModal(false); });

  window.whatsnewOpen = function () {
    overlay.style.display = 'flex';
    dismissing = false;
    var okBtn = document.getElementById('whatsnew-ok');
    if (okBtn) okBtn.disabled = false;
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', jumpReopenBtn);
  } else {
    jumpReopenBtn();
  }

  if (forced) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', window.whatsnewOpen);
    } else {
      window.whatsnewOpen();
    }
  }
})();
</script>
HTML;
}
