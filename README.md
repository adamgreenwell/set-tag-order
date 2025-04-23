# Set Tag Order

This plugin adds a Tag Order panel to the WordPress Block Editor that allows arranging tags in a specific order. Tags are displayed in the set order using the built-in WordPress "the_tags()" function or can be called using "the_ordered_post_tags()".

## Description

The Set Tag Order plugin enhances the WordPress tagging system by allowing users to specify a custom display order for post tags in both the Block Editor and Classic Editor. This plugin provides a user-friendly interface for rearranging tags using drag-and-drop functionality, ensuring that tags are displayed in the desired order on the front end of the website.

### Key Features

* **Custom Tag Order**: Easily rearrange tags in the desired order for posts, providing better control over how tags are presented to users.
* **Custom Tag Separator**: Specify a custom character to separate tags in the output, allowing for greater flexibility in how tags are displayed. Users can leave this field empty for no separator.
* **Custom CSS Classes**: Add custom CSS classes to tag links, enabling users to style tags according to their theme's design. This feature allows for separation of multiple classes with spaces.
* **Compatibility**: Works seamlessly with both the Classic Editor and the Block Editor, ensuring a consistent user experience across different editing environments.
* **Debug Mode**: Debug mode can be enabled to log diagnostic information, which is useful for troubleshooting and development purposes.

## Installation

1. In your admin panel, go to Plugins > and click the Add New button.
2. Click Upload Plugin and Choose File, then select the plugin .zip file. Click Install Now.
3. Click Activate to use your new plugin right away.

## Usage

After installation, you can access the settings under Settings > Set Tag Order.

## Changelog

### 1.1.0
* Refactored the plugin to follow WordPress plugin development guidelines.
* Improved sanitization and validation for all input/output variables.

### 1.0.6
* Added improved support for block-based themes like Twenty Twenty-Five
* Fixed issue with tags not displaying in post-terms blocks
* Improved DOM manipulation for more reliable class and separator handling
* Implemented custom block renderer for better tag order control

### 1.0.5
* Improve classic or block editor usage detection
* Retain tag integrity when switching editors

### 1.0.3
* Added support for Classic Editor
* Fixed application of tag classes and separators
* Added explicit debug log option

### 1.0.2 
* Refactor GitHub Updater class to prevent conflict with other plugins

### 1.0.1 
* Update block editor panel presentation with more clear UI elements
* Better initialization before a tag order is set

### 1.0.0 
* Initial release