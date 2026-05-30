<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function map10_register_cpt_locations() {

  $labels = [
    'name'          => 'Locations',
    'singular_name' => 'Location',
    'add_new_item'  => 'Add Location',
    'edit_item'     => 'Edit Location',
    'menu_name'     => 'Locations',
  ];

  $args = [
    'labels'        => $labels,
    'public'        => false,
    'show_ui'       => true,
    'show_in_menu'  => 'edit.php?post_type=map10_map',
    'supports'      => ['title'],
    'rewrite'       => false,
  ];

  register_post_type( 'map10_location', $args );
}
add_action( 'init', 'map10_register_cpt_locations' );