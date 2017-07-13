<?php
/**

Plugin Name: WooCommerce P.IVA e Codice Fiscale per Italia
Plugin URI: http://dot4all.it/woocommerce-inserire-codice-fiscale-partita-iva/
Description: Il plugin rende compatibile woocommerce per mercato italiano. Aggiunge i campi "codice fiscale" e "partita iva". I campi saranno memorizzati e si visualizzeranno sia nell'ordine che nell'email inviata cliente. Il plugin si integra con il plugin di fatturazione WooCommerce PDF Invoices & Packing Slips e permette agli utenti di avere adisposizione la ricevuta o fattura per clienti provati e fattura per aziende. La fattura per le aziende sarà possibile averla con la sola P.IVA oppure con P.IVA e Codice Fiscale. 
Version: 1.0.2
Author: dot4all
Author URI: http://dot4all.it

*/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( !class_exists( 'WooCommerce_Piva_Cf_Invoice_Ita' ) ) :
define('WCPIVACF_IT_DOMAIN', 'woocommerce-piva-cf-invoice-ita');
session_start();

class WooCommerce_Piva_Cf_Invoice_Ita {
	public $plugin_basename;
	public $plugin_url;
	public $plugin_path;
	public $version = '0.0.1';
	protected static $instance = null;
	
	public $regexCF = "/^([A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST]{1}[0-9LMNPQRSTUV]{2}[A-Za-z]{1}[0-9LMNPQRSTUV]{3}[A-Z]{1})$/i";
	public $regexPIVA = "/^(ATU[0-9]{8}|BE0[0-9]{9}|BG[0-9]{9,10}|CY[0-9]{8}L| CZ[0-9]{8,10}|DE[0-9]{9}|DK[0-9]{8}|EE[0-9]{9}|(EL|GR)[0-9]{9}|ES[0-9A-Z][0-9]{7}[0-9A-Z]|FI[0-9]{8}|FR[0-9A-Z]{2}[0-9]{9}|GB([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{13})|HU[0-9]{8}|IE[0-9]S[0-9]{5}L|IT[0-9]{11}|LT([0-9]{9}|[0-9]{12})|LU[0-9]{8}|LV[0-9]{11}|MT[0-9]{8}|NL[0-9]{9}B[0-9]{2}|PL[0-9]{10}|PT[0-9]{9}|RO[0-9]{2,10}|SE[0-9]{12}|SI[0-9]{8}|SK[0-9]{10})$/i";

	public static function instance() {
		if ( is_null( self::$instance ) ) self::$instance = new self();
		return self::$instance;
	}

	public function __construct() {
		$this->plugin_basename = plugin_basename(__FILE__);
		$this->plugin_url = plugin_dir_url($this->plugin_basename);
		$this->plugin_path = trailingslashit(dirname(__FILE__));
		$this->init_hooks();
	}

	public function init() {
		$locale = apply_filters( 'plugin_locale', get_locale(), WCPIVACF_IT_DOMAIN );
		load_textdomain( WCPIVACF_IT_DOMAIN, WP_LANG_DIR."/plugins/{" . WCPIVACF_IT_DOMAIN . "}-{$locale}.mo" );
		load_plugin_textdomain( WCPIVACF_IT_DOMAIN, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		if ($this->is_wc_active()) {
			add_action( 'init', array( $this, 'init_integration' ) );
			add_filter( 'woocommerce_billing_fields' , array( $this, 'billing_fields'), 10, 1);
			add_filter( 'woocommerce_admin_billing_fields' , array( $this, 'admin_field_cfpiva' ));
			add_action( 'woocommerce_after_edit_address_form_billing', array( $this, 'after_order_notes') );
			add_action( 'woocommerce_after_order_notes', array( $this, 'after_order_notes') );
			add_action( 'woocommerce_checkout_process', array( $this, 'piva_checkout_field_process'));
			add_filter( 'woocommerce_order_formatted_billing_address' , array( $this, 'woocommerce_order_formatted_billing_address'), 10, 2 );
			add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'my_account_my_address_formatted_address'), 10, 3 );
			add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'formatted_address_replacements'), 10, 2 );
			add_filter( 'woocommerce_localisation_address_formats', array( $this, 'localisation_address_format') );
			add_filter( 'woocommerce_found_customer_details', array( $this, 'found_customer_details') );
			add_filter( 'woocommerce_customer_meta_fields', array( $this, 'customer_meta_fields') );

			add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_invoice_type_column' ));
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'invoice_type_column_data' ));
		} else {
			add_action( 'admin_notices', array ( $this, 'check_wc' ) );
		}
	}

	public function is_wc_active() {
		$plugins = get_site_option( 'active_sitewide_plugins', array());
		if (in_array('woocommerce/woocommerce.php', get_option( 'active_plugins', array())) || isset($plugins['woocommerce/woocommerce.php'])) {
			return true;
		} else {
			return false;
		}
	}

	public function check_wc( $fields ) {
		$class = "error";
		$message = sprintf( __( 'WooCommerce Italian Add-on requires %sWooCommerce%s to be installed and activated!' , WCPIVACF_IT_DOMAIN ), '<a href="https://wordpress.org/plugins/woocommerce/">', '</a>' );
		echo"<div class=\"$class\"> <p>$message</p></div>";
	}	

	public function init_integration() {

		if ( in_array( 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			include_once 'includes/class-wcpdf-integration.php';
		} else {
			
		}
	}

	public function billing_fields( $fields ) {
		$fields['billing_invoice_type'] = array(
			'label' => __('Invoice or Receipt', WCPIVACF_IT_DOMAIN),
			'placeholder' => __( 'Invoice or Receipt', WCPIVACF_IT_DOMAIN ),
			'required'    => false,
			'class'       => array( 'form-row-first'),
			'clear'       => false,
			'type'        => 'select',
			'options'     => array(
				'receipt' => __('Receipt', WCPIVACF_IT_DOMAIN ),
				'invoice' => __('Invoice', WCPIVACF_IT_DOMAIN ),
				'private_invoice' => __('Invoice with Fiscal Code', WCPIVACF_IT_DOMAIN ),
				'professionist_invoice' => __('Invoice with VAT number + Fiscal Code', WCPIVACF_IT_DOMAIN )
			),
			'value'       => get_user_meta( get_current_user_id(), 'billing_invoice_type', true )
		);
		$fields['billing_cf'] = array(
			'label'       => __('Fiscal Code', WCPIVACF_IT_DOMAIN),
			'placeholder' => __('Please enter your Fiscal code', WCPIVACF_IT_DOMAIN),
			'required'    => false,
			'class'       => array( 'form-row-last' ),
			'value'       => get_user_meta( get_current_user_id(), 'billing_cf', true )
		);
		$fields['billing_piva'] = array(
			'label'       => __('VAT', WCPIVACF_IT_DOMAIN),
			'placeholder' => __('Please enter your VAT number', WCPIVACF_IT_DOMAIN),
			'required'    => false,
			'class'       => array( 'form-row-last' ),
			'value'       => get_user_meta( get_current_user_id(), 'billing_piva', true )
		);
		
		
		switch(isset($_POST['invoice_type'])){
			case "invoice": $fields['billing_cf']['required'] = false; $fields['billing_piva']['required'] = true; break;
			case "receipt": $fields['billing_cf']['required'] = true; $fields['billing_piva']['required'] = false; break;
			case "private_invoice": $fields['billing_cf']['required'] = true; $fields['billing_piva']['required'] = false;break;
			case "professionist_invoice": $fields['billing_cf']['required'] = true;$fields['billing_piva']['required'] = true; break;
		}
			
		
		return $fields;
	}
	
	public function admin_field_cfpiva( $fields ) {
		$fields['invoice_type'] = array(
		'label' => __('Invoice or Receipt', WCPIVACF_IT_DOMAIN),
		'show' => false,
		'wrapper_class' => 'form-field-wide',
		'type'        => 'select',
		'options'     => array(
			'receipt' => __('Receipt', WCPIVACF_IT_DOMAIN ),
			'invoice' => __('Invoice', WCPIVACF_IT_DOMAIN ),
			'private_invoice' => __('Invoice with Fiscal Code', WCPIVACF_IT_DOMAIN ),
			'professionist_invoice' => __('Invoice with VAT number + Fiscal Code', WCPIVACF_IT_DOMAIN )
			)
		);
		$fields['cf'] = array(
		'label' => __('Fiscal Code', WCPIVACF_IT_DOMAIN),
		'wrapper_class' => 'form-field-wide',
		'show' => false
		);
		$fields['piva'] = array(
		'label' => __('VAT', WCPIVACF_IT_DOMAIN),
		'wrapper_class' => 'form-field-wide',
		'show' => false
		);
	
		return $fields;
	}
	
	public function after_order_notes() {
		if(wp_script_is( "select2")) {
			echo '<script type="text/javascript">
			jQuery(function() {
				jQuery("#billing_invoice_type").select2({minimumResultsForSearch: Infinity});
				jQuery("#billing_cf_field label").append("<abbr class=\"required\" title=\"obbligatorio\">*</abbr>");
				jQuery("#billing_piva_field label").append("<abbr class=\"required\" title=\"obbligatorio\">*</abbr>");
				if(jQuery("#billing_invoice_type").val() == "receipt" || jQuery("#billing_invoice_type").val() == "private_invoice"){
					jQuery(".woocommerce-checkout .woocommerce #billing_piva_field").css("display","none");
				}
				if(jQuery("#billing_invoice_type").val() == "invoice" ){
					jQuery(".woocommerce-checkout .woocommerce #billing_cf_field").css("display","none");
				}
				jQuery("#billing_invoice_type").change(function(){
					if(jQuery("#billing_invoice_type").val() == "invoice"){
						jQuery(".woocommerce-checkout .woocommerce #billing_piva_field").css("display","block"); 
						jQuery(".woocommerce-checkout .woocommerce #billing_cf_field").css("display","none"); 
					}else if (jQuery("#billing_invoice_type").val() == "professionist_invoice"){
						jQuery(".woocommerce-checkout .woocommerce #billing_piva_field").css("display","block"); 
						jQuery(".woocommerce-checkout .woocommerce #billing_cf_field").css("display","block");
					}else if (jQuery("#billing_invoice_type").val() == "private_invoice"){
						jQuery(".woocommerce-checkout .woocommerce #billing_piva_field").css("display","none"); 
						jQuery(".woocommerce-checkout .woocommerce #billing_cf_field").css("display","block");
					}else{
						jQuery(".woocommerce-checkout .woocommerce #billing_piva_field").css("display","none"); 
						jQuery(".woocommerce-checkout .woocommerce #billing_cf_field").css("display","block");
					}
				})
			});
			</script>';
		}
	}
	
	
	public function piva_checkout_field_process() {
		global $woocommerce;
		
		if($_POST["billing_invoice_type"] == "invoice") {	
				
			if(!trim($_POST['billing_piva'])) {
				wc_add_notice(__('Please enter your VAT number', WCPIVACF_IT_DOMAIN) ,$notice_type = 'error');
			} elseif($_POST["billing_country"] == 'IT' && strlen($_POST['billing_piva']) > 13) {
				if(!preg_match($this->regexCF, $_POST['billing_piva'])) {
					wc_add_notice(sprintf(__('Tax Identification Number %1$s is not correct', WCPIVACF_IT_DOMAIN), "<strong>". strtoupper($_POST['billing_piva']) . "</strong>"),$notice_type = 'error');
				}
			} else {
				if(!(preg_match($this->regexPIVA, $_POST["billing_country"].$_POST['billing_piva']))) wc_add_notice(sprintf(__('VAT number %1$s is not correct', WCPIVACF_IT_DOMAIN), "<strong>". $_POST["billing_country"].$_POST['billing_piva'] . "</strong>"),$notice_type = 'error');
			}
		}
		if(($_POST["billing_invoice_type"] == "receipt" || $_POST["billing_invoice_type"] == "private_invoice") && $_POST['billing_cf'] == '' && $_POST["billing_country"] == 'IT'){
			if(!preg_match($this->regexCF, $_POST['billing_cf']) && !preg_match("/^([0-9]{11})$/i", $_POST['billing_cf'])) {
				wc_add_notice(sprintf(__('Tax Identification Number %1$s is not correct', WCPIVACF_IT_DOMAIN), "<strong>". strtoupper($_POST['billing_cf']) . "</strong>"),$notice_type = 'error');
			}
		}
		if($_POST["billing_invoice_type"] == "professionist_invoice") {
			if($_POST['billing_cf'] && $_POST['billing_piva'] && $_POST["billing_country"] == 'IT'){
				if(!preg_match($this->regexCF, $_POST['billing_cf']) && !preg_match("/^([0-9]{11})$/i", $_POST['billing_cf'])) {
					wc_add_notice(sprintf(__('Tax Identification Number %1$s is not correct', WCPIVACF_IT_DOMAIN), "<strong>". strtoupper($_POST['billing_cf']) . "</strong>"),$notice_type = 'error');
				}
				if(!trim($_POST['billing_piva'])) {
					wc_add_notice(__('Please enter your VAT number', WCPIVACF_IT_DOMAIN) ,$notice_type = 'error');
				} elseif($_POST["billing_country"] == 'IT' && strlen($_POST['billing_piva']) > 13) {
					if(!preg_match($this->regexCF, $_POST['billing_piva'])) {
						wc_add_notice(sprintf(__('VAT Number %1$s is not correct', WCPIVACF_IT_DOMAIN), "<strong>". strtoupper($_POST['billing_piva']) . "</strong>"),$notice_type = 'error');
					}
				}
			}
			if(! $_POST['billing_cf'] || ! $_POST['billing_piva'] && $_POST["billing_country"] == 'IT'){
				wc_add_notice(__('Please enter your VAT number and your Tax Identification Number', WCPIVACF_IT_DOMAIN) ,$notice_type = 'error');
			}
		}
	}
	
	
	public function woocommerce_order_formatted_billing_address( $fields, $order) {
		$fields['invoice_type'] = $order->billing_invoice_type;
		
		$_SESSION["invoice_type"] = $fields['invoice_type'];
		$fields['cf'] = $order->billing_cf;
		$fields['piva'] = $order->billing_piva;
		return $fields;
	}
	
	public function my_account_my_address_formatted_address( $fields, $customer_id, $type ) {
		if ( $type == 'billing' ) {
			$fields['invoice_type'] = get_user_meta( $customer_id, 'billing_invoice_type', true );
			$fields['cf'] = get_user_meta( $customer_id, 'billing_cf', true );
			$fields['piva'] = get_user_meta( $customer_id, 'billing_piva', true );
		}
		return $fields;
	}
	
	public function formatted_address_replacements( $address, $args ) {
		$address['{invoice_type}'] = '';
		$address['{cf}'] = '';
		$address['{piva}'] = '';
	
		if (! empty( $args['invoice_type'] ) ) {
			switch($args['invoice_type']){
				case "receipt":
					$address['{cf}'] =  __('Fiscal code', WCPIVACF_IT_DOMAIN) . ': ' . strtoupper( $args['cf'] );
				case "private_invoice":
					$address['{cf}'] =  __('Fiscal code', WCPIVACF_IT_DOMAIN) . ': ' . strtoupper( $args['cf'] );
				case "invoice":
					$address['{piva}'] = __('VAT', WCPIVACF_IT_DOMAIN) . ": " . $args['country'] . strtoupper( $args['piva'] );
				case "professionist_invoice":
					$address['{cf}'] =  __('Fiscal code', WCPIVACF_IT_DOMAIN) . ': ' . strtoupper( $args['cf'] );
					$address['{piva}'] = __('VAT', WCPIVACF_IT_DOMAIN) . ": " . $args['country'] . strtoupper( $args['piva'] );
			}
		}
	
		return $address;
	}
	
	public function localisation_address_format( $formats ) {

		if($_SESSION["invoice_type"] == 'receipt' || $_SESSION["invoice_type"] == 'private_invoice'){
			$formats['IT'] .= "\n\n{cf}";	
		}else if ($_SESSION["invoice_type"] == 'invoice'){
			$formats['IT'] .= "\n\n{piva}";
		}else{
			$formats['IT'] .= "\n\n{cf}\n\n{piva}";
		}
		return $formats;
	}
	
	public function found_customer_details( $customer_data ) {
		$customer_data['billing_invoice_type'] = get_user_meta( $_POST['user_id'], 'billing_invoice_type', true );
		$customer_data['billing_cf'] = get_user_meta( $_POST['user_id'], 'billing_cf', true );
		$customer_data['billing_piva'] = get_user_meta( $_POST['user_id'], 'billing_piva', true );
		return $customer_data;
	}
	
	/* Add fields in Edit User Page */
	public function customer_meta_fields( $fields ) {
		$fields['billing']['fields']['billing_invoice_type'] = array(
			'label'       => __('Invoice or Receipt', WCPIVACF_IT_DOMAIN),
			'type'        => 'select',
			'options'     => array(
				'receipt' => __('Receipt', WCPIVACF_IT_DOMAIN ),
				'invoice' => __('Invoice', WCPIVACF_IT_DOMAIN ),
				'private_invoice' => __('Invoice with Fiscal Code', WCPIVACF_IT_DOMAIN ),
				'professionist_invoice' => __('Invoice with VAT number + Fiscal Code', WCPIVACF_IT_DOMAIN )
			),
			'description'       => ""
		);
		$fields['billing']['fields']['billing_cf'] = array(
			'label'       => __('Fiscal Code', WCPIVACF_IT_DOMAIN),
			'description'       => ""
		);
		$fields['billing']['fields']['billing_piva'] = array(
			'label'       => __('VAT number', WCPIVACF_IT_DOMAIN),
			'description'       => ""
		);
		return $fields;
	}
	
	public function add_invoice_type_column( $columns ) {
		global $woocommerce;
		$new_columns = array_slice($columns, 0, 2, true) +
			array( 'invoice_type' => '<span class="status_head tips" data-tip="' . __( 'Invoice or Receipt', WCPIVACF_IT_DOMAIN ) . '">' . __( 'Invoice or Receipt', WCPIVACF_IT_DOMAIN ) . '</span>') +
			array_slice($columns, 2, count($columns) - 1, true) ;
?>
<style>
table.wp-list-table .column-invoice_type{width:48px; text-align:center; color:#999}
.manage-column.column-invoice_type span.status_head:after{content:"\e00f"}
</style>
<?php
		return $new_columns;
	}

	public function invoice_type_column_data( $column ) {
		global $post, $woocommerce, $the_order;
		if ( empty( $the_order ) || $the_order->id != $post->ID ) $the_order = wc_get_order( $post->ID );
		if ( $column === 'invoice_type' ) {
			$invoicetype = get_post_meta($the_order->id,"_billing_invoice_type",true);
			switch($invoicetype) {
				case "invoice": echo "<i class=\"dashicons dashicons-media-document tips\" data-tip=\"" . __( 'invoice', WCPIVACF_IT_DOMAIN ) . "\"></i>"; break;
				case "private_invoice": echo "<i class=\"dashicons dashicons-media-document tips\" data-tip=\"" . __( 'private_invoice', WCPIVACF_IT_DOMAIN ) . "\"></i>"; break;
				case "receipt": echo "<i class=\"dashicons dashicons-media-default tips\" data-tip=\"" . __( 'receipt', WCPIVACF_IT_DOMAIN ) . "\"></i>"; break;
				case "professionist_invoice": echo "<i class=\"dashicons dashicons-media-document tips\" data-tip=\"" . __( 'professionist_invoice', WCPIVACF_IT_DOMAIN ) . "\"></i>"; break;

				default: echo "-"; break;
			}
		}
		return $column;
	}
}
endif;

$wcpivacf_IT = new WooCommerce_Piva_Cf_Invoice_Ita();
