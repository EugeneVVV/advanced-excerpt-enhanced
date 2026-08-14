<?php
/**
 * Code used when the plugin is removed (not just deactivated but actively deleted through the WordPress Admin).
 */

if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) exit();

// All current settings live in this single row (a serialized array) - see
// the $default_options property in class/advanced-excerpt.php.
delete_option( 'advanced_excerpt' );

// Tracks the last-seen plugin version, used by load_options() to detect an
// upgrade and merge in any newly-added default options - a separate row
// since it has to persist independently of (and outlive resets to) the
// options array above. Introduced by this fork.
delete_option( 'advanced_excerpt_version' );
