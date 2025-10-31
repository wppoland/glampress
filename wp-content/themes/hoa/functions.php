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

/**
 * Glampress Embed Shortcode
 * Embeds content from another post/page on the site
 * 
 * Usage: [glampress_embed url="https://glampress.pl/post-url/"]
 * 
 * @param array $atts Shortcode attributes
 * @return string The embedded content HTML
 */
function glampress_embed_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'url' => '',
        'show_excerpt' => 'true',
        'show_image' => 'true',
        'show_title' => 'true',
    ), $atts, 'glampress_embed');
    
    // Validate URL
    if (empty($atts['url'])) {
        return '<p class="glampress-embed-error">Brak adresu URL do osadzenia.</p>';
    }
    
    // Sanitize URL
    $url = esc_url_raw($atts['url']);
    
    // Extract post ID from URL
    $post_id = url_to_postid($url);
    
    // If url_to_postid fails, try to find by slug
    if (!$post_id) {
        $parsed_url = parse_url($url);
        if (isset($parsed_url['path'])) {
            $path = trim($parsed_url['path'], '/');
            $slug = basename($path);
            
            // Try to find post by slug
            $post_obj = get_page_by_path($slug, OBJECT, array('post', 'page'));
            if ($post_obj) {
                $post_id = $post_obj->ID;
            }
        }
    }
    
    // Validate post ID
    if (!$post_id) {
        return '<p class="glampress-embed-error">Nie znaleziono artykułu dla podanego adresu URL.</p>';
    }
    
    // Get post object
    $post = get_post($post_id);
    
    // Check if post exists and is published
    if (!$post || $post->post_status !== 'publish') {
        return '<p class="glampress-embed-error">Artykuł nie jest dostępny.</p>';
    }
    
    // Start output buffering
    ob_start();
    
    // Build embed wrapper
    ?>
    <div class="glampress-embed-wrapper">
        <?php if ($atts['show_title'] === 'true') : ?>
            <h3 class="glampress-embed-title">
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
                    <?php echo esc_html(get_the_title($post_id)); ?>
                </a>
            </h3>
        <?php endif; ?>
        
        <?php if ($atts['show_image'] === 'true' && has_post_thumbnail($post_id)) : ?>
            <div class="glampress-embed-image">
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
                    <?php echo get_the_post_thumbnail($post_id, 'medium_large', array('class' => 'glampress-embed-thumbnail')); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($atts['show_excerpt'] === 'true') : ?>
            <div class="glampress-embed-content">
                <?php 
                if (has_excerpt($post_id)) {
                    echo '<p>' . wp_kses_post(get_the_excerpt($post_id)) . '</p>';
                } else {
                    $content = get_post_field('post_content', $post_id);
                    $excerpt = wp_trim_words($content, 55, '...');
                    echo '<p>' . wp_kses_post($excerpt) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div class="glampress-embed-footer">
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="glampress-embed-link">
                Czytaj więcej →
            </a>
        </div>
    </div>
    
    <style>
        .glampress-embed-wrapper {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            background: #f9f9f9;
        }
        .glampress-embed-title {
            margin: 0 0 15px 0;
            font-size: 1.25em;
        }
        .glampress-embed-title a {
            color: inherit;
            text-decoration: none;
        }
        .glampress-embed-title a:hover {
            text-decoration: underline;
        }
        .glampress-embed-image {
            margin: 0 0 15px 0;
        }
        .glampress-embed-image img {
            width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .glampress-embed-content {
            margin: 0 0 15px 0;
            line-height: 1.6;
        }
        .glampress-embed-footer {
            margin-top: 15px;
        }
        .glampress-embed-link {
            display: inline-block;
            color: #0073aa;
            text-decoration: none;
            font-weight: 500;
        }
        .glampress-embed-link:hover {
            text-decoration: underline;
        }
        .glampress-embed-error {
            color: #d63638;
            padding: 10px;
            background: #fef2f2;
            border-left: 4px solid #d63638;
            margin: 20px 0;
        }
    </style>
    <?php
    
    // Return buffered content
    return ob_get_clean();
}
add_shortcode('glampress_embed', 'glampress_embed_shortcode');

