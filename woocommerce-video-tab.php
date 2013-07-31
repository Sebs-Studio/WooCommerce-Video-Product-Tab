<?php
/*
 * Plugin Name: WooCommerce Video Product Tab
 * Plugin URI: http://www.sebs-studio.com/wp-plugins/woocommerce-video-product-tab
 * Description: Extends WooCommerce to allow you to add a Video to the Product page. An additional tab is added on the single products page to allow your customers to view the video you embeded. 
 * Version: 2.3.1
 * Author: Sebs Studio
 * Author URI: http://www.sebs-studio.com
 * Requires at least: 3.1
 * Tested up to: 3.5.1
 *
 * Text Domain: wc_video_product_tab
 * Domain Path: /lang/
 * 
 * Copyright: � 2013 Sebs Studio.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Required minimum version of WordPress.
if(!function_exists('woo_video_tab_min_required')){
	function woo_video_tab_min_required(){
		global $wp_version;
		$plugin = plugin_basename(__FILE__);
		$plugin_data = get_plugin_data(__FILE__, false);

		if(version_compare($wp_version, "3.3", "<")){
			if(is_plugin_active($plugin)){
				deactivate_plugins($plugin);
				wp_die("'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress Admin</a>.");
			}
		}
	}
	add_action('admin_init', 'woo_video_tab_min_required');
}

// Checks if the WooCommerce plugins is installed and active.
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(is_plugin_active('woocommerce/woocommerce.php')){

	/* Localisation */
	$locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-video-product-tab');
	load_textdomain('wc_video_product_tab', WP_PLUGIN_DIR."/".plugin_basename(dirname(__FILE__)).'/lang/wc_video_product_tab-'.$locale.'.mo');
	load_plugin_textdomain('wc_video_product_tab', false, dirname(plugin_basename(__FILE__)).'/lang/');

	if(!class_exists('WooCommerce_Video_Product_Tab')){
		class WooCommerce_Video_Product_Tab{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			private $tab_data = false;

			/**
			 * Gets things started by adding an action to initialize this plugin once
			 * WooCommerce is known to be active and initialized
			 */
			public function __construct(){
				WooCommerce_Video_Product_Tab::$plugin_prefix = 'wc_video_tab_';
				WooCommerce_Video_Product_Tab::$plugin_basefile = plugin_basename(__FILE__);
				WooCommerce_Video_Product_Tab::$plugin_url = plugin_dir_url(WooCommerce_Video_Product_Tab::$plugin_basefile);
				WooCommerce_Video_Product_Tab::$plugin_path = trailingslashit(dirname(__FILE__));
				add_action('woocommerce_init', array(&$this, 'init'));
			}

			/**
			 * Init WooCommerce Video Product Tab extension once we know WooCommerce is active
			 */
			public function init(){
				// backend stuff
				add_filter('plugin_row_meta', array($this, 'add_support_link'), 10, 2);
				add_action('woocommerce_product_write_panel_tabs', array($this, 'product_write_panel_tab'));
				add_action('woocommerce_product_write_panels', array($this, 'product_write_panel'));
				add_action('woocommerce_process_product_meta', array($this, 'product_save_data'), 10, 2);
				// frontend stuff
				if(version_compare(WOOCOMMERCE_VERSION, "2.0", '>=')){
					// WC >= 2.0
					add_filter('woocommerce_product_tabs', array($this, 'video_product_tabs_two'));
				}
				else{
					add_action('woocommerce_product_tabs', array($this, 'video_product_tabs'), 25);
					// in between the attributes and reviews panels.
					add_action('woocommerce_product_tab_panels', array($this, 'video_product_tabs_panel'), 25);
				}
			}

			/**
			 * Add donation link to plugin page.
			 */
			public function add_support_link($links, $file){
				if(!current_user_can('install_plugins')){
					return $links;
				}
				if($file == WooCommerce_Video_Product_Tab::$plugin_basefile){
					$links[] = '<a href="http://docs.sebs-studio.com/user-guide/extension/woocommerce-video-product-tab/" target="_blank">'.__('Docs', 'wc_video_product_tab').'</a>';
					$links[] = '<a href="http://wordpress.org/support/plugin/woocommerce-video-product-tab" target="_blank">'.__('Support', 'wc_video_product_tab').'</a>';
					$links[] = '<a href="http://www.sebs-studio.com/donation/" target="_blank">'.__('Donate', 'wc_video_product_tab').'</a>';
					$links[] = '<a href="http://www.sebs-studio.com/wp-plugins/woocommerce-extensions/" target="_blank">'.__('More WooCommerce Extensions', 'wc_video_product_tab').'</a>';
				}
				return $links;
			}

			/**
			 * Write the video tab on the product view page for WC 2.0+.
			 * In WooCommerce these are handled by templates.
			 */
			public function video_product_tabs_two($tabs){
				global $product;

				if($this->product_has_video_tabs($product)){
					foreach($this->tab_data as $tab){
						$tabs[$tab['id']] = array(
												'title'    => $tab['title'],
												'priority' => 25,
												'callback' => array($this, 'video_product_tabs_panel_content'),
												'content'  => $tab['video']
						);
					}
				}
				return $tabs;
			}

			/**
			 * Write the video tab on the product view page for WC 1.6.6 and below.
			 * In WooCommerce these are handled by templates.
			 */
			public function video_product_tabs(){
				global $product;

				if($this->product_has_video_tabs($product)){
					foreach($this->tab_data as $tab){
						echo "<li><a href=\"#{$tab['id']}\">".$tab['title']."</a></li>";
					}
				}
			}

			/**
			 * Write the video tab panel on the product view page WC 2.0+.
			 * In WooCommerce these are handled by templates.
			 */
			public function video_product_tabs_panel_content(){
				global $product;

				$embed = new WP_Embed(); // Since version 1.2

				if($this->product_has_video_tabs($product)){
					foreach($this->tab_data as $tab){
						if($tab['hide_title'] == '') echo '<h2>'.$tab['title'].'</h2>';
						echo $embed->autoembed(apply_filters('woocommerce_video_product_tab', $tab['video'], $tab['id']));
					}
				}
			}

			/**
			 * Write the video tab panel on the product view page for WC 1.6.6 and below.
			 * In WooCommerce these are handled by templates.
			 */
			public function video_product_tabs_panel(){
				global $product;

				$embed = new WP_Embed(); // Since version 1.2

				if($this->product_has_video_tabs($product)){
					foreach($this->tab_data as $tab){
						echo '<div class="panel" id="'.$tab['id'].'">';
						if($tab['hide_title'] == '') echo '<h2>'.$tab['title'].'</h2>';
						echo $embed->autoembed(apply_filters('woocommerce_video_product_tab', $tab['video'], $tab['id'])); // Altered in version 1.2
						echo '</div>';
					}
				}
			}

			/**
			 * Lazy-load the product_tabs meta data, and return true if it exists,
			 * false otherwise.
			 * 
			 * @return true if there is video tab data, false otherwise.
			 */
			private function product_has_video_tabs($product){
				if($this->tab_data === false){
					$this->tab_data = maybe_unserialize(get_post_meta($product->id, 'woo_video_product_tab', true));
				}
				// tab must at least have a embed code inserted.
				return !empty($this->tab_data) && !empty($this->tab_data[0]) && !empty($this->tab_data[0]['video']);
			}

			/**
			 * Adds a new tab to the Product Data postbox in the admin product interface
			 */
			public function product_write_panel_tab(){
				$tab_icon = WooCommerce_Video_Product_Tab::$plugin_url.'play.png';

				if(version_compare(WOOCOMMERCE_VERSION, "2.0.0") >= 0 ){
					$style = 'padding:5px 5px 5px 28px; background-image:url('.$tab_icon.'); background-repeat:no-repeat; background-position:5px 7px;';
					$active_style = '';
				}
				else{
					$style = 'padding:9px 9px 9px 34px; line-height:16px; border-bottom:1px solid #d5d5d5; text-shadow:0 1px 1px #fff; color:#555555; background-image:url('.$tab_icon.'); background-repeat:no-repeat; background-position:9px 9px;';
					$active_style = '#woocommerce-product-data ul.product_data_tabs li.my_plugin_tab.active a { border-bottom: 1px solid #F8F8F8; }';
				}
				?>
				<style type="text/css">
				#woocommerce-product-data ul.product_data_tabs li.video_tab a { <?php echo $style; ?> }
				<?php echo $active_style; ?>
				</style>
				<?php
				echo "<li class=\"video_tab\"><a href=\"#video_tab\">".__('Video', 'wc_video_product_tab')."</a></li>";
			}

			/**
			 * Adds the panel to the Product Data postbox in the product interface
			 */
			public function product_write_panel(){
				global $post;

				// Pull the video tab data out of the database
				$tab_data = maybe_unserialize(get_post_meta($post->ID, 'woo_video_product_tab', true));

				if(empty($tab_data)){
					$tab_data[] = array('title' => '', 'hide_title' => '', 'video' => '');
				}

				// Display the video tab panel
				foreach($tab_data as $tab){
					echo '<div id="video_tab" class="panel woocommerce_options_panel">';
					$this->wc_video_product_tab_text_input(
															array(
																'id' => '_tab_video_title', 
																'label' => __('Video Title', 'wc_video_product_tab'), 
																'placeholder' => __('Enter your title here.', 'wc_video_product_tab'), 
																'value' => $tab['title'], 
																'style' => 'width:70%;',
															)
					);
					woocommerce_wp_checkbox( array(
												'id' => '_hide_title', 
												'label' => __('Hide Title ?', 'wc_video_product_tab'), 
												'description' => __('Enable this option to hide the title in the video tab.', 'wc_video_product_tab'), 
												'value' => $tab['hide_title'], 
											)
					);
					$this->wc_video_product_tab_textarea_input(
															array(
																'id' => '_tab_video', 
																'label' => __('Embed Code', 'wc_video_product_tab'), 
																'placeholder' => __('Place your video embed code here.', 'wc_video_product_tab'), 
																'value' => $tab['video'], 
																'style' => 'width:70%;height:140px;',
															)
					);
					echo '</div>';
				}
			}

			/**
			 * Output a text input box.
			 */
			public function wc_video_product_tab_text_input($field){
				global $thepostid, $post, $woocommerce;

				$thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
				$field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
				$field['class']         = isset( $field['class'] ) ? $field['class'] : 'short';
				$field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
				$field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
				$field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
				$field['type']          = isset( $field['type'] ) ? $field['type'] : 'text';

				echo '<p class="form-field '.esc_attr($field['id']).'_field '.esc_attr($field['wrapper_class']).'"><label for="'.esc_attr($field['id']).'">'.wp_kses_post($field['label']).'</label><input type="'.esc_attr($field['type']).'" class="'.esc_attr($field['class']).'" name="'.esc_attr($field['name']).'" id="'.esc_attr($field['id']).'" value="'.esc_attr($field['value']).'" placeholder="'.esc_attr($field['placeholder']).'"'.(isset($field['style']) ? ' style="'.$field['style'].'"' : '').' /> ';

				if(!empty($field['description'])){
					if(isset($field['desc_tip'])){
						echo '<img class="help_tip" data-tip="'.esc_attr($field['description']).'" src="'.$woocommerce->plugin_url().'/assets/images/help.png" height="16" width="16" />';
					}
					else{
						echo '<span class="description">'.wp_kses_post($field['description']).'</span>';
					}
				}
				echo '</p>';
			}

			/**
			 * Output a textarea box.
			 */
			public function wc_video_product_tab_textarea_input($field){
				global $thepostid, $post;

				if(!$thepostid) $thepostid = $post->ID;
				if(!isset($field['placeholder'])) $field['placeholder'] = '';
				if(!isset($field['class'])) $field['class'] = 'short';
				if(!isset($field['value'])) $field['value'] = get_post_meta($thepostid, $field['id'], true);

				echo '<p class="form-field '.$field['id'].'_field"><label for="'.$field['id'].'">'.$field['label'].'</label><textarea class="'.$field['class'].'" name="'.$field['id'].'" id="'.$field['id'].'" placeholder="'.$field['placeholder'].'" rows="2" cols="20"'.(isset($field['style']) ? ' style="'.$field['style'].'"' : '').'">'.esc_textarea( $field['value']).'</textarea>';

				if(isset($field['description']) && $field['description']) echo '<span class="description">' .$field['description'] . '</span>';

				echo '</p>';
			}

			/**
			 * Saves the data inputed into the product boxes, as post meta data
			 * identified by the name 'woo_video_product_tab'
			 * 
			 * @param int $post_id the post (product) identifier
			 * @param stdClass $post the post (product)
			 */
			public function product_save_data($post_id, $post){

				$tab_title = stripslashes($_POST['_tab_video_title']);
				if($tab_title == ''){
					$tab_title = __('Video', 'wc_video_product_tab');
				}
				$hide_title = stripslashes($_POST['_hide_title']);
				$tab_video = stripslashes($_POST['_tab_video']);

				if(empty($tab_video) && get_post_meta($post_id, 'woo_video_product_tab', true)){
					// clean up if the video tabs are removed
					delete_post_meta($post_id, 'woo_video_product_tab');
				}
				elseif(!empty($tab_video)){
					$tab_data = array();

					$tab_id = '';
					// convert the tab title into an id string
					$tab_id = strtolower($tab_title);
					$tab_id = preg_replace("/[^\w\s]/",'',$tab_id); // remove non-alphas, numbers, underscores or whitespace 
					$tab_id = preg_replace("/_+/", ' ', $tab_id); // replace all underscores with single spaces
					$tab_id = preg_replace("/\s+/", '-', $tab_id); // replace all multiple spaces with single dashes
					$tab_id = 'tab-'.$tab_id; // prepend with 'tab-' string

					// save the data to the database
					$tab_data[] = array('title' => $tab_title, 'hide_title' => $hide_title, 'id' => $tab_id, 'video' => $tab_video);
					update_post_meta($post_id, 'woo_video_product_tab', $tab_data);
				}
			}
		}
	}

	/* 
	 * Instantiate plugin class and add it to the set of globals.
	 */
	$woocommerce_video_tab = new WooCommerce_Video_Product_Tab();
}
else{
	add_action('admin_notices', 'wc_video_tab_error_notice');
	function wc_video_tab_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>WooCommerce Video Product Tab '.__('requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.', 'wc_video_product_tab').'</p></div>';
		}
	}
}
?>