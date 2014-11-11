<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));
include_once( __DIR__ . "/payu.cls.php" );

/**
 * PayU Payment Gateway
 *
 * Provides a PayU Payment Gateway.
 *
 * @class 		WC_PayU
 * @extends		WC_Gateway_PayU
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		WooThemes
 */
class WC_Gateway_PayU extends WC_Payment_Gateway {

	var $notify_url;

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
	public function __construct() {
		global $woocommerce;

        $this->id           = 'payu';
        $this->icon         = apply_filters( 'woocommerce_payu_icon', 'https://raw.github.com/PayUUA/Prestashop-1.5/master/payu/img/payu.jpg' );
        $this->has_fields   = false;
        $this->method_title = __( 'PayU', 'woocommerce' );
   		$this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_PayU', home_url( '/' ) ) );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables


		$values = array ( "title", "description", "merchant", "secret_key", "country", "debug", "price_currency", "language", "VAT", "backref" );

		foreach ( $values as $v )
		{
			$this->$v = $this->get_option( $v );
		}


		// Actions
		add_action( 'valid-payu-standard-ipn-request', array( $this, 'successful_request' ) );
		add_action( 'woocommerce_receipt_payu', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_payu', array( $this, 'check_ipn_response' ) );

		if ( !$this->is_valid_for_use() ) $this->enabled = false;
    }


    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use() {
        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_payu_supported_currencies', array( 'USD', 'UAH', 'RUB', 'TRY', 'EUR' ) ) ) ) return false;
        return true;
    }

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

		?>
		<h3><?php _e( 'PayU standard', 'woocommerce' ); ?></h3>
		<p><?php _e( 'PayU standard works by sending the user to PayU to enter their payment information.', 'woocommerce' ); ?></p>

    	<?php if ( $this->is_valid_for_use() ) : ?>

			<table class="form-table">
			<?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->

		<?php else : ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'PayU does not support your store currency.', 'woocommerce' ); ?></p></div>
		<?php
			endif;
	}


    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {
	$country = array("RU" => "Russia", "UA" => "Ukraine");
  	$curType = array('UAH' => 'Гривны', 'RUB' => 'Рубли');
  	$country = array('https://secure.payu.ua/order/lu.php' => 'Украина', 'https://secure.payu.ru/order/lu.php' => 'Россия');
  	$languages = array('RU' => 'Русский', 'EN' => 'Английский');

    $this->form_fields = array(
		
    		'ipn_url' => array(
							'title' => __( 'Ссылка для IPN', 'woocommerce' ),
							'type' => 'title',
							'description' => 'Установите такую ссылку IPN : <b>'.$this->notify_url."</b>",
							),


    		'General_ops' => array(
							'title' => __( 'Обшие настройки', 'woocommerce' ),
							'type' => 'title',
							'description' => '',
							),

			'enabled' => array(
							'title' => __( 'Включен/Выключен', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Включить работу палатежного шлюза PayU', 'woocommerce' ),
							'default' => 'yes'
						),
			'title' => array(
							'title' => __( 'Название', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'Такоей название будет отображаться в корзине.', 'woocommerce' ),
							'default' => __( 'PayU', 'woocommerce' ),
							'desc_tip'      => true,
						),
			'description' => array(
							'title' => __( 'Описание', 'woocommerce' ),
							'type' => 'textarea',
							'description' => __( 'Такое описание будет под названием способа оплаты.', 'woocommerce' ),
							'default' => __( 'Оплата через платежный шлюз PayU<a target="_blank" href="payu.ua">payu.ua</a>', 'woocommerce' )
						),
			'Merchant_ops' => array(
							'title' => __( 'Настройки мерчанта', 'woocommerce' ),
							'type' => 'title',
							'description' => '',
							),

			'merchant' => array(
							'title' => __( 'Идентификатор мерчанта', 'woocommerce' ),
							'type' 			=> 'text',
							'description' => __( 'Идентификатор мерчанта в системе PayU.', 'woocommerce' ),
							'default' => '',
							'desc_tip'      => true,
						),
			'secret_key' => array(
							'title' => __( 'Секретный ключ', 'woocommerce' ),
							'type' 			=> 'text',
							'description' => __( 'Секретный ключ системы PayU.', 'woocommerce' ),
							'default' => '',
							'desc_tip'      => true,
						),
			'country' => array(
							'title' => __( 'Страна мерчанта', 'woocommerce' ),
							'type' => 'select',
							'description' => __( 'Выберите страну, в которой зарегистрирован мерчант PayU.', 'woocommerce' ),
							'default' => 'https://secure.payu.ru/order/lu.php',
							'options' => $country,
							'desc_tip'      => true,
						),
			'debug' => array(
							'title' => __( 'Режим отладки', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Включить режим отладки', 'woocommerce' ),
							'default' => 'no',
							'description' => __( 'При включеном режиме все транзакции будут тестовыми', 'woocommerce' ),
						),			
				
			'language' => array(
							'title' => __( 'Язык страницы оплаты', 'woocommerce' ),
							'type' => 'select',
							'description' => __( 'Выберите язык для старницы оплаты.', 'woocommerce' ),
							'default' => 'RU',
							'desc_tip'      => true,
							'options' => $languages
						),
			'Optional' => array(
							'title' => __( 'Опциональные настройки', 'woocommerce' ),
							'type' => 'title',
							'description' => '',
							),
			'VAT' => array(
							'title' => __( 'Ставка НДС', 'woocommerce' ),
							'type' => 'text',
							'description' => '0 - для того, чтобы не учитывать НДС в стоимости',
							'default' => '0',
							'desc_tip'      => true,
						),
			'backref' => array(
							'title' => __( 'Ссылка для возврата клиента', 'woocommerce' ),
							'type' => 'text',
							'label' => __( 'Ссылка, по которой клиент вернется после оплаты.', 'woocommerce' ),
							'description' => '',
							'default' => 'no'
						)
			);

    }


	/**
	 * Get PayU Args for passing to PP
	 *
	 * @access public
	 * @param mixed $order
	 * @return array
	 */
	function get_payu_args( $order ) {
		global $woocommerce;

		$order_id = $order->id;


		$debug = ( $this->get_option( "debug" ) === "yes" ) ? 1 : 0;
		
		$payu_args = array_merge(
			array(
				'MERCHANT' 				=> $this->get_option( "merchant" ),
				'SECRET_KEY' 			=> $this->get_option( "secret_key" ),
				'DEBUG'					=> $debug,
				'LuUrl'					=> $this->get_option("country"),

				'cmd' 					=> '_cart',
				
				'no_note' 				=> 1,
				'currency_code' 		=> get_woocommerce_currency(),
				'charset' 				=> 'UTF-8',
				'rm' 					=> is_ssl() ? 2 : 1,
				'upload' 				=> 1,

			)
		);


		$billing = array(
					"BILL_FNAME" => $order->billing_first_name,
					"BILL_LNAME" => $order->billing_last_name,
					"BILL_ADDRESS" => $order->billing_address_1,
					"BILL_ADDRESS2" => $order->billing_address_2,
					"BILL_CITY" => $order->billing_city,
					"BILL_PHONE" => $order->billing_phone,
					"BILL_EMAIL" => $order->billing_email,
					"BILL_COUNTRYCODE" => $order->billing_country,
					"BILL_ZIPCODE" => $order->billing_postcode,
					"LANGUAGE" => $this->get_option( "language" ),
					"ORDER_SHIPPING" => number_format( $order->get_shipping() + $order->get_shipping_tax() , 2, '.', '' ),#$order->get_shipping(),
					"PRICES_CURRENCY" => get_woocommerce_currency(),
					"ORDER_REF" => $order->id
	);

		// Shipping
			$delivery = array();
			$delivery = array(
					"DELIVERY_FNAME" => $order->shipping_first_name,
					"DELIVERY_LNAME" => $order->shipping_last_name,
					"DELIVERY_ADDRESS" => $order->shipping_address_1,
					"DELIVERY_ADDRESS2" => $order->shipping_address_2,
					"DELIVERY_CITY" => $order->shipping_city,
					"DELIVERY_PHONE" => $order->billing_phone,
					"DELIVERY_EMAIL" => $order->billing_email,
					"DELIVERY_COUNTRYCODE" => $order->shipping_country,
					"DELIVERY_ZIPCODE" => $order->shipping_postcode,
					);
 


		$OrderArray = array_merge( $billing, $delivery );



		if (  $this->get_option( "backref" ) !== "" && $this->get_option( "backref" ) !== "no" ) 
			$OrderArray['BACK_REF'] = $this->get_option( "backref" );


		
		// Discount not used
			# $payu_args['discount_amount_cart'] = $order->get_order_discount();

			
			$item_names = array();

			if ( sizeof( $order->get_items() ) > 0 )
				foreach ( $order->get_items() as $item )
				{
						$OrderArray['ORDER_PNAME'][] = $item['name']; # Array with data of goods
						$OrderArray['ORDER_QTY'][] = $item['qty']; # Array with data of counts of each goods 
						$OrderArray['ORDER_PRICE'][] = $order->get_item_subtotal( $item, false ); #number_format( $order->get_total() - $order->get_shipping() - $order->get_shipping_tax() + $order->get_order_discount(), 2, '.', '' ); # Array with prices of goods
						$OrderArray['ORDER_PCODE'][] = $item['product_id'] ; #"testgoods_".$item['id']; # Array with codes of goods
						$OrderArray['ORDER_VAT'][] = $this->get_option( "VAT" );# Array with VAT of each goods  => from settings
				}
	if($order->get_order_discount()) $OrderArray['DISCOUNT'] = $order->get_order_discount();
		$payu_args['Payu_data'] = $OrderArray;
		$payu_args = apply_filters( 'woocommerce_payu_args', $payu_args );

		return $payu_args;
	}


    /**
	 * Generate the payu button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_payu_form( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );



		$payu_args = $this->get_payu_args( $order );

	$button = 	'<input type="submit" class="button alt" id="submit_payu_payment_form" value="' . __( 'Оплатить через PayU', 'woocommerce' ) . '" />'.
				' <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Отменить заказ и вернуться в корзину', 'woocommerce' ).'</a>';

		$option  = array( 'merchant' => $payu_args['MERCHANT'], 
						  'secretkey' => $payu_args['SECRET_KEY'], 
						  'debug' => $payu_args['DEBUG'], 
						  'luUrl' => $payu_args['LuUrl'],
						  'button' => $button
						  );

		$pay = PayU::getInst()->setOptions( $option )->setData( $payu_args['Payu_data'] )->LU();


		$woocommerce->add_inline_js( '
			
			jQuery("#submit_payu_payment_form").click( function(){
				jQuery("body").block({
					message: "' . esc_js( __( 'Спасибо за ваш заказ. Сейчас вы будете перенаправлены в систему PayU для оплаты.', 'woocommerce' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
				        padding:        "20px",
				        zindex:         "9999999",
				        textAlign:      "center",
				        color:          "#555",
				        border:         "3px solid #aaa",
				        backgroundColor:"#fff",
				        cursor:         "wait",
				        lineHeight:		"24px",
				    }
				});
			});
		' );



	return  $pay;
	}


    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
	function process_payment( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

		return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
			);

	}


    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
	function receipt_page( $order ) {

		echo '<p>'.__( 'Спасибо за ваш заказ. Нажмите кнопку, для перехода к оплате через PayU.', 'woocommerce' ).'</p>';
		echo $this->generate_payu_form( $order );

	}

	/**
	 * Check PayU IPN validity
	 **/
	function check_ipn_request_is_valid() {
		global $woocommerce;

		$debug = ( $this->get_option( "debug" ) === "yes" ) ? 1 : 0;

		$this->option  = array( 'merchant' => $this->get_option( "merchant" ), 
						  'secretkey' => $this->get_option( "secret_key" ), 
						  'debug' => $debug, 
				  		);
		$this->payansewer = PayU::getInst()->setOptions( $this->option )->IPN();

		if ($_POST['ORDERSTATUS'] !== "COMPLETE" && ( $debug == 1 &&  $_POST['ORDERSTATUS'] !== "TEST")  ) return false;

		echo $this->payansewer;
		
		return true;
    }


	/**
	 * Check for PayU IPN Response
	 *
	 * @access public
	 * @return void
	 */
	function check_ipn_response() {
		@ob_clean();
    	if ( ! empty( $_POST ) && $this->check_ipn_request_is_valid() ) {

    		header( 'HTTP/1.1 200 OK' );

        	do_action( "valid-payu-standard-ipn-request", $_POST );
		} else {
			wp_die( "PayU IPN Request Failure" );

   		}

	}


	/**
	 * Successful Payment!
	 *
	 * @access public
	 * @param array $posted
	 * @return void
	 */
	function successful_request( $posted ) {
		global $woocommerce;

		$order = $this->get_payu_order( $posted );

        if ( $this->option['debug'] == 1 && $posted['ORDERSTATUS'] == 'TEST' )
	        	update_post_meta( $order->id, 'Тип транзакции', $posted['ORDERSTATUS'] );

           	// Check order not already completed
	            	if ( $order->status == 'completed' ) {
						exit;
	            	}
					// Validate Amount
				    if ( $order->get_total() != $posted['IPN_TOTALGENERAL'] ) {
				    	// Put this order on-hold for manual checking
				    	$order->update_status( 'on-hold', sprintf( __( 'Ошибка валидации: сумма оплаты не совпадает (сумма PayU : %s).', 'woocommerce' ), $posted['IPN_TOTALGENERAL'] ) );

				    	exit;
				    }

					 // Store PP Details
	                if ( ! empty( $posted['payer_email'] ) )
	                	update_post_meta( $order->id, 'Адрес плательщики', $posted['payer_email'] );
	                if ( ! empty( $posted['REFNO'] ) )
	                	update_post_meta( $order->id, 'ID транзакции', $posted['REFNO'] );
	                if ( ! empty( $posted['FIRSTNAME'] ) )
	                	update_post_meta( $order->id, 'Имя плательщика', $posted['FIRSTNAME'] );
	                if ( ! empty( $posted['LASTNAME'] ) )
	                	update_post_meta( $order->id, 'Фамилия плательщика', $posted['LASTNAME'] );
	                if ( ! empty( $posted['PAYMETHOD'] ) )
	                	update_post_meta( $order->id, 'Платежная система', $posted['PAYMETHOD'] );

	            	// Payment completed
	                $order->add_order_note( __( 'IPN оплата завершена', 'woocommerce' ) );
	                $order->payment_complete();
	}


	/**
	 * get_payu_order function.
	 *
	 * @access public
	 * @param mixed $posted
	 * @return void
	 */
	function get_payu_order( $posted ) {
    	$order_id = $_POST['REFNOEXT'];
		$order = new WC_Order( $order_id );
	    return $order;
	}

}





class WC_PayU extends WC_Gateway_PayU {
	public function __construct() {
		_deprecated_function( 'WC_PayU', '1.4', 'WC_Gateway_PayU' );
		parent::__construct();
	}
}

?>
