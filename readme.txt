=== CHIP for Paymattic ===
Contributors: chipasia, wanzulnet
Tags: chip
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Better Payment & Business Solutions. Securely accept payment with CHIP for Paymattic.

== Description ==

This is a CHIP plugin for Paymattic.

CHIP is a payment and business solutions platform that allow you to securely sell your products and get paid via multiple local and international payment methods.

== Screenshots ==
* Fill up the form with Brand ID and Secret Key on Global Configuration.
* Fill up the form with Brand ID and Secret Key on Form-specific Configuration.
* Form editor page.
* Form that have been integrated with CHIP.
* Test mode payment page.
* Confirmation page after successful payment.
* List of successful paid entries.

== Changelog ==

= 1.0.2 - 2025-02-27 =
* New - Updated CSF.
* Fixed - Fixed issue with missing CHIP icon.

== Installation ==

= Minimum Requirements =

* WordPress 6.0 or greater
* PaymatticPro 4.6.0 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "CHIP for Paymattic" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Where is the Brand ID and Secret Key located? =

Brand ID and Secret Key available through our merchant dashboard.

= Do I need to set public key for webhook? =

No.

= Where can I find documentation? =

You can visit our [API documentation](https://docs.chip-in.asia/) for your reference.

= What CHIP API services used in this plugin? =

This plugin rely on CHIP API ([PAYMATTIC_CHIP_ROOT_URL](https://gate.chip-in.asia)) as follows:

  - **/purchases/**
    - This is for accepting payment
  - **/purchases/<id\>**
    - This is for getting payment status from CHIP

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://docs.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)