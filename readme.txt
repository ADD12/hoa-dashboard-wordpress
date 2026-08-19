=== HOA Dashboard ===
Contributors: yourorg
Tags: hoa, homeowners association, reserves, dues, payments, 2fa
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later

Member and board dashboard for HOA financial health, dues/autopay, ticketing,
property manager oversight, calendar, and newsletters.

== Description ==

HOA Dashboard gives homeowners a clear, always-current view of their
association's financial health, sourced from audited financial statements
and the reserve study, alongside dues, tickets, board meetings, and
newsletters — with board oversight tools and an auditable trail of
property-manager activity.

Core features:

* Twilio SMS two-factor authentication at login
* Red / Yellow / Green reserve fund health, board/PM-configurable thresholds
* Multi-page reserve account detail (per-account balances vs. targets and
  California minimum-required figures)
* Dues balance with "Pay Now" (card or bank/ACH) and autopay setup;
  processing fees are itemized and passed through per your gateway settings
* Monthly calendar of board meetings with Zoom link or physical location
* Trouble ticket system with ticket numbers, status, and full audit trail
* Board-only ticket oversight (counts, response/close timestamps, escalation)
* Separate, auditable Property Manager edit role — every PM write is logged
* Homeowner performance reviews of the PM firm, visible to the board only
* Automated post-close follow-up email inviting a PM performance review
* Auto-drafted monthly newsletter (financial + ticket summary) that the
  board or PM can extend with property-management news before sending

== Required setup after activation ==

1. Go to *HOA Dashboard > Settings* and enter your Twilio Verify credentials
   and payment gateway keys.
2. Create a page containing the shortcode `[hoa_login]`.
3. Create a page containing the shortcode `[hoa_dashboard]`.
4. Assign the **HOA Board Member** and **Property Manager** roles to the
   appropriate users under Users > All Users. Homeowners default to
   **HOA Member**.
5. Enter your association's reserve accounts under the Reserve Accounts tab
   (Board or Property Manager), sourced from your latest audited financial
   statement / reserve study.

== Important notes ==

* This plugin never stores raw card or bank account numbers. Payment
  tokenization must happen client-side via your gateway's JS SDK (e.g.
  Stripe Elements); only the resulting token reaches the server.
* Threshold percentages and the "CA minimum required" field are entered by
  your board/PM based on your association's reserve study — this plugin
  does not calculate statutory compliance for you. Confirm exact disclosure
  language with your association's CPA/attorney (see Civil Code §5300 and
  §5550–5570 for the general California disclosure framework).
* Autopay charges run via WP-Cron once daily; for reliability, point a real
  system cron at wp-cron.php.

== Changelog ==

= 0.0.1 (Build 001) =
* Initial release: roles/capabilities, Twilio 2FA, reserve health
  R/Y/G dashboard, dues & autopay scaffolding, ticketing with escalation
  and audit trail, calendar, PM audit log, board-only PM reviews, and
  auto-drafted monthly newsletter.
