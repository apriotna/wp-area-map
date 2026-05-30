<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add Categories submenu page under Maps
 */
function map10_add_categories_menu() {
  add_submenu_page(
    'edit.php?post_type=map10_map',
    'Map Categories',
    'Categories',
    'manage_categories',
    'map10-categories',
    'map10_render_categories_page'
  );
}
add_action( 'admin_menu', 'map10_add_categories_menu' );

/**
 * Handle form submissions early (before any output) to allow wp_redirect()
 */
function map10_categories_handle_early() {
  if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'map10-categories' ) return;

  if ( isset( $_POST['map10_add_category'] ) && check_admin_referer( 'map10_add_category' ) ) {
    map10_handle_add_category();
  }

  if ( isset( $_POST['map10_edit_category'] ) && check_admin_referer( 'map10_edit_category' ) ) {
    map10_handle_edit_category();
  }

  if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['term_id'] ) ) {
    if ( check_admin_referer( 'map10_delete_category_' . $_GET['term_id'] ) ) {
      map10_handle_delete_category( intval( $_GET['term_id'] ) );
    }
  }
}
add_action( 'admin_init', 'map10_categories_handle_early' );

/**
 * Enqueue Pickr + media on this admin page
 */
function map10_enqueue_categories_page_assets( $hook ) {
  if ( $hook !== 'map10_map_page_map10-categories' ) return;

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
add_action( 'admin_enqueue_scripts', 'map10_enqueue_categories_page_assets' );

/**
 * Render the RGBA color picker widget inline
 */
function map10_admin_cat_color_picker( $field_id, $value ) {
  $safe = esc_attr( $value ?: 'rgba(232,76,61,1)' );
  ?>
  <div class="map10-pickr-wrap" style="display:flex;align-items:center;gap:12px;margin-top:4px;">
    <div id="map10-pickr-<?php echo esc_attr( $field_id ); ?>"></div>
    <input type="hidden"
           id="<?php echo esc_attr( $field_id ); ?>"
           name="<?php echo esc_attr( $field_id ); ?>"
           value="<?php echo $safe; ?>">
    <span class="map10-color-swatch"
          style="display:inline-block;width:80px;height:28px;border-radius:4px;border:1px solid #ddd;background:<?php echo $safe; ?>;"></span>
    <code id="<?php echo esc_attr( $field_id ); ?>_label"
          style="font-size:12px;color:#555;"><?php echo $safe; ?></code>
  </div>
  <script>
  (function(){
    function initP_<?php echo esc_js( $field_id ); ?>(){
      if(typeof Pickr==='undefined'){setTimeout(initP_<?php echo esc_js( $field_id ); ?>,100);return;}
      var cur = document.getElementById('<?php echo esc_js( $field_id ); ?>').value||'rgba(232,76,61,1)';
      var p = Pickr.create({
        el:'#map10-pickr-<?php echo esc_js( $field_id ); ?>',
        theme:'nano',
        default:cur,
        components:{preview:true,opacity:true,hue:true,interaction:{hex:true,rgba:true,input:true,save:true}}
      });
      function upd(c){
        var rgba=c.toRGBA();
        var r=Math.round(rgba[0]),g=Math.round(rgba[1]),b=Math.round(rgba[2]),a=Math.round(rgba[3]*100)/100;
        var v='rgba('+r+','+g+','+b+','+a+')';
        document.getElementById('<?php echo esc_js( $field_id ); ?>').value=v;
        document.getElementById('<?php echo esc_js( $field_id ); ?>_label').textContent=v;
        var w=document.querySelector('#map10-pickr-<?php echo esc_js( $field_id ); ?>').closest('.map10-pickr-wrap').querySelector('.map10-color-swatch');
        if(w)w.style.background=v;
      }
      p.on('save',function(c){if(c)upd(c);p.hide();});
      p.on('change',function(c){if(c)upd(c);});
    }
    initP_<?php echo esc_js( $field_id ); ?>();
  })();
  </script>
  <?php
}

/**
 * Render categories management page
 */
function map10_render_categories_page() {

  // Get all categories
  $categories = get_terms([
    'taxonomy'   => 'map10_location_category',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC'
  ]);

  // Check if editing
  $edit_term = null;
  if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['term_id'] ) ) {
    $edit_term = get_term( intval( $_GET['term_id'] ), 'map10_location_category' );
  }

  ?>
  <div class="wrap">
    <h1 class="wp-heading-inline">Map Categories</h1>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['message'] ) ): ?>
      <div class="notice notice-success is-dismissible">
        <p>
          <?php
          switch ( $_GET['message'] ) {
            case 'added':   echo 'Category added successfully!'; break;
            case 'updated': echo 'Category updated successfully!'; break;
            case 'deleted': echo 'Category deleted successfully!'; break;
          }
          ?>
        </p>
      </div>
    <?php endif; ?>

    <div id="col-container" class="wp-clearfix">

      <!-- LEFT: Add / Edit form -->
      <div id="col-left">
        <div class="col-wrap">

          <?php if ( $edit_term ): ?>
            <!-- EDIT FORM -->
            <div class="form-wrap">
              <h2>Edit Category</h2>
              <form method="post" action="">
                <?php wp_nonce_field( 'map10_edit_category' ); ?>
                <input type="hidden" name="term_id" value="<?php echo esc_attr( $edit_term->term_id ); ?>">

                <div class="form-field form-required">
                  <label for="cat_name">Name</label>
                  <input type="text" name="cat_name" id="cat_name" value="<?php echo esc_attr( $edit_term->name ); ?>" required>
                  <p>The name is how it appears on your site.</p>
                </div>

                <div class="form-field">
                  <label for="cat_slug">Slug</label>
                  <input type="text" name="cat_slug" id="cat_slug" value="<?php echo esc_attr( $edit_term->slug ); ?>">
                  <p>The "slug" is the URL-friendly version of the name.</p>
                </div>

                <div class="form-field">
                  <label>Category Color</label>
                  <?php
                  $edit_color = get_term_meta( $edit_term->term_id, 'map10_category_color', true ) ?: 'rgba(232,76,61,1)';
                  map10_admin_cat_color_picker( 'cat_color', $edit_color );
                  ?>
                  <p>Color with transparency for polygons on the map.</p>
                </div>

                <div class="form-field">
                  <label for="cat_layer_order">Layer Order</label>
                  <?php $edit_order = get_term_meta( $edit_term->term_id, 'map10_layer_order', true ); $edit_order = ( $edit_order !== '' ) ? intval( $edit_order ) : 10; ?>
                  <input type="number" name="cat_layer_order" id="cat_layer_order"
                         value="<?php echo esc_attr( $edit_order ); ?>"
                         min="1" max="99" step="1" style="width:80px;">
                  <p>Render order on the map. Lower = background (rendered first), Higher = foreground (rendered last). Default: 10.</p>
                </div>

                <div class="form-field">
                  <label>Category Icon</label>
                  <?php $edit_icon = get_term_meta( $edit_term->term_id, 'map10_category_icon', true ); ?>
                  <div id="map10_cat_icon_preview" style="margin-bottom:8px;<?php echo $edit_icon ? '' : 'display:none;'; ?>">
                    <img src="<?php echo esc_url( $edit_icon ); ?>" style="width:48px;height:48px;object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;">
                  </div>
                  <input type="hidden" id="map10_cat_icon_url" name="map10_category_icon" value="<?php echo esc_url( $edit_icon ); ?>">
                  <button type="button" class="button" id="map10_cat_icon_upload"><?php echo $edit_icon ? 'Change Icon' : 'Upload Icon'; ?></button>
                  <?php if ( $edit_icon ): ?>
                    <button type="button" class="button" id="map10_cat_icon_remove" style="margin-left:5px;">Remove Icon</button>
                  <?php endif; ?>
                  <p>Icon shown in filter button and info box. Does NOT affect map marker.</p>
                </div>

                <p class="submit">
                  <input type="submit" name="map10_edit_category" class="button button-primary" value="Update Category">
                  <a href="<?php echo admin_url( 'edit.php?post_type=map10_map&page=map10-categories' ); ?>" class="button">Cancel</a>
                </p>
              </form>
            </div>

          <?php else: ?>
            <!-- ADD FORM -->
            <div class="form-wrap">
              <h2>Add New Category</h2>
              <form method="post" action="">
                <?php wp_nonce_field( 'map10_add_category' ); ?>

                <div class="form-field form-required">
                  <label for="cat_name">Name</label>
                  <input type="text" name="cat_name" id="cat_name" required>
                  <p>The name is how it appears on your site.</p>
                </div>

                <div class="form-field">
                  <label for="cat_slug">Slug</label>
                  <input type="text" name="cat_slug" id="cat_slug">
                  <p>The "slug" is the URL-friendly version of the name. Leave blank for auto-generate.</p>
                </div>

                <div class="form-field">
                  <label>Category Color</label>
                  <?php map10_admin_cat_color_picker( 'cat_color', 'rgba(232,76,61,1)' ); ?>
                  <p>Color with transparency for polygons on the map.</p>
                </div>

                <div class="form-field">
                  <label for="cat_layer_order">Layer Order</label>
                  <input type="number" name="cat_layer_order" id="cat_layer_order"
                         value="10" min="1" max="99" step="1" style="width:80px;">
                  <p>Render order on the map. Lower = background (rendered first), Higher = foreground (rendered last). Default: 10.</p>
                </div>

                <div class="form-field">
                  <label>Category Icon</label>
                  <div id="map10_cat_icon_preview" style="margin-bottom:8px;display:none;">
                    <img src="" style="width:48px;height:48px;object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;">
                  </div>
                  <input type="hidden" id="map10_cat_icon_url" name="map10_category_icon" value="">
                  <button type="button" class="button" id="map10_cat_icon_upload">Upload Icon</button>
                  <p>Icon shown in filter button and info box. Does NOT affect map marker.</p>
                </div>

                <p class="submit">
                  <input type="submit" name="map10_add_category" class="button button-primary" value="Add New Category">
                </p>
              </form>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- RIGHT: Categories List -->
      <div id="col-right">
        <div class="col-wrap">
          <h2>Categories</h2>

          <?php if ( empty( $categories ) ): ?>
            <p>No categories found. Add your first category using the form on the left.</p>
          <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
              <thead>
                <tr>
                  <th class="manage-column column-primary">Name</th>
                  <th class="manage-column">Slug</th>
                  <th class="manage-column">Color</th>
                  <th class="manage-column">Layer Order</th>
                  <th class="manage-column">Icon</th>
                  <th class="manage-column">Count</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $categories as $cat ):
                  $color = get_term_meta( $cat->term_id, 'map10_category_color', true ) ?: 'rgba(232,76,61,1)';
                  $icon  = get_term_meta( $cat->term_id, 'map10_category_icon', true );
                  $edit_url = add_query_arg(['action' => 'edit', 'term_id' => $cat->term_id]);
                  $delete_url = wp_nonce_url(
                    add_query_arg(['action' => 'delete', 'term_id' => $cat->term_id]),
                    'map10_delete_category_' . $cat->term_id
                  );
                ?>
                  <tr>
                    <td class="name column-name has-row-actions column-primary" data-colname="Name">
                      <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $cat->name ); ?></a></strong>
                      <div class="row-actions">
                        <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>">Edit</a> |</span>
                        <span class="delete">
                          <a href="<?php echo esc_url( $delete_url ); ?>"
                             onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
                        </span>
                      </div>
                    </td>
                    <td><?php echo esc_html( $cat->slug ); ?></td>
                    <td>
                      <span style="display:inline-block;width:40px;height:20px;background:<?php echo esc_attr( $color ); ?>;border:1px solid #ddd;border-radius:3px;vertical-align:middle;"></span>
                      <code style="margin-left:6px;font-size:11px;"><?php echo esc_html( $color ); ?></code>
                    </td>
                    <td>
                      <?php
                      $order = get_term_meta( $cat->term_id, 'map10_layer_order', true );
                      echo ( $order !== '' ) ? intval( $order ) : 10;
                      ?>
                    </td>
                    <td>
                      <?php if ( $icon ): ?>
                        <img src="<?php echo esc_url( $icon ); ?>" style="width:32px;height:32px;object-fit:contain;">
                      <?php else: ?>
                        <span style="color:#999;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo intval( $cat->count ); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

        </div>
      </div>

    </div><!-- #col-container -->

    <style>
      #col-container { display:table; width:100%; }
      #col-left  { width:35%; display:table-cell; padding-right:20px; }
      #col-right { width:65%; display:table-cell; }
      .form-field { margin-bottom:20px; }
      .form-field label { display:block; font-weight:600; margin-bottom:5px; }
      .form-field input[type="text"] { width:95%; }
      .form-field p { margin-top:5px; color:#666; font-size:13px; }
      /* Pickr button override to match WP admin */
      .pcr-button { border-radius:4px !important; border:1px solid #ddd !important; }
    </style>

    <script>
    jQuery(document).ready(function($) {
      var mediaUploader;
      $(document).on('click', '#map10_cat_icon_upload', function(e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }
        mediaUploader = wp.media({ title:'Choose Category Icon', button:{text:'Use this image'}, multiple:false });
        mediaUploader.on('select', function() {
          var att = mediaUploader.state().get('selection').first().toJSON();
          $('#map10_cat_icon_url').val(att.url);
          $('#map10_cat_icon_preview img').attr('src', att.url);
          $('#map10_cat_icon_preview').show();
          $('#map10_cat_icon_upload').text('Change Icon');
          if ($('#map10_cat_icon_remove').length === 0) {
            $('#map10_cat_icon_upload').after('<button type="button" class="button" id="map10_cat_icon_remove" style="margin-left:5px;">Remove Icon</button>');
          }
        });
        mediaUploader.open();
      });
      $(document).on('click', '#map10_cat_icon_remove', function(e) {
        e.preventDefault();
        $('#map10_cat_icon_url').val('');
        $('#map10_cat_icon_preview').hide();
        $('#map10_cat_icon_upload').text('Upload Icon');
        $(this).remove();
      });
    });
    </script>

  </div>
  <?php
}

/**
 * Sanitize rgba color from POST
 */
function map10_sanitize_rgba_color( $value ) {
  $v = sanitize_text_field( wp_unslash( $value ) );
  if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d.]+\s*)?\)$/', $v ) ) return $v;
  if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ) return $v;
  return 'rgba(232,76,61,1)';
}

/**
 * Handle add category
 */
function map10_handle_add_category() {
  if ( ! current_user_can( 'manage_categories' ) ) wp_die( 'Permission denied.' );

  $name  = sanitize_text_field( $_POST['cat_name'] );
  $slug  = sanitize_title( $_POST['cat_slug'] );
  $color = map10_sanitize_rgba_color( $_POST['cat_color'] ?? '' );

  if ( empty( $name ) ) wp_die( 'Category name is required.' );

  $args = [];
  if ( ! empty( $slug ) ) $args['slug'] = $slug;

  $result = wp_insert_term( $name, 'map10_location_category', $args );
  if ( is_wp_error( $result ) ) wp_die( $result->get_error_message() );

  update_term_meta( $result['term_id'], 'map10_category_color', $color );
  update_term_meta( $result['term_id'], 'map10_category_icon', esc_url_raw( $_POST['map10_category_icon'] ?? '' ) );
  update_term_meta( $result['term_id'], 'map10_layer_order', intval( $_POST['cat_layer_order'] ?? 10 ) );

  wp_redirect( add_query_arg( 'message', 'added', admin_url( 'edit.php?post_type=map10_map&page=map10-categories' ) ) );
  exit;
}

/**
 * Handle edit category
 */
function map10_handle_edit_category() {
  if ( ! current_user_can( 'manage_categories' ) ) wp_die( 'Permission denied.' );

  $term_id = intval( $_POST['term_id'] );
  $name    = sanitize_text_field( $_POST['cat_name'] );
  $slug    = sanitize_title( $_POST['cat_slug'] );
  $color   = map10_sanitize_rgba_color( $_POST['cat_color'] ?? '' );

  if ( empty( $name ) ) wp_die( 'Category name is required.' );

  $result = wp_update_term( $term_id, 'map10_location_category', ['name' => $name, 'slug' => $slug] );
  if ( is_wp_error( $result ) ) wp_die( $result->get_error_message() );

  update_term_meta( $term_id, 'map10_category_color', $color );
  update_term_meta( $term_id, 'map10_category_icon', esc_url_raw( $_POST['map10_category_icon'] ?? '' ) );
  update_term_meta( $term_id, 'map10_layer_order', intval( $_POST['cat_layer_order'] ?? 10 ) );

  wp_redirect( add_query_arg( 'message', 'updated', admin_url( 'edit.php?post_type=map10_map&page=map10-categories' ) ) );
  exit;
}

/**
 * Handle delete category
 */
function map10_handle_delete_category( $term_id ) {
  if ( ! current_user_can( 'manage_categories' ) ) wp_die( 'Permission denied.' );

  $result = wp_delete_term( $term_id, 'map10_location_category' );
  if ( is_wp_error( $result ) ) wp_die( $result->get_error_message() );

  wp_redirect( add_query_arg( 'message', 'deleted', admin_url( 'edit.php?post_type=map10_map&page=map10-categories' ) ) );
  exit;
}
