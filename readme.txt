=== Instant Design Tool ===
Contributors: instantdesigntool
Tags: design software, design tool, woocommerce, print on demand, drop shipping
Requires at least: 5.0
Tested up to: 6.6.1
Stable tag: 3.0.5
Requires PHP: 7.4

Connect WooCommerce to your Instant Design Tool. Enable your customers to create and order their own designs with Instant Design Tool.

== Description ==

Enable your customers to create and order their own designs with the Instant Design Tool. A modern, fast web app in your own corporate identity that's compatible with any device and supports multiple languages.

This plugin allows you to connect WooCommerce to your own Instant Design Tool.

See [www.instantdesigntool.com](https://www.instantdesigntool.com) for more information.


== Installation ==

= Requirements =

* WooCommerce 3.7.0 or greater is recommended
* An Instant Design Tool account (with access to management panel)

This section describes how to install the plugin and get it working.

1. Install the plugin through the WordPress plugins.
2. Use the WooCommerce->Settings->IDT Settings.
3. Copy the connect code from the management panel of your Instant Design Tool.
4. Paste the connect code in the IDT Settings to get started!


**Fields**

= IDT API Key =
The value in this field will be automatically generated and will be sent with every request to the editor to authenticate the request


= IDT Connect code =
The connect code can be found in the managemenent area of your design tool under the tab "Plugins". To unlock the plugins tab, you'll first need to move the tool to a subdomain of your website.

= Use the custom texts for buttons? =
This setting is used to determine if custom button texts should appear on the customer-end of the website. The buttons will appear on the shop page and the product page and have respective options for those pages


= Editable product button text =
This setting will determine the custom text on the shop page buttons for editable products


= Editable product button text on the product page =
This setting will determine the custom text on the product page buttons for editable products


= Use  Print API? =
This is the usage setting for Print API, which can be set to: never, optionally or always


= Print API client_id =
This is the client_id for Print API, which you can find in Print API dashboard


= Print API secret =
This is the secret for Print API, which you can find in Print API dashboard


= Print API environment =
This option is used to determine the environment Print API is working in. If you are using development keys, please specify test.


= Print API status =
This option is used to indicate the status of the Print API. If all is well and specified, this should show connected.

**Print API usage modes**
Print API is our print-on-demand partner. They print it and ship it to your customer with your logo on the box. The Print API field offers three different usage modes:

= Never =
This usage mode will never use the Print API.

= Optionally =
This usage mode will offer the option to forward orders to the Print API.
That means, it will display a meta-box on the order page, and offer you the ability to send all the editable items on this order to the Print API,
but will only do so when you implicitly order the plugin to do so.

= Always =
This option will forward any orders editable items to Print API automatically.
This option is to be used with great caution. Due to the fact that product id's might differ from platform to platform it is advised to test thoroughly before enabling this setting in production.
Product id mismatches will result in an exception which is very hard to circumvent.

**Elementor Pro**
This plugin supports custom buttons for product pages.
First start by creating a template with a button. Go to 'Templates' -> 'Saved templates' -> 'Add new'. Select 'Section'. Here you can add a button, feel free to customize it any way you want! Before the button is read for use, at the Link attribute fill in 'IDT' (without the quotes) and save. Now go back to 'Saved templates' and you will see the newly created button with a shortcode. In the Editor Pro theme builder, instead of adding a button widget, you can add a 'shortcode' widget with the given shortcode.
Note: Make sure 'custom texts' is on 'no', when using Elementor Pro buttons.


== Frequently Asked Questions ==

= I am getting an error in the Design Tool when ordering, what to do? =

There could be a few thing going on. Please check the following.

1. Is the plug-in active?
2. Is your Instant Design Tool connected?
3. Have you selected the product type: 'Design Tool product'?
4. Have you given the product a regular price?
5. Is the visibility of the product set to 'visible'?

If you have checked all of the above an none have worked for you, please contact customer support and provide as much information as possible to help us help you resolve the issue.

= How can I add a button to my product page using Elementor Pro? =

1. Go to 'Templates' -> 'Saved Templates'.
2. Click on 'Add new'.
3. Select 'Section' as the type.
4. Design the button as you whish.
5. Fill in the following at the link attribute: IDT.
6. Save the template.
7. Go back to 'Templates' -> 'Saved Templates'.
8. Copy the shortcode displayed at your newly created button.
9. Now in the Elementor Pro page builder you can add a shortcode widget and paste the button shortcode.

You will now see the button you have just made on the page. 'IDT' will be automatically replaced with the correct Design tool URL.

= Where can I forward an order to Print API? =

Make sure you have a Print API connected in the IDT settings.

Go to that specific order you want to forward. On the right you should see a button to forward the order to Print API.

If you do not see the button you have not connected Print API properly.


== Screenshots ==

1. Instant Design Tool example
2. Instant Design Tool application example
3. Settings fields in WooCommerce
4. How to add a design product in WooCommerce

== Changelog ==

= 3.0.5 =
* Fixed crash when no products are available from the IDT api. And fixed linking uploads after login.

= 3.0.4 =
* Fixed broken uninstall.

= 3.0.2 =
* Automated updating IDT management panel settings for the reworked login implementation.

= 3.0.1 =
* Fixed error when a cart item contains no products.

= 3.0.0 =
* Reworked login implementation. Requires settings changes in the IDT management panel.

= 2.0.2 =
* Overwritable product id has been added for customizable products

= 2.0 =
* Added support for WordPress 6.1
* Fixed bug where people were unable to add the edited product to their cart

= 1.4.0 =
_This update includes substantial changes in some parts of the plugin. We recommend backing up your site before installing the plugin._
* New: Custom logging capabilities.
* New: Instant Design Tool Elementor widget.
* New: stock management for IDT products.
* New: the ability to change the start of the extra price per page.
* Tweak: Order matching between PrintAPI and WooCommerce.
* Fix: Bug regarding the output PDF.

= 1.3.1 =
* Added support for WordPress 5.7
* Fix return passed in WordPress filter value
* Remove redundant logging

= 1.3.0 =
* Resolved product dropdown issues.
* Refactored the way cookies get handled.
* Changed error handling of forwarded orders.

= 1.2.0 =
* Added auto polling the output request when it isn't ready yet.
* Bumped WordPress version to 5.6.
* Fixed type error that could trouble the editing of a product.
* Fixed error where some pdf outputs didn't come through.

= 1.1.0 =
* Added functionality to translate/change the text of the plugin.
* Squashed some bugs.

= 1.0.9 =
* Wordpress version 5.5 minor compatibility issue resolved

= 1.0.8 =
* Prolong session cookie lifetime

= 1.0.7 =
* Fixed an address sync issue

= 1.0.6 =
* Added default values for IDT settings
* New PrintAPI flow (if enabled)

= 1.0.5 =
* Fixed bug when placing an order
* Added option to allow unpaid orders to be forwarded

= 1.0.4 =
* Minor fix for IDT button

= 1.0.3 =
* Fix login url missing parameters
* Added more documentation

= 1.0.2 =
* Ability to place order from the design tool without being logged in. (Requires resupply of the connect code)
* Elementor Pro: Ability to add a custom IDT button
* Fixed an bug when saving 'Simple product' or 'External product' it's still recognized internally as a 'Design tool product'
* Fixed a cookie issue when IDT is not yet synced
* Other minor bug fixes

= 1.0.1 =
* Elementor Pro WooCommerce templates support.
* Added: Ability to retry failed pdf requests.

= 1.0.0 =
* First stable release.

== Upgrade Notice ==

= 1.0.2 =
To get all features of this update please resupply the connect code after updating. Log in your Design Tool management panel and navigate to the 'Plugins' tab to retrieve your connect code.