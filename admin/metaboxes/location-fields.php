<?php
/**
 * Location Fields Metabox — v2.0
 * Fields: Map, Polygon, Marker Icon, Description, Image, URL,
 *         Border Style/Color/Width, Hide from Dropdown, Hide Pinpoint,
 *         Area Color Override, Custom URL Slug
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function map10_add_location_metabox() {
  add_meta_box(
    'map10_location_editor',
    'Location Details & Map',
    'map10_render_location_metabox',
    'map10_location',
    'normal',
    'default'
  );
}
add_action( 'add_meta_boxes', 'map10_add_location_metabox' );

function map10_render_location_metabox( $post ) {

  $map_id         = get_post_meta( $post->ID, '_map10_map_id',         true );
  $lat            = get_post_meta( $post->ID, '_map10_lat',             true );
  $lng            = get_post_meta( $post->ID, '_map10_lng',             true );
  $polygons       = get_post_meta( $post->ID, '_map10_polygons',        true );
  $desc           = get_post_meta( $post->ID, '_map10_desc',            true );
  $url            = get_post_meta( $post->ID, '_map10_url',             true );
  $url_slug       = get_post_meta( $post->ID, '_map10_url_slug',        true );
  $marker_icon    = get_post_meta( $post->ID, '_map10_marker_icon',     true );
  $location_image = get_post_meta( $post->ID, '_map10_image',           true );

  // Border
  $border_style = get_post_meta( $post->ID, '_map10_border_style', true ) ?: 'none';
  $border_color = get_post_meta( $post->ID, '_map10_border_color', true ) ?: '#000000';
  $border_width = intval( get_post_meta( $post->ID, '_map10_border_width', true ) ?: 2 );

  // Visibility
  $hide_from_dropdown = (bool) get_post_meta( $post->ID, '_map10_hide_from_dropdown', true );
  $hide_pinpoint      = (bool) get_post_meta( $post->ID, '_map10_hide_pinpoint',      true );
  $not_clickable      = (bool) get_post_meta( $post->ID, '_map10_not_clickable',       true );

  // Area color override
  $area_color_override = get_post_meta( $post->ID, '_map10_area_color_override', true );

  $terms             = wp_get_post_terms( $post->ID, 'map10_location_category' );
  $selected_category = ! empty( $terms ) ? $terms[0] : null;

  $map_center_lat = 52.370216; $map_center_lng = 4.895168; $map_zoom = 16;
  if ( $map_id ) {
    $map_center_lat = get_post_meta( $map_id, '_map10_center_lat', true ) ?: $map_center_lat;
    $map_center_lng = get_post_meta( $map_id, '_map10_center_lng', true ) ?: $map_center_lng;
    $map_zoom       = get_post_meta( $map_id, '_map10_zoom',       true ) ?: $map_zoom;
  }

  $maps = get_posts([ 'post_type' => 'map10_map', 'numberposts' => -1 ]);

  wp_nonce_field( 'map10_save_location', 'map10_location_nonce' );
  ?>

  <!-- ===================== MAP SELECTION ===================== -->
  <p>
    <label><strong>Select Map</strong></label><br>
    <select name="map10_map_id" id="map10_map_select">
      <option value="">— Select Map —</option>
      <?php foreach ( $maps as $map ): ?>
        <option value="<?php echo $map->ID; ?>" <?php selected( $map_id, $map->ID ); ?>
          data-lat="<?php echo esc_attr( get_post_meta( $map->ID, '_map10_center_lat', true ) ); ?>"
          data-lng="<?php echo esc_attr( get_post_meta( $map->ID, '_map10_center_lng', true ) ); ?>"
          data-zoom="<?php echo esc_attr( get_post_meta( $map->ID, '_map10_zoom', true ) ); ?>">
          <?php echo esc_html( $map->post_title ); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </p>

  <!-- ===================== ADDRESS SEARCH ===================== -->
  <p>
    <label><strong>Search Address</strong></label><br>
    <input type="text" id="map10_address" style="width:70%;" placeholder="Type address to find location...">
    <button type="button" class="button" id="map10_search">Search</button>
  </p>

  <!-- ===================== MAP EDITOR ===================== -->
  <div id="map10-location-editor"
    style="height:500px;border:1px solid #ddd;margin-bottom:15px;"
    data-lat="<?php echo esc_attr( $lat ?: $map_center_lat ); ?>"
    data-lng="<?php echo esc_attr( $lng ?: $map_center_lng ); ?>"
    data-zoom="<?php echo esc_attr( $map_zoom ); ?>"
    data-polygons='<?php echo esc_attr( $polygons ); ?>'
    data-category-slug="<?php echo $selected_category ? esc_attr( $selected_category->slug ) : ''; ?>"
  ></div>

  <p style="color:#666;font-size:13px;margin-top:-10px;">
    <strong>How to use:</strong><br>
    • Use polygon tool to draw area(s)<br>
    • Yellow marker shows the center point (auto-calculated from polygon)<br>
    • You can draw multiple polygons for one location
  </p>

  <!-- Hidden coordinate/polygon fields -->
  <input type="hidden" id="map10_lat"      name="map10_lat"      value="<?php echo esc_attr( $lat ); ?>">
  <input type="hidden" id="map10_lng"      name="map10_lng"      value="<?php echo esc_attr( $lng ); ?>">
  <input type="hidden" id="map10_polygons" name="map10_polygons" value='<?php echo esc_attr( $polygons ); ?>'>

  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">

  <!-- ===================== VISIBILITY ===================== -->
  <h3 style="margin-bottom:10px;">Visibility Options</h3>

  <p>
    <label>
      <input type="checkbox" name="map10_hide_from_dropdown" value="1"
        <?php checked( $hide_from_dropdown ); ?>>
      <strong>Hide from dropdown menu</strong>
      <span style="color:#666;font-size:12px;margin-left:6px;">Area/polygon will still show on map, but won't appear in the location select list</span>
    </label>
  </p>
  <p>
    <label>
      <input type="checkbox" name="map10_hide_pinpoint" value="1"
        <?php checked( $hide_pinpoint ); ?>>
      <strong>Hide pinpoint marker</strong>
      <span style="color:#666;font-size:12px;margin-left:6px;">Area will still be visible and clickable on map, but no pin marker will be shown</span>
    </label>
  </p>
  <p>
    <label>
      <input type="checkbox" name="map10_not_clickable" value="1"
        <?php checked( $not_clickable ); ?>>
      <strong>Not clickable (background area)</strong>
      <span style="color:#666;font-size:12px;margin-left:6px;">Area stays visible on map but won't intercept clicks — pins inside it remain clickable</span>
    </label>
  </p>

  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">

  <!-- ===================== BORDER STYLE ===================== -->
  <h3 style="margin-bottom:10px;">Area Border</h3>

  <table style="border-collapse:collapse;width:100%;">
    <tr>
      <td style="padding:6px 12px 6px 0;width:140px;"><label><strong>Border Style</strong></label></td>
      <td>
        <select name="map10_border_style" id="map10_border_style">
          <option value="none"   <?php selected( $border_style, 'none' ); ?>>None</option>
          <option value="solid"  <?php selected( $border_style, 'solid' ); ?>>Solid</option>
          <option value="dotted" <?php selected( $border_style, 'dotted' ); ?>>Dotted</option>
        </select>
      </td>
    </tr>
    <tr id="map10_border_color_row" style="<?php echo $border_style === 'none' ? 'display:none;' : ''; ?>">
      <td style="padding:6px 12px 6px 0;"><label><strong>Border Color</strong></label></td>
      <td>
        <input type="color" name="map10_border_color" value="<?php echo esc_attr( $border_color ); ?>"
               style="width:50px;height:30px;padding:2px;border:1px solid #ddd;border-radius:3px;cursor:pointer;">
        <span style="color:#666;font-size:12px;margin-left:8px;"><?php echo esc_html( $border_color ); ?></span>
      </td>
    </tr>
    <tr id="map10_border_width_row" style="<?php echo $border_style === 'none' ? 'display:none;' : ''; ?>">
      <td style="padding:6px 12px 6px 0;"><label><strong>Border Width</strong></label></td>
      <td>
        <input type="number" name="map10_border_width" value="<?php echo esc_attr( $border_width ); ?>"
               min="1" max="20" style="width:60px;"> px
      </td>
    </tr>
  </table>

  <script>
  document.getElementById('map10_border_style').addEventListener('change', function() {
    var show = this.value !== 'none';
    document.getElementById('map10_border_color_row').style.display = show ? '' : 'none';
    document.getElementById('map10_border_width_row').style.display = show ? '' : 'none';
  });
  </script>

  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">

  <!-- ===================== AREA COLOR OVERRIDE ===================== -->
  <h3 style="margin-bottom:6px;">Area Color Override</h3>
  <p style="color:#666;font-size:13px;margin-bottom:10px;">
    Optionally override the category color for <em>this specific location's area</em>.
    Leave empty to use the category color. Accepts rgba(...) or #hex.
  </p>
  <?php map10_render_color_picker( 'map10_area_color_override', $area_color_override ?: 'rgba(232,76,61,1)' ); ?>
  <p style="margin-top:8px;">
    <label>
      <input type="checkbox" name="map10_clear_area_color" value="1"
        <?php checked( empty($area_color_override) ); ?>>
      <span style="color:#666;font-size:12px;">Use category color (clear override)</span>
    </label>
  </p>

  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">

  <!-- ===================== CUSTOM MARKER ICON ===================== -->
  <h3 style="margin-bottom:10px;">Custom Pin Point</h3>
  <p style="color:#666;font-size:13px;margin-bottom:10px;">
    Upload a custom image for this location's marker. If not set, the default yellow marker will be used.
  </p>

  <div style="margin-bottom:15px;">
    <div id="map10_marker_preview" style="margin-bottom:10px;<?php echo $marker_icon ? '' : 'display:none;'; ?>">
      <img src="<?php echo esc_url( $marker_icon ); ?>" style="max-width:100px;max-height:100px;border:2px solid #ddd;border-radius:4px;padding:5px;background:#f9f9f9;">
    </div>
    <input type="hidden" id="map10_marker_icon" name="map10_marker_icon" value="<?php echo esc_url( $marker_icon ); ?>">
    <button type="button" class="button" id="map10_upload_marker_btn">
      <?php echo $marker_icon ? 'Change Marker Icon' : 'Upload Marker Icon'; ?>
    </button>
    <?php if ( $marker_icon ): ?>
      <button type="button" class="button" id="map10_remove_marker_btn" style="margin-left:5px;">Remove Custom Marker</button>
    <?php endif; ?>
    <p style="color:#999;font-size:12px;margin-top:5px;">Recommended: PNG/SVG, max 100×100px</p>
  </div>

  <script>
  jQuery(document).ready(function($) {
    var markerUploader;
    $('#map10_upload_marker_btn').on('click', function(e) {
      e.preventDefault();
      if (markerUploader) { markerUploader.open(); return; }
      markerUploader = wp.media({ title: 'Choose Marker Icon', button: { text: 'Use this image' }, multiple: false });
      markerUploader.on('select', function() {
        var att = markerUploader.state().get('selection').first().toJSON();
        $('#map10_marker_icon').val(att.url);
        $('#map10_marker_preview img').attr('src', att.url);
        $('#map10_marker_preview').show();
        $('#map10_upload_marker_btn').text('Change Marker Icon');
        if (!$('#map10_remove_marker_btn').length) {
          $('#map10_upload_marker_btn').after('<button type="button" class="button" id="map10_remove_marker_btn" style="margin-left:5px;">Remove Custom Marker</button>');
          $('#map10_remove_marker_btn').on('click', function(e) {
            e.preventDefault(); $('#map10_marker_icon').val(''); $('#map10_marker_preview').hide();
            $('#map10_upload_marker_btn').text('Upload Marker Icon'); $(this).remove();
          });
        }
      });
      markerUploader.open();
    });
    $('#map10_remove_marker_btn').on('click', function(e) {
      e.preventDefault(); $('#map10_marker_icon').val(''); $('#map10_marker_preview').hide();
      $('#map10_upload_marker_btn').text('Upload Marker Icon'); $(this).remove();
    });
  });
  </script>

  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">

  <!-- ===================== DESCRIPTION ===================== -->
  <p>
    <label><strong>Description</strong></label><br>
    <textarea name="map10_desc" rows="3" style="width:100%;"><?php echo esc_textarea( $desc ); ?></textarea>
  </p>

  <!-- ===================== INFO BOX IMAGE ===================== -->
  <p>
    <label><strong>Info Box Image</strong></label><br>
    <span style="color:#666;font-size:12px;">Image shown inside the info box (right side, next to description).</span>
  </p>
  <div style="margin-bottom:15px;">
    <div id="map10_image_preview" style="margin-bottom:10px;<?php echo $location_image ? '' : 'display:none;'; ?>">
      <img src="<?php echo esc_url( $location_image ); ?>" style="max-width:200px;max-height:150px;border:2px solid #ddd;border-radius:4px;padding:5px;background:#f9f9f9;object-fit:cover;">
    </div>
    <input type="hidden" id="map10_location_image" name="map10_image" value="<?php echo esc_url( $location_image ); ?>">
    <button type="button" class="button" id="map10_upload_image_btn">
      <?php echo $location_image ? 'Change Image' : 'Upload Image'; ?>
    </button>
    <?php if ( $location_image ): ?>
      <button type="button" class="button" id="map10_remove_image_btn" style="margin-left:5px;">Remove Image</button>
    <?php endif; ?>
  </div>
  <script>
  jQuery(document).ready(function($) {
    var imgUploader;
    $('#map10_upload_image_btn').on('click', function(e) {
      e.preventDefault();
      if (imgUploader) { imgUploader.open(); return; }
      imgUploader = wp.media({ title: 'Choose Info Box Image', button: { text: 'Use this image' }, multiple: false });
      imgUploader.on('select', function() {
        var att = imgUploader.state().get('selection').first().toJSON();
        $('#map10_location_image').val(att.url);
        $('#map10_image_preview img').attr('src', att.url);
        $('#map10_image_preview').show();
        $('#map10_upload_image_btn').text('Change Image');
        if (!$('#map10_remove_image_btn').length) {
          $('#map10_upload_image_btn').after('<button type="button" class="button" id="map10_remove_image_btn" style="margin-left:5px;">Remove Image</button>');
          $('#map10_remove_image_btn').on('click', function(e) {
            e.preventDefault(); $('#map10_location_image').val(''); $('#map10_image_preview').hide();
            $('#map10_upload_image_btn').text('Upload Image'); $(this).remove();
          });
        }
      });
      imgUploader.open();
    });
    $('#map10_remove_image_btn').on('click', function(e) {
      e.preventDefault(); $('#map10_location_image').val(''); $('#map10_image_preview').hide();
      $('#map10_upload_image_btn').text('Upload Image'); $(this).remove();
    });
  });
  </script>

  <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">

  <!-- ===================== URL ===================== -->
  <p>
    <label><strong>URL</strong></label><br>
    <input type="url" name="map10_url" value="<?php echo esc_attr( $url ); ?>" style="width:100%;" placeholder="https://example.com">
  </p>

  <!-- ===================== LINK BUTTON TEXT ===================== -->
  <p>
    <label><strong>Link Button Text</strong></label><br>
    <span style="color:#666;font-size:12px;">Override the button text in the info box for this location. Leave empty to use default: <em>"Boek een ruimte"</em>.</span><br>
    <input type="text" name="map10_link_button_text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_map10_link_button_text', true ) ); ?>"
           style="width:60%;margin-top:4px;" placeholder="e.g. Meer informatie">
  </p>

  <?php
}

/**
 * Save metabox
 */
function map10_save_location_metabox( $post_id ) {
  if ( ! isset( $_POST['map10_location_nonce'] ) ) return;
  if ( ! wp_verify_nonce( $_POST['map10_location_nonce'], 'map10_save_location' ) ) return;
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

  update_post_meta( $post_id, '_map10_map_id',   intval( $_POST['map10_map_id']   ?? 0 ) );
  update_post_meta( $post_id, '_map10_lat',       sanitize_text_field( $_POST['map10_lat']  ?? '' ) );
  update_post_meta( $post_id, '_map10_lng',       sanitize_text_field( $_POST['map10_lng']  ?? '' ) );
  update_post_meta( $post_id, '_map10_polygons',  wp_unslash( $_POST['map10_polygons'] ?? '' ) );
  update_post_meta( $post_id, '_map10_desc',      wp_kses_post( $_POST['map10_desc']  ?? '' ) );
  update_post_meta( $post_id, '_map10_url',       esc_url_raw( $_POST['map10_url']    ?? '' ) );
  update_post_meta( $post_id, '_map10_marker_icon', esc_url_raw( $_POST['map10_marker_icon'] ?? '' ) );
  update_post_meta( $post_id, '_map10_image',     esc_url_raw( $_POST['map10_image']  ?? '' ) );

  // Link button text
  update_post_meta( $post_id, '_map10_link_button_text', sanitize_text_field( $_POST['map10_link_button_text'] ?? '' ) );

  // Border
  $border_style = in_array( $_POST['map10_border_style'] ?? '', ['none','solid','dotted'] )
    ? $_POST['map10_border_style'] : 'none';
  update_post_meta( $post_id, '_map10_border_style', $border_style );
  update_post_meta( $post_id, '_map10_border_color', sanitize_hex_color( $_POST['map10_border_color'] ?? '#000000' ) );
  update_post_meta( $post_id, '_map10_border_width', intval( $_POST['map10_border_width'] ?? 2 ) );

  // Visibility
  update_post_meta( $post_id, '_map10_hide_from_dropdown', isset( $_POST['map10_hide_from_dropdown'] ) ? 1 : 0 );
  update_post_meta( $post_id, '_map10_hide_pinpoint',      isset( $_POST['map10_hide_pinpoint'] )      ? 1 : 0 );
  update_post_meta( $post_id, '_map10_not_clickable',      isset( $_POST['map10_not_clickable'] )      ? 1 : 0 );

  // Area color override — clear if "use category color" checkbox is ticked
  if ( isset( $_POST['map10_clear_area_color'] ) ) {
    update_post_meta( $post_id, '_map10_area_color_override', '' );
  } else {
    $aco = sanitize_text_field( wp_unslash( $_POST['map10_area_color_override'] ?? '' ) );
    if ( $aco && ! preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d.]+\s*)?\)$/', $aco )
              && ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $aco ) ) {
      $aco = '';
    }
    update_post_meta( $post_id, '_map10_area_color_override', $aco );
  }
}
add_action( 'save_post_map10_location', 'map10_save_location_metabox' );
