# What's New – GLPI Plugin

Displays a modal popup to users when they open GLPI, announcing new features or changes. Supports separate announcements per audience (Technicians, Self-Service, or All users).

---

## Features

- ✅ Modal shown automatically on every page after login
- ✅ **Separate announcements** for Technicians (central interface) and Self-Service (helpdesk interface), with an "All users" fallback
- ✅ **"Don't show this again"** checkbox per user — dismissal is confirmed server-side before the modal closes, preventing reappearance on fast refresh
- ✅ If the announcement content is updated, **all users see the modal again** (tracked via a SHA-256 hash of content + profile type)
- ✅ Full **TinyMCE rich-text editor** for content authoring
- ✅ **Save history** per audience — last 10 saves shown in a collapsible panel, with the currently active version badged in green
- ✅ **Persistent reopen button** (ⓘ) fixed to the bottom-right corner so users can re-read the announcement at any time
- ✅ Stale dismissal records automatically purged when announcements are updated, keeping the database lean
- ✅ GLPI permission system integration — manage access via **Administration → Profiles**
- ✅ No external dependencies — pure GLPI APIs

---

## Requirements

- GLPI **11.0.0** or later

---

## Installation

1. Copy the `whatsnew/` folder into `<GLPI_ROOT>/plugins/`
2. Log in as GLPI superadmin
3. Go to **Setup → Plugins**
4. Find **"What's New"** and click **Install**, then **Enable**

Default sample announcements are created automatically on first install for both the Technician and Self-Service audiences.

---

## Managing Announcements

Go to **Setup → What's New** (or navigate directly to `/plugins/whatsnew/front/config.php`).

The editor shows three cards — one for each audience:

| Audience | Who sees it |
|---|---|
| **Technicians (Central interface)** | Users with a central-interface profile (e.g. technicians, admins) |
| **Self-Service (Helpdesk interface)** | Users with a helpdesk-interface profile (e.g. end users) |
| **All users (fallback)** | Any logged-in user — only shown if no audience-specific announcement exists for their interface |

Edit the **Title** and **Content** for the relevant card, then click **Save & Notify All Users**. Every save regenerates the version hash, causing all matching users to see the modal again on their next page visit regardless of any previous dismissal.

---

## How "Don't Show Again" Works

| Event | Behaviour |
|---|---|
| User checks "Don't show this again" and clicks **Got it!** | The dismiss request is sent to the server; the modal only closes once the server confirms the record was written |
| Admin saves new or updated content | A new SHA-256 hash is generated from `content + profile_type`; existing dismissal records no longer match → modal reappears for all affected users |
| Admin saves new content | Dismissal records for hashes that no longer exist in the announcements table are automatically purged |
| User closes the modal without checking the box | Modal will reappear on the next page load |
| User wants to re-read the announcement | Click the **ⓘ** button fixed to the bottom-right of every page |

---

## Permissions

The plugin registers a `plugin_whatsnew_announcement` right visible under **Administration → Profiles → What's New**.

| Right | Effect |
|---|---|
| **Read** | (reserved for future use) |
| **Update** | Grants access to the announcement editor |

The super-admin profile receives **Update** automatically on install. Any profile granted **Update** will see the **What's New** item in the **Setup** menu.

---

## Database Tables

| Table | Purpose |
|---|---|
| `glpi_plugin_whatsnew_announcements` | Active announcements — one row per audience (`central`, `helpdesk`, `all`) |
| `glpi_plugin_whatsnew_user_dismissals` | Records which users dismissed which content version (by SHA-256 hash) |
| `glpi_plugin_whatsnew_history` | Saves every published announcement for audit/history display (last 10 shown per audience) |

---

## File Structure

```
plugins/whatsnew/
├── setup.php                      # Plugin metadata, hooks, display logic & modal renderer
├── hook.php                       # Install / uninstall / sample data
├── composer.json                  # Plugin manifest
├── inc/
│   ├── announcement.class.php     # All DB logic — fetch, save, hash, dismiss, purge
│   ├── config.class.php           # Menu registration
│   └── profile.class.php         # Profile tab & rights management
├── ajax/
│   └── dismiss.php                # Records user dismissal (CSRF-protected POST)
└── front/
    └── config.php                 # Admin announcement editor page
```

---

## Security Notes

- All database queries use GLPI's parameterised query API — no raw string interpolation
- `profile_type` from POST is validated against a strict whitelist before use
- The `version_hash` submitted to `dismiss.php` is validated against the announcements table before any write — arbitrary hashes are rejected
- Modal content is sanitised through `RichText::getSafeHtml()` on both save and render
- All HTML output is escaped with `htmlspecialchars(ENT_QUOTES, 'UTF-8')`
- CSRF tokens are required on all POST endpoints
- Internal server errors are logged via `Toolbox::logError()` and never exposed to the client
