<?php
if ( ! class_exists( 'wcpdf_WooCommerce_Piva_Cf_Invoice_Ita' )) :

class wcpdf_WooCommerce_Piva_Cf_Invoice_Ita_PDF extends WooCommerce_Piva_Cf_Invoice_Ita {

	public function __construct() {
		add_filter( 'wpo_wcpdf_meta_box_actions' , array( $this, 'wcpdf_meta_box_actions') );
		add_filter( 'wpo_wcpdf_listing_actions' , array( $this, 'wcpdf_listing_actions') );
		add_filter( 'wpo_wcpdf_bulk_actions' , array( $this, 'wcpdf_bulk_actions') );
		add_filter( 'wpo_wcpdf_process_template_order' , array( $this, 'wcpdf_process_template_order'), 20,2);
		add_filter( 'wpo_wcpdf_process_order_ids' , array( $this, 'wcpdf_process_order_ids'), 20,2 );
		add_filter( 'wpo_wcpdf_custom_email_condition' , array( $this, 'wcpdf_custom_email_condition'), 20,3);
		add_filter( 'wpo_wcpdf_myaccount_actions', array( $this, 'wcpdf_my_account'), 10, 2 );
		add_filter( 'wpo_wcpdf_template_file', array( $this, 'wcpdf_template_files'), 20, 2 );
		add_filter( 'wpo_wcpdf_attach_documents', array( $this, 'wcpdf_attach_receipt'), 20, 1 );
	}
	
	public function wcpdf_meta_box_actions( $meta_actions ) {
		global $post_id;
		$invoicetype = get_post_meta($post_id,"_billing_invoice_type",true);
		if($invoicetype == "receipt") {
			$meta_actions = array_merge(array("receipt" => array(
				'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=receipt&order_ids=' . $post_id ), 'generate_wpo_wcpdf' ),
				'alt'		=> esc_attr__( 'PDF Receipt', WCPIVACF_IT_DOMAIN ),
				'title'		=> __( 'PDF Receipt', WCPIVACF_IT_DOMAIN )
			)), $meta_actions);
			unset($meta_actions['invoice']);
			delete_post_meta( $post_id, '_wcpdf_invoice_exists' );
			delete_post_meta( $post_id, '_wcpdf_invoice_date' );
			delete_post_meta( $post_id, '_wcpdf_invoice_number' );
		}
		return $meta_actions;
	}
	
	public function wcpdf_listing_actions( $listing_actions) {
		global $the_order ;
		$invoicetype = get_post_meta($the_order->id,"_billing_invoice_type",true);
		if($invoicetype == "receipt") {
			$listing_actions = array_merge(array("receipt" => array(
				'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=receipt&order_ids=' . $the_order->id ), 'generate_wpo_wcpdf' ),
				'img'		=> plugins_url() . '/woo-piva-codice-fiscale-e-fattura-pdf-per-italia/images/receipt.png',
				'alt'		=> __( 'PDF Receipt', WCPIVACF_IT_DOMAIN )
			)), $listing_actions);
			unset($listing_actions['invoice']);
		}
		if($invoicetype == "professionist_invoice") {
			$listing_actions = array_merge(array("professionist_invoice" => array(
				'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $the_order->id ), 'generate_wpo_wcpdf' ),
				'img'		=> plugins_url() . '/woo-piva-codice-fiscale-e-fattura-pdf-per-italia/images/professionist_invoice.png',
				'alt'		=> __( 'PDF Invoice', WCPIVACF_IT_DOMAIN )
			)), $listing_actions);
			unset($listing_actions['invoice']);
		}
		if($invoicetype == "private_invoice") {
			$listing_actions = array_merge(array("private_invoice" => array(
				'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $the_order->id ), 'generate_wpo_wcpdf' ),
				'img'		=> plugins_url() . '/woo-piva-codice-fiscale-e-fattura-pdf-per-italia/images/private_invoice.png',
				'alt'		=> __( 'PDF Invoice', WCPIVACF_IT_DOMAIN )
			)), $listing_actions);
			unset($listing_actions['invoice']);
		}
		return $listing_actions;
	}
	
	public function wcpdf_bulk_actions( $bulk_actions) {
		$bulk_actions['receipt'] = __( 'PDF Receipts', WCPIVACF_IT_DOMAIN );
		return $bulk_actions;
	}
	
	public function wcpdf_process_template_order($template_type, $order_id) {
		if(in_array( $template_type, array('invoice', 'professionist_invoice','private_invoice'))) {
			$invoicetype = get_post_meta($order_id,"_billing_invoice_type",true);
			$template_type = $invoicetype ? $invoicetype : "invoice";
		}
		return $template_type;
	}
	
	public function wcpdf_process_order_ids( $order_ids, $template_type) {
		$oids = array();
		if( in_array( $template_type, array('invoice', 'receipt','professionist_invoice','private_invoice') ) ) return($order_ids);
	
		foreach ($order_ids as $order_id) {
			$invoicetype = get_post_meta($order_id,"_billing_invoice_type",true);
			if((empty($invoicetype) && in_array( $template_type, array('invoice', 'professionist_invoice','private_invoice')) ) || ($invoicetype == $template_type)) $oids[] = $order_id;
		}
		return $oids;
	}
	
	public function wcpdf_custom_email_condition($flag, $order, $status) {
		$invoicetype = get_post_meta($order->id,"_billing_invoice_type",true);
		return (in_array( $template_type, array('invoice', 'professionist_invoice','private_invoice'))) ? true : false;
	}
	
	public function wcpdf_my_account( $actions, $order ) {
		$invoicetype = get_post_meta($order->id,"_billing_invoice_type",true);
		
		if ( $invoicetype == 'receipt') {
			$wpo_wcpdf_general_settings = get_option('wpo_wcpdf_general_settings');
			$pdf_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=receipt&order_ids=' . $order->id . '&my-account'), 'generate_wpo_wcpdf' );
			if (isset($wpo_wcpdf_general_settings['my_account_buttons'])) {
				switch ($wpo_wcpdf_general_settings['my_account_buttons']) {
					case 'available':
					case 'always':
						$invoice_allowed = true;
						break;
					case 'never':
						$invoice_allowed = false;
						break;
					case 'custom':
						if ( isset( $wpo_wcpdf_general_settings['my_account_restrict'] ) && in_array( $order->status, array_keys( $wpo_wcpdf_general_settings['my_account_restrict'] ) ) ) {
							$invoice_allowed = true;
						} else {
							$invoice_allowed = false;							
						}
						break;
				}
			} else {
				$invoice_allowed = true; 
			}
			if ($invoice_allowed) {
				$actions['receipt'] = array(
					'url'  => $pdf_url,
					'name' => __( 'Download Receipt (PDF)', WCPIVACF_IT_DOMAIN )
				);				
			}
			unset($actions['invoice']);
		}
		return $actions;
	}
	
	public function wcpdf_template_files( $template, $template_type ) {
		global $wcpivacf_IT;
	
		if ( file_exists( $template ) ) {
			return $template;
		}
		
		if ( $template_type == 'receipt') {
			$receipt_template = $wcpivacf_IT->plugin_path . 'templates/pdf/Simple/receipt.php';
			if( file_exists( $receipt_template ) ) {
				$template = $receipt_template;
			}
		}
	
		return $template;
	}
	
	public function wcpdf_attach_receipt( $documents ) {
		global $wpo_wcpdf;
		$invoicetype = get_post_meta($wpo_wcpdf->export->order->id,"_billing_invoice_type",true);
		if ( $invoicetype == 'receipt') {
			$documents['receipt'] = $documents['invoice'];
			unset($documents['invoice']);
		}
		return $documents;
	}

}
$wcpivacf_it_add_on = new wcpdf_WooCommerce_Piva_Cf_Invoice_Ita_PDF();
endif;
?>