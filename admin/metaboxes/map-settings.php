<?php
/**
 * Map Settings Metabox
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register metabox
 */
function map10_add_map_settings_metabox() {

  add_meta_box(
    'map10_map_settings',
    'Map Settings',
    'map10_render_map_settings_metabox',
    'map10_map',
    'normal',
    'default'
  );

}
add_action( 'add_meta_boxes', 'map10_add_map_settings_metabox' );

/**
 * Render metabox fields
 */
function map10_render_map_settings_metabox( $post ) {

  $center_lat = get_post_meta( $post->ID, '_map10_center_lat', true );
  $center_lng = get_post_meta( $post->ID, '_map10_center_lng', true );
  $zoom       = get_post_meta( $post->ID, '_map10_zoom', true );

  // Default Amsterdam if empty
  $center_lat = $center_lat ?: '52.370216';
  $center_lng = $center_lng ?: '4.895168';
  $zoom = $zoom ?: 13;

  // Selected locations & categories for this map
  $selected_locations   = get_post_meta( $post->ID, '_map10_selected_locations',   true ) ?: [];
  $selected_categories  = get_post_meta( $post->ID, '_map10_selected_categories',  true ) ?: [];

  // All available locations & categories
  $all_locations = get_posts([
    'post_type'   => 'map10_location',
    'numberposts' => -1,
    'post_status' => 'publish',
    'orderby'     => 'title',
    'order'       => 'ASC',
  ]);
  $all_categories = get_terms([ 'taxonomy' => 'map10_location_category', 'hide_empty' => false ]);
  usort( $all_categories, function($a, $b) { return strcmp( $a->name, $b->name ); } );

  wp_nonce_field( 'map10_save_map_settings', 'map10_map_settings_nonce' );
  ?>

  <p>
    <label for="map10_address"><strong>Search Address</strong></label><br>
    <input
      type="text"
      id="map10_address"
      style="width:70%;"
      placeholder="Type address to center map..."
    >
    <button type="button" class="button" id="map10_search_btn">🔍 Search</button>
  </p>

  <div
    id="map10-map-editor"
    style="height:400px;border:1px solid #ddd;margin:10px 0;"
    data-lat="<?php echo esc_attr( $center_lat ); ?>"
    data-lng="<?php echo esc_attr( $center_lng ); ?>"
    data-zoom="<?php echo esc_attr( $zoom ); ?>"
  ></div>

  <p style="color: #666; font-size: 13px;">
    ℹ️ Use the search box to find a location. The map will automatically center and zoom. Current center and zoom will be saved when you publish/update this map.
  </p>

  <!-- Hidden fields for lat/lng/zoom -->
  <input type="hidden" id="map10_center_lat" name="map10_center_lat" value="<?php echo esc_attr( $center_lat ); ?>">
  <input type="hidden" id="map10_center_lng" name="map10_center_lng" value="<?php echo esc_attr( $center_lng ); ?>">
  <input type="hidden" id="map10_zoom" name="map10_zoom" value="<?php echo esc_attr( $zoom ); ?>">

  <!-- ===================== CATEGORIES SELECTOR ===================== -->
  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">
  <h3 style="margin-bottom:6px;">Categories on this Map</h3>
  <p style="color:#666;font-size:13px;margin-bottom:10px;">
    Select which categories should be available on this map. Only selected categories will show as filter buttons.
  </p>
  <div style="columns:2;gap:16px;max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:10px;background:#fafafa;">
    <?php if ( empty( $all_categories ) ): ?>
      <p style="color:#999;font-size:13px;">No categories found.</p>
    <?php else: ?>
      <?php foreach ( $all_categories as $cat ): ?>
        <label style="display:block;margin-bottom:6px;font-size:13px;cursor:pointer;">
          <input type="checkbox"
                 name="map10_selected_categories[]"
                 value="<?php echo esc_attr( $cat->term_id ); ?>"
                 <?php checked( in_array( $cat->term_id, (array) $selected_categories ) ); ?>>
          <?php echo esc_html( $cat->name ); ?>
        </label>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ===================== LOCATIONS SELECTOR ===================== -->
  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">
  <h3 style="margin-bottom:6px;">Locations on this Map</h3>
  <p style="color:#666;font-size:13px;margin-bottom:10px;">
    Select which locations should appear on this map. One location can be used across multiple maps.
  </p>
  <p style="margin-bottom:8px;">
    <button type="button" class="button button-small" id="map10_select_all_locs">Select All</button>
    <button type="button" class="button button-small" id="map10_deselect_all_locs" style="margin-left:4px;">Deselect All</button>
  </p>
  <div id="map10_locations_list" style="max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:10px;background:#fafafa;">
    <?php if ( empty( $all_locations ) ): ?>
      <p style="color:#999;font-size:13px;">No locations found.</p>
    <?php else: ?>
      <?php foreach ( $all_locations as $loc ):
        $loc_cats = wp_get_post_terms( $loc->ID, 'map10_location_category' );
        $cat_names = array_map( function($t){ return $t->name; }, $loc_cats );
      ?>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:13px;cursor:pointer;">
          <input type="checkbox"
                 name="map10_selected_locations[]"
                 value="<?php echo esc_attr( $loc->ID ); ?>"
                 <?php checked( in_array( $loc->ID, (array) $selected_locations ) ); ?>>
          <span>
            <?php echo esc_html( $loc->post_title ); ?>
            <?php if ( ! empty( $cat_names ) ): ?>
              <span style="color:#999;font-size:11px;">(<?php echo esc_html( implode( ', ', $cat_names ) ); ?>)</span>
            <?php endif; ?>
          </span>
        </label>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <script>
  jQuery(document).ready(function($) {
    $('#map10_select_all_locs').on('click', function() {
      $('#map10_locations_list input[type=checkbox]').prop('checked', true);
    });
    $('#map10_deselect_all_locs').on('click', function() {
      $('#map10_locations_list input[type=checkbox]').prop('checked', false);
    });
  });
  </script>

  <?php if ( $post->post_status === 'publish' ): ?>
  
  <!-- Embed Code Section -->
  <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
  
  <h3 style="margin-bottom: 10px;">Embed Map</h3>
  <p style="color: #666; font-size: 13px; margin-bottom: 10px;">
    Copy the code below to embed this map on any website:
  </p>
  
  <div style="margin-bottom: 15px;">
    <label><strong>Embed Height:</strong></label><br>
    <input type="number" id="map10_embed_height" value="600" min="300" max="1200" style="width: 100px;"> px
  </div>
  
  <textarea 
    id="map10_embed_code" 
    readonly 
    style="width: 100%; height: 120px; font-family: monospace; font-size: 12px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;"
  ><?php echo esc_textarea( map10_generate_embed_code( $post->ID, 600 ) ); ?></textarea>
  
  <button type="button" class="button button-primary" id="map10_copy_embed" style="margin-top: 10px;">
    Copy Embed Code
  </button>
  <span id="map10_copy_status" style="margin-left: 10px; color: green; display: none;">✓ Copied!</span>

  <script>
  jQuery(document).ready(function($) {
    // Update embed code when height changes
    $('#map10_embed_height').on('input', function() {
      var height = $(this).val();
      var mapId = <?php echo (int) $post->ID; ?>;
      var siteUrl = '<?php echo esc_js( home_url() ); ?>';
      var embedUrl = siteUrl + '/?map10_embed=' + mapId + '&height=' + height;
      var iframeCode = '<iframe src="' + embedUrl + '" width="100%" height="' + height + '" style="border:none;" allowfullscreen></iframe>';
      $('#map10_embed_code').val(iframeCode);
    });

    // Copy embed code to clipboard
    $('#map10_copy_embed').on('click', function() {
      var embedCode = $('#map10_embed_code');
      embedCode.select();
      document.execCommand('copy');
      
      $('#map10_copy_status').fadeIn().delay(2000).fadeOut();
    });
  });
  </script>
  
  <?php endif; ?>

  <?php
}

/**
 * Save metabox data
 */
function map10_save_map_settings( $post_id ) {

  if ( ! isset( $_POST['map10_map_settings_nonce'] ) ) return;
  if ( ! wp_verify_nonce( $_POST['map10_map_settings_nonce'], 'map10_save_map_settings' ) ) return;

  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

  if ( isset( $_POST['map10_center_lat'] ) ) {
    update_post_meta(
      $post_id,
      '_map10_center_lat',
      sanitize_text_field( $_POST['map10_center_lat'] )
    );
  }

  if ( isset( $_POST['map10_center_lng'] ) ) {
    update_post_meta(
      $post_id,
      '_map10_center_lng',
      sanitize_text_field( $_POST['map10_center_lng'] )
    );
  }

  if ( isset( $_POST['map10_zoom'] ) ) {
    update_post_meta(
      $post_id,
      '_map10_zoom',
      intval( $_POST['map10_zoom'] )
    );
  }

  // Selected locations for this map
  $selected_locations = isset( $_POST['map10_selected_locations'] )
    ? array_map( 'intval', (array) $_POST['map10_selected_locations'] )
    : [];
  update_post_meta( $post_id, '_map10_selected_locations', $selected_locations );

  // Selected categories for this map
  $selected_categories = isset( $_POST['map10_selected_categories'] )
    ? array_map( 'intval', (array) $_POST['map10_selected_categories'] )
    : [];
  update_post_meta( $post_id, '_map10_selected_categories', $selected_categories );
}
add_action( 'save_post_map10_map', 'map10_save_map_settings' );

/**
 * Generate embed code for map
 */
function map10_generate_embed_code( $map_id, $height = 600 ) {
  $embed_url = home_url( '/?map10_embed=' . $map_id . '&height=' . $height );
  $iframe_code = '<iframe src="' . esc_url( $embed_url ) . '" width="100%" height="' . (int) $height . '" style="border:none;" allowfullscreen></iframe>';
  return $iframe_code;
}
