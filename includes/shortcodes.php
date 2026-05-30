<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared helper: build location & category data for a given map ID.
 */
function map10_build_map_payload( $map_id ) {
  $lat  = (float) get_post_meta( $map_id, '_map10_center_lat', true ) ?: 52.370216;
  $lng  = (float) get_post_meta( $map_id, '_map10_center_lng', true ) ?: 4.895168;
  $zoom = (int)   get_post_meta( $map_id, '_map10_zoom',       true ) ?: 13;

  // --- LOCATIONS ---
  // New method: use selected_locations stored on the map
  $selected_location_ids = get_post_meta( $map_id, '_map10_selected_locations', true );

  if ( ! empty( $selected_location_ids ) && is_array( $selected_location_ids ) ) {
    // New method: query by selected IDs
    $locations = get_posts([
      'post_type'   => 'map10_location',
      'numberposts' => -1,
      'post__in'    => $selected_location_ids,
      'post_status' => 'publish',
      'orderby'     => 'title',
      'order'       => 'ASC',
    ]);
  } else {
    // Backward compatibility: fall back to old _map10_map_id method
    $locations = get_posts([
      'post_type'   => 'map10_location',
      'numberposts' => -1,
      'meta_key'    => '_map10_map_id',
      'meta_value'  => $map_id,
      'post_status' => 'publish',
      'orderby'     => 'title',
      'order'       => 'ASC',
    ]);
  }

  // --- CATEGORIES ---
  // New method: only show categories selected for this map
  $selected_category_ids = get_post_meta( $map_id, '_map10_selected_categories', true );

  if ( ! empty( $selected_category_ids ) && is_array( $selected_category_ids ) ) {
    $categories = get_terms([
      'taxonomy'   => 'map10_location_category',
      'hide_empty' => false,
      'include'    => $selected_category_ids,
    ]);
  } else {
    // Backward compatibility: show all categories
    $categories = get_terms([ 'taxonomy' => 'map10_location_category', 'hide_empty' => false ]);
  }

  $category_data = [];
  foreach ( $categories as $cat ) {
    $category_data[] = [
      'id'    => $cat->term_id,
      'name'  => $cat->name,
      'slug'  => $cat->slug,
      'color' => get_term_meta( $cat->term_id, 'map10_category_color', true ) ?: 'rgba(232,76,61,1)',
      'icon'  => get_term_meta( $cat->term_id, 'map10_category_icon',  true ) ?: '',
      'order' => ( get_term_meta( $cat->term_id, 'map10_layer_order', true ) !== '' )
                   ? intval( get_term_meta( $cat->term_id, 'map10_layer_order', true ) ) : 10,
    ];
  }

  $location_data = [];
  foreach ( $locations as $loc ) {
    $terms     = wp_get_post_terms( $loc->ID, 'map10_location_category' );
    $cat_ids   = array_map( function($t){ return $t->term_id; }, $terms );
    $cat_slugs = array_map( function($t){ return $t->slug; },    $terms );

    $location_data[] = [
      'id'                 => $loc->ID,
      'title'              => $loc->post_title,
      'lat'                => (float) get_post_meta( $loc->ID, '_map10_lat',  true ),
      'lng'                => (float) get_post_meta( $loc->ID, '_map10_lng',  true ),
      'polygons'           => get_post_meta( $loc->ID, '_map10_polygons', true ),
      'desc'               => get_post_meta( $loc->ID, '_map10_desc',    true ),
      'url'                => get_post_meta( $loc->ID, '_map10_url',     true ),
      'url_slug'           => sanitize_title( get_post_meta( $loc->ID, '_map10_url_slug', true ) ),
      'categories'         => $cat_ids,
      'category_slugs'     => $cat_slugs,
      'category'           => ! empty( $cat_ids )   ? $cat_ids[0]   : 0,
      'slug'               => ! empty( $cat_slugs ) ? $cat_slugs[0] : '',
      'marker_icon'        => get_post_meta( $loc->ID, '_map10_marker_icon',       true ),
      'image'              => get_post_meta( $loc->ID, '_map10_image',              true ),
      'border_style'       => get_post_meta( $loc->ID, '_map10_border_style',      true ) ?: 'none',
      'border_color'       => get_post_meta( $loc->ID, '_map10_border_color',      true ) ?: '#000000',
      'border_width'       => intval( get_post_meta( $loc->ID, '_map10_border_width', true ) ?: 2 ),
      'hide_from_dropdown' => (bool) get_post_meta( $loc->ID, '_map10_hide_from_dropdown', true ),
      'hide_pinpoint'      => (bool) get_post_meta( $loc->ID, '_map10_hide_pinpoint',      true ),
      'not_clickable'      => (bool) get_post_meta( $loc->ID, '_map10_not_clickable',       true ),
      'area_color_override'=> get_post_meta( $loc->ID, '_map10_area_color_override', true ),
      'link_button_text'   => get_post_meta( $loc->ID, '_map10_link_button_text', true ),
    ];
  }

  return compact( 'lat', 'lng', 'zoom', 'category_data', 'location_data' );
}


/**
 * Shortcode: [map10 id="123"]
 */
function map10_render_map_shortcode( $atts ) {
  $atts   = shortcode_atts([ 'id' => '', 'height' => '600px' ], $atts );
  $map_id = intval( $atts['id'] );
  if ( ! $map_id ) return '';

  $d = map10_build_map_payload( $map_id );
  extract( $d );

  ob_start();
  ?>
  <div class="map10-wrapper" id="map10-wrapper-<?php echo esc_attr( $map_id ); ?>">

    <div class="map10-top-controls">
      <div class="map10-category-filters">
        <?php foreach ( $category_data as $cat ): ?>
          <button
            class="map10-category-btn"
            data-category-id="<?php echo esc_attr( $cat['id'] ); ?>"
            data-category-slug="<?php echo esc_attr( $cat['slug'] ); ?>"
            data-map-id="<?php echo esc_attr( $map_id ); ?>"
            style="background-color:<?php echo esc_attr( $cat['color'] ); ?>">
            <?php echo esc_html( $cat['name'] ); ?>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="map10-location-dropdown">
        <select id="map10-location-select-<?php echo esc_attr( $map_id ); ?>">
          <option value="">Kies locatie</option>
          <?php foreach ( $location_data as $loc ):
            if ( $loc['hide_from_dropdown'] ) continue;
          ?>
            <option
              value="<?php echo esc_attr( $loc['id'] ); ?>"
              data-categories="<?php echo esc_attr( implode( ',', $loc['categories'] ) ); ?>">
              <?php echo esc_html( $loc['title'] ); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="map10-container" id="map10-<?php echo esc_attr( $map_id ); ?>"
         style="height:<?php echo esc_attr( $atts['height'] ); ?>"></div>

    <div class="map10-info-box" id="map10-info-box-<?php echo esc_attr( $map_id ); ?>">
      <div class="map10-info-header">
        <h3 class="map10-info-title"></h3>
        <div class="map10-header-category-icons"></div>
        <button class="map10-info-close" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20px" height="20px" fill="black"><path d="M21.88 16.22L16.22 21.88 5.3 10.96 10.69 5.03zM29.66 24L42.685 10.975 37.32 5.02 5.307 37.033 10.686 42.974 24 29.66 37.314 42.974 42.693 37.033z"/></svg>
        </button>
      </div>
      <div class="map10-info-body">
        <div class="map10-info-content"></div>
        <img class="map10-info-image" src="" alt="" style="display:none;">
      </div>
      <div class="map10-info-footer">
        <a href="#" class="map10-info-link" target="_blank">Boek een ruimte</a>
      </div>
    </div>

    <div class="map10-compass">
      <img src="<?php echo esc_url( MAP10_URL . 'public/images/compass.png' ); ?>" alt="Compass">
    </div>
  </div>

  <script>
    window.map10Data = window.map10Data || {};
    window.map10Data[<?php echo (int) $map_id; ?>] = {
      lat:        <?php echo $lat; ?>,
      lng:        <?php echo $lng; ?>,
      zoom:       <?php echo $zoom; ?>,
      categories: <?php echo wp_json_encode( $category_data ); ?>,
      locations:  <?php echo wp_json_encode( $location_data ); ?>
    };
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode( 'map10', 'map10_render_map_shortcode' );


/**
 * Iframe embed
 */
function map10_handle_embed_request() {
  if ( ! isset( $_GET['map10_embed'] ) ) return;
  $map_id = intval( $_GET['map10_embed'] );
  $height = isset( $_GET['height'] ) ? intval( $_GET['height'] ) : 600;
  if ( ! $map_id ) wp_die( 'Invalid map ID' );

  $d = map10_build_map_payload( $map_id );
  extract( $d );
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Embed</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css">
    <link rel="stylesheet" href="<?php echo MAP10_URL; ?>public/css/map10-fonts.css">
    <link rel="stylesheet" href="<?php echo MAP10_URL; ?>public/css/map.css">
    <style>
      *{margin:0;padding:0;box-sizing:border-box}
      html,body{width:100%;height:100%;overflow:hidden}
      .map10-wrapper{width:100%!important;height:100%!important;position:relative}
      .map10-container{width:100%!important;height:100%!important;border-radius:0!important;box-shadow:none!important}
    </style>
  </head>
  <body>
    <div class="map10-wrapper" id="map10-wrapper-<?php echo $map_id; ?>">
      <div class="map10-top-controls">
        <div class="map10-category-filters">
          <?php foreach ( $category_data as $cat ): ?>
            <button class="map10-category-btn"
              data-category-id="<?php echo esc_attr($cat['id']); ?>"
              data-category-slug="<?php echo esc_attr($cat['slug']); ?>"
              data-map-id="<?php echo esc_attr($map_id); ?>"
              style="background-color:<?php echo esc_attr($cat['color']); ?>">
              <?php echo esc_html($cat['name']); ?>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="map10-location-dropdown">
          <select id="map10-location-select-<?php echo $map_id; ?>">
            <option value="">Kies locatie</option>
            <?php foreach ( $location_data as $loc ):
              if ( $loc['hide_from_dropdown'] ) continue; ?>
              <option value="<?php echo esc_attr($loc['id']); ?>"
                      data-categories="<?php echo esc_attr(implode(',',$loc['categories'])); ?>">
                <?php echo esc_html($loc['title']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="map10-container" id="map10-<?php echo $map_id; ?>"></div>
      <div class="map10-info-box" id="map10-info-box-<?php echo $map_id; ?>">
        <div class="map10-info-header">
          <h3 class="map10-info-title"></h3>
          <div class="map10-header-category-icons"></div>
          <button class="map10-info-close" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20px" height="20px" fill="black"><path d="M21.88 16.22L16.22 21.88 5.3 10.96 10.69 5.03zM29.66 24L42.685 10.975 37.32 5.02 5.307 37.033 10.686 42.974 24 29.66 37.314 42.974 42.693 37.033z"/></svg>
          </button>
        </div>
        <div class="map10-info-body">
          <div class="map10-info-content"></div>
          <img class="map10-info-image" src="" alt="" style="display:none;">
        </div>
        <div class="map10-info-footer">
          <a href="#" class="map10-info-link" target="_blank">Boek een ruimte</a>
        </div>
      </div>
      <div class="map10-compass">
        <img src="<?php echo esc_url(MAP10_URL.'public/images/compass.png'); ?>" alt="Compass">
      </div>
    </div>
    <script>
      window.map10Data = window.map10Data || {};
      window.map10Data[<?php echo $map_id; ?>] = {
        lat:        <?php echo $lat; ?>,
        lng:        <?php echo $lng; ?>,
        zoom:       <?php echo $zoom; ?>,
        categories: <?php echo wp_json_encode($category_data); ?>,
        locations:  <?php echo wp_json_encode($location_data); ?>
      };
      var map10_vars = { plugin_url: '<?php echo esc_js(MAP10_URL); ?>' };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
    <script src="<?php echo MAP10_URL; ?>public/js/map.js"></script>
  </body>
  </html>
  <?php
  exit;
}
add_action( 'template_redirect', 'map10_handle_embed_request' );
