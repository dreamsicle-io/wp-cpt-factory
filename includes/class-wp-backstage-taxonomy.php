<?php
/**
 * WP Backstage Taxonomy
 * 
 * @version     0.0.1
 * @package     wp_backstage
 * @subpackage  includes
 */
class WP_Backstage_Taxonomy extends WP_Backstage {

	/**
	 * Slug
	 * 
	 * @since 0.0.1
	 */
	public $slug = '';

	/**
	 * Args
	 * 
	 * @since 0.0.1
	 */
	public $args = array();

	/**
	 * Default Args
	 * 
	 * @since 0.0.1
	 */
	public $default_args = array(
		'singular_name'   => '', 
		'plural_name'     => '', 
		'description'     => '', 
		'public'          => true, 
		'hierarchical'    => true, 
		'with_front'      => false, 
		'archive_base'    => '', 
		'rest_base'       => '', 
	);

	/**
	 * Required Args
	 * 
	 * @since 0.0.1
	 */
	public $required_args = array(
		'singular_name', 
		'plural_name', 
	);

	/**
	 * Add
	 * 
	 * @since   0.0.1
	 * @param   string  $slug 
	 * @param   array   $args 
	 * @return  void 
	 */
	public static function add( $slug = '', $args = array() ) {

		$Taxonomy = new WP_Backstage_Taxonomy( $slug, $args );

		$Taxonomy->init();

	}

	/**
	 * Construct
	 * 
	 * @since   0.0.1
	 * @param   string  $slug 
	 * @param   array   $args 
	 * @return  void 
	 */
	function __construct( $slug = '', $args = array() ) {

		$this->default_field_args = array_merge( $this->default_field_args, array(
			'has_column'  => false, 
			'is_sortable' => false, 
		) );
		$this->slug = sanitize_title_with_dashes( $slug );
		$this->set_args( $args );
		$this->screen_id = sprintf( 'edit-%1$s', $this->slug );
		$this->has_media = ! empty( $this->get_field_by( 'type', 'media' ) );
		$this->has_date = ! empty( $this->get_field_by( 'type', 'date' ) );
		$this->has_color = ! empty( $this->get_field_by( 'type', 'color' ) );
		$this->code_editors = $this->get_fields_by( 'type', 'code' );
		$this->set_errors();

		parent::__construct();

	}

	/**
	 * Set Args
	 * 
	 * @since   0.0.1
	 * @return  boolean  Whether the instance has errors or not. 
	 */
	public function set_args( $args = array() ) {

		$this->args = wp_parse_args( $args, $this->default_args );

		if ( empty( $this->args['singular_base'] ) ) {

			$this->args['singular_base'] = $this->slug;

		}

		if ( empty( $this->args['archive_base'] ) ) {

			$this->args['archive_base'] = $this->slug;

		}

		if ( empty( $this->args['menu_name'] ) ) {

			if ( ! empty( $this->args['plural_name'] ) ) {

				$this->args['menu_name'] = $this->args['plural_name'];

			} elseif ( ! empty( $this->args['singular_name'] ) ) {

				$this->args['menu_name'] = $this->args['singular_name'];

			} else {

				$this->args['menu_name'] = $this->slug;

			}

		}

	}

	/**
	 * Set Errors
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function set_errors() {

		if ( empty( $this->slug ) ) {
			
			$this->errors[] = new WP_Error( 'required_taxonomy_slug', sprintf( 
				/* translators: 1: taxonomy slug. */
				__( '[taxonomy: %1$s] A slug is required when adding a new taxonomy.', 'wp-backstage' ), 
				$this->slug
			) );
		
		} elseif ( strlen( $this->slug ) > 32 ) {
			
			$this->errors[] = new WP_Error( 'taxonomy_slug_length', sprintf( 
				/* translators: 1: taxonomy slug. */
				__( '[taxonomy: %1$s] A taxonomy slug must be between 1 and 32 characters.', 'wp-backstage' ), 
				$this->slug
			) );
		
		} elseif ( in_array( $this->slug, get_taxonomies() ) ) {

			$this->errors[] = new WP_Error( 'taxonomy_exists', sprintf( 
				/* translators: 1: taxonomy slug */
				__( '[taxonomy: %1$s] A taxonomy with this slug already exists.', 'wp-backstage' ), 
				$this->slug
			) );

		}

		if ( is_array( $this->required_args ) && ! empty( $this->required_args ) ) {

			foreach ( $this->required_args as $required_arg ) {

				if ( empty( $this->args[$required_arg] ) ) {

					$this->errors[] = new WP_Error( 'required_taxonomy_arg', sprintf( 
						/* translators: 1: taxonomy slug, 2:required arg key. */
						__( '[taxonomy: %1$s] The %2$s key is required.', 'wp-backstage' ), 
						$this->slug, 
						'<code>' . $required_arg . '</code>'
					) );

				}

			}

		}

	}

	/**
	 * Init
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function init() {

		if ( $this->has_errors() ) {
			
			add_action( 'admin_notices', array( $this, 'print_errors' ) );
			
			return;

		}

		add_action( 'init', array( $this, 'register' ), 0 );
		add_action( sprintf( '%1$s_add_form_fields', $this->slug ), array( $this, 'render_add_nonce' ), 10 );
		add_action( sprintf( '%1$s_add_form_fields', $this->slug ), array( $this, 'render_add_fields' ), 10 );
		add_action( sprintf( '%1$s_term_edit_form_top', $this->slug ), array( $this, 'render_edit_nonce' ), 10 );
		add_action( sprintf( '%1$s_edit_form_fields', $this->slug ), array( $this, 'render_edit_fields' ), 10, 2 );
		add_action( sprintf( 'edited_%1$s', $this->slug ), array( $this, 'save' ), 10, 2 );
		add_action( sprintf( 'created_%1$s', $this->slug ), array( $this, 'save' ), 10, 2 );
		add_action( sprintf( 'manage_edit-%1$s_columns', $this->slug ), array( $this, 'add_field_columns' ), 10 );
		add_action( sprintf( 'manage_edit-%1$s_sortable_columns', $this->slug ), array( $this, 'manage_sortable_columns' ), 10 );
		add_filter( sprintf( 'manage_%1$s_custom_column', $this->slug ), array( $this, 'render_admin_column' ), 10, 3 );
		add_filter( 'terms_clauses', array( $this, 'manage_sorting' ), 10, 3 );

		parent::init();

	}

	/**
	 * Get Label
	 * 
	 * @since   0.0.1
	 * @param   string  $template 
	 * @return  string 
	 */
	public function get_label( $template = '' ) {

		return sprintf(
			/* translators: 1: taxonomy singular name, 2: taxonomy plural name. */
			$template,
			$this->args['singular_name'], 
			$this->args['plural_name']
		);

	}

	/**
	 * Render Add nonce
	 * 
	 * @since   0.0.1
	 * @return  string 
	 */
	public function render_add_nonce() {

		wp_nonce_field( 'add', sprintf( '_%1$s_nonce', $this->slug ) );

	}

	/**
	 * Render Add nonce
	 * 
	 * @since   0.0.1
	 * @return  string 
	 */
	public function render_edit_nonce() {

		wp_nonce_field( 'edit', sprintf( '_%1$s_nonce', $this->slug ) );

	}

	public function register() {

		$labels = array(
			'name'                       => $this->args['plural_name'],
			'singular_name'              => $this->args['singular_name'],
			'menu_name'                  => $this->args['plural_name'],
			'all_items'                  => $this->get_label( __( 'All %2$s', 'wp-backstage' ) ),
			'parent_item'                => $this->get_label( __( 'Parent %1$s', 'wp-backstage' ) ),
			'parent_item_colon'          => $this->get_label( __( 'Parent %1$s:', 'wp-backstage' ) ),
			'new_item_name'              => $this->get_label( __( 'New %1$s Name', 'wp-backstage' ) ),
			'add_new_item'               => $this->get_label( __( 'Add New %1$s', 'wp-backstage' ) ),
			'edit_item'                  => $this->get_label( __( 'Edit %1$s', 'wp-backstage' ) ),
			'update_item'                => $this->get_label( __( 'Update %1$s', 'wp-backstage' ) ),
			'view_item'                  => $this->get_label( __( 'View %1$s', 'wp-backstage' ) ),
			'separate_items_with_commas' => $this->get_label( __( 'Separate %2$s with commas', 'wp-backstage' ) ),
			'add_or_remove_items'        => $this->get_label( __( 'Add or remove %2$s', 'wp-backstage' ) ),
			'choose_from_most_used'      => $this->get_label( __( 'Choose from the most used %2$s', 'wp-backstage' ) ),
			'popular_items'              => $this->get_label( __( 'Popular %2$s', 'wp-backstage' ) ),
			'search_items'               => $this->get_label( __( 'Search %2$s', 'wp-backstage' ) ),
			'not_found'                  => $this->get_label( __( 'No %2$s Found', 'wp-backstage' ) ),
			'no_terms'                   => $this->get_label( __( 'No %2$s', 'wp-backstage' ) ),
			'items_list'                 => $this->get_label( __( '%2$s list', 'wp-backstage' ) ),
			'items_list_navigation'      => $this->get_label( __( '%2$s list navigation', 'wp-backstage' ) ),
			'back_to_items'              => $this->get_label( __( 'Back to %2$s', 'wp-backstage' ) ),
		);

		$rewrite = array(
			'slug'                       => $this->args['archive_base'],
			'with_front'                 => $this->args['with_front'],
			'hierarchical'               => $this->args['hierarchical'],
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => $this->args['hierarchical'],
			'public'                     => $this->args['public'],
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => $this->args['public'],
			'show_tagcloud'              => $this->args['public'],
			'rewrite'                    => $this->args['public'] ? $rewrite : false,
			'show_in_rest'               => ( $this->args['public'] && ! empty( $this->args['rest_base'] ) ),
			'rest_base'                  => $this->args['rest_base'],
		);

		register_taxonomy( $this->slug, $this->args['post_types'], $args );

	}

	/**
	 * Get Fields
	 * 
	 * @since   0.0.1
	 * @return  array  
	 */
	public function get_fields() {

		$fields = array();

		if ( is_array( $this->args['fields'] ) && ! empty( $this->args['fields'] ) ) {
			
			foreach ( $this->args['fields'] as $field ) {
			
				$fields[] = wp_parse_args( $field, $this->default_field_args );
			
			}
		
		}

		return $fields;

	}

	/**
	 * Render Add Fields
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function render_add_fields( $taxonomy = null ) {

		$fields = $this->get_fields();

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			
			foreach ( $fields as $field ) {

				$this->render_field_by_type( $field );

			}

		}

	}

	/**
	 * Render Edit Fields
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function render_edit_fields( $term = null, $taxonomy = null ) {

		$fields = $this->get_fields();

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			
			foreach ( $fields as $field ) {

				$field['value'] = get_term_meta( $term->term_id, $field['name'], true ); ?>

				<tr class="form-field term-group-wrap">
					
					<th scope="row">
						
						<label><?php 

							echo wp_kses( $field['label'], $this->kses_label ); 

						?></label>

					</th>

					<td><?php 

						$this->render_field_by_type( $field ); 

					?></td>

				</tr>

			<?php }

		}

	}

	/**
	 * Save
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function save( $term_id = 0, $tt_id = 0 ) {

		$nonce_key = sprintf( '_%1$s_nonce', $this->slug );

		if ( ! $term_id > 0 ) { return; }
		if ( ! current_user_can( 'manage_categories' ) ) { return; }
		if ( ! $_POST || empty( $_POST ) ) { return; }
		if ( $_POST['taxonomy'] !== $this->slug ) { return; }
		if ( empty( $_POST[$nonce_key] ) ) { return; }
		if ( ! wp_verify_nonce( $_POST[$nonce_key], 'add' ) && ! wp_verify_nonce( $_POST[$nonce_key], 'edit' ) ) { return; }

		$fields = $this->get_fields();

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			
			$values = array();

			foreach ( $fields as $field ) {

				if ( isset( $_POST[$field['name']] ) ) {

					$value = $this->sanitize_field( $field, $_POST[$field['name']] );

					update_term_meta( $term_id, $field['name'], $value );

					$values[$field['name']] = $value;

				} elseif ( in_array( $field['type'], array( 'checkbox', 'checkbox_set' ) ) ) {

					update_term_meta( $term_id, $field['name'], false );

				} 

			}

			if ( ! empty( $this->args['group_meta_key'] ) ) {

				update_term_meta( $term_id, $this->args['group_meta_key'], $values );

			}

		}

	}

	/**
	 * Render Admin Column
	 * 
	 * @since   0.0.1
	 * @return  void
	 */
	public function render_admin_column( $content = '', $column = '', $term_id = 0 ) {

		$field = $this->get_field_by( 'name', $column );

		if ( ! empty( $field ) ) {

			$value = get_term_meta( $term_id, $column, true );
			$formatted_value = $this->format_field_value( $value, $field );

			if ( ! empty( $formatted_value ) ) {

				$content = $formatted_value;

			} else {

				$content = '&horbar;';

			}

		}

		return $content;

	}

	/**
	 * Filter WP_Term_Query meta query
	 *
	 * @param   object  $query  WP_Term_Query
	 * @return  object
	 */
	function manage_sorting( $pieces = array(), $taxonomies = array(), $args = array() ) {

		global $wpdb; 

		if ( in_array( $this->slug, $taxonomies ) ) {

			$orderby = isset( $_GET['orderby'] ) ? esc_attr( $_GET['orderby'] ) : ''; 

			if ( ! empty( $orderby ) ) {

				$field = $this->get_field_by( 'name', $orderby );

				if ( is_array( $field ) && ! empty( $field ) ) {

					if ( $field['is_sortable'] ) {

						$pieces['join']    .= ' INNER JOIN ' . $wpdb->termmeta . ' AS tm ON t.term_id = tm.term_id ';
						$pieces['where']   .= ' AND tm.meta_key = "' . esc_attr( $field['name'] ) . '"'; 

						if ( $field['type'] === 'number' ) {
							$pieces['orderby']  = ' ORDER BY CAST(tm.meta_value AS SIGNED) '; 
						} else {
							$pieces['orderby']  = ' ORDER BY tm.meta_value '; 
						}

					}

				}

			}

		}

		return $pieces;
	}

}