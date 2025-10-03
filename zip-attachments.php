<?php
/*
Plugin Name: Download ACF Attachments
Plugin URI: https://www.bytehub.it
Description: Add a button to download all attachments and ACF files of a post/page as a ZIP.
Author: Fabrizio Faraone
Version: 1.1
Author URI: https://www.bytehub.it
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*-------------------------------------------------------------
| Define plugin URL and Path
-------------------------------------------------------------*/
define('bh_attachments_url', plugins_url() ."/".dirname(plugin_basename(__FILE__)));
define('bh_attachments_path', WP_PLUGIN_DIR."/".dirname(plugin_basename(__FILE__)));

/*-------------------------------------------------------------
| Enqueue JS
-------------------------------------------------------------*/
function bh_plugin_scripts() {
	wp_enqueue_script('bh-general', bh_attachments_url . '/js/general.js', array(), '1.1', true );
	wp_localize_script('bh-general', 'bh_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('admin_enqueue_scripts', 'bh_plugin_scripts');

/*-------------------------------------------------------------
| Create ZIP for a post including attachments and ACF files
-------------------------------------------------------------*/
function bh_create_zip_for_post($post_id) {

	if (!$post_id || !is_numeric($post_id)) return new WP_Error('invalid_post_id', 'Invalid Post ID');

	$files_to_zip = array();

	// Standard attachments
	$args = array(
		'post_type'      => 'attachment',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'post_parent'    => $post_id
	);
	$attachments = get_posts($args);
	if ($attachments) {
		foreach ($attachments as $attachment) {
			$file_path = get_attached_file($attachment->ID);
			if ($file_path && file_exists($file_path)) $files_to_zip[] = $file_path;
		}
	}

	// ACF attachments
	$fields = get_fields($post_id);
	if ($fields) {
		foreach ($fields as $field) {
			if (is_array($field) && isset($field['ID'])) {
				$attachment_id = $field['ID'];
				$file_path = get_attached_file($attachment_id);
				if ($file_path && file_exists($file_path)) $files_to_zip[] = $file_path;
			} elseif (is_numeric($field)) {
				$attachment_id = intval($field);
				$file_path = get_attached_file($attachment_id);
				if ($file_path && file_exists($file_path)) $files_to_zip[] = $file_path;
			} elseif (is_array($field)) {
				foreach ($field as $sub_field) {
					if (is_array($sub_field) && isset($sub_field['ID'])) {
						$attachment_id = $sub_field['ID'];
						$file_path = get_attached_file($attachment_id);
						if ($file_path && file_exists($file_path)) $files_to_zip[] = $file_path;
					}
				}
			}
		}
	}

	if (empty($files_to_zip)) return new WP_Error('no_attachments', 'No attachments found for this post');

	// Create temp ZIP
	$upload_dir = wp_upload_dir();
	$zip_path = tempnam($upload_dir['path'], "bh_attachments_") . '.zip';
	$zip = new ZipArchive();
	if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) return new WP_Error('zip_creation_failed', 'Unable to create ZIP file');

	foreach ($files_to_zip as $file) $zip->addFile($file, basename($file));
	$zip->close();

	return $zip_path;
}

/*-------------------------------------------------------------
| AJAX callback to generate ZIP
-------------------------------------------------------------*/
function bh_create_zip_callback() {
	$postId = intval(sanitize_text_field($_POST['postId']));
	$zip_file = bh_create_zip_for_post($postId);
	if (is_wp_error($zip_file)) wp_send_json_error($zip_file->get_error_message());

	// Update download counter
	$counter = get_post_meta($postId, '_bh_counter', true);
	$counter = $counter ? $counter + 1 : 1;
	update_post_meta($postId, '_bh_counter', $counter);

	$pretty_filename = sanitize_file_name(get_the_title($postId));
	$filename_array = explode('/', $zip_file);
	$real_filename = end($filename_array);

	wp_send_json_success(bh_attachments_url . "/download.php?bh_pretty_filename={$pretty_filename}&bh_real_filename={$real_filename}");
}
add_action('wp_ajax_bh_create_zip_file', 'bh_create_zip_callback');
add_action('wp_ajax_nopriv_bh_create_zip_file', 'bh_create_zip_callback');

/*-------------------------------------------------------------
| Add Metabox Button in Admin
-------------------------------------------------------------*/
add_action('admin_init', 'bh_download_metabox');
function bh_download_metabox() {
	add_meta_box(
		'download_metabox',
		__('Tools', 'download-acf-attachments'),
		'bh_author_download_metabox',
		'post',
		'side',
		'default'
	);
}

function bh_author_download_metabox($post, $args) {
	echo '<button class="bh_download_button" onclick="bh_create_zip(\''. $post->ID .'\')"><i class="fas fa-download fa-2x"></i> Download all attachments</button>';
}

/*-------------------------------------------------------------
| Show Button Shortcode
-------------------------------------------------------------*/
function bh_show_button($text='Download Attachments', $counter=false, $counter_format='(%)') {
	$button_counter = '';
	if ($counter === "true") {
		global $wpdb;
		$post_ID = get_the_ID();
		$download_count = get_post_meta($post_ID, '_bh_counter', true);
		$download_count = $download_count ? $download_count : 0;
		$button_counter = str_replace('%', $download_count, $counter_format);
	}
	return '<button class="bh_download_button" onclick="bh_create_zip(\''. get_the_ID() .'\')">'. sanitize_text_field($text) . $button_counter .'</button>';
}

function bh_show_download_button_callback($atts) {
	extract(shortcode_atts(array(
		'text' => 'Download Attachments',
		'counter' => false,
		'counter_format' => '(%)'
	), $atts));
	return bh_show_button($text, $counter, $counter_format);
}
add_shortcode('bh_show_download_button', 'bh_show_download_button_callback');

/*-------------------------------------------------------------
| Admin column for download counter
-------------------------------------------------------------*/
function bh_columns($columns) {
	unset($columns['author']);
	return array_merge($columns, array('bh_counter' => __('Downloads', 'download-acf-attachments')));
}
add_filter('manage_posts_columns', 'bh_columns');
add_filter('manage_pages_columns', 'bh_columns');

function bh_columns_values($column, $post_id) {
	if ($column === 'bh_counter') echo get_post_meta($post_id, '_bh_counter', true) ?: '0';
}
add_action('manage_posts_custom_column', 'bh_columns_values', 10, 2);
add_action('manage_pages_custom_column', 'bh_columns_values', 10, 2);

function bh_sortable_columns($columns) {
	$columns['bh_counter'] = 'bh_counter';
	return $columns;
}
add_action('admin_init', 'bh_sort_all_public_post_types');
function bh_sort_all_public_post_types() {
	foreach (get_post_types(array('public'=>true), 'names') as $post_type_name) {
		add_action('manage_edit-'.$post_type_name.'_sortable_columns', 'bh_sortable_columns');
	}
	add_filter('request', 'bh_column_sort_orderby');
}
function bh_column_sort_orderby($vars) {
	if (isset($vars['orderby']) && $vars['orderby'] === 'bh_counter') {
		$vars = array_merge($vars, array('meta_key'=>'_bh_counter','orderby'=>'meta_value_num'));
	}
	return $vars;
}
