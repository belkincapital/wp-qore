<?php
/*
   Plugin Name: WP Qore
   Plugin URI: http://wpqore.com/
   Description: For WordPress Standalone and Multisite.
   Version: 2.6
   Author: Jason Jersey
   Author URI: http://twitter.com/degersey
   License: GNU GPL 3.0
   License URI: http://www.gnu.org/licenses/gpl.html
   Text Domain: wp-qore
*/


if ( ! defined( 'ABSPATH' ) ) exit;

/**  Remove dashboard widgets **/
add_action('admin_init', 'run_admin_init');
function run_admin_init() {   
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
    remove_meta_box('dashboard_quick_press', 'dashboard', 'normal');
}

/** Remove admin notices **/
function wphidenag_updates(){
    remove_action( 'admin_notices', 'update_nag', 3 );
}
add_action('admin_menu','wphidenag_updates');

/** Remove version strings **/
function wpqorefunc_remove_cssjs_ver( $src ) {
    if( strpos( $src, '?ver=' ) )
    $src = remove_query_arg( 'ver', $src );
    return $src;
}

add_filter( 'style_loader_src', 'wpqorefunc_remove_cssjs_ver', 10, 2 );
add_filter( 'script_loader_src', 'wpqorefunc_remove_cssjs_ver', 10, 2 );

/** Remove junk from frontend source **/
remove_action('wp_head', 'rsd_link');    
remove_action('wp_head', 'wp_generator');    
remove_action('wp_head', 'feed_links', 2);    
remove_action('wp_head', 'index_rel_link');    
remove_action('wp_head', 'wlwmanifest_link');    
remove_action('wp_head', 'feed_links_extra', 3);    
remove_action('wp_head', 'start_post_rel_link', 10, 0);    
remove_action('wp_head', 'parent_post_rel_link', 10, 0);    
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );

/** Customize left wp-admin footer text **/
function custom_admin_footer2() {
    echo "";
} 
add_filter('admin_footer_text', 'custom_admin_footer2');

/** Remove wp version from admin footer **/
function replace_footer_version2() {
    echo "";
}
add_filter( 'update_footer', 'replace_footer_version2', '1234');

/** Dashboard CSS **/
function load_dash_wp_admin_style(){    
    wp_register_style( 'custom_wp_admin_css', plugins_url( 'welcome_widget.css' , __FILE__ ), false, '1.0' );    
    wp_enqueue_style( 'custom_wp_admin_css' );
}
add_action('admin_enqueue_scripts', 'load_dash_wp_admin_style');

/** Memory graph api **/
function enqueue_jsapi(){    
    wp_register_script( 'enqueue_jsapi_js', 'https://www.google.com/jsapi', false, '1.0' );    
    wp_enqueue_script( 'enqueue_jsapi_js' );
}
add_action('admin_enqueue_scripts', 'enqueue_jsapi');

/** Show post thumbnails in feeds **/
function diw_post_thumbnail_feeds($content) {	global $post;	if(has_post_thumbnail($post->ID)) {		$content = '<div>' . get_the_post_thumbnail($post->ID) . '</div>' . $content;	}	return $content;}add_filter('the_excerpt_rss', 'diw_post_thumbnail_feeds');add_filter('the_content_feed', 'diw_post_thumbnail_feeds');

/** Redirect To Post When Search Query Returns Single Result **/
add_action('template_redirect', 'single_result');
function single_result() {
	if (is_search()) {
		global $wp_query;
		if ($wp_query->post_count == 1) {
			wp_redirect( get_permalink( $wp_query->posts['0']->ID ) );
		}
	}
}

/** Check for HTTP-Refferer and UserAgent, to prevent spammers **/
function check_HTTP_request( $commentdata ) {
    if (!isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] == "") {
        wp_die( __('<b>Please enable referrers in your browser!</b><br><br><small>MSG ID: Refer = none</small>') );
    }
    if (!isset($_SERVER['HTTP_USER_AGENT']) || $_SERVER['HTTP_USER_AGENT'] == "") {
        wp_die( __('<b>Please enable your user agent in your browser!</b><br><br><small>MSG ID: UserAgent = none</small>') );
    }
 
    /** Always remove the URL from the comment author's comment **/
    unset( $commentdata['comment_author_url'] );
    
    return $commentdata;
}
add_action('preprocess_comment', 'check_HTTP_request');
add_action('pre_comment_on_post', 'check_HTTP_request');

/** Add stuff to your feed content **/
function q_addtorssposts($content) {
if(is_feed()){
$content .= '<br><br>(Powered by <a target="_blank" href="http://www.muchsocial.com/?ref=feed">MuchSocial</a>)<br><br>';
}
return $content;
}
add_filter('the_excerpt_rss', 'q_addtorssposts');
add_filter('the_content', 'q_addtorssposts');
				
/* Hide default welcome dashboard and create new one */
function rc_my_welcome_panel() { 
    include('welcome_widget.php');
}

/* Remove old Welcome Panel */
remove_action( 'welcome_panel', 'wp_welcome_panel' );

/* Add new Welcome Panel */
add_action( 'welcome_panel', 'rc_my_welcome_panel' );

/* Always show the welcome panel on subdomains */
if ( is_multisite() ) {
    add_action( 'load-index.php', 'show_welcome_panel_on_multisite' );
} else {
    add_action( 'load-index.php', 'show_welcome_panel_on_multisite' );
}

function show_welcome_panel_on_multisite() {
	$user_id = get_current_user_id();

	if ( 0 == get_user_meta( $user_id, 'show_welcome_panel', true ) ) {
		update_user_meta( $user_id, 'show_welcome_panel', 1 );
        }
}
