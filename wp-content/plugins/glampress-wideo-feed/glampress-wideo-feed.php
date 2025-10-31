<?php
/**
 * Plugin Name:       Glampress Wideo Feed
 * Plugin URI:        https://glampress.pl
 * Description:       Creates a TikTok-style video feed for mobile devices using a shortcode.
 * Version:           1.4.0
 * Author:            Krzysztof Galant
 * Author URI:        https://glampress.pl
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       glampress-wideo-feed
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function gpf_enqueue_assets() {
    wp_enqueue_style( 'swiper-css', 'https://unpkg.com/swiper/swiper-bundle.min.css', array(), '8.4.5' );
    wp_enqueue_script( 'swiper-js', 'https://unpkg.com/swiper/swiper-bundle.min.js', array(), '8.4.5', true );
    wp_enqueue_style( 'gpf-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), '1.4.0' );
    wp_enqueue_script( 'gpf-main-js', plugin_dir_url( __FILE__ ) . 'assets/js/main.js', array( 'jquery', 'swiper-js' ), '1.2.0', true );
}
add_action( 'wp_enqueue_scripts', 'gpf_enqueue_assets' );

function gpf_render_wideo_feed_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'category' => 'wideo', 'count' => 10 ), $atts, 'glampress_wideo_feed' );
    $args = array( 'post_type' => 'post', 'posts_per_page' => $atts['count'], 'category_name' => $atts['category'], 'post_status' => 'publish' );
    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return '<p>Brak wideo do wyświetlenia.</p>';
    }

    ob_start();
    ?>

    <div class="gpf-container">
        <button class="gpf-mute-toggle">
            <span class="gpf-icon-unmuted"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg></span>
            <span class="gpf-icon-muted" style="display: none;"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg></span>
        </button>

        <div class="swiper gpf-wideo-feed">
            <div class="swiper-wrapper">
                <?php
                while ( $query->have_posts() ) : $query->the_post();
                    $video_url = get_post_meta( get_the_ID(), 'video_url', true );
                    if ( empty( $video_url ) ) continue;
                    
                    $poster_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
                    $post_link = get_permalink();
                ?>
                <div class="swiper-slide" data-url="<?php echo esc_url($post_link); ?>" data-title="<?php echo esc_attr(get_the_title()); ?>">
                    
                    <div class="gpf-video-wrapper">
                        <video src="<?php echo esc_url( $video_url ); ?>" poster="<?php echo esc_url( $poster_url ); ?>" loop playsinline></video>
                        <div class="gpf-play-pause-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 0 24 24" width="48"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                        </div>
                    </div>
                    
                    <div class="gpf-top-controls">
                        <a href="<?php echo esc_url($post_link); ?>" class="gpf-article-link">
                            <span class="gpf-icon gpf-icon-link"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"></path></svg></span>
                            <span class="gpf-link-text">Zobacz całość</span>
                            <span class="gpf-icon gpf-icon-bang"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path></svg></span>
                            <span class="gpf-icon gpf-icon-point"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M2.5 13.55l7.02 7.02c.81.81 2.13.81 2.94 0l7.02-7.02c.81-.81.81-2.13 0-2.94L12.45 3.58c-.81-.81-2.13-.81-2.94 0L2.5 10.61c-.82.81-.82 2.13 0 2.94zM10 12H5v2h5v3l4-4-4-4v3z"></path></svg></span>
                        </a>
                    </div>
                    
                    <div class="gpf-bottom-controls">
                        <div class="gpf-info-container">
                             <button class="gpf-share-button">
                                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3s3-1.34 3-3-1.34-3-3-3z"/></svg>
                            </button>
                        </div>
                        <div class="gpf-timeline-container">
                            <span class="gpf-current-time">0:00</span>
                            <div class="gpf-progress-bar"><div class="gpf-progress"></div></div>
                            <span class="gpf-duration">0:00</span>
                        </div>
                    </div>
                    
                    <div class="gpf-swipe-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 0 24 24" width="48"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
                    </div>

                    <div class="gpf-share-overlay">
                        <button class="gpf-close-share">&times;</button>
                        <div class="gpf-share-content">
                            <h3>Udostępnij</h3>
                            <div class="gpf-share-links">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($post_link); ?>" target="_blank">Facebook</a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($post_link); ?>&text=<?php echo urlencode(get_the_title()); ?>" target="_blank">X</a>
                                <a href="fb-messenger://share/?link=<?php echo urlencode($post_link); ?>" target="_blank">Messenger</a>
                                <a href="https://api.whatsapp.com/send?text=<?php echo urlencode(get_the_title() . ' ' . $post_link); ?>" target="_blank">WhatsApp</a>
                                <a href="mailto:?subject=<?php echo urlencode(get_the_title()); ?>&body=<?php echo urlencode($post_link); ?>">Email</a>
                                <button class="gpf-copy-link-button">Kopiuj link</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'glampress_wideo_feed', 'gpf_render_wideo_feed_shortcode' );