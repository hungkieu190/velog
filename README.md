=== VeLog — Digital Vehicle Passport ===
Contributors:      mamflow
Tags:              vehicle, repair-shop, passport, maintenance, automotive
Requires at least: 6.4
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        0.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Building the digital identity of every vehicle.

== Description ==

**VeLog** is a WordPress plugin scaffold for a planned **Digital Vehicle Passport** for repair shops.

> The digital passport for every vehicle.

Developed and maintained by [Mamflow](https://mamflow.com).

= Core Features (Planned) =

* **Vehicle Passport** — Complete digital identity for every vehicle.
* **Service Timeline** — Full history of services performed.
* **Maintenance Reminders** — Proactive scheduling for preventive care.
* **Photo Check-in** — Visual documentation of vehicle condition.

= Philosophy =

Every feature is:

1. **Documented first**
2. **Designed second**
3. **Implemented third**

= Requirements =

* WordPress 6.4 or higher
* PHP 8.1 or higher

== Installation ==

1. Upload the `velog` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress admin.
3. This version contains the bootstrap scaffold; vehicle features and the VeLog admin menu are not implemented yet.

== Frequently Asked Questions ==

= Is VeLog free? =

Yes. VeLog is open-source software licensed under GPL-2.0-or-later.

= Where can I get support? =

Visit [mamflow.com/support](https://mamflow.com/support) for documentation and support.

== Changelog ==

= 0.1.0 =
* Initial release — project scaffold and architecture.

== Upgrade Notice ==

= 0.1.0 =
Initial release.

== Development workflow ==

Read [AGENTS.md](AGENTS.md) and [the workflow index](ai-document/README.md). Use Node 24, `npm ci`, and `composer install`. Run `npm run progress` for task status, `npm run dev` for a development build, `npm run production` for production assets, and `npm run release` for a local installable package. See [build and release](ai-document/build-and-release.md) for prerequisites and acceptance limits.
