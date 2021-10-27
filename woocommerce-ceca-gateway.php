<?php
/*
Plugin Name: Pasarela de pago para CECA (modulosdepago.es)
Plugin URI: http://modulosdepago.es/
Description: La pasarela de pago CECA de para WooCommerce de ZhenIT Software <a href="http://www.modulosdepago.es/">vea otras pasarelas de ZhenIT Software</a>.
Version: 2.3.0
Author: Mikel Martin (Modified by Ilie Florea) 
Author URI: http://ZhenIT.com/
WC requires at least: 3.0.0
WC tested up to: 3.9.3
	Copyright: © 2009-2012 ZhenIT Software.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
/**
 * Required functions
 */
//if ( ! function_exists( 'is_woocommerce_active' ) ) require_once( 'zhenit-includes/zhenit-functions.php' );
add_action('plugins_loaded', 'woocommerce_ceca_init', 100);

function woocommerce_ceca_init() {
	/**
	* Required functions
	*/
	if ( ! function_exists( 'is_woocommerce_active' ) ) require_once( 'zhenit-includes/zhenit-functions.php' );

	if ( ! class_exists( 'WC_Payment_Gateway' ) || ! is_woocommerce_active() )
		return;

	load_plugin_textdomain( 'wc_ceca', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	 * Pasarela CECA Gateway Class
	 * */
	class woocommerce_ceca extends WC_Payment_Gateway {


		public function __construct() {
			global $woocommerce;

			$this->id = 'ceca';
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/cards.png';
			$this->has_fields = false;
			$this->liveurl = 'https://pgw.ceca.es/cgi-bin/tpv';
			$this->testurl = 'http://tpv.ceca.es:8000/cgi-bin/tpv';

			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Get setting values
			$this->enabled = $this->settings['enabled'];
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->merchant = $this->settings['merchant'];
			$this->terminal = $this->settings['terminal'];
			$this->acquirerbin = $this->settings['acquirerbin'];
			$this->entorno = $this->settings['entorno'];
			$this->clave = $this->settings['clave'];
			$this->clave2 = $this->settings['clave2'];
            $this->form_submission_method = ( isset( $this->settings['form_submission_method'] ) && $this->settings['form_submission_method'] == 'yes' ) ? true : false;
			$this->debug = $this->settings['debug'];

			// Logs
			if ($this->debug == 'yes')
				$this->log = new WC_Logger();

			// Hooks
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_woocommerce_' . $this->id, array( $this, 'check_' . $this->id . '_resquest' ) );			
			//BOF dejamos por compatibilidad con las versiones 1.6.x
			add_action('init', array(&$this, 'check_' . $this->id. '_resquest'));
			add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
			//EOF dejamos por compatibilidad con las versiones 1.6.x

			if ($this->debug == 'yes')
                $this->log->add('ceca','Init Succesfil');
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'label' => __('Habilitar pasarela CECA', 'wc_ceca'),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default' => __('Tarjeta de crédito o débito (vía CECA)', 'wc_ceca')
				),
				'description' => array(
					'title' => __('Description', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
					'default' => 'Pague con trajeta de crédito de forma segura a través de la pasarela de CECA.'
				),
				'merchant' => array(
					'title' => __('Número de comercio (Merchant ID)', 'wc_ceca'),
					'type' => 'text',
					'description' => __('Número de comercio proporcionado por CECA.', 'wc_ceca'),
					'default' => ''
				),
				'acquirerbin' => array(
					'title' => __('AcquirerBin', 'wc_ceca'),
					'type' => 'text',
					'description' => __('AcquirerBin proporcionado por CECA.', 'wc_ceca'),
					'default' => ''
				),
				'terminal' => array(
					'title' => __('Número de terminal (Terminal Id)', 'wc_ceca'),
					'type' => 'text',
					'description' => __('Número de terminal proporcionado por CECA.', 'wc_ceca'),
					'default' => '00000003'
				),
				'clave' => array(
					'title' => __('Clave', 'wc_ceca'),
					'type' => 'text',
					'description' => __('Clave de encriptación para la firma, proporcionada por CECA.', 'wc_ceca'),
					'default' => ''
				),
				'clave2' => array(
					'title' => __('Clave (pruebas)', 'wc_ceca'),
					'type' => 'text',
					'description' => __('Clave de encriptación del entorno de pruebas.', 'wc_ceca'),
					'default' => ''
				),
				'entorno' => array(
					'title' => __( 'Entorno', 'wc_ceca' ),
					'type' => 'select',
					'description' => '',
					'default' => 'order',
					'options' => array(
					  'p' => __('Pruebas', 'wc_ceca'),
					  'r' => __('Real', 'wc_ceca')
					)
				),
                'form_submission_method' => array(
                    'title' => __( 'Submission method', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Use form submission method.', 'woocommerce' ),
                    'description' => __( 'Enable this to post order data to CECA via a form instead of using a redirect/querystring.', 'wc_ceca' ),
                    'default' => 'no'
                ),
				'debug' => array(
					'title' => __('Debugging', 'woocommerce'),
					'label' => __('Modo debug', 'woocommerce'),
					'type' => 'checkbox',
					'description' => __('Sólo para desarrolladores.', 'wc_ceca'),
					'default' => 'no'
				)
			);
		}

		/**
		 * There are no payment fields for CECA, but we want to show the description if set.
		 * */
		function payment_fields() {
			if ($this->description)
				echo wpautop(wptexturize($this->description));
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 * */
		public function admin_options() {
			?>
			<h3><?php _e('Pasarela CECA', 'wc_ceca'); ?></h3>
			<p><?php _e('<p>La pasarela CECA de <a href="http://ZhenIT.com">ZhenIT Software</a> para Woocommerce le permitirá dar la opción de pago por tarjeta de crédito o débito en su comercio. Para ello necesitará un tpv virtual de CECA con acceso a <a href="https://comercios.ceca.es">https://comercios.ceca.es</a>.</p><p>Recuerde configurar en el backoffice de CECA como "URL de comunicación on.line OK:"  '.str_replace( 'https:', 'http:', add_query_arg('cecaLstr','notify',add_query_arg( 'wc-api', 'woocommerce_'. $this->id, home_url( '/' ) ) )).'</p>', 'wc_ceca'); ?></p>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->
			<?php
		}

		/**
		 * Check for CECA IPN Response
		 * */
		function check_ceca_resquest() {
			if (!isset($_GET['cecaLstr']))
				return;

			if ($_GET['cecaLstr'] == 'notify'){
				$order_id = (int) substr($_REQUEST['Num_operacion'],0,8);
				$order = new WC_Order($order_id);

                // SHA1 - Acualmente en deshuso ya que IberCaja nos solicitó cambiar el método de encriptación
                //$firma = sha1($this->getClave() .$_REQUEST['MerchantID'] .$_REQUEST['AcquirerBIN'] .$_REQUEST['TerminalID'] .$_REQUEST['Num_operacion'] .$_REQUEST['Importe'] .$_REQUEST['TipoMoneda'] .$_REQUEST['Exponente'] .$_REQUEST['Referencia']);

                // SHA256 - Método de encriptación usado actualmente recomendado por IberCaja
                $firma = hash('sha256', $this->getClave() .$_REQUEST['MerchantID'] .$_REQUEST['AcquirerBIN'] .$_REQUEST['TerminalID'] .$_REQUEST['Num_operacion'] .$_REQUEST['Importe'] .$_REQUEST['TipoMoneda'] .$_REQUEST['Exponente'] .$_REQUEST['Referencia']);

				if ($_REQUEST['Firma'] != $firma){
					if ($this->debug=='yes')
						$this->log->add( 'ceca', 'Hacking attempt from: '.$_SERVER['REMOTE_ADDR'] );
					return;
				}

				// Check order not already completed
				if ($order->status == 'completed'){
						if ($this->debug=='yes') $this->log->add( 'ceca', 'Aborting, Order #' . $posted['custom'] . ' is already complete.' );
						return;
						wp_redirect($url, 303);
						exit();
				}

				update_post_meta( (int) $order->id, '[RV] Resultado', 'Pago realizado');
				update_post_meta( (int) $order->id, '[RV] Importe (centimos)',$_REQUEST['Importe']);
				update_post_meta( (int) $order->id, '[RV] Referencia del pago',$_REQUEST['Referencia']);

				// Payment completed
				$order->add_order_note( __('CECA payment completed', 'wc_ceca') );
				$order->payment_complete();

				if ($this->debug=='yes') $this->log->add( 'ceca', 'Payment complete.' );

				echo '<HTML><HEAD><TITLE>Respuesta correcta a la comunicaci&oacute;n ON-LINE</TITLE></HEAD><BODY>$*$OKY$*$</BODY></HTML>';
				exit();
			}
		}

		/**
		 * Get CECA language code
		 * */
		function _getLanguange() {
			switch (substr(get_bloginfo('language'),0,2)){
				case 'en':
					return '6';
				case 'ca':
					return '2';
				case 'fr':
					return '7';
				case 'de':
					return '8';
				case 'pt':
					return '9';
				case 'gl':
					return '4';
				case 'eu':
					return '3';
				default:
					return '1';
			}
			return '1';
		}

		/**
		 * Get CECA Args for passing to PP
		 * */
		function get_ceca_args($order) {
			global $woocommerce;

			$idioma	 = $this->_getLanguange();
			$fecha	  = date ("ymdhis");
			$Num_operacion  = str_pad($order->id, 8, "0", STR_PAD_LEFT) . date('is');
			$importe	= round($order->get_total()*100);
			$moneda		= '978';
			$exponente	= '2';
			$cifrado	= 'SHA2';

			$URL_OK  = html_entity_decode($this->get_return_url( $order ));
			$URL_NOK = html_entity_decode($order->get_cancel_order_url());

            // SHA1 - Acualmente en deshuso ya que IberCaja nos solicitó cambiar el método de encriptación
			//$firma = sha1($this->getClave() . $this->merchant.$this->acquirerbin.$this->terminal.$Num_operacion.$importe.$moneda.$exponente.$cifrado. $URL_OK . $URL_NOK);

            // SHA256 - Método de encriptación usado actualmente recomendado por IberCaja
            $firma = hash('sha256',$this->getClave() . $this->merchant.$this->acquirerbin.$this->terminal.$Num_operacion.$importe.$moneda.$exponente.$cifrado. $URL_OK . $URL_NOK);

			$ceca_args = array(
				'MerchantID'	=> $this->merchant,
				'AcquirerBIN'	=> $this->acquirerbin,
				'TerminalID'	=> $this->terminal,
				'Num_operacion'	=> $Num_operacion,
				'Importe'		=> $importe,
				'TipoMoneda'	=> $moneda,
				'Exponente'		=> $exponente,
				'Idioma'		=> $idioma,
				'Pago_soportado'=> 'SSL',
				'Cifrado'		=> $cifrado,
				'Firma'			=> $firma,
				'URL_OK'		=> $URL_OK,
				'URL_NOK'		=> $URL_NOK
			);
		//$ceca_args = apply_filters( 'woocommerce_ceca_args', $ceca_args );
			return $ceca_args;
		}

		function getClave() {
			if ($this->entorno == 'p')
				return $this->clave2;
			return $this->clave;
		}
		/**
		 * Generate the ceca button link
		 * */
		function generate_ceca_form($order_id) {
			global $woocommerce;
            if ($this->debug == 'yes')
                $this->log->add('ceca','generate_ceca_form');
			$order = new WC_Order($order_id);
			$ceca_args = $this->get_ceca_args($order);
			$ceca_args_array = array();

			foreach ($ceca_args as $key => $value) {
				$ceca_args_array[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
			}

			if ($this->entorno == 'p'){
				$ceca_adr = $this->testurl;
			} else {
				$ceca_adr = $this->liveurl;
			}

			wc_enqueue_js('
			jQuery("body").block({
					message: "<img src=\"' . esc_url($woocommerce->plugin_url()) . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to CECA to make payment.', 'woocommerce') . '",
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:		20,
						textAlign:	  "center",
						color:		  "#555",
						border:		 "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:		 "wait",
						lineHeight:		"32px"
					}
				});
			jQuery("#submit_ceca_payment_form").click();
		');

		return '<form action="' . esc_url($ceca_adr) . '" method="post" id="ceca_payment_form" target="_top">
				' . implode('', $ceca_args_array) . '
				<input type="submit" class="button-alt" id="submit_ceca_payment_form" value="' . __('Pagar con trajeta de crédito', 'wc_ceca') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'wc_ceca') . '</a>
			</form>';
		}

		function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );
            if ( ! $this->form_submission_method ) {

                $ceca_args = $this->get_ceca_args( $order );

                $ceca_args = http_build_query( $ceca_args, '', '&' );

                if ( $this->entorno == 'p' ):
                    $ceca_adr = $this->testurl . '?';
                else :
                    $ceca_adr = $this->liveurl . '?';
                endif;

                return array(
                    'result'    => 'success',
                    'redirect'  => $ceca_adr . $ceca_args
                );

            } else {
                return array(
                    'result'     => 'success',
                    'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url( true )))
                );
            }
		}

		/**
		 * receipt_page
		 * */
		function receipt_page($order) {
            if ($this->debug == 'yes')
                $this->log->add('ceca','receipt_page');
			echo '<p>' . __('Thank you for your order, please click the button below to pay with your Credit Card.', 'wc_ceca') . '</p>';
			echo $this->generate_ceca_form($order);
		}

		/**
		 * Get post data if set
		 * */
		private function get_post($name) {
			if (isset($_POST[$name])) {
				return $_POST[$name];
			}
			return NULL;
		}

	}

	/**
	 * Add the gateway to woocommerce
	 * */
	function add_ceca_gateway($methods) {
		$methods[] = 'woocommerce_ceca';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_ceca_gateway');
}