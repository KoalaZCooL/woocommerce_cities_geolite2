<?php
/**
 * Plugin Name: Cities Dropdown for Woocommerce
 * Description: Woocommerce plugin for listing provinces/states/regions and cities.
 * Version: 1.0.0
 * Developer: Faza F. Adhiman
 * Developer URI: https://www.linkedin.com/in/faza-adhiman/
 */
/**
 * Die if accessed directly
 */
defined('ABSPATH') or die('You can not access this file directly!');

require_once(dirname(__FILE__) . '/vendor/autoload.php');
//
/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	class WC_Cities_Geolite2 {

		const VERSION = '1.0.0';

		private $states;
		private $places;

		public function __construct() {
			add_action('plugins_loaded', array($this, 'init'));
		}

		public function init() {
			$this->init_states();
			$this->init_cities();
		}

		public function init_states() {
			add_filter('woocommerce_states', array($this, 'wc_states'));
		}

		public function init_cities() {
			add_filter('woocommerce_default_address_fields', [$this, 'wc_default_fields']);
			add_filter('woocommerce_billing_fields', array( $this, 'wc_billing_fields' ), 10, 2 );
			add_filter('woocommerce_shipping_fields', array( $this, 'wc_shipping_fields' ), 10, 2 );
			add_filter('woocommerce_form_field_city', array($this, 'wc_form_field_city'), 10, 4);

			add_action('wp_enqueue_scripts', array($this, 'load_scripts'));
		}

		/**
		 * Implement WC States
		 * @param mixed $states
		 * @return mixed
		 */
		public function wc_states($states) {
			//get countries allowed by store owner
			$allowed = $this->get_store_allowed_countries();

			if (!empty($allowed)) {
				foreach ($allowed as $code => $country) {
					if (!isset($states[$code]) && file_exists($this->get_plugin_path() . '/states/' . $code . '.php')) {
						include($this->get_plugin_path() . '/states/' . $code . '.php');
					}
				}
			}

			return $states;
		}

		public function wc_default_fields($fields) {
			$fields['country']['priority'] = 77;
			$fields['city']['priority'] = 88;

			return $fields;
		}

		/**
		 * Modify billing field
		 * @param mixed $fields
		 * @param mixed $country
		 * @return mixed
		 */
		public function wc_billing_fields($fields, $country) {
//			if('AU'!==WC()->checkout->get_value('billing_country')){
//				$fields['billing_city']['priority'] = 88;
//			}
			$fields['billing_city']['type'] = 'city';

			return $fields;
		}

		/**
		 * Modify shipping field
		 * @param mixed $fields
		 * @param mixed $country
		 * @return mixed
		 */
		public function wc_shipping_fields($fields, $country) {
			$fields['shipping_city']['type'] = 'city';

			return $fields;
		}

		/**
		 * Implement places/city field
		 * @param mixed $field
		 * @param string $key
		 * @param mixed $args
		 * @param string $value
		 * @return mixed
		 */
		public function wc_form_field_city($field, $key, $args, $value) {
			// Do we need a clear div?
			if ((!empty($args['clear']))) {
				$after = '<div class="clear"></div>';
			} else {
				$after = '';
			}

			// Required markup
			if ($args['required']) {
				$args['class'][] = 'validate-required';
				$required = ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce') . '">*</abbr>';
			} else {
				$required = '';
			}

			// Custom attribute handling
			$custom_attributes = array();

			if (!empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
				foreach ($args['custom_attributes'] as $attribute => $attribute_value) {
					$custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
				}
			}

			// Validate classes
			if (!empty($args['validate'])) {
				foreach ($args['validate'] as $validate) {
					$args['class'][] = 'validate-' . $validate;
				}
			}

			// field p and label
			$field = '<p class="form-row ' . esc_attr(implode(' ', $args['class'])) . '" id="' . esc_attr($args['id']) . '_field">';
			if ($args['label']) {
				$field .= '<label for="' . esc_attr($args['id']) . '" class="' . esc_attr(implode(' ', $args['label_class'])) . '">' . $args['label'] . $required . '</label>';
			}

			// Get Country
			$country_key = $key == 'billing_city' ? 'billing_country' : 'shipping_country';
			$current_cc = WC()->checkout->get_value($country_key);

			$state_key = $key == 'billing_city' ? 'billing_state' : 'shipping_state';
			$current_sc = WC()->checkout->get_value($state_key);

			// Get country places
			$places = $this->get_places($current_cc);

			if (is_array($places) ) {//&& 'zh_CN' !== get_locale()
				$field .= '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="city_select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' placeholder="' . esc_attr($args['placeholder']) . '">';

				$field .= '<option value="">' . __('Select an option&hellip;', 'woocommerce') . '</option>';

				if ($current_sc) {
					$dropdown_places = $places[$current_sc];
				} else if (is_array($places) && isset($places[0])) {
					$dropdown_places = array_reduce($places, 'array_merge', array());
					sort($dropdown_places);
				} else {
					$dropdown_places = $places;
				}

				foreach ($dropdown_places as $city_name) {
					if (!is_array($city_name)) {
						$field .= '<option value="' . esc_attr($city_name) . '" ' . selected($value, $city_name, false) . '>' . $city_name . '</option>';
					}
				}

				$field .= '</select>';
			} else {
echo 'GARUT '.$value;
				$field .= '<input type="text" class="input-text ' . esc_attr(implode(' ', $args['input_class'])) . '" value="' . esc_attr($value) . '"  placeholder="' . esc_attr($args['placeholder']) . '" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" ' . implode(' ', $custom_attributes) . ' />';
			}

			// field description and close wrapper
			if ($args['description']) {
				$field .= '<span class="description">' . esc_attr($args['description']) . '</span>';
			}

			$field .= '</p>' . $after;

			return $field;
		}

		/**
		 * Get places
		 * @param string $p_code(default:)
		 * @return mixed
		 */
		public function get_places($p_code = null) {
			if (empty($this->places) ) {//&& 'zh_CN' !== get_locale()
				$this->load_country_places();
			}

			if (!is_null($p_code)) {
				return isset($this->places[$p_code]) ? $this->places[$p_code] : false;
			} else {
				return $this->places;
			}
		}

		/**
		 * Get country places
		 * @return mixed
		 */
		public function load_country_places() {
			global $places;

			$allowed = $this->get_store_allowed_countries();

			if ($allowed) {
				$locale = get_locale();
				foreach ($allowed as $code => $country) {
					if (!isset($places[$code]) && file_exists($this->get_plugin_path() ."/cities/{$code}_{$locale}.php")) {
						include( $this->get_plugin_path() . "/cities/{$code}_{$locale}.php" );
					}
				}
			}

			$this->places = $places;
		}

		/**
		 * Load scripts
		 */
		public function load_scripts() {
			if (is_cart() || is_checkout() || is_wc_endpoint_url('edit-address')) {

				$city_select_path = $this->get_plugin_url() . 'js/place-select.js';
				wp_enqueue_script('wc-city-select', $city_select_path, array('jquery', 'woocommerce'), self::VERSION, true);

				$places = json_encode($this->get_places());
				wp_localize_script('wc-city-select', 'wc_city_select_params', array(
						'cities' => $places,
						'i18n_select_city_text' => esc_attr__('Select an option&hellip;', 'woocommerce')
				));
			}
		}

		private function get_plugin_path() {
			if (isset($this->plugin_path)) {
				return $this->plugin_path;
			}
			$path = $this->plugin_path = plugin_dir_path(__FILE__);

			return untrailingslashit($path);
		}

		private function get_store_allowed_countries() {
			return array_merge(WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries());
		}

		public function get_plugin_url() {

			if (isset($this->plugin_url)) {
				return $this->plugin_url;
			}

			return $this->plugin_url = plugin_dir_url(__FILE__);
		}

		public function get_the_user_ip() {
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				//check ip from share internet
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				//to check ip is pass from proxy
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			return apply_filters( 'wpb_get_ip', $ip );
		}
	}

	/**
	 * Instantiate class
	 */
	$GLOBALS['wc_cities_geolite2'] = new WC_Cities_Geolite2();
};
