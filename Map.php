<?php
/**
 * Plugin Name: WP Area Map
 * Description: Interactive map with polygons and locations.
 * Version: 1.0
 * Author: Apriyanto
 * Text Domain: wp-area-map
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * --------------------------------------------------
 * CONSTANTS
 * --------------------------------------------------
 */
define( 'MAP10_VERSION', '2.0' );
define( 'MAP10_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAP10_URL', plugin_dir_url( __FILE__ ) );

/**
 * --------------------------------------------------
 * INCLUDES
 * --------------------------------------------------
 */
require_once MAP10_DIR . 'includes/cpt-maps.php';
require_once MAP10_DIR . 'includes/cpt-locations.php';
require_once MAP10_DIR . 'includes/taxonomy-categories.php';
require_once MAP10_DIR . 'includes/shortcodes.php';


require_once MAP10_DIR . 'admin/admin-categories.php';

require_once MAP10_DIR . 'admin/metaboxes/map-settings.php';
require_once MAP10_DIR . 'admin/metaboxes/location-fields.php';

/**
 * --------------------------------------------------
 * FRONTEND ASSETS
 * --------------------------------------------------
 */
add_action( 'wp_enqueue_scripts', 'map10_enqueue_frontend_assets' );

function map10_enqueue_frontend_assets() {

  // MapLibre GL JS (replaces Leaflet on frontend)
  wp_enqueue_style(
    'maplibre-css',
    'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css'
  );

  wp_enqueue_style(
    'map10-fonts',
    MAP10_URL . 'public/css/map10-fonts.css',
    [],
    MAP10_VERSION
  );

  wp_enqueue_style(
    'map10-css',
    MAP10_URL . 'public/css/map.css',
    ['map10-fonts'],
    MAP10_VERSION
  );

  wp_enqueue_script(
    'maplibre-js',
    'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js',
    [],
    null,
    true
  );

  wp_enqueue_script(
    'map10-js',
    MAP10_URL . 'public/js/map.js',
    ['maplibre-js'],
    MAP10_VERSION,
    true
  );

  // Pass plugin URL to JS so assets (default pin, etc.) can be referenced
  wp_localize_script( 'map10-js', 'map10_vars', [
    'plugin_url' => MAP10_URL,
  ]);
}

/**
 * --------------------------------------------------
 * ADMIN ASSETS
 * --------------------------------------------------
 */
add_action( 'admin_enqueue_scripts', 'map10_enqueue_admin_assets' );

function map10_enqueue_admin_assets( $hook ) {

  if ( ! in_array( $hook, ['post.php', 'post-new.php'] ) ) return;

  global $post;
  if ( ! $post ) return;

  /**
   * -------------------------------
   * LEAFLET CORE
   * -------------------------------
   */
  wp_enqueue_style(
    'leaflet-css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
  );

  wp_enqueue_script(
    'leaflet-js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    [],
    null,
    true
  );

  /**
   * -------------------------------
   * LOCATION EDITOR MAP
   * -------------------------------
   */
  if ( $post->post_type === 'map10_location' ) {

    // Enqueue WordPress media uploader
    wp_enqueue_media();

    // Pickr color picker (needed for Area Color Override field)
    wp_enqueue_style(
      'pickr-css',
      'https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/nano.min.css',
      [],
      '1.9.1'
    );
    wp_enqueue_script(
      'pickr-js',
      'https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js',
      [],
      '1.9.1',
      true
    );

    // Enqueue Leaflet Draw for polygon drawing
    wp_enqueue_style(
      'leaflet-draw-css',
      'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css'
    );

    wp_enqueue_script(
      'leaflet-draw-js',
      'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js',
      ['leaflet-js'],
      null,
      true
    );

    wp_enqueue_script(
      'map10-location-editor',
      MAP10_URL . 'admin/js/location-editor.js',
      ['leaflet-draw-js'],
      MAP10_VERSION,
      true
    );

wp_enqueue_script(
  'map10-auto-detector',
  MAP10_URL . 'admin/js/auto-location-detector.js',
  ['map10-location-editor'],
  MAP10_VERSION,
  true
);

  }

  /**
   * -------------------------------
   * MAP EDITOR (for Add Map)
   * -------------------------------
   */
  if ( $post->post_type === 'map10_map' ) {

    wp_enqueue_script(
      'map10-map-editor',
      MAP10_URL . 'admin/js/map-editor.js',
      ['leaflet-js'],
      MAP10_VERSION,
      true
    );
  }
}

/**
 * --------------------------------------------------
 * ACTIVATION / DEACTIVATION
 * --------------------------------------------------
 */
register_activation_hook( __FILE__, 'map10_activate' );
register_deactivation_hook( __FILE__, 'map10_deactivate' );

function map10_activate() {
  map10_migrate_location_map_relations();
  flush_rewrite_rules();
}

/**
 * One-time migration: move _map10_map_id from location meta
 * to _map10_selected_locations on each map.
 * Safe to run multiple times (checks if already migrated).
 */
function map10_migrate_location_map_relations() {
  $maps = get_posts([ 'post_type' => 'map10_map', 'numberposts' => -1, 'post_status' => 'any' ]);
  foreach ( $maps as $map ) {
    // Skip if already migrated
    $existing = get_post_meta( $map->ID, '_map10_selected_locations', true );
    if ( ! empty( $existing ) ) continue;

    $old_locations = get_posts([
      'post_type'   => 'map10_location',
      'numberposts' => -1,
      'meta_key'    => '_map10_map_id',
      'meta_value'  => $map->ID,
      'post_status' => 'publish',
      'fields'      => 'ids',
    ]);

    if ( ! empty( $old_locations ) ) {
      update_post_meta( $map->ID, '_map10_selected_locations', $old_locations );
    }
  }
}

function map10_deactivate() {
  flush_rewrite_rules();
}
