<?php
// Exit if accessed directly for better security
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Glampress Hello Elementor Child Theme Functions
 *
 * @package Glampress
 * @version 1.0.0
 */

/**
 * Enqueue parent and child theme styles and scripts
 */
function glampress_theme_enqueue_scripts() {
    // Enqueue parent theme styles
    wp_enqueue_style(
        'hello-elementor-style',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme('hello-elementor')->get('Version')
    );

    // Enqueue child theme styles
    wp_enqueue_style(
        'glampress-hello-elementor-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['hello-elementor-style'],
        wp_get_theme()->get('Version')
    );

    // Enqueue compiled assets if they exist
    if (file_exists(get_stylesheet_directory() . '/assets/css/app.css')) {
        wp_enqueue_style(
            'glampress-hello-elementor-child-compiled',
            get_stylesheet_directory_uri() . '/assets/css/app.css',
            ['glampress-hello-elementor-child-style'],
            filemtime(get_stylesheet_directory() . '/assets/css/app.css')
        );
    }

    if (file_exists(get_stylesheet_directory() . '/assets/js/app.js')) {
        wp_enqueue_script(
            'glampress-hello-elementor-child-compiled',
            get_stylesheet_directory_uri() . '/assets/js/app.js',
            [], // Change to ['jquery'] if you need jQuery!
            filemtime(get_stylesheet_directory() . '/assets/js/app.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'glampress_theme_enqueue_scripts');

/**
 * Add custom body classes
 */
function glampress_theme_body_classes($classes) {
    // Add glampress-specific body classes
    $classes[] = 'glampress-theme';
    
    // Add page-specific classes (with extra safety)
    if (is_page()) {
        $current_post = get_post();
        if ($current_post) {
            $classes[] = 'page-' . $current_post->post_name;
        }
    }
    
    return $classes;
}
add_filter('body_class', 'glampress_theme_body_classes');

// Add Google AdSense code to the <head> section
function glampress_add_adsense_to_head() {
    ?>
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7961945693715196"
        crossorigin="anonymous"></script>
    <?php
}
add_action('wp_head', 'glampress_add_adsense_to_head');

