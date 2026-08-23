=== Set Tag Order ===
Contributors: adamgreenwell
Tags: taxonomy, post tags, block editor, classic editor
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.2.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows a user to set a specific post tag order in either the classic or block editor.

== Description ==
The Set Tag Order plugin enhances the WordPress tagging system by allowing users to specify a custom display order for post tags in both the Block Editor and Classic Editor. This plugin provides a user-friendly interface for rearranging tags using drag-and-drop functionality, ensuring that tags are displayed in the desired order on the front end of the website.

= Key Features =

* **Custom Tag Order**: Easily rearrange tags in the desired order for posts, providing better control over how tags are presented to users.
* **Custom Tag Separator**: Specify a custom character to separate tags in the output, allowing for greater flexibility in how tags are displayed. Users can leave this field empty for no separator.
* **Custom CSS Classes**: Add custom CSS classes to tag links, enabling users to style tags according to their theme's design. This feature allows for separation of multiple classes with spaces.
* **Compatibility**: Works seamlessly with both the Classic Editor and the Block Editor, ensuring a consistent user experience across different editing environments.
* **Debug Mode**: Debug mode can be enabled to log diagnostic information, which is useful for troubleshooting and development purposes.

== Installation ==

1. In your admin panel, go to Plugins > and click the Add New button.
2. Click Upload Plugin and Choose File, then select the plugin .zip file. Click Install Now.
3. Click Activate to use your new plugin right away.

== Usage ==

After installation, you can access the settings under Settings > Set Tag Order.

== Changelog ==

= 1.2.1 =
* Run the settagord_ordered_tags filter exactly once per render, and on posts that have no saved order. Callbacks previously never saw unordered posts, and ran twice on ordered ones.

= 1.2.0 =
* Add WordPress 7.1 compatibility, including the always-iframed editor and the jQuery UI 1.14.2 update.
* Stop replacing the core/post-terms block renderer. Tag order and CSS classes are now applied before the block renders, so the block keeps its own wrapper markup, alignment, link colour, block supports, and prefix and suffix text.
* Apply custom tag CSS classes without rebuilding the link, preserving rel="tag" and attributes added by other plugins.
* Fix tag order synchronization when loading a post in the Classic Editor, which never ran because it required a nonce WordPress does not issue.
* Replace the custom tag separator only between tags, no longer inside tag names or URLs.
* Load the separator stylesheet from a plugin-owned handle so it still applies on themes that do not load the core block styles.
* Reduce front-end overhead: debug logging no longer formats messages when debug mode is off, and the supported post type lookup is cached per request.
* Add accessible labels to the Block Editor reorder buttons and translate the panel strings.
* Raise the minimum WordPress version to 6.3, which the plugin already required in practice.
* Remove a REST API filter that could never run, and an unused separator filter for a hook that does not exist in WordPress.
* Add keyboard reordering to the Classic Editor tag box. Reordering was previously drag-only, leaving no way to change tag order without a mouse.
* Add a Sort A-Z control to both editors.
* Announce reordering, adding, and removing to screen readers.
* Translate the plugin. Settings, the tag box, and the editor panel were previously hardcoded English despite the plugin declaring a text domain; a translation template now ships in languages/.
* Add a Settings link to the plugin's row on the Plugins screen.
* Add uninstall handling so deleting the plugin removes its options and tag order metadata, across all sites on multisite.
* Add settagord_ordered_tags, settagord_separator, and settagord_link_classes filters for theme and plugin authors.
* Follow the editor colour scheme in both editors instead of using fixed greys.
* Validate the Tag CSS Class setting as CSS class names rather than free text.

= 1.1.3 =
* Add Classic Editor support for creating new tags from the custom tag box.
* Improve tag order synchronization when tags are added or removed.
* Preserve tag order metadata saved by older plugin versions.
* Restore legacy template helpers for existing theme integrations.
* Add PHPUnit and GitHub Actions coverage for WordPress 7.0 compatibility.

= 1.1.2 =
* Fix conflict with core/post-terms default block

= 1.1.1 =
* Fixed JavaScript enqueueing to use WordPress standards
* Refactor function names to prevent conflicts with other plugins
* Remove restricted verbs from function names

= 1.1.0 =
* Refactored the plugin to follow WordPress plugin development guidelines.
* Improved sanitization and validation for all input/output variables.

= 1.0.6 =
* Added improved support for block-based themes like Twenty Twenty-Five
* Fixed issue with tags not displaying in post-terms blocks
* Improved DOM manipulation for more reliable class and separator handling
* Implemented custom block renderer for better tag order control

= 1.0.5 =
* Improve classic or block editor usage detection
* Retain tag integrity when switching editors

= 1.0.3 =
* Added support for Classic Editor
* Fixed application of tag classes and separators
* Added explicit debug log option

= 1.0.2 =
* Refactor GitHub Updater class to prevent conflict with other plugins

= 1.0.1 =
* Update block editor panel presentation with more clear UI elements
* Better initialization before a tag order is set

= 1.0.0 =
* Initial release
