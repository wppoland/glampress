<?php
/**
 * Plugin Name:       Najlepsza Galeria dla S.
 * Description:       Tworzy sekcję "Galerie" do budowy galerii wielokrotnego użytku. Wyświetlaj za pomocą shortcode [reloading_gallery id="123"].
 * Version:           5.1.0 (Stable)
 * Author:            Krzysztof Galant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Rejestracja typu posta "Galerie"
function crg_register_gallery_cpt() {
    $labels = [
        'name'                  => _x( 'Galerie', 'Post type general name' ), 'singular_name' => _x( 'Galeria', 'Post type singular name' ),
        'menu_name'             => _x( 'Galerie', 'Admin Menu text' ), 'add_new' => __( 'Dodaj nową' ),
        'add_new_item'          => __( 'Dodaj nową galerię' ), 'edit_item' => __( 'Edytuj galerię' ),
        'new_item'              => __( 'Nowa galeria' ), 'view_item' => __( 'Zobacz galerię' ),
        'search_items'          => __( 'Szukaj galerii' ), 'not_found' => __( 'Nie znaleziono galerii' ),
        'not_found_in_trash'    => __( 'Nie znaleziono galerii w koszu' ),
    ];
    $args = [
        'labels'                => $labels, 'public' => true, 'publicly_queryable' => false, 'show_ui' => true,
        'show_in_menu'          => true, 'query_var' => true, 'rewrite' => ['slug' => 'gallery'],
        'capability_type'       => 'post', 'has_archive' => false, 'hierarchical' => false, 'menu_position' => 20,
        'menu_icon'             => 'dashicons-format-gallery', 'supports' => ['title'], 'exclude_from_search' => true,
    ];
    register_post_type( 'crg_gallery', $args );
}
add_action( 'init', 'crg_register_gallery_cpt' );

// Dodanie meta boxa do wyboru obrazów
function crg_add_gallery_meta_box() {
    add_meta_box('crg_gallery_images', 'Obrazy w Galerii', 'crg_render_gallery_meta_box', 'crg_gallery', 'normal', 'high');
}
add_action( 'add_meta_boxes', 'crg_add_gallery_meta_box' );

// Renderowanie zawartości meta boxa
function crg_render_gallery_meta_box( $post ) {
    wp_nonce_field( 'crg_save_gallery_data', 'crg_gallery_nonce' );
    $image_ids_str = get_post_meta( $post->ID, '_crg_image_ids', true );
    ?>
    <p><em>Przeciągnij i upuść miniatury, aby zmienić ich kolejność.</em></p>
    <div id="crg-gallery-container"><ul class="crg-gallery-preview">
        <?php if ( !empty($image_ids_str) ) { foreach ( explode(',', $image_ids_str) as $id ) { echo '<li data-id="'.esc_attr($id).'">'.wp_get_attachment_image($id, 'thumbnail').'</li>'; } } ?>
    </ul></div>
    <input type="hidden" id="crg_image_ids" name="crg_image_ids" value="<?php echo esc_attr($image_ids_str); ?>">
    <button type="button" class="button" id="crg-add-edit-gallery-button">Dodaj lub Edytuj Obrazy w Galerii</button>
    <style>
        #crg-gallery-container ul { display: flex; flex-wrap: wrap; gap: 10px; margin: 15px 0; }
        #crg-gallery-container li { list-style-type: none; margin: 0; cursor: move; }
        #crg-gallery-container li.ui-sortable-helper { opacity: 0.7; border: 2px dashed #999; }
        #crg-gallery-container img { max-width: 100px; height: auto; border: 1px solid #ddd; }
    </style>
    <?php
}

// Dołączanie skryptów do panelu admina
function crg_enqueue_admin_scripts( $hook ) {
    global $post;
    if ( ($hook == 'post-new.php' || $hook == 'post.php') && isset($post->post_type) && 'crg_gallery' === $post->post_type ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'crg-admin-js',
            plugin_dir_url( __FILE__ ) . 'admin-gallery.js',
            ['jquery', 'jquery-ui-sortable'],
            '3.0.0',
            true
        );
    }
}
add_action( 'admin_enqueue_scripts', 'crg_enqueue_admin_scripts' );

// Zapisywanie danych z meta boxa
function crg_save_gallery_data( $post_id ) {
    if ( ! isset( $_POST['crg_gallery_nonce'] ) || ! wp_verify_nonce( $_POST['crg_gallery_nonce'], 'crg_save_gallery_data' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( isset( $_POST['crg_image_ids'] ) ) {
        update_post_meta( $post_id, '_crg_image_ids', sanitize_text_field( $_POST['crg_image_ids'] ) );
    }
}
add_action( 'save_post', 'crg_save_gallery_data' );

// Funkcja shortcode do wyświetlania galerii
function crg_reusable_shortcode_handler( $atts ) {
    $atts = shortcode_atts( ['id' => ''], $atts, 'reloading_gallery' );
    $gallery_id = intval( $atts['id'] );
    if ( empty( $gallery_id ) ) return '<p><em>Galeria nie znaleziona. Proszę podać ID galerii w shortcode, np. [reloading_gallery id="123"].</em></p>';

    $image_ids_str = get_post_meta( $gallery_id, '_crg_image_ids', true );
    if ( empty( $image_ids_str ) ) return '';

    $image_ids = explode(',', $image_ids_str);
    $image_data_for_js = [];
    foreach($image_ids as $id) {
        $large_src = wp_get_attachment_image_src($id, 'large');
        $thumb_src = wp_get_attachment_image_src($id, 'thumbnail');
        if ($large_src && $thumb_src) {
            $image_data_for_js[] = ['id' => $id, 'large' => $large_src[0], 'thumb' => $thumb_src[0] ];
        }
    }

    if (empty($image_data_for_js)) return '';

    wp_enqueue_script('crg-frontend-js', plugin_dir_url( __FILE__ ) . 'frontend-gallery.js', [], '2.0.0', true);

    $initial_index = isset( $_GET['image_index'] ) ? intval( $_GET['image_index'] ) : 0;
    if ($initial_index < 0 || $initial_index >= count($image_data_for_js)) $initial_index = 0;

    $gallery_anchor_id = 'custom-photo-gallery-' . $gallery_id;
    $base_url = get_permalink( get_the_ID() );

    $gallery_css = "
        #{$gallery_anchor_id} { position:relative; margin: 2em 0; scroll-margin-top: 50px; }
        .crg-main-image-wrapper { margin-bottom: 1rem; text-align: center; min-height: 250px; }
        .crg-main-image-wrapper img { max-width: 100%; height: auto; border: 2px solid #E22594; padding: 4px; background-color: #fff; transition: opacity 0.3s ease-in-out; }
        .crg-main-image-wrapper img.crg-loading { opacity: 0.5; }
        .crg-thumbnail-nav { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; border-top: 1px solid #eee; padding-top: 1rem; }
        .crg-nav-button-wrapper { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .crg-nav-button-wrapper a { display: block; cursor: pointer; transition: opacity 0.2s ease-in-out; }
        .crg-nav-button-wrapper a:hover { opacity: 0.7; }
        .crg-nav-button-wrapper img { max-width: 120px; height: auto; border: 1px solid #ddd; }
        .crg-nav-label { color: #E22594; font-weight: bold; font-size: 0.9em; }
        .crg-counter { font-style: italic; color: #666; flex-shrink: 0; padding-bottom: 1rem; }
    ";
    wp_register_style( 'crg-inline-styles', false ); wp_enqueue_style( 'crg-inline-styles' ); wp_add_inline_style( 'crg-inline-styles', $gallery_css );

    $count = count($image_data_for_js);
    $prev_index = ($initial_index > 0) ? $initial_index - 1 : $count - 1;
    $next_index = ($initial_index < $count - 1) ? $initial_index + 1 : 0;
    
    ob_start();
    ?>
    <div id="<?php echo esc_attr( $gallery_anchor_id ); ?>" class="crg-gallery-wrapper" data-images="<?php echo esc_attr(json_encode($image_data_for_js)); ?>" data-initial-index="<?php echo $initial_index; ?>" data-base-url="<?php echo esc_url($base_url); ?>">
        <div class="crg-main-image-wrapper">
            <img src="<?php echo esc_url($image_data_for_js[$initial_index]['large']); ?>" alt="">
        </div>
        <div class="crg-thumbnail-nav">
            <div class="crg-nav-button-wrapper">
                <a class="crg-thumb-prev" data-index="<?php echo $prev_index; ?>">
                    <img src="<?php echo esc_url($image_data_for_js[$prev_index]['thumb']); ?>" alt="Poprzednie zdjęcie">
                </a>
                <div class="crg-nav-label">Poprzedni</div>
            </div>
            <span class="crg-counter"><?php echo ($initial_index + 1) . ' / ' . $count; ?></span>
            <div class="crg-nav-button-wrapper">
                <a class="crg-thumb-next" data-index="<?php echo $next_index; ?>">
                    <img src="<?php echo esc_url($image_data_for_js[$next_index]['thumb']); ?>" alt="Następne zdjęcie">
                </a>
                <div class="crg-nav-label">Następny</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'reloading_gallery', 'crg_reusable_shortcode_handler' );