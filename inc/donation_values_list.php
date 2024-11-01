<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class I13_List_Table extends WP_List_Table {

	

	private $sample_data = array(

		
	);



	public function __construct() {

		parent::__construct(
			array(
				'singular'  => __('Donation Amout', 'simple-donation-for-woo'),
				'plural'    => __('Donation Amouts', 'simple-donation-for-woo'),
				'ajax'      => true
			)
		);

	}

	
	public function get_columns() {

		$columns = array(
			'id'      => __('ID', 'simple-donation-for-woo'),
			'donation'  =>__('Donation Value', 'simple-donation-for-woo'),
			'actions'  =>__('Actions', 'simple-donation-for-woo'),
			
		);
		return $columns;

	}

	

	public function column_default( $item, $column_name ) {
				
				$loader=plugins_url('../public/images/bx_loader.gif', __FILE__);
		switch ( $column_name ) {
			case 'id':
			case 'donation':
				return wc_price($item[ $column_name ]);
								break;
			case 'actions':
				return "<span data-id='" . $item[ 'id' ] . "'  data-donation='" . $item[ 'donation' ] . "' class='edit_donation dashicons dashicons-edit' style='cursor:pointer'></span> &nbsp;<span data-id='" . $item[ 'id' ] . "'  data-donation='" . $item[ 'donation' ] . "' class='delete_donation dashicons dashicons-dismiss' style='cursor:pointer'></span><img class='loader_simple_{$item[ 'id' ]}' style='vertical-align:middle;width:20px;display: none' src='$loader'/>";
								break;
			default:
				return print_r( $item, true );
		}
	}

	

	private $hidden_columns = array(
		'id'
	);

	

	public function get_sortable_columns() {

		 $sortable_columns = array(
			'donation'	=> array( 'donation', false )
			
		 );
				 
				 return $sortable_columns;
	}

	

	public function prepare_items() {

		
		$per_page = 5;

		
		$columns  = $this->get_columns();
		$hidden   = $this->hidden_columns;
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);

		
		if (get_option('i13_simple_donations_values')) {
					
			$data = get_option('i13_simple_donations_values');
		} else {
					
			$data = array();
					
		}
				
				
		function usort_reorder( $a, $b ) {

			$orderby = esc_sql(sanitize_text_field(( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'donation'));
			$order = esc_sql(sanitize_text_field(( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc'));
			$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
			return ( 'asc' === $order ) ? $result : -$result;
		}
		usort( $data, 'usort_reorder' );

		$current_page = $this->get_pagenum();

		$total_items = count($data);

		$data = array_slice($data, ( ( $current_page-1 )*$per_page ), $per_page);

		$this->items = $data;

		$this->set_pagination_args(
			array(

				'total_items'	=> $total_items,
				'per_page'	    => $per_page,
				'total_pages'	=> ceil( $total_items / $per_page ),
				'orderby'	    => esc_sql(sanitize_text_field(( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'donation')),
				'order'		    => esc_sql(sanitize_text_field(( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc'))
			)
		);
	}

	
	public function display() {

		
		wp_nonce_field( 'ajax-custom-list-nonce', '_ajax_custom_list_nonce' );

		echo wp_kses_post('<input type="hidden" id="order" name="order" value="' . $this->_pagination_args['order'] . '" />');
		echo wp_kses_post('<input type="hidden" id="orderby" name="orderby" value="' . $this->_pagination_args['orderby'] . '" />');

		parent::display();
	}

	
	public function ajax_response() {

		check_ajax_referer( 'ajax-custom-list-nonce', '_ajax_custom_list_nonce' );

		$this->prepare_items();

		extract( $this->_args );
		extract( $this->_pagination_args, EXTR_SKIP );

		ob_start();
		if ( ! empty( $_REQUEST['no_placeholder'] ) ) {
			$this->display_rows();
		} else {
			$this->display_rows_or_placeholder();
		}
		$rows = ob_get_clean();

		ob_start();
		$this->print_column_headers();
		$headers = ob_get_clean();

		ob_start();
		$this->pagination('top');
		$pagination_top = ob_get_clean();

		ob_start();
		$this->pagination('bottom');
		$pagination_bottom = ob_get_clean();

		$response = array( 'rows' => $rows );
		$response['pagination']['top'] = $pagination_top;
		$response['pagination']['bottom'] = $pagination_bottom;
		$response['column_headers'] = $headers;

		if ( isset( $total_items ) ) {
						
						/* translators: %s: Items */
			$response['total_items_i18n'] = sprintf( _n( '%s item', '%s items', $total_items, 'simple-donation-for-woo' ), number_format_i18n( $total_items ) );
		}

		if ( isset( $total_pages ) ) {
			$response['total_pages'] = $total_pages;
			$response['total_pages_i18n'] = number_format_i18n( $total_pages );
		}

		die( json_encode( $response ) );
	}

}
