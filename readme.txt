=== Bug Library ===
Contributors: jackdewey
Donate link: https://ylefebvre.github.io/wordpress-plugins/bug-library/
Tags: bug, issue, tracker, feature, request
Requires at least: 3.0
Tested up to: 6.5.3
Stable tag: 2.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin provides an easy way to incorporate a bug/enhancement tracking system to a WordPress site.

== Description ==

This plugin provides an easy way to incorporate a bug/enhancement tracking system to a WordPress site. By adding a shortcode to a page, users will be able to display a bug list and allow visitors to submit new bugs / enhancements. The plugin will also provide search and sorting capabilities. A captcha and approval mechanism will allow the site admin to avoid spam.

You can try it out in a temporary copy of WordPress [here](https://demo.tastewp.com/bug-library).

* [Changelog](http://wordpress.org/extend/plugins/bug-library/other_notes/)
* [Support Forum](http://wordpress.org/tags/bug-library)

== Installation ==

1. Download the plugin
1. Upload the extracted folder to the /wp-content/plugins/ directory
1. Activate the plugin in the Wordpress Admin
1. To get a basic Bug Library list showing on one of your Wordpress pages, create a new page and type the following text: [bug-library]

There are a number of optional arguments that can be specified with the shortcode. Here they are with examples for each:

[bug-library bugcategorylist='3,4,5'] = List of bug categories to display
[bug-library bugtypeid='4'] = List of bugs from a specific category
[bug-library bugstatusid='5'] = List of bugs that have a specific status
[bug-library bugpriorityid='6'] = List of bugs that have a specific priority

These shortcode options can be combined:

[bug-library bugcategorylist='3,4,5' bugtypeid='4' bugstatusid='5' bugpriorityid='6']

1. Configure the Bug Library General Options section for more control over the plugin functionality.
1. Copy the file single-bug-library-bugs.php from the bug-library plugin directory to your theme directory to display all information related to your bugs. You might have to edit this file a bit and compare it to single.php to get the proper layout to show up on your web site.

== Changelog ==

= 2.1.4 =
* Fixed potential security issue

= 2.1.2 =
* Fixed potential security issue

= 2.1.1 =
* Restricted file types that can be attached to user bug reports to 'bmp', 'txt', 'png', 'jpg', 'pdf', 'jpeg'

= 2.1 =
* Added new function to export links to a CSV file

= 2.0.8 =
* Addressed possible security issue

= 2.0.3 =
* New plugin icon
* Minor bug fixes

= 2.0.2 =
* Fixed issue with resolution date picker not working

= 2.0.1 =
* Single bug template provided with plugin now automatically loads from plugin folder if not found in theme
* Fixed issues with undefined variables in single-bug-library-bugs.php

= 2.0 =
* Added translation support

= 1.5.5 =
* Added new option to specify default product to be selected in new bug submission form

= 1.5.4 =
* Fixed issue where visitors could not report new issues when filtering issues by product

= 1.5.3 =
* Added ID column when showing Products, Status, Types and Priority

= 1.5.2 =
* Fix for display of bug reporter name

= 1.5.1 =
* Selections in drop-down lists are re-selected if submission is not accepted

= 1.5 =
* Added options to add empty option to product and issue list to make sure that users select a product and issue

= 1.4.6 =
* Enhanced quick edit for bugs to be able to easily change product, status, priority and other fields
* Added option to remove bugs from site search results

= 1.4.5 =
* Re-wrote user query code for bug assignment to avoid issues on some installations

= 1.4.4 =
* Fixes to work on sites that are not installed at the root of a URL

= 1.4.3 =
* Fixes to work on sites that are not installed at the root of a URL

= 1.4.2 =
* Fixed issue accessing category menus

= 1.4.1 =
* Added options to be able to hide the product, version number and issue type fields

= 1.4 =
* Modified bug query for shortcode display to avoid displaying bugs that are in trash

= 1.3.9 =
* Re-arranged all menu items under Bugs menu in admin to make it easier to find all related items
* General code cleanup

= 1.3.8 =
* Changed file_get_contents for wp_remote_fopen

= 1.3.6 =
* Updated single item template for twenty-fifteen theme
* Corrected some issues with default options not being created correctly
* Added uninstall function
* Corrected label in admin

= 1.3.5 =
* Corrected PHP code warning

= 1.3.4 =
* Added new option to allow comments to be closed automatically when a user-defined closure status is assigned to a bug

= 1.3.3 =
* Fixed problem with activating file attachments

= 1.3.2 =
* Removed hard-coded image file extension when uploading attachments

= 1.3.1 =
* Corrected PHP warnings

= 1.3 =
* Changed mechanism to display the submit new issues popup

= 1.2.9 =
* Corrected PHP warnings

= 1.2.8 =
* Updated colorbox script to fix problem with black box when submitting new issues in latest version of WordPress

= 1.2.7 =
* Updated jQuery datapicker script to fix problem with latest versions of WordPress

= 1.2.6 =
* Fixed uncaught reference error in javascript code

= 1.2.5 =
* Added field to define default user bug priority in configuration panel
* New user reported issues now have a priority so they can appear in the list

= 1.2.4 =
* Added code to make sure that post data is available when saving custom bug data

= 1.2.3 =
* Added CDATA tags around javascript code
* Removed unnecessary quotes around PHP code to render meta boxes

= 1.2.2 =
* Removed reference to non-existent table in admin menu code

= 1.2.1 =
* Update to ensure compatibility with WordPress 3.3

= 1.2 =
* Added options to shortcode to allow users to specify bug priority, type and status as arguments

= 1.1.2 =
* Fixed issue with status field not display correct entry when editing bugs
* Modified join condition in bug display code to avoid upgrade issues with missing priorities

= 1.1.1 =
* Changed Upload Image option to Upload File. Changed code that displayed image to become link to attached file.
= 1.0.3 =
* Added options to make the reporter name and reporter e-mail required fields in the user issue submission form

= 1.0.2 =
* Corrected variable with bad name

= 1.0.1 =
* Added filters in admin bug list page to filters bugs by type, status and product
* Corrected problem with product, status and type getting deleted if you quick edited a bug

= 1.0 =
* First release of Bug Library

== Frequently Asked Questions ==

None at this time

== Screenshots ==

1. Bug Listing
2. Form to report new issues
