<?php

/**
 * Coop Shared Media
 *
 * A plugin to list shared text and media from the home network blog, and
 * provide an interface to insert the media, or include the next content by the
 * use of a shortcode.
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\SharedMedia\CoopSharedMedia
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2013-2022 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Coop Shared Media
 * Description:       Central media and pages repository interface
 * Version:           1.1.0
 * Network:           true
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       coop-shared-media
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace BCLibCoop\SharedMedia;

// No Direct Access
defined('ABSPATH') || die('No direct access');
define('SHAREDMEDIA_PLUGIN_FILE', __FILE__);

/**
 * Require Composer autoloader if installed on it's own
 */
if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

add_action('plugins_loaded', function () {
    new CoopSharedMedia();
});
