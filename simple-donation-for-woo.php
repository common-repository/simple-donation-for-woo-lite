<?php
/**
 * Plugin Name:Simple donation For Woo Lite
 * Plugin URI: https://i13websolution.com/product/simple-donation-for-woo/
 * Description:Accept donation easily with your WooCommerce powered shop.
 * Version: 1.0
 * Author: I Thirteen Web Solution 
 * Author URI: https://www.i13websolution.com
 * WC requires at least: 3.2
 * WC tested up to: 6.0
 * Text Domain:simple-donation-for-woo
 * Domain Path: languages/
 */
//Check if WooCommerce is active

class I13_Woo_SImple_Donation_Lite {

	public function __construct() {


				global $wpdb;

		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');
		add_action('plugins_loaded', array($this, 'i13_woo_load_lang_for_woo_simple_donation'));
		add_action('get_footer', array($this, 'i13_woo_add_css_js_for_woo_donation_cart'), 999);

		add_action('wp_ajax_fcf_get_cart_data', array($this, 'fcf_get_cart_data_callback'));
		add_action('wp_ajax_nopriv_fcf_get_cart_data', array($this, 'fcf_get_cart_data_callback'));

		add_filter('woocommerce_add_to_cart_validation', array($this, 'i13_woo_simple_donation_validation'), 10, 3);
		add_action('wp_ajax_sdfw_add_donation', array($this, 'sdfw_add_donation_callback'));
		add_action('wp_ajax_nopriv_sdfw_add_donation', array($this, 'sdfw_add_donation_callback'));
		if (1 == intval($i13_simple_donations_settings['show_donation_form_on_cart'])) {

			add_action('woocommerce_after_cart_table', array($this, 'action_woocommerce_after_cart_contents'), 10, 0);
			add_action('woocommerce_after_cart', array($this, 'i13_add_scripts_after_cart_action'), 10, 0);
		}
				add_filter('widget_text', 'do_shortcode');
		add_action('woocommerce_before_calculate_totals', array($this, 'woo_add_donation'));
		add_action('woocommerce_remove_cart_item', array($this, 'i13_woo_simple_donation_remove_sessions'), 10, 2);
		add_filter('user_has_cap', array($this, 'i13_woo_simple_donation_admin_cap_list'), 10, 4);
		
		add_action('admin_menu', array($this, 'i13_woo_simple_donation_add_admin_menu'));
		register_activation_hook(__FILE__, array($this, 'i13_woo_simple_donation_check_network'));
		add_action('admin_init', array($this, 'i13_woo_simple_donation_multisite_check_activated'));
		add_action('wp_ajax__ajax_donation_display', array($this, '_ajax_donation_display_callback'));
		add_action('wp_ajax__ajax_fetch_sts_history', array($this, '_ajax_fetch_sts_history_callback'));
		add_action('admin_footer', array($this, 'fetch_ts_script'));
		add_action('wp_ajax_add_update_donation_amt', array($this, 'add_update_donation_amt_callback'));
		add_action('wp_ajax_delete_donation_amt', array($this, 'delete_donation_amt_callback'));
		add_filter('woocommerce_add_cart_item_data', array($this, 'i13_force_multiple_donation'), 10, 2);
		
		add_filter('woocommerce_order_item_name', array($this, 'i13_filter_woocommerce_order_item_name'), 10, 2);
		add_filter('woocommerce_checkout_create_order_line_item', array($this, 'i13_checkout_create_order_line_item'), 10, 4);

		
		if (1 == intval($i13_simple_donations_settings['show_donation_form_on_checkout'])) {
			add_action('woocommerce_review_order_before_payment', array($this, 'Show_donation_form_on_checkout'), 20, 3);
		}
		add_filter('woocommerce_cart_item_permalink', array($this, 'remove_donation_product_perma_link'), 20, 3);
		add_filter('woocommerce_order_item_permalink', array($this, 'i13_remove_donation_filter_order_item_permalink_callback'), 10, 3);
		add_shortcode('i13_donation_print_form', array($this, 'i13_donation_print_form_func'));
                
                
	}

        
        

	public function remove_donation_product_perma_link( $product_get_permalink_cart_item, $cart_item, $cart_item_key) {

		$productId = $cart_item['product_id'];

		$product = wc_get_product($productId);
		if ($product) {

			if ($product->get_sku() == 'i13_donation_single') {

				return null;
			}
		}
		return $product_get_permalink_cart_item;
	}

	

	

	
	public function i13_checkout_create_order_line_item( $item, $cart_item_key, $values, $order) {


		$product = wc_get_product($item->get_product_id());
		if ($product && 'i13_donation_single' == $product->get_sku()) {

			if (isset($values['donation_amt'])) {
				$item->update_meta_data('donation_amt', $values['donation_amt']);
			}

			
		}
	}

	public function i13_filter_woocommerce_order_item_name( $item_name, $item) {

		return $item_name;
	}

	

	public function i13_force_multiple_donation( $cart_item_data, $product_id) {

		
		$product = wc_get_product($product_id);
		if ($product) {

			if ($product->get_sku() == 'i13_donation_single') {

								$retrieved_nonce = '';
				if (isset($_POST['vNonce']) && '' != $_POST['vNonce']) {

						$retrieved_nonce = sanitize_text_field($_POST['vNonce']);
				}

				if (!wp_verify_nonce($retrieved_nonce, 'add_to_cart_donation')) {


						wp_die(esc_html(__('Security check fail', 'simple-donation-for-woo')));
				}

				$unique_cart_item_key = md5(microtime() . rand());
				$cart_item_data['unique_key'] = $unique_cart_item_key;

				if (isset($_POST['donation_amt']) && !empty($_POST['donation_amt'])) {

					$cart_item_data['donation_amt'] = sanitize_text_field($_POST['donation_amt']);
				}
				
			}
		}

		return $cart_item_data;
	}

	public function delete_donation_amt_callback() {


		if (isset($_POST) && is_array($_POST)) {


			$retrieved_nonce = '';
			$delete_id = 0;
			if (isset($_POST['vNonce']) && '' != $_POST['vNonce']) {

				$retrieved_nonce = sanitize_text_field($_POST['vNonce']);
			}
			if (isset($_POST['del_id']) && '' != $_POST['del_id']) {

				$delete_id = intval($_POST['del_id']);
			}

			if (!wp_verify_nonce($retrieved_nonce, 'save_data')) {


				wp_die('Security check fail');
			}


			$options = get_option('i13_simple_donations_values');
			if (false != $options) {

				if ($delete_id >= 0) {

					unset($options[$delete_id]);
				}

				update_option('i13_simple_donations_values', $options);
			}
		}


		$options = get_option('i13_simple_donations_values');

		$result = array('msg' => 'success');
		 wp_send_json($result);
		exit;
	}

	public function add_update_donation_amt_callback() {


		if (isset($_POST) && is_array($_POST)) {


			$retrieved_nonce = '';
			$edit_id = 0;
			if (isset($_POST['vNonce']) && '' != $_POST['vNonce']) {

				$retrieved_nonce = sanitize_text_field($_POST['vNonce']);
			}
			if (isset($_POST['edit_id']) && '' != $_POST['edit_id']) {

				$edit_id = intval($_POST['edit_id']);
			}

			if (!wp_verify_nonce($retrieved_nonce, 'save_data')) {


				wp_die(esc_html(__('Security check fail', 'simple-donation-for-woo')));
			}


			$amt = '';

			if (isset($_POST['amt']) && intval($_POST['amt']) > 0) {

				if (is_int($_POST['amt'])) {

					$amt = intval($_POST['amt']);
				} else {

					$amt = floatval($_POST['amt']);
				}
			}

			$options = get_option('i13_simple_donations_values');
			if (false != $options) {

				$key = max(array_keys($options));
				$key++;

				if ($edit_id >= 0) {

					$options[$edit_id] = array('id' => $edit_id, 'donation' => $amt);
				} else {

					$options[$key] = array('id' => $key, 'donation' => $amt);
				}

				update_option('i13_simple_donations_values', $options);
			} else {

				$options = array();
				$options[0] = array('id' => 0, 'donation' => $amt);
				update_option('i13_simple_donations_values', $options);
			}
		}


		$options = get_option('i13_simple_donations_values');

		$result = array('msg' => 'success');
		wp_send_json($result);
		exit;
	}

	

	public function i13_woo_simple_donation_multisite_check_activated() {

		
		global $wpdb;
		$activated = get_site_option('i13_woo_simple_donation_multisite_activated');

		if (false == $activated) {
			return false;
		} else {

			$blog_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM ' . esc_sql($wpdb->blogs) . ' where 1=%d', 1));
			foreach ($blog_ids as $blog_id) {
				if (!in_array($blog_id, $activated)) {
					switch_to_blog($blog_id);
					$this->i13_woo_simple_donation_install();
					$activated[] = $blog_id;
				}
			}
			restore_current_blog();
			update_site_option('i13_woo_simple_donation_multisite_activated', $activated);
		}
	}

	

	public function i13_woo_simple_donation_check_network( $network_wide) {

		if (is_multisite() && $network_wide) {
			// running on multi site with network install
			global $wpdb;
			$activated = array();
			$blog_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM ' . esc_sql($wpdb->blogs) . ' where 1=%d', 1));
			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);
				$this->i13_woo_simple_donation_install();
				$activated[] = $blog_id;
			}
			restore_current_blog();
			update_site_option('i13_woo_simple_donation_multisite_activated', $activated);
		} else {

			$this->i13_woo_simple_donation_install();
		}
	}

	public function i13_woo_simple_donation_add_admin_menu() {



		$hook_suffix_dn = add_menu_page(__('Woo Simple Donation', 'simple-donation-for-woo'), __('Woo Simple Donation', 'simple-donation-for-woo'), 'i13_woo_manage_donation_settings', 'i13_woo_simple_donations_settings', array($this, 'i13_woo_simple_donations_settings_func'), 'dashicons-money-alt');
		$hook_suffix_dn = add_submenu_page('i13_woo_simple_donations_settings', __('Donation Settings', 'simple-donation-for-woo'), __('Donation Settings', 'simple-donation-for-woo'), 'i13_woo_manage_donation_settings', 'i13_woo_simple_donations_settings', array($this, 'i13_woo_simple_donations_settings_func'));
		$hook_suffix_dn4 = add_submenu_page('i13_woo_simple_donations_settings', __('Sucess/Error Messages', 'simple-donation-for-woo'), __('Sucess/Error Messages', 'simple-donation-for-woo'), 'i13_woo_manage_donation_error_msg', 'i13_woo_manage_donation_error_msg', array($this, 'i13_woo_manage_donation_error_msg'));
		

		add_action('load-' . $hook_suffix_dn, array($this, 'i13_woo_simple_donation_admin_init'));
		add_action('load-' . $hook_suffix_dn2, array($this, 'i13_woo_simple_donation_admin_init'));
		add_action('load-' . $hook_suffix_dn3, array($this, 'i13_woo_simple_donation_admin_init'));
		add_action('load-' . $hook_suffix_dn4, array($this, 'i13_woo_simple_donation_admin_init'));
	}

	public function i13_woo_simple_donation_admin_init() {


		$url = plugin_dir_url(__FILE__);

		wp_enqueue_style('admincss', plugins_url('/admin/css/admincss.css', __FILE__), array(), '1.0');

		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery.validate', $url . '/admin/js/jquery.validate.js', array(), '1.0');

		$currentScreen = get_current_screen();
		if ('woo-simple-donation_page_i13_woo_manage_donation_reports' == $currentScreen->id) {

			wp_enqueue_script('i13_moment.min', $url . '/admin/js/i13_moment.min.js', array(), '1.0');
			wp_enqueue_script('i13_daterangepicker', $url . '/admin/js/i13_daterangepicker.js', array(), '1.0');
			wp_enqueue_script('chosen.jquery.min', $url . '/admin/js/chosen.jquery.min.js', array(), '1.0');
			wp_enqueue_style('i13_daterangepicker', plugins_url('/admin/css/i13_daterangepicker.css', __FILE__), array(), '1.0');
			wp_enqueue_style('chosen.min', plugins_url('/admin/css/chosen.min.css', __FILE__), array(), '1.0');
		}
				
				
	}
	
	public function i13_woo_simple_donations_settings_func() {

		if (!current_user_can('i13_woo_manage_donation_settings')) {

			wp_die(esc_html_e('Access Denied', 'simple-donation-for-woo'));
		}

		$retrieved_nonce = '';
		if (isset($_POST['add_edit_nonce']) && '' != $_POST['add_edit_nonce']) {

			$retrieved_nonce = sanitize_text_field($_POST['add_edit_nonce']);
		}



		if (wp_verify_nonce($retrieved_nonce, 'action_news_category_add_edit') && isset($_POST['btnsave'])) {

			$donation_type = ( isset($_POST['donation_type']) && !empty($_POST['donation_type']) ) ? intval($_POST['donation_type']) : 1;
			$show_donation_form_on_cart = ( isset($_POST['show_donation_form_on_cart']) && !empty($_POST['show_donation_form_on_cart']) ) ? intval($_POST['show_donation_form_on_cart']) : 1;
			$show_donation_form_on_checkout = ( isset($_POST['show_donation_form_on_checkout']) && !empty($_POST['show_donation_form_on_checkout']) ) ? intval($_POST['show_donation_form_on_checkout']) : 1;
			$donation_label = ( isset($_POST['donation_label']) && !empty($_POST['donation_label']) ) ? sanitize_text_field($_POST['donation_label']) : '';
			$donation_lbl = ( isset($_POST['donation_lbl']) && !empty($_POST['donation_lbl']) ) ? sanitize_text_field($_POST['donation_lbl']) : '';
			$donation_placeholder = ( isset($_POST['donation_placeholder']) && !empty($_POST['donation_placeholder']) ) ? sanitize_text_field($_POST['donation_placeholder']) : '';
			$add_donation_button_label = ( isset($_POST['add_donation_button_label']) && !empty($_POST['add_donation_button_label']) ) ? sanitize_text_field($_POST['add_donation_button_label']) : '';
			$donation_amount_label = ( isset($_POST['donation_amount_label']) && !empty($_POST['donation_amount_label']) ) ? sanitize_text_field($_POST['donation_amount_label']) : '';
			
			$i13_simple_donations_settings = array(
				'donation_type' => $donation_type,
				'show_donation_form_on_cart' => $show_donation_form_on_cart,
				'show_donation_form_on_checkout' => $show_donation_form_on_checkout,
				'donation_label' => $donation_label,
				'donation_lbl' => $donation_lbl,
				'donation_placeholder' => $donation_placeholder,
				'add_donation_button_label' => $add_donation_button_label,
				'donation_amount_label' => $donation_amount_label,
				
			);

						
			update_option('i13_simple_donations_settings', $i13_simple_donations_settings);
						
						$i13_woo_sd_messages=array();
						$i13_woo_sd_messages['type']='succ';
						$i13_woo_sd_messages['message']=__('Settings updated successfully.', 'simple-donation-for-woo');
						update_option('i13_woo_sd_messages', $i13_woo_sd_messages);
		}

		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');
				
		$name = '';
		$vNonce = wp_create_nonce('save_data');
				
				$messages = get_option('i13_woo_sd_messages');
		$type = '';
		$message = '';
		if (isset($messages ['type']) && ''!=$messages ['type']) {

			$type = $messages ['type'];
			$message = $messages ['message'];
		}

		if (trim($type)=='err') {
			echo "<div class='notice notice-error is-dismissible'><p>";
			echo esc_html($message);
			echo '</p></div>';
		} else if ('succ'==trim($type)) {
			echo "<div class='notice notice-success is-dismissible'><p>";
			echo esc_html($message);
			echo '</p></div>';
		}
		update_option('i13_woo_sd_messages', array ());
							  
		?>
		<div class="wrap">

                        <span><h3 style="color: blue;"><a target="_blank" href="https://i13websolution.com/product/simple-donation-for-woocommerce/"><?php echo __('UPGRADE TO PRO VERSION', 'simple-donation-for-woo'); ?></a></h3></span>
                        
			<h2><?php echo esc_html(__('Donation Settings', 'simple-donation-for-woo')); ?></h2>
			<br>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-1">
                                    
									<div class='notice notice-error is-dismissible'><p><?php echo esc_html_e('To print donation forms other than the cart and checkout page please use the shortcode ', 'simple-donation-for-woo'); ?><input type text style="text-align:left;border:none;width:172px" readonly="" onclick="this.focus(); this.select()" value="[i13_donation_print_form]" /></p></div>
					<div id="post-body-content">
						<form method="post" action="" id="addupdatesettings" name="addupdatesettings">

							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Type', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="radio" id="donation_type_user_defined" name="donation_type" class="donation_type" value="1" 
									<?php
									if (1 == intval($i13_simple_donations_settings['donation_type'])) :
										?>
												checked="" <?php endif; ?> ><?php echo esc_html_e('User Input', 'simple-donation-for-woo'); ?> &nbsp; <input  class="donation_type" type="radio" name="donation_type" value="2" id="donation_type_pref_defined"  
											<?php
											if (2 == intval($i13_simple_donations_settings['donation_type'])) :
												?>
												checked="" <?php endif; ?>> <?php echo esc_html_e('User select from predefined values', 'simple-donation-for-woo'); ?>
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>
														<div class="stuffbox predefined_donation_values_div" id="namediv" style="width:50%;display: none">
								<br/>
								<h3><label for="link_name"><?php echo esc_html(__('Donation List Values', 'simple-donation-for-woo')); ?></label><a id="add_new_dn_amt" href="#" class="page-title-action"><?php echo esc_html_e('Add New', 'simple-donation-for-woo'); ?></a></h3>
								<div class="inside">
									<div id="predefined_donation_values">

										<input type="hidden" name="page" value="<?php echo isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0; ?>" />
										<input type="hidden" name="order" value="<?php echo isset($_REQUEST['order']) ? esc_html(sanitize_text_field($_REQUEST['order'])) : ''; ?>" />
										<input type="hidden" name="orderby" value="<?php echo isset($_REQUEST['orderby']) ? esc_html(sanitize_text_field($_REQUEST['orderby'])) : ''; ?>" />

										<div id="ts-history-table" style="">
											<?php
											wp_nonce_field('ajax-custom-list-nonce', '_ajax_custom_list_nonce');
											?>
										</div>



									</div>    
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>
							
														
							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Show donation form on cart page', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="radio" id="show_donation_form_on_cart_yes" name="show_donation_form_on_cart" class="show_donation_form_on_cart" value="1" 
									<?php
									if (1 == intval($i13_simple_donations_settings['show_donation_form_on_cart'])) :
										?>
												checked="" <?php endif; ?> ><?php echo esc_html_e('Yes', 'simple-donation-for-woo'); ?> &nbsp; <input  class="show_donation_form_on_cart" type="radio" name="show_donation_form_on_cart" value="2" id="show_donation_form_on_cart_no"  
											<?php
											if (2 == intval($i13_simple_donations_settings['show_donation_form_on_cart'])) :
												?>
												checked="" <?php endif; ?>> <?php echo esc_html_e('No', 'simple-donation-for-woo'); ?>
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>
							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Show donation form on checkout page', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="radio" id="show_donation_form_on_checkout_yes" name="show_donation_form_on_checkout" class="show_donation_form_on_checkout" value="1" 
									<?php
									if (1 == intval($i13_simple_donations_settings['show_donation_form_on_checkout'])) :
										?>
												checked="" <?php endif; ?> ><?php echo esc_html_e('Yes', 'simple-donation-for-woo'); ?> &nbsp; <input  class="show_donation_form_on_checkout" type="radio" name="show_donation_form_on_checkout" value="2" id="show_donation_form_on_checkout_no"  
											<?php
											if (2 == intval($i13_simple_donations_settings['show_donation_form_on_checkout'])) :
												?>
												checked="" <?php endif; ?>> <?php echo esc_html_e('No', 'simple-donation-for-woo'); ?>
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>
							

							

							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Box Label', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_label" name="donation_label" class="donation_label" value="<?php echo esc_html($i13_simple_donations_settings['donation_label']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>

							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Label', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_lbl" name="donation_lbl" class="donation_label" value="<?php echo esc_html($i13_simple_donations_settings['donation_lbl']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>
							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Placeholder', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_placeholder" name="donation_placeholder" class="donation_label" value="<?php echo esc_html($i13_simple_donations_settings['donation_placeholder']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>

							

							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Amount Selection Placeholder', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_amount_label" name="donation_amount_label" class="donation_label" value="<?php echo esc_html($i13_simple_donations_settings['donation_amount_label']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>

							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Add Donation Button Label', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="add_donation_button_label" name="add_donation_button_label" class="donation_label" value="<?php echo esc_html($i13_simple_donations_settings['add_donation_button_label']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>

							

							<?php wp_nonce_field('action_news_category_add_edit', 'add_edit_nonce'); ?>      
							<input type="submit" name="btnsave" id="btnsave" value="<?php echo esc_html(__('Save Changes', 'simple-donation-for-woo')); ?>" class="button-primary">

						</form> 
						<?php add_thickbox(); ?>
						<div id="my-content-id" style="display:none;">
							<form id="add_update_list_donation" name="add_update_list_donation"> 
								<table class="form-table">

									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="amt"><?php echo esc_html(__('Amount', 'simple-donation-for-woo')); ?></label>
										</th>
										<td class="forminp forminp-text">
											<input min="0" name="amt" id="amt" type="number" style="" value="" class="" placeholder="" autocomplete="off"> <p class="description"><?php echo esc_html(__('For example 100', 'simple-donation-for-woo')); ?></p>							
											<div class="error_label"></div>
										</td>
									</tr>
								</table>
								<p class="submit">
									<input type="hidden" name="edit_id" id="edit_id" value="-1" />
									<button name="save" id="save_data" class="button-primary woocommerce-save-button" type="button" value="<?php echo esc_html(__('Save', 'simple-donation-for-woo')); ?>"><?php echo esc_html(__('Save', 'simple-donation-for-woo')); ?></button>
									<img class="loader_simple" style="vertical-align:middle;width:20px;display: none" src="<?php echo esc_html(plugins_url('/public/images/bx_loader.gif', __FILE__)); ?>"/>
								</p>
							</form>
													
						</div>

						<script>

							jQuery("#add_new_dn_amt").click(function (e) {

								jQuery("#edit_id").val('-1');
								jQuery("#amt").val('');
								tb_show("<?php echo esc_html(__('Add Donation Amount', 'simple-donation-for-woo')); ?>", "#TB_inline?width=600&height=350&inlineId=my-content-id");

							})

							jQuery('input[type=radio][name=donation_type]').change(function () {
								if (this.value == '1') {

									jQuery(".predefined_donation_values_div").hide();
								} else if (this.value == '2') {

									jQuery(".predefined_donation_values_div").show();
								}
							});
														
							

							jQuery(document).ready(function () {



								if (jQuery('input[type=radio][name=donation_type]:checked').val() == "1") {
									jQuery(".predefined_donation_values_div").hide();
								} else if (jQuery('input[type=radio][name=donation_type]:checked').val() == "2") {

									jQuery(".predefined_donation_values_div").show();
								}
																
								

							});

							jQuery("body").on('click', '.edit_donation', function (e) {


								jQuery("#amt").val(jQuery(this).attr('data-donation'));
								jQuery("#edit_id").val(jQuery(this).attr('data-id'));

								tb_show("<?php echo esc_html(__('Update Donation Amount', 'simple-donation-for-woo')); ?>", "#TB_inline?width=600&height=350&inlineId=my-content-id");

							})
							jQuery("body").on('click', '.delete_donation', function (e) {

								var r = confirm("<?php echo esc_html(__('Are you sure want to delete? This action can not be undone.', 'simple-donation-for-woo')); ?>");
								if (r == true) {
									var del_id = jQuery(this).attr('data-id');
									jQuery(".loader_simple_" + del_id).show();
									var data = {
										'action': 'delete_donation_amt',
										'del_id': del_id,
										'vNonce': '<?php echo esc_html($vNonce); ?>'
									};

									jQuery.post(ajaxurl, data, function (response) {
										/*response = jQuery.parseJSON(response);*/
										if (response.msg == 'success') {

											list.display();

										}


									});

								}
							})
							jQuery("#save_data").click(function (e) {

								jQuery('#add_update_list_donation').validate();

								if (jQuery("#add_update_list_donation").valid()) {

									jQuery(".loader_simple").show();

									var data = {
										'action': 'add_update_donation_amt',
										'amt': jQuery("#amt").val(),
										'edit_id': jQuery("#edit_id").val(),
										'vNonce': '<?php echo esc_html($vNonce); ?>'
									};

									jQuery.post(ajaxurl, data, function (response) {
										/*console.log(response);
										response = jQuery.parseJSON(response);*/
										if (response.msg == 'success') {

											jQuery(".loader_simple").hide();
											tb_remove();
											list.display();

										}


									});
								}

							})

							jQuery(document).ready(function () {

								jQuery("#predefined_donation_values_div").trigger("change");

								jQuery("#addupdatesettings").validate({
									rules: {
										donation_type: {
											required: true
										},
										
										donation_label: {
											maxlength: 200
										},
										donation_lbl: {
											maxlength: 200
										},
										
										donation_placeholder: {
											maxlength: 200
										},
										
										donation_amount_label: {
											maxlength: 200
										},
										add_donation_button_label: {
											maxlength: 200
										}



									},
									messages: {
										donation_type: "<?php echo esc_html(__('This field is required.', 'simple-donation-for-woo')); ?>",
										donation_placeholder: {
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										},
										donation_label: {
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										},
										
										donation_amount_label: {
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										},
										add_donation_button_label: {
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										}
									},
									errorClass: "image_error",
									errorPlacement: function (error, element) {
										jQuery(element).closest('div').find('.error_label').html(error);
									}


								});

								jQuery("#add_update_list_donation").validate({
									rules: {

										amt: {
											required: true,
											number: true
										}


									},
									messages: {

										amt: {
											required: "<?php echo esc_html(__('This field is required.', 'simple-donation-for-woo')); ?>",
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										}

									},
									errorClass: "image_error",
									errorPlacement: function (error, element) {
										jQuery(element).closest('td').find('.error_label').html(error);
									}


								})

							});

						</script> 

					</div>
				</div>        
			</div>

		</div>      


		<?php
	}

	public function i13_woo_manage_donation_error_msg() {


		if (!current_user_can('i13_woo_manage_donation_error_msg')) {

			wp_die(esc_html_e('Access Denied', 'simple-donation-for-woo'));
		}

				$retrieved_nonce = '';
		if (isset($_POST['add_edit_nonce']) && '' != $_POST['add_edit_nonce']) {

			$retrieved_nonce = sanitize_text_field($_POST['add_edit_nonce']);
		}



		if (wp_verify_nonce($retrieved_nonce, 'action_news_category_add_edit') && isset($_POST['btnsave'])) {
		


			$failed_load_product = ( isset($_POST['failed_load_product']) && !empty($_POST['failed_load_product']) ) ? sanitize_text_field($_POST['failed_load_product']) : ''; 
			$donation_cant_zero = ( isset($_POST['donation_cant_zero']) && !empty($_POST['donation_cant_zero']) ) ? sanitize_text_field($_POST['donation_cant_zero']) : ''; 
			$donation_added = ( isset($_POST['donation_added']) && !empty($_POST['donation_added']) ) ? sanitize_text_field($_POST['donation_added']) : ''; 
			$donation_updated = ( isset($_POST['donation_updated']) && !empty($_POST['donation_updated']) ) ? sanitize_text_field($_POST['donation_updated']) : '';  
			$verification_failed = ( isset($_POST['verification_failed']) && !empty($_POST['verification_failed']) ) ? sanitize_text_field($_POST['verification_failed']) : '';

			$i13_simple_donations_msg_settings = array(
				'failed_load_product' => $failed_load_product,
				'donation_cant_zero' => $donation_cant_zero,
				'donation_added' => $donation_added,
				'donation_updated' => $donation_updated,
				'verification_failed' => $verification_failed
			);

			update_option('i13_simple_donations_msg_settings', $i13_simple_donations_msg_settings);
						
						$i13_woo_sd_messages=array();
						$i13_woo_sd_messages['type']='succ';
						$i13_woo_sd_messages['message']=__('Messages updated successfully.', 'simple-donation-for-woo');
						update_option('i13_woo_sd_messages', $i13_woo_sd_messages);
		

		}

		$i13_simple_donations_msg_settings = get_option('i13_simple_donations_msg_settings');

		$name = '';
				
				
				$messages = get_option('i13_woo_sd_messages');
		$type = '';
		$message = '';
		if (isset($messages ['type']) && ''!=$messages ['type']) {

			$type = $messages ['type'];
			$message = $messages ['message'];
		}

		if (trim($type)=='err') {
			echo "<div class='notice notice-error is-dismissible'><p>";
			echo esc_html($message);
			echo '</p></div>';
		} else if ('succ'==trim($type)) {
			echo "<div class='notice notice-success is-dismissible'><p>";
			echo esc_html($message);
			echo '</p></div>';
		}
		update_option('i13_woo_sd_messages', array ());
				
		?>
		<div class="wrap">

                        <span><h3 style="color: blue;"><a target="_blank" href="https://i13websolution.com/product/simple-donation-for-woocommerce/"><?php echo __('UPGRADE TO PRO VERSION', 'simple-donation-for-woo'); ?></a></h3></span>
                     
			<h2><?php echo esc_html(__('Sucess/Error messages', 'simple-donation-for-woo')); ?></h2>
			<br>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-1">

					<div id="post-body-content">
						<form method="post" action="" id="addupdatesettings" name="addupdatesettings">



							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Failed to load product', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="failed_load_product" name="failed_load_product" class="donation_label" value="<?php echo esc_html($i13_simple_donations_msg_settings['failed_load_product']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>


							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation required', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_cant_zero" name="donation_cant_zero" class="donation_label" value="<?php echo esc_html($i13_simple_donations_msg_settings['donation_cant_zero']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>
							


							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Added', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_added" name="donation_added" class="donation_label" value="<?php echo esc_html($i13_simple_donations_msg_settings['donation_added']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>


							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Donation Updated', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="donation_updated" name="donation_updated" class="donation_label" value="<?php echo esc_html($i13_simple_donations_msg_settings['donation_updated']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>


							<div class="stuffbox" id="namediv" style="width:50%">
								<h3><label for="link_name"><?php echo esc_html(__('Verification failed', 'simple-donation-for-woo')); ?></label></h3>
								<div class="inside">
									<input  type="text" id="verification_failed" name="verification_failed" class="donation_label" value="<?php echo esc_html($i13_simple_donations_msg_settings['verification_failed']); ?>" />
									<div style="clear:both"></div>
									<div></div>
									<div style="clear:both" class="error_label"></div>
								</div>
							</div>




							<?php wp_nonce_field('action_news_category_add_edit', 'add_edit_nonce'); ?>      
							<input type="submit" name="btnsave" id="btnsave" value="<?php echo esc_html(__('Save Changes', 'simple-donation-for-woo')); ?>" class="button-primary">

						</form> 


						<script>


							jQuery(document).ready(function () {


								jQuery("#addupdatesettings").validate({
									rules: {

										donation_amount_label: {
											maxlength: 200
										},
										add_donation_button_label: {
											maxlength: 200
										}



									},
									messages: {

										donation_amount_label: {
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										},
										add_donation_button_label: {
											maxlength: jQuery.validator.format("<?php echo esc_html(__('Please enter no more than {0} characters.', 'simple-donation-for-woo')); ?>"),

										}
									},
									errorClass: "image_error",
									errorPlacement: function (error, element) {
										jQuery(element).closest('div').find('.error_label').html(error);
									}


								});



							});

						</script> 

					</div>
				</div>        
			</div>

		</div>      


		<?php
	}

	/**
	 * Action wp_ajax for fetching ajax_response
	 */
	public function _ajax_fetch_sts_history_callback() {

		include_once dirname(__FILE__) . '/inc/donation_values_list.php';

		$wp_list_table = new I13_List_Table();
		$wp_list_table->ajax_response();
	}

	/**
	 * Action wp_ajax for fetching the first time table structure
	 */
	public function _ajax_donation_display_callback() {

		include_once dirname(__FILE__) . '/inc/donation_values_list.php';
		$wp_list_table = new I13_List_Table();

		check_ajax_referer('ajax-custom-list-nonce', '_ajax_custom_list_nonce', true);
		$wp_list_table->prepare_items();

		ob_start();
		$wp_list_table->display();
		$display = ob_get_clean();
				wp_send_json(array(
			'display' => $display
				));
				die;
	}

	public function fetch_ts_script() {
		$screen = get_current_screen();

		?>

		
		<?php
		if ('toplevel_page_i13_woo_simple_donations_settings'!=$screen->id) {
			return;
		}
		?>

		<script type="text/javascript">

					(function ($) {

						list = {


							display: function () {

								$.ajax({

									url: ajaxurl,
									dataType: 'json',
									data: {
										_ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
										action: '_ajax_donation_display'
									},
									success: function (response) {

										$("#ts-history-table").html(response.display);

										$("tbody").on("click", ".toggle-row", function (e) {
											e.preventDefault();
											$(this).closest("tr").toggleClass("is-expanded")
										});

										list.init();
									}
								});

							},

							init: function () {

								var timer;
								var delay = 500;

								$('.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a').on('click', function (e) {
									e.preventDefault();
									var query = this.search.substring(1);

									var data = {
										paged: list.__query(query, 'paged') || '1',
										order: list.__query(query, 'order') || 'asc',
										orderby: list.__query(query, 'orderby') || 'title'
									};
									list.update(data);
								});

								$('input[name=paged]').on('keyup', function (e) {

									if (13 == e.which)
										e.preventDefault();

									var data = {
										paged: parseInt($('input[name=paged]').val()) || '1',
										order: $('input[name=order]').val() || 'asc',
										orderby: $('input[name=orderby]').val() || 'title'
									};

									window.clearTimeout(timer);
									timer = window.setTimeout(function () {
										list.update(data);
									}, delay);
								});

								$('#email-sent-list').on('submit', function (e) {

									e.preventDefault();

								});

							},

							update: function (data) {

								$.ajax({

									url: ajaxurl,
									data: $.extend(
											{
												_ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
												action: '_ajax_fetch_sts_history',
											},
											data
											),
									success: function (response) {

										var response = $.parseJSON(response);

										if (response.rows.length)
											$('#the-list').html(response.rows);
										if (response.column_headers.length)
											$('thead tr, tfoot tr').html(response.column_headers);
										if (response.pagination.bottom.length)
											$('.tablenav.top .tablenav-pages').html($(response.pagination.top).html());
										if (response.pagination.top.length)
											$('.tablenav.bottom .tablenav-pages').html($(response.pagination.bottom).html());

										list.init();
									}
								});
							},

														__query: function (query, variable) {

								var vars = query.split("&");
								for (var i = 0; i < vars.length; i++) {
									var pair = vars[i].split("=");
									if (pair[0] == variable)
										return pair[1];
								}
								return false;
							},
						}

						list.display();

					})(jQuery);

		</script>
		<?php
	}

	public function i13_woo_simple_donation_install() {


		$file = dirname(__FILE__) . '/admin/images/i13_donation.png';
		$filename = basename($file);
		$attachment_id = 0;
		$upload_file = wp_upload_bits($filename, null, file_get_contents($file));
		if (!$upload_file['error']) {
			$wp_filetype = wp_check_filetype($filename, null);
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_parent' => 0,
				'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $parent_post_id, true);
			if (!is_wp_error($attachment_id)) {
				require_once(ABSPATH . 'wp-admin/includes/image.php');
				$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
				wp_update_attachment_metadata($attachment_id, $attachment_data);
			}
		}
		$this->create_donation_product_if_not_exist($attachment_id);

                $i13_simple_donations_settings = get_option('i13_simple_donations_settings');
                
                if(!$i13_simple_donations_settings){
                    
                    $i13_simple_donations_settings = array(
                            'donation_type' => '1',
                            'show_categories' => '0',
                            'show_donation_form_on_cart' => '1',
                            'show_donation_form_on_checkout' => '1',
                            'category_required' => '0',
                            'donation_label' => 'Donation',
                            'donation_lbl' => 'Donation',
                            'donation_placeholder' => 'Enter Amount',
                            'donation_category_lbl' => 'Category',
                            'donation_category_label' => 'Donation Category',
                            'add_donation_button_label' => 'Submit Donation',
                            'donation_amount_label' => 'Donation Amount',
                            'donation_cal_format' => 'mm/dd/YYYY'
                    );

                    update_option('i13_simple_donations_settings', $i13_simple_donations_settings);
                }

                $i13_simple_donations_msg_settings = get_option('i13_simple_donations_msg_settings');
                
                if(!$i13_simple_donations_msg_settings){
                    
                    $i13_simple_donations_msg_settings = array(
                            'failed_load_product' => 'Failed to find product.',
                            'donation_cant_zero' => 'Donation must not be less than or 0.',
                            'category_required' => 'Category is required field.',
                            'donation_added' => 'Donation added to cart.',
                            'donation_updated' => 'Donation updated to cart.',
                            'verification_failed' => 'Nonce verification failed.'
                    );

                    update_option('i13_simple_donations_msg_settings', $i13_simple_donations_msg_settings);

                }
		$this->i13_woo_simple_donation_add_access_capabilities();
				
				
				
	}

	public function create_donation_product_if_not_exist( $attachment_id) {

		$product_id = wc_get_product_id_by_sku('i13_donation_single');
		//no product exist with the given SKU so create one
		if (!$product_id) {

			$product = new WC_Product_Simple();
			$product->set_name('Donation');
			$product->set_sku('i13_donation_single');
			$product->set_status('publish');
			$product->set_catalog_visibility('hidden');
			$product->set_price(0);
			$product->set_regular_price(0);
			$product->set_sold_individually(false);
			$product->set_image_id($attachment_id);
			$product->set_virtual(true);
			$product->save();
		}
	}

	public function i13_woo_simple_donation_admin_cap_list( $allcaps, $caps, $args, $user) {


		if (!in_array('administrator', $user->roles)) {

			return $allcaps;
		} else {

			if (!isset($allcaps['i13_woo_manage_donation_settings'])) {

				$allcaps['i13_woo_manage_donation_settings'] = true;
			}

			
			if (!isset($allcaps['i13_woo_manage_donation_reports'])) {

				$allcaps['i13_woo_manage_donation_reports'] = true;
			}
			if (!isset($allcaps['i13_woo_manage_donation_error_msg'])) {

				$allcaps['i13_woo_manage_donation_error_msg'] = true;
			}
		}

		return $allcaps;
	}

	public function map_i13_woo_map_simple_donation_for_woo_meta_caps( array $caps, $cap, $user_id, array $args) {


		if (!in_array(
						$cap, array(
					'i13_woo_manage_donation_settings',
					'i13_woo_manage_donation_reports',
					'i13_woo_manage_donation_error_msg',
						), true
				)
		) {

			return $caps;
		}




		$caps = array();

		switch ($cap) {

			case 'i13_woo_manage_donation_settings':
				$caps[] = 'i13_woo_manage_donation_settings';
				break;

			
			case 'i13_woo_manage_donation_reports':
				$caps[] = 'i13_woo_manage_donation_reports';
				break;
			case 'i13_woo_manage_donation_error_msg':
				$caps[] = 'i13_woo_manage_donation_error_msg';
				break;

			default:
				$caps[] = 'do_not_allow';
				break;
		}


		return apply_filters('i13_woo_map_woo_simple_donation_meta_caps', $caps, $cap, $user_id, $args);
	}

	public function i13_woo_simple_donation_add_access_capabilities() {

		// Capabilities for all roles.
		$roles = array('administrator');
		foreach ($roles as $role) {

			$role = get_role($role);
			if (empty($role)) {
				continue;
			}


			if (!$role->has_cap('i13_woo_manage_donation_settings')) {

				$role->add_cap('i13_woo_manage_donation_settings');
			}

			

			if (!$role->has_cap('i13_woo_manage_donation_reports')) {

				$role->add_cap('i13_woo_manage_donation_reports');
			}
			if (!$role->has_cap('i13_woo_manage_donation_error_msg')) {

				$role->add_cap('i13_woo_manage_donation_error_msg');
			}
		}

		$user = wp_get_current_user();
		$user->get_role_caps();
	}

	

	
	

	public function i13_woo_simple_donation_remove_sessions( $cart_item_key, $cart) {

		
		$retrive_data = WC()->session->get('i13_donation');
		if (isset($retrive_data[$cart_item_key])) {

			//unset($retrive_data[$cart_item_key]);
			$retrive_data[$cart_item_key]['is_delete'] = '1';
			WC()->session->set('i13_donation', $retrive_data);
		}
	}

	public function woo_add_donation() {
		global $woocommerce;

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

			if ($cart_item['data']->get_sku() == 'i13_donation_single') {



				$cart_item['data']->set_price($cart_item['donation_amt']);
				$new_price = $cart_item['data']->get_price();
			}
		}
		return;
		$retrive_data = WC()->session->get('i13_donation');

		$alreadyDoneKeys = array();
		if (!empty($retrive_data) && is_array($retrive_data)) {

			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

				if ($cart_item['data']->get_sku() == 'i13_donation_single' && !empty($retrive_data)) {

					if (isset($retrive_data[$cart_item_key])) {

						$ret_itm = $retrive_data[$cart_item_key];
						$cart_item['data']->set_price($ret_itm['i13_single_donation']);
						$new_price = $cart_item['data']->get_price();
					}
				}
			}
		}
	}

	public function sdfw_add_donation_callback() {

		$i13_simple_donations_msg_settings = get_option('i13_simple_donations_msg_settings');
		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');

		if (isset($_POST['add_to_cart']) && isset($_POST['donation_amt']) && isset($_POST['vNonce'])) {

					
						$retrieved_nonce = '';
			$retrieved_nonce = sanitize_text_field($_POST['vNonce']);
			if (!wp_verify_nonce($retrieved_nonce, 'add_to_cart_donation')) {

				if (is_array($i13_simple_donations_msg_settings) && isset($i13_simple_donations_msg_settings['verification_failed']) && trim($i13_simple_donations_msg_settings['verification_failed']) != '') {

								$msg = esc_html($i13_simple_donations_msg_settings['verification_failed']);
				} else {

									$msg = esc_html(__('Nonce verification failed.', 'simple-donation-for-woo'));
				}

				if ('shortcode' != $from) {

						wc_add_notice($msg, apply_filters('woo_simple_donation_notice_type_error', 'error'));
				}

										$returnArray = array('is_error' => 'true', 'msg' => $msg);
										wp_send_json($returnArray);
										die;

										// wp_die('Security check fail');
			}

			global $woocommerce;
			
			$amount = sanitize_text_field($_POST['donation_amt']);
			
			$from = '';
			if (isset($_POST['from']) && sanitize_text_field($_POST['from']) != '') {
				$from = trim(sanitize_text_field($_POST['from']));
			}


			if ($amount > 0) {


				$sku = 'i13_donation_single';
				$product_id = wc_get_product_id_by_sku($sku);
				if ($product_id) {

					$product = wc_get_product($product_id);
				} else {


					if (is_array($i13_simple_donations_msg_settings) && isset($i13_simple_donations_msg_settings['failed_load_product']) && trim($i13_simple_donations_msg_settings['failed_load_product']) != '') {

						$msg = esc_html($i13_simple_donations_msg_settings['failed_load_product']);
					} else {

						$msg = esc_html(__('Failed to find product.', 'simple-donation-for-woo'));
					}

					if ('shortcode' != $from) {
						wc_add_notice($msg, apply_filters('woo_simple_donation_notice_type_error', 'error'));
					}

					$returnArray = array('is_error' => 'true', 'msg' => $msg);
					 wp_send_json($returnArray);
					
					die;
				}
				if ($product) {

					WC()->cart->calculate_totals();
					$is_in = false;
					$retrive_data = WC()->session->get('i13_donation');
					if (!empty($retrive_data)) {

						foreach ($retrive_data as $k => $ret) {

							if ($ret['i13_single_donation'] == $amount ) {

								$is_in = true;

								if (is_array($i13_simple_donations_msg_settings) && isset($i13_simple_donations_msg_settings['donation_updated']) && trim($i13_simple_donations_msg_settings['donation_updated']) != '') {

									$msg = esc_html($i13_simple_donations_msg_settings['donation_updated']);
								} else {

									$msg = esc_html(__('Donation updated to cart.', 'simple-donation-for-woo'));
								}

								if ('shortcode' != $from) {
									wc_add_notice($msg, apply_filters('woo_simple_donation_notice_type_success', 'success'));
								}
								$returnArray = array('is_error' => 'false', 'msg' => $msg);
								wp_send_json($returnArray);
								die;
								break;
							}
						}

						if (!$is_in) {

							$woocommerce->cart->add_to_cart($product_id);
							$allElm = WC()->cart->get_cart();
							end($allElm);
							$key = key($allElm);

							$retrive_data[$key] = array('i13_single_donation' => $amount,'is_deleted' => '0');
						}
					} else {


						$woocommerce->cart->add_to_cart($product_id);
						$allElm = WC()->cart->get_cart();
						end($allElm);
						$key = key($allElm);

						$retrive_data[$key] = array('i13_single_donation' => $amount,'is_deleted' => '0');
					}

					WC()->session->set('i13_donation', $retrive_data);

					if (is_array($i13_simple_donations_msg_settings) && isset($i13_simple_donations_msg_settings['donation_added']) && trim($i13_simple_donations_msg_settings['donation_added']) != '') {

						$msg = esc_html($i13_simple_donations_msg_settings['donation_added']);
					} else {

						$msg = esc_html(__('Donation added to cart.', 'simple-donation-for-woo'));
					}

					if ('shortcode' != $from) {
						wc_add_notice($msg, apply_filters('woo_simple_donation_notice_type_success', 'success'));
					}
					$returnArray = array('is_error' => 'false', 'msg' => $msg);
					wp_send_json($returnArray);
					 
					die;
				} else {


					if (is_array($i13_simple_donations_msg_settings) && isset($i13_simple_donations_msg_settings['failed_load_product']) && trim($i13_simple_donations_msg_settings['failed_load_product']) != '') {

						$msg = esc_html($i13_simple_donations_msg_settings['failed_load_product']);
					} else {

						$msg = esc_html(__('Failed to load product.', 'simple-donation-for-woo'));
					}

					if ('shortcode' != $from) {
						wc_add_notice($msg, apply_filters('woo_simple_donation_notice_type_success', 'error'));
					}
					$returnArray = array('is_error' => 'true', 'msg' => $msg);
					wp_send_json($returnArray);
					die;
				}
			} else {



				if (is_array($i13_simple_donations_msg_settings) && isset($i13_simple_donations_msg_settings['donation_cant_zero']) && trim($i13_simple_donations_msg_settings['donation_cant_zero']) != '') {

					$msg = esc_html($i13_simple_donations_msg_settings['donation_cant_zero']);
				} else {

					$msg = esc_html(__('Donation must not be less than or 0.', 'simple-donation-for-woo'));
				}

				if ('shortcode' != $from) {

					wc_add_notice($msg, apply_filters('woo_simple_donation_notice_type_success', 'error'));
				}
				$returnArray = array('is_error' => 'true', 'msg' => $msg);
				wp_send_json($returnArray);
				die;
			}
		}
	}

	public function i13_woo_simple_donation_validation( $passed_validation, $product_id, $quantity) {

				$product = wc_get_product($product_id);
				$sku='';
		if ($product) {
					
			$sku=$product->get_sku();
		}
		
		if ('i13_donation_single'==$sku) {

			$retrive_data = WC()->session->get('i13_donation');
			if (!empty($retrive_data) && is_array($retrive_data)) {

				return $passed_validation;
			} else {

				return false;
			}
		}

		return $passed_validation;
	}

	public function i13_woo_load_lang_for_woo_simple_donation() {

		load_plugin_textdomain('simple-donation-for-woo', false, basename(dirname(__FILE__)) . '/languages/');
		add_filter('map_meta_cap', array($this, 'map_i13_woo_map_simple_donation_for_woo_meta_caps'), 10, 4);
	}

	public function i13_woo_add_css_js_for_woo_donation_cart() {


		wp_register_style('i13_donation', plugins_url('/public/css/i13_donation.css', __FILE__), array(), '1.0.0.1');
		wp_register_style('sdw_grid', plugins_url('/public/css/sdw_grid.css', __FILE__), array(), '1.0.1');
		wp_enqueue_script('jquery');
	}

	public function donation_html_form( $uniqId = '') {


		wp_enqueue_script('jquery');
		wp_enqueue_style('i13_donation');
		wp_enqueue_style('sdw_grid');

		
		$vNonce = wp_create_nonce('add_to_cart_donation');
		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');
		$i13_simple_donations_values = get_option('i13_simple_donations_values');

		$label_donation = $i13_simple_donations_settings['donation_label'];
		if ('' == esc_html($label_donation)) {

			$label_donation = __('Donation', 'recaptcha-for-woocommerce');
		}

		$donation_lbl = $i13_simple_donations_settings['donation_lbl'];
		if ('' == esc_html($donation_lbl)) {

			$donation_lbl = __('Donation', 'recaptcha-for-woocommerce');
		}


		$donation_placeholder = $i13_simple_donations_settings['donation_placeholder'];
		if ('' == esc_html($donation_placeholder)) {

			$donation_placeholder = __('Enter Amount', 'recaptcha-for-woocommerce');
		}

		
		$add_donation_button_label = $i13_simple_donations_settings['add_donation_button_label'];
		if ('' == esc_html($add_donation_button_label)) {

			$add_donation_button_label = __('Submit Donation', 'recaptcha-for-woocommerce');
		}

		$donation_amount_label = $i13_simple_donations_settings['donation_amount_label'];
		if ('' == esc_html($donation_amount_label)) {

			$donation_amount_label = __('Donation Amount', 'recaptcha-for-woocommerce');
		}

		

		
		?>



		<div class="donation_ <?php echo esc_html($uniqId); ?>_scontainer">
			<details open class="donation_blk">
				<summary class="donation_summary"> <label><?php echo esc_html($label_donation); ?></label></summary>
				<div class="scontainer <?php echo esc_html($uniqId); ?>">

					<?php if (1 == $i13_simple_donations_settings['donation_type']) : ?>
						<div class="scolupdate-xs-12 scolupdate-md-4 scolupdate-lg-4">
							<label><?php echo esc_html($donation_lbl); ?><span class="i13_req">*</span></label>
							<input type='number' step="any" placeholder="<?php echo esc_html($donation_placeholder); ?>"   class="<?php echo esc_html($uniqId); ?>_make_donation woocommerce_checkout_make_donation woocommerce_cart_make_donation make_donation input-text qty text" name='make_donation'>
						</div>
						  
						<div class="scolupdate-scolupdate-xs-12 scolupdate-md-4 scolupdate-lg-4">
							<label>&nbsp;</label>
							<input type='button' name='add_donation' class='<?php echo esc_html($uniqId); ?>_add_donation  woocommerce_checkout_add_donation  woocommerce_cart_form_add_donation  add_donation' value='<?php echo esc_html($add_donation_button_label); ?>' />    
							<div class="clear lblerr"></div>
						</div>   
					<?php elseif (2 == $i13_simple_donations_settings['donation_type']) : ?>

						<div class="scolupdate-xs-12 scolupdate-md-4 scolupdate-lg-4">
							<label><?php echo esc_html($donation_lbl); ?><span class="i13_req">*</span></label>
							<select class="<?php echo esc_html($uniqId); ?>_make_donation woocommerce_checkout_make_donation woocommerce_cart_make_donation make_donation input-text qty text" name='make_donation'>
								<option value="0"><?php echo esc_html($donation_amount_label); ?></option>
								<?php if (is_array($i13_simple_donations_values) && count($i13_simple_donations_values) > 0) : ?>

									<?php foreach ($i13_simple_donations_values as $sm_d) : ?>
										<option value="<?php echo esc_html($sm_d['donation']); ?>"><?php echo wp_kses_post(wc_price($sm_d['donation'])); ?> </option>
									<?php endforeach; ?>

								<?php endif; ?>
							</select>  

						</div>
						  
						<div class="scolupdate-scolupdate-xs-12 scolupdate-md-4 scolupdate-lg-4">
							<label>&nbsp;</label>
							<input type='button' name='add_donation' class='<?php echo esc_html($uniqId); ?>_add_donation  woocommerce_checkout_add_donation  woocommerce_cart_form_add_donation  add_donation' value='<?php echo esc_html($add_donation_button_label); ?>' />    
							<div class="clear lblerr"></div>
						</div> 



					<?php endif; ?>
				</div>
			</details>
			<script>

		<?php $intval = uniqid('interval_'); ?>

				var <?php echo esc_html($intval); ?> = setInterval(function () {

					if (document.readyState === 'complete') {

						clearInterval(<?php echo esc_html($intval); ?>);
						if (jQuery(".<?php echo esc_html($uniqId); ?>").width() < 558) {

							jQuery(".<?php echo esc_html($uniqId); ?>_make_donation").parent().removeClass('sfull-width').addClass('sfull-width');
							jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").parent().removeClass('sfull-width').addClass('sfull-width');
							jQuery(".<?php echo esc_html($uniqId); ?>_add_donation").parent().removeClass('sfull-width').addClass('sfull-width');

						}

					}

				}, 100);
			</script>

		</div>


		<?php
		
	}

	public function action_woocommerce_after_cart_contents() {


		wp_enqueue_script('jquery');
		wp_enqueue_style('i13_donation');

		ob_start();

		$vNonce = wp_create_nonce('add_to_cart_donation');
		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');
		$i13_simple_donations_values = get_option('i13_simple_donations_values');

		

		if (is_page('cart') || is_cart()) {

			$uniqId = 'cart';
		} else {
			$uniqId = uniqid();
		}
		$this->donation_html_form($uniqId);
	}

	public function i13_add_scripts_after_cart_action() {


		wp_enqueue_script('jquery');
		wp_enqueue_style('i13_donation');

	
		$vNonce = wp_create_nonce('add_to_cart_donation');

		$uniqId = 'cart';
		?>

		<script type="text/javascript" id="13_donation_script">

			jQuery(document).on('keypress', '.<?php echo esc_html($uniqId); ?>_make_donation', function (e) {

				if (e.which == 13) {

					e.preventDefault();
					jQuery(".<?php echo esc_html($uniqId); ?>_add_donation").trigger("click");
				}
			});

			jQuery(document).on('click', '.<?php echo esc_html($uniqId); ?>_add_donation', function (e) {

				e.preventDefault();
				jQuery('form.woocommerce-cart-form').addClass('processing').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				var donation_cat = '-1';
				if (jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").length > 0) {

					donation_cat = parseInt(jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").val());
				}

				var data = {
					'action': 'sdfw_add_donation',
					'donation_amt': jQuery('.<?php echo esc_html($uniqId); ?>_make_donation').val(),
					'donation_cat': donation_cat,
					'vNonce': '<?php echo esc_html($vNonce); ?>',
					'add_to_cart': 'donation',
					'from': 'cart'

				};
				jQuery.post('<?php echo esc_html(admin_url('admin-ajax.php')); ?>', data, function (response) {

					/*response = jQuery.parseJSON(response);*/
					jQuery('form.woocommerce-cart-form').removeClass('processing').unblock();
					if (response.is_error == "false") {

						jQuery('[name="update_cart"]').prop("disabled", false).trigger('click');

					} else {
						jQuery('[name="update_cart"]').prop("disabled", false).trigger('click');



					}



				});



			});




		</script>    
		<?php
		
	}

	public function Show_donation_form_on_checkout() {

		wp_enqueue_script('jquery');
		wp_enqueue_style('i13_donation');


		$vNonce = wp_create_nonce('add_to_cart_donation');
		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');
		$i13_simple_donations_values = get_option('i13_simple_donations_values');

		

		$uniqId = uniqid();
		$this->donation_html_form($uniqId);
		?>

		<script>


			jQuery(document).on('keypress', '.<?php echo esc_html($uniqId); ?>_make_donation', function (e) {

				if (e.which == 13) {

					e.preventDefault();
					jQuery(".<?php echo esc_html($uniqId); ?>_make_donation").trigger("click");
				}
			});

			jQuery(document).on('click', '.<?php echo esc_html($uniqId); ?>_add_donation', function (e) {

				e.preventDefault();
				jQuery('form.woocommerce-checkout').addClass('processing').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				var donation_cat = '-1';
				if (jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").length > 0) {

					donation_cat = parseInt(jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").val());
				}

				var data = {
					'action': 'sdfw_add_donation',
					'donation_amt': jQuery(".<?php echo esc_html($uniqId); ?>_make_donation").val(),
					'donation_cat': donation_cat,
					'vNonce': '<?php echo esc_html($vNonce); ?>',
					'add_to_cart': 'donation',
					'from': 'checkout'

				};
				jQuery.post('<?php echo esc_html(admin_url('admin-ajax.php')); ?>', data, function (response) {

					/*response = jQuery.parseJSON(response);*/
					jQuery('form.woocommerce-checkout').removeClass('processing').unblock();
					jQuery('body').trigger('update_checkout');
					jQuery(".<?php echo esc_html($uniqId); ?>_make_donation").val('0');
					jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").val('0');


				});



			});


		</script>    
		<?php
		
	}

	public function i13_donation_print_form_func( $atts) {

		wp_enqueue_script('jquery');
		wp_enqueue_style('i13_donation');

		ob_start();

		$vNonce = wp_create_nonce('add_to_cart_donation');
		$i13_simple_donations_settings = get_option('i13_simple_donations_settings');
		$i13_simple_donations_values = get_option('i13_simple_donations_values');

		

		$uniqId = uniqid();
		$this->donation_html_form($uniqId);
		?>

		<script>

			jQuery(document).on('keypress', '.<?php echo esc_html($uniqId); ?>_make_donation', function (e) {

				if (e.which == 13) {

					e.preventDefault();
					jQuery("#add_donation").trigger("click");
				}
			});

			jQuery(document).on('click', '.<?php echo esc_html($uniqId); ?>_add_donation', function (e) {

				e.preventDefault();
				jQuery('.<?php echo esc_html($uniqId); ?>_scontainer').addClass('processing').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				var donation_cat = '-1';
				if (jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").length > 0) {

					donation_cat = parseInt(jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").val());
				}

				var data = {
					'action': 'sdfw_add_donation',
					'donation_amt': jQuery(".<?php echo esc_html($uniqId); ?>_make_donation").val(),
					'donation_cat': donation_cat,
					'vNonce': '<?php echo esc_html($vNonce); ?>',
					'add_to_cart': 'donation',
					'from': 'shortcode'

				};
				jQuery.post('<?php echo esc_html(admin_url('admin-ajax.php')); ?>', data, function (response) {


					/*response = jQuery.parseJSON(response);*/
					jQuery('.<?php echo esc_html($uniqId); ?>_scontainer').removeClass('processing').unblock();
					jQuery('body').trigger('update_checkout');
					if (jQuery('[name="update_cart"]').length > 0) {
						jQuery('[name="update_cart"]').prop("disabled", false).trigger('click');
					}
					jQuery(".<?php echo esc_html($uniqId); ?>_make_donation").val('0');
					jQuery(".<?php echo esc_html($uniqId); ?>_make_donation_cat").val('0');

					if (response.msg != "") {

						alert(response.msg);
					}

				});



			});


		</script>    
		<?php
		$output = ob_get_clean();
		return $output;
	}

	

	
	public function i13_remove_donation_filter_order_item_permalink_callback( $product_permalink, $item, $order) {

		$product = $item->get_product();

		if ('i13_donation_single' == $product->get_sku()) {

			$product_permalink = '';
		}

		return $product_permalink;
	}

	

}

if (!defined('ABSPATH')) {
	exit;
}


$active_plugins = (array) apply_filters('active_plugins', get_option('active_plugins', array()));

if (function_exists('is_multisite') && is_multisite()) {
	$active_plugins = array_merge($active_plugins, apply_filters('active_plugins', get_site_option('active_sitewide_plugins', array())));
}

if (in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins)) {

	global $I13_Woo_SImple_Donation_Lite;
	$I13_Woo_SImple_Donation_Lite = new I13_Woo_SImple_Donation_Lite();
}
