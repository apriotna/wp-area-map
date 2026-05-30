<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function map10_register_cpt_maps() {

  $labels = [
    'name'               => 'Maps',
    'singular_name'      => 'Map',
    'add_new'            => 'Add Map',
    'add_new_item'       => 'Add New Map',
    'edit_item'          => 'Edit Map',
    'new_item'           => 'New Map',
    'view_item'          => 'View Map',
    'search_items'       => 'Search Maps',
    'not_found'          => 'No maps found',
    'menu_name'          => 'Maps',
  ];

  $args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => true,
    'show_in_menu'       => true,
    'menu_icon'          => 'dashicons-location-alt',
    'supports'           => ['title'],
    'capability_type'    => 'post',
    'rewrite'            => false,
  ];

  register_post_type( 'map10_map', $args );
}
add_action( 'init', 'map10_register_cpt_maps' );
