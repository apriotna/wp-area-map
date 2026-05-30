<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register Location Category Taxonomy
 */
function map10_register_location_category() {

  $labels = [
    'name'              => 'Categories',
    'singular_name'     => 'Category',
    'search_items'      => 'Search Categories',
    'all_items'         => 'All Categories',
    'parent_item'       => 'Parent Category',
    'parent_item_colon' => 'Parent Category:',
    'edit_item'         => 'Edit Category',
    'update_item'       => 'Update Category',
    'add_new_item'      => 'Add New Category',
    'new_item_name'     => 'New Category Name',
    'menu_name'         => 'Categories',
  ];

  $args = [
    'hierarchical'      => true,
    'labels'            => $labels,
    'show_ui'           => true,
    'show_admin_column' => true,
    'query_var'         => true,
    'rewrite'           => ['slug' => 'location-category'],
    'show_in_menu'      => true,
    'meta_box_cb'       => 'post_categories_meta_box',
  ];

  register_taxonomy( 'map10_location_category', ['map10_location', 'map10_area'], $args );
}
add_action( 'init', 'map10_register_location_category' );

/**
 * Enqueue Pickr color picker on taxonomy pages
 */
function map10_enqueue_pickr( $hook ) {
  if ( ! in_array( $hook, ['edit-tags.php', 'term.php'] ) ) return;
  if ( ! isset( $_GET['taxonomy'] ) || $_GET['taxonomy'] !== 'map10_location_category' ) return;

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
  wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'map10_enqueue_pickr' );

/**
 * Shared: render RGBA color picker field
 * $field_id  = id of hidden input
 * $value     = current color value (rgba or hex)
 * $label_id  = for= attribute on label
 */
function map10_render_color_picker( $field_id, $value, $label_id = '' ) {
  $safe_value = esc_attr( $value ?: 'rgba(232,76,61,1)' );
  ?>
  <div class="map10-pickr-wrap" style="display:flex;align-items:center;gap:12px;margin-top:4px;">
    <div id="map10-pickr-<?php echo esc_attr( $field_id ); ?>"></div>
    <input type="hidden"
           id="<?php echo esc_attr( $field_id ); ?>"
           name="<?php echo esc_attr( $field_id ); ?>"
           value="<?php echo $safe_value; ?>">
    <span class="map10-color-preview"
          style="display:inline-block;width:80px;height:28px;border-radius:4px;border:1px solid #ddd;background:<?php echo $safe_value; ?>;"></span>
    <code id="<?php echo esc_attr( $field_id ); ?>_label"
          style="font-size:12px;color:#555;"><?php echo $safe_value; ?></code>
  </div>
  <script>
  (function() {
    function initPickr_<?php echo esc_js( $field_id ); ?>() {
      if (typeof Pickr === 'undefined') { setTimeout(initPickr_<?php echo esc_js( $field_id ); ?>, 100); return; }

      var currentVal = document.getElementById('<?php echo esc_js( $field_id ); ?>').value || 'rgba(232,76,61,1)';

      var pickr = Pickr.create({
        el: '#map10-pickr-<?php echo esc_js( $field_id ); ?>',
        theme: 'nano',
        default: currentVal,
        components: {
          preview: true,
          opacity: true,
          hue: true,
          interaction: {
            hex: true,
            rgba: true,
            input: true,
            save: true
          }
        }
      });

      function updateFields(color) {
        var rgba = color.toRGBA();
        var r = Math.round(rgba[0]);
        var g = Math.round(rgba[1]);
        var b = Math.round(rgba[2]);
        var a = Math.round(rgba[3] * 100) / 100;
        var val = 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';

        document.getElementById('<?php echo esc_js( $field_id ); ?>').value = val;
        document.getElementById('<?php echo esc_js( $field_id ); ?>_label').textContent = val;

        var preview = document.querySelector('#map10-pickr-<?php echo esc_js( $field_id ); ?>').closest('.map10-pickr-wrap').querySelector('.map10-color-preview');
        if (preview) preview.style.background = val;
      }

      pickr.on('save', function(color) {
        if (color) updateFields(color);
        pickr.hide();
      });

      pickr.on('change', function(color) {
        if (color) updateFields(color);
      });
    }
    initPickr_<?php echo esc_js( $field_id ); ?>();
  })();
  </script>
  <?php
}

/**
 * Add color + icon fields to category EDIT form
 */
function map10_add_category_color_field( $term ) {
  $color       = get_term_meta( $term->term_id, 'map10_category_color', true );
  $color       = $color ?: 'rgba(232,76,61,1)';
  $icon        = get_term_meta( $term->term_id, 'map10_category_icon', true );
  $layer_order = get_term_meta( $term->term_id, 'map10_layer_order', true );
  $layer_order = ( $layer_order !== '' ) ? intval( $layer_order ) : 10;
  ?>
  <tr class="form-field">
    <th scope="row" valign="top">
      <label>Category Color</label>
    </th>
    <td>
      <?php map10_render_color_picker( 'map10_category_color', $color ); ?>
      <p class="description">Color (with transparency) for polygons on the map.</p>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top">
      <label for="map10_layer_order">Layer Order</label>
    </th>
    <td>
      <input type="number" id="map10_layer_order" name="map10_layer_order"
             value="<?php echo esc_attr( $layer_order ); ?>"
             min="1" max="99" step="1" style="width:80px;">
      <p class="description">Render order on the map. Lower = background (rendered first), Higher = foreground (rendered last). Default: 10.</p>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top">
      <label>Category Icon</label>
    </th>
    <td>
      <?php if ( $icon ): ?>
        <div id="map10_category_icon_preview" style="margin-bottom:8px;">
          <img src="<?php echo esc_url( $icon ); ?>" style="width:48px;height:48px;object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;">
        </div>
      <?php else: ?>
        <div id="map10_category_icon_preview" style="margin-bottom:8px;display:none;">
          <img src="" style="width:48px;height:48px;object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;">
        </div>
      <?php endif; ?>
      <input type="hidden" id="map10_category_icon" name="map10_category_icon" value="<?php echo esc_url( $icon ); ?>">
      <button type="button" class="button map10-upload-icon-btn" id="map10_upload_icon_btn">
        <?php echo $icon ? 'Change Icon' : 'Upload Icon'; ?>
      </button>
      <?php if ( $icon ): ?>
        <button type="button" class="button map10-remove-icon-btn" id="map10_remove_icon_btn" style="margin-left:5px;">Remove Icon</button>
      <?php endif; ?>
      <p class="description">Icon shown in filter button and in info box. Does NOT affect map marker. Recommended: PNG/SVG 48×48px.</p>
      <?php map10_category_icon_uploader_script(); ?>
    </td>
  </tr>
  <?php
}
add_action( 'map10_location_category_edit_form_fields', 'map10_add_category_color_field' );

/**
 * Add color + icon fields to NEW category form
 */
function map10_add_category_color_field_new() {
  ?>
  <div class="form-field">
    <label>Category Color</label>
    <?php map10_render_color_picker( 'map10_category_color', 'rgba(232,76,61,1)' ); ?>
    <p class="description">Color (with transparency) for polygons on the map.</p>
  </div>
  <div class="form-field">
    <label for="map10_layer_order">Layer Order</label>
    <input type="number" id="map10_layer_order" name="map10_layer_order"
           value="10" min="1" max="99" step="1" style="width:80px;">
    <p class="description">Render order on the map. Lower = background (rendered first), Higher = foreground (rendered last). Default: 10.</p>
  </div>
  <div class="form-field">
    <label>Category Icon</label>
    <div id="map10_category_icon_preview" style="margin-bottom:8px;display:none;">
      <img src="" style="width:48px;height:48px;object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;">
    </div>
    <input type="hidden" id="map10_category_icon" name="map10_category_icon" value="">
    <button type="button" class="button" id="map10_upload_icon_btn">Upload Icon</button>
    <p class="description">Icon shown in filter button and in info box. Does NOT affect map marker. Recommended: PNG/SVG 48×48px.</p>
    <?php map10_category_icon_uploader_script(); ?>
  </div>
  <?php
}
add_action( 'map10_location_category_add_form_fields', 'map10_add_category_color_field_new' );

/**
 * Shared JS for icon uploader
 */
function map10_category_icon_uploader_script() {
  ?>
  <script>
  jQuery(document).ready(function($) {
    if (window.map10IconUploaderInit) return;
    window.map10IconUploaderInit = true;

    var mediaUploader;

    $(document).on('click', '#map10_upload_icon_btn', function(e) {
      e.preventDefault();
      if (mediaUploader) { mediaUploader.open(); return; }
      mediaUploader = wp.media({
        title: 'Choose Category Icon',
        button: { text: 'Use this image' },
        multiple: false
      });
      mediaUploader.on('select', function() {
        var attachment = mediaUploader.state().get('selection').first().toJSON();
        $('#map10_category_icon').val(attachment.url);
        $('#map10_category_icon_preview img').attr('src', attachment.url);
        $('#map10_category_icon_preview').show();
        $('#map10_upload_icon_btn').text('Change Icon');
        if ($('#map10_remove_icon_btn').length === 0) {
          $('#map10_upload_icon_btn').after('<button type="button" class="button" id="map10_remove_icon_btn" style="margin-left:5px;">Remove Icon</button>');
        }
      });
      mediaUploader.open();
    });

    $(document).on('click', '#map10_remove_icon_btn', function(e) {
      e.preventDefault();
      $('#map10_category_icon').val('');
      $('#map10_category_icon_preview').hide();
      $('#map10_upload_icon_btn').text('Upload Icon');
      $(this).remove();
    });
  });
  </script>
  <?php
}

/**
 * Save category color + icon
 * Color stored as rgba string (not sanitize_hex_color)
 */
function map10_save_category_color( $term_id ) {
  if ( isset( $_POST['map10_category_color'] ) ) {
    $color = sanitize_text_field( wp_unslash( $_POST['map10_category_color'] ) );
    // Accept rgba(...) or hex formats only
    if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d.]+\s*)?\)$/', $color )
         || preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ) {
      update_term_meta( $term_id, 'map10_category_color', $color );
    }
  }
  if ( isset( $_POST['map10_category_icon'] ) ) {
    update_term_meta(
      $term_id,
      'map10_category_icon',
      esc_url_raw( $_POST['map10_category_icon'] )
    );
  }
  if ( isset( $_POST['map10_layer_order'] ) ) {
    update_term_meta(
      $term_id,
      'map10_layer_order',
      intval( $_POST['map10_layer_order'] )
    );
  }
}
add_action( 'edited_map10_location_category', 'map10_save_category_color' );
add_action( 'created_map10_location_category', 'map10_save_category_color' );
