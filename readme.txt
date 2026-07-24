=== Visitor Sentinel ===
Contributors: byot
Tags: security, firewall, honeypot, bot protection, ban ip
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitors your site's visitors, automatically detects bots and attackers, plants decoy honeypots, and blocks problematic IPs.

== Description ==

Visitor Sentinel is a security and traffic analysis plugin for WordPress. It combines real-time attack detection with an active deception layer, so an attacker is not just noticed — they are lured into giving themselves away.

**Detection**

* Records site visits (IP, page visited, user-agent, device/browser, account type).
* Analyzes every request in real time using multiple heuristics: unusual request rate, user-agent associated with scanning/attack tools, requests to sensitive resources (wp-config.php, .env, .git, etc.), repeated failed logins, credential-stuffing patterns, XML-RPC abuse, invisible honeypot fields on login/comment forms, submission-timing checks, and scanning of non-existent pages (404).
* Calculates a risk score per IP address and permanently blocks it once the score exceeds the configured threshold — sheer browsing volume alone never triggers a block, only genuine attack/bot/spam signals do.
* Blocks are permanent by design and apply everywhere on the site (the full-page cache, if any, is purged automatically on every block). Lifting one requires a signed declaration, kept permanently in History.

**Deception layer (honeypots & honeytokens)**

* A decoy backup file (honeyfile) at a random, unlinked URL — any access to it is conclusive proof of directory scanning.
* A decoy admin username that was never a real account — any login attempt against it is an instant, certain block.
* A decoy REST endpoint that hands out a fake API key — using that key anywhere is what triggers the block, not merely finding it.
* A hidden spam-trap email address planted only where scrapers read markup — if it ever comes back in a submitted form, that visitor harvested this exact site.
* Every one of these bypasses the normal scoring threshold entirely: interacting with any of them is treated as certain malicious intent, not a "maybe."

**Management & visibility**

* Optional email alerts whenever an IP is blocked or escalated, and one-click CSV export of the blocked IPs list.
* A complete control panel: a live dashboard, an auto-refreshing visitor log, a list of blocked IPs with details about the block reason, and options to unblock, extend, or change the block type (temporary/permanent).
* Displays, to logged-in users and optionally guests, a discreet badge showing how many people are on the site right now, updating live in the browser without a page reload.
* Fully responsive admin panel, usable on desktop, tablet, and phone.

All text is translation-ready. The plugin loads no external resources (CDN) and does not enable any third-party tracking. The only optional external call is described in the FAQ below, and it is off by default.

== Installation ==

1. Upload the `visitor-sentinel` folder to the `/wp-content/plugins/` directory, or install directly from the Plugins screen.
2. Activate the plugin from the WordPress "Plugins" menu.
3. Go to the "Visitor Sentinel" menu in the admin panel to configure the detection thresholds and the deception layer.

== Frequently Asked Questions ==

= Does the plugin accidentally block real visitors? =

Thresholds default to conservative values so that regular visitors are not affected, and a block always requires genuine evidence of an attack — never browsing volume alone. Note that every block is permanent and applies to the IP itself with no exceptions, including for logged-in administrators, so add your own trusted IPs to the whitelist in Settings first. If you ever lock yourself out, remove the block from your site's database (the `wp_visise_bans` table).

= What are the honeyfile, honeytoken username, and honeytoken API key? =

They are fake bait, generated automatically per site and never linked or displayed to real visitors: a decoy backup file at a random URL, a decoy admin username, and a decoy API key handed out by a fake internal endpoint. Because no genuine visitor has any reason to ever touch them, any interaction is treated as certain evidence of an attacker, and results in an immediate block. You can see the exact generated values, and turn the whole layer off, in Settings.

= Does this plugin send any data outside my site? =

By default, no. The only optional exception is the country-flag feature: when you explicitly enable it in Settings, each new IP address is sent to the free, third-party service ip-api.com to determine its country. Results are cached locally for 30 days so the same IP is never looked up twice. This feature is off by default and the plugin makes no external requests unless you turn it on.

= What is the "device fingerprinting" option? =

An optional, off-by-default feature (Settings -> Device recognition). It never decides a ban by itself: a block is still only ever applied through the same evidence-based rules described above (a real attack/bot/spam signal, never sheer browsing volume). The fingerprint is only recorded afterwards, on an IP that was already blocked, so that the same browser is still recognized if it later returns from a different IP address. Because it can identify a specific browser, treat it as personal data in your privacy policy, the same as an IP address.

= My site uses Cloudflare or another proxy, what should I do? =

Enable the "Site behind a proxy/CDN" option in Settings so the plugin can correctly identify the visitor's real IP address.

= Is the visitor counter visible to everyone? =

By default, yes — it shows the live "online now" count to guests as well as logged-in members. You can turn off "Show to guests too" in Settings to restrict it to logged-in users only, and choose which role can see it there.

== External services ==

This plugin connects to one third-party service, and only for a single, optional, off-by-default feature:

**IP-to-country lookup (ip-api.com)** — used only if you explicitly enable "Show country flags next to IPs" in Settings. When enabled, and only then, each new IP address seen by the plugin is sent to ip-api.com so it can look up which country it belongs to. Nothing else about the visitor (no page content, no personal data, no credentials) is sent. Results are cached locally for 30 days so the same IP is never looked up twice. If you never enable this setting, the plugin makes no external requests at all.

Service provided by ip-api.com: [Terms of Service](https://ip-api.com/docs/legal) and [Privacy Policy](https://ip-api.com/docs/legal).

== Screenshots ==

1. Dashboard overview: live visitor count, visit trend, top pages and referrers, threat types and device breakdown.
2. Blocked IPs panel: manual blocking, per-IP inspection, and one-click unblock/extend/permanent actions.
3. Settings screen, organized into clear sections for detection, alerts, the visitor counter, and the deception layer.

== Changelog ==

= 2.2.0 =
* New, opt-in "device fingerprinting" option (Settings -> Device recognition, disabled by default): recognizes a browser that was already permanently banned on real evidence if it later returns from a different IP address, complementing the existing device-recognition cookie. It never triggers a ban by itself and is only ever recorded on an IP that was already blocked by the normal evidence-based rules.

= 2.1.1 =
* Removed the .htaccess-based server-level blocking entirely: on some hosts it could corrupt .htaccess parsing, which silently broke both the ban and the site's own styling at once. Blocks are now enforced purely inside WordPress, which cannot affect the server configuration or site layout, and a ban still purges any full-page cache automatically so it takes effect immediately.
* The device-recognition cookie added in 2.1.0 is unaffected: it is still checked inside WordPress, alongside the IP.

= 2.1.0 =
* Blocks now also recognize the specific browser that was blocked, not only its IP address, closing a real gap: a blocked visitor's IP can change afterward (mobile networks reassign IPs, and a dual-stack site can see the same visitor over IPv4 on one request and IPv6 on the next), which previously let them straight back in once their address changed. The block page now also tags the browser with a private, unguessable recognition cookie, enforced at the same web-server level as the IP block (including on cached pages), so the same device stays blocked even after its IP changes.
* This is a second, independent layer alongside the existing IP block, not a replacement for it — both still apply together.

= 2.0.0 =
* Blocks are now always permanent. Temporary blocks have been removed entirely: because blocks are enforced by the web server before WordPress runs, nothing inside WordPress could reliably end one on schedule for a visitor who was already locked out, which left them blocked past their expiry. Removing them makes blocking simple and predictable — a blocked IP stays blocked until you deliberately lift it.
* Lifting a block always goes through the signed declaration, which is kept permanently in History and automatically clears the server-level rule.
* Whitelisting an IP still releases it immediately, so you can always recover from blocking your own address.

= 1.9.5 =
* Fixed: a temporary ban could keep blocking after it expired. Because the server-level rules are enforced before WordPress runs, a blocked visitor could not trigger the cleanup that ends their own ban, leaving them locked out indefinitely. The block page now re-checks the ban and clears the rule itself the moment it expires, so temporary bans end exactly on time and permanent ones stay permanent.
* The server-level block page now shows the same full detail as the in-WordPress one (reason, and when a temporary block lifts).

= 1.9.4 =
* Server-level blocking now uses a plain access-deny rule instead of a cache directive, so a banned IP is refused by the web server itself even when a page is already cached (the previous cache-bypass method was ignored by some LiteSpeed setups). Banned visitors see a styled block page via a custom 403 document. This is a simple access rule, not a redirect, so it never affects normal visitors or the site layout.

= 1.9.3 =
* Bans now apply on cached pages too (e.g. the home page), not just on uncached URLs like wp-login.php. This is done the safe way: banned IPs are simply told to bypass the LiteSpeed page cache, so WordPress runs for them and shows the block page everywhere. It performs no redirect or rewrite, so — unlike the removed 1.9.0/1.9.1 approach — it cannot affect the site's layout for normal visitors. A status line on the Blocked IPs screen shows whether it's active on your host.

= 1.9.2 =
* Removed the experimental server-level (.htaccess) blocking added in 1.9.0/1.9.1: on some hosts it could interfere with the site's own rules and cause pages to render without styling. Bans are enforced inside WordPress and the full-page cache is purged automatically on every block. If you use a page cache and a banned visitor can still see a cached page, clear the cache once after blocking.

= 1.8.2 =
* A banned IP is now blocked everywhere on the site with zero exceptions, including for logged-in administrators. Previously, a logged-in admin's own IP was silently exempt from bans, which could look like the block "wasn't working." If you ban your own IP by mistake, add it to the whitelist in Settings beforehand, or remove it directly from the database if you get locked out.

= 1.8.1 =
* The Blocked IPs list now only shows currently active bans — an expired temporary ban disappears from the list on its own instead of lingering there until the daily cleanup runs.

= 1.8.0 =
* Lifting a permanent block now requires a signed declaration (reason + digital signature) before the IP's ban and activity history are wiped. Every declaration is kept forever in a new "History" screen, with a printable/PDF-exportable record of each one.
* Fixed a detection loophole where two soft, 404-related signals alone (e.g. "not_found" and "not_found_flood") could satisfy the "multiple signal types" requirement and trigger a block without any genuine attack evidence.

= 1.7.5 =
* Fixed misaligned "View details" / "Unblock" buttons on the Blocked IPs table.

= 1.7.4 =
* Fixed the block page so it always reliably covers the full screen and stays scrollable from the top, regardless of the site's own markup.
* Manually created bans no longer auto-escalate to a permanent block just from repeated visits (e.g. while the site owner is testing it) — that safeguard now only applies to bans the automatic detection created. A manual ban only becomes permanent if you explicitly choose to.

= 1.7.3 =
* Fixed: a newly banned IP could still see the site by simply refreshing, if a full-page caching plugin (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache) served an old cached page without WordPress running. A ban now automatically purges the site's full-page cache so it takes effect immediately.

= 1.7.2 =
* Fixed the blocked-visitor page: the icon and title could fail to display depending on the site's markup, because the layout relied on fixed positioning. It now uses normal page flow, so it always displays fully and correctly.

= 1.7.1 =
* Detection now recognizes more HTTP client libraries commonly embedded in custom desktop programs, mobile apps, and API-testing tools (Axios, Postman, Insomnia, WinHTTP, CFNetwork, Alamofire, and others) rather than an actual browser.
* Added a soft signal for requests missing a standard Accept header, typical of scripts/apps making raw requests instead of real browsing.

= 1.7.0 =
* Added DDoS-style traffic-flood detection: an extremely fast burst of requests (well beyond human browsing speed) is now recognized as high-confidence attack evidence on its own.
* Expanded detection to cover common web-shell/malware filenames and typical phishing-kit paths, plus more phishing-style phrasing in spam comment detection.
* Fixed an over-aggressive rule that could temporarily block a genuine visitor for simply hitting more than 10 broken/missing pages in 5 minutes — the threshold is now much higher and this signal alone can no longer trigger a block.
* Redesigned the "you are blocked" page shown to blocked visitors: modern, professional look, with the precise technical reason shown in a clear log format.

= 1.6.2 =
* Country flags are now shown as clean text badges instead of emoji flags, which often failed to render as actual flag images on Windows.
* The Blocked IPs detail view now shows a full origin & network profile for the IP (city, region, country, ISP, organization, ASN, and VPN/proxy or hosting detection) when country flags are enabled in Settings.

= 1.6.1 =
* Fixed: Settings could not be saved, and Blocked IPs actions (unblock, extend, manual block, CSV export) failed with a WordPress error page, due to a leftover internal naming mismatch introduced in 1.6.0.

= 1.6.0 =
* Added a full deception layer: honeyfile (decoy backup file), honeytoken admin username, honeytoken REST API key, and a hidden spam-trap email address. Any interaction with any of them results in an immediate block.
* Redesigned the admin panel with clearly separated cards per settings section and a consistent icon set.

= 1.5.0 =
* Added optional email alerts: get notified whenever an IP is automatically blocked or escalated to a permanent block.
* Added CSV export of the Blocked IPs list.
* The Visitors list now keeps one live, up-to-date entry per visitor instead of accumulating repeated rows.
* Added a Platform column (device and browser), detected locally from the user-agent.

= 1.4.0 =
* Much deeper automatic detection: invisible honeypot fields on the login and comment forms catch bots that blindly fill every field.
* Submission-timing check: login/comment submissions faster than a human can type are flagged.
* Dedicated brute-force detection on the login form, independent of the general rate limit.
* Credential-stuffing detection: many different usernames tried from the same IP in a short window.
* XML-RPC abuse detection (pingback reflection, multicall brute-force).
* Greatly expanded list of known attack/scanner path patterns.

= 1.2.0 =
* The Visitors list now auto-refreshes live, so new visits and the page each visitor is currently on appear automatically.
* Added an optional country flag next to each IP, off by default (see FAQ).

= 1.1.0 =
* Added a real-time "online now" indicator (auto-refreshing, no page reload) on both the front-end badge and the admin dashboard.
* Made the admin panel fully responsive for phones and tablets.
* Smarter detection: browsing volume alone can no longer trigger a block; a real attack/bot/spam signal is now required.
* Added a detailed block page showing the visitor the specific reason and detected activity.
* Added smart dashboard statistics: visit trend, top pages, referrers, threat types, device breakdown.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.6.0 =
Adds an active deception layer (honeyfile, honeytoken login, honeytoken API key, spam-trap email) alongside a redesigned settings screen. Fully backward compatible.
