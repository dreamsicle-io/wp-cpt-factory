<?php
/**
 * WP Backstage Options
 *
 * @package     wp_backstage
 * @subpackage  includes
 */

/**
 * WP Backstage Options
 *
 * @package     wp_backstage
 * @subpackage  includes
 */
class WP_Backstage_Options extends WP_Backstage {

	/**
	 * Default Args
	 * 
	 * @var  array  $default_args  An array of default arguments for this instance.
	 */
	protected $default_args = array(
		'type'              => 'settings', // settings, theme, tools
		'title'             => '', 
		'menu_title'        => '', 
		'description'       => '', 
		'capability'        => 'manage_options', 
		'group_options_key' => '', 
		'sections'          => array(), 
	);

	/**
	 * Default Section Args
	 * 
	 * @var  array  $default_section_args  An array of default section arguments.
	 */
	protected $default_section_args = array(
		'id'          => '', 
		'title'       => '', 
		'description' => '', 
		'fields'      => array(), 
	);

	/**
	 * Required Args
	 * 
	 * @var  array  $required_args  An array of required argument keys.
	 */
	protected $required_args = array(
		'type', 
	);

	/**
	 * Registered
	 * 
	 * @var  array  $registered  An array of already-registered option keys.
	 */
	protected static $registered = array();

	/**
	 * Add
	 * 
	 * @since   0.0.1
	 * @param   array                 $args 
	 * @return  WP_Backstage_Options  A fully constructed `WP_Backstage_Options` instance.
	 */
	public static function add( $slug = '', $args = array() ) {

		$Options = new WP_Backstage_Options( $slug, $args );
		$Options->init();
		return $Options;

	}

	/**
	 * Construct
	 * 
	 * @since   0.0.1
	 * @param   array   $args 
	 * @return  void 
	 */
	protected function __construct( $slug = '', $args = array() ) {

		$this->default_address_args = array_merge( $this->default_address_args, array(
			'max_width'  => '50em', 
		) );
		$this->default_code_args = array_merge( $this->default_code_args, array(
			'max_width'  => '50em', 
		) );
		$this->default_editor_args = array_merge( $this->default_editor_args, array(
			'max_width'  => '50em', 
		) );
		$this->slug = sanitize_title_with_dashes( $slug );
		$this->set_args( $args );
		$this->screen_id = array( 
			sprintf( 'settings_page_%1$s', $this->slug ), 
			sprintf( 'appearance_page_%1$s', $this->slug ), 
			sprintf( 'tools_page_%1$s', $this->slug ) 
		);
		$this->set_errors();

		parent::__construct();

	}

	/**
	 * Set Args
	 * 
	 * @since   0.0.1
	 * @return  boolean  Whether the instance has errors or not. 
	 */
	protected function set_args( $args = array() ) {

		$this->args = wp_parse_args( $args, $this->default_args );

		if ( empty( $this->args['title'] ) ) {

			$this->args['title'] = $this->slug;

		}

		if ( empty( $this->args['menu_title'] ) ) {

			$this->args['menu_title'] = $this->args['title'];

		}

	}

	/**
	 * Set Errors
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	protected function set_errors() {

		if ( empty( $this->slug ) ) {
			
			$this->errors[] = new WP_Error( 'required_options_slug', sprintf( 
				/* translators: 1: post type slug. */
				__( '[options: %1$s] A slug is required when adding a new options page.', 'wp-backstage' ), 
				$this->slug
			) );
		
		} 

		if ( is_array( $this->required_args ) && ! empty( $this->required_args ) ) {

			foreach ( $this->required_args as $required_arg ) {

				if ( empty( $this->args[$required_arg] ) ) {

					$this->errors[] = new WP_Error( 'required_options_arg', sprintf( 
						/* translators: 1: options page slug, 2:required arg key. */
						__( '[options: %1$s] The %2$s key is required.', 'wp-backstage' ), 
						$this->slug,
						'<code>' . $required_arg . '</code>'
					) );

				}

			}

		}

		if ( ! empty( $this->args['group_options_key'] ) ) {

			if ( in_array( $this->args['group_options_key'], self::$registered ) ) {

				$this->errors[] = new WP_Error( 'duplicate_options_key', sprintf( 
					/* translators: 1: options page slug, 2: option key. */
					__( '[options: %1$s] There is already an option with the %2$s key.', 'wp-backstage' ), 
					$this->slug,
					'<code>' . $this->args['group_options_key'] . '</code>'
				) );

			} else {

				self::$registered[] = $this->args['group_options_key'];

			}

		}

		$fields = $this->get_fields();

		if ( is_array( $fields ) && ! empty( $fields ) ) {

			foreach ( $fields as $field ) {

				if ( in_array( $field['name'], self::$registered ) ) {

					$this->errors[] = new WP_Error( 'duplicate_options_key', sprintf( 
						/* translators: 1: options page slug, 2: option key. */
						__( '[options: %1$s] There is already an option with the %2$s key.', 'wp-backstage' ), 
						$this->slug,
						'<code>' . $field['name'] . '</code>'
					) );

				} else {

					self::$registered[] = $field['name'];

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

		add_action( 'admin_menu', array( $this, 'add_page' ), 10 );
		add_action( 'admin_init', array( $this, 'add_settings' ), 10 );
		add_action( 'tool_box', array( $this, 'add_tool_card' ), 10 );

		parent::init();

	}

	public function add_tool_card() {

		if ( $this->args['type'] === 'tools' ) {

			$link_url = add_query_arg( array( 'page' => $this->slug ), admin_url( '/tools.php' ) );
			$link_text = __( 'Go to tool', 'wp-backstage' ); ?>

			<div class="card">

				<?php if ( ! empty( $this->args['title'] ) ) { ?>

					<h2 class="title">

						<a 
						href="<?php echo esc_url( $link_url ); ?>"
						style="text-decoration:none;"><?php 

							echo esc_html( $this->args['title'] );

						?></a>

					</h2>

				<?php } ?>

				<?php if ( ! empty( $this->args['description'] ) ) { ?>

					<p><?php 

						echo wp_kses( $this->args['description'], $this->kses_p );

					?></p>

				<?php } ?>
			
			</div>

		<?php }

	}

	/**
	 * Add Page
	 *
	 * Adds the page according to the `type` argument. If `type` is set to 
	 * `theme`, `add_theme_page()` will be used. If `type` is set to `tools`, 
	 * `add_management_page()` will be used. If `type` is set to `settings` 
	 * (default), `add_options_page()` will be used.
	 *
	 * @link    https://codex.wordpress.org/Function_Reference/add_theme_page add_theme_page()
	 * @link    https://codex.wordpress.org/Function_Reference/add_management_page add_management_page()
	 * @link    https://codex.wordpress.org/Function_Reference/add_options_page add_options_page()
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function add_page() {

		switch ( $this->args['type'] ) {
			case 'theme':
				add_theme_page( 
					$this->args['title'], 
					$this->args['menu_title'], 
					$this->args['capability'], 
					$this->slug, 
					array( $this, 'render_page' ) 
				);
				break;
			case 'tools':
				add_management_page( 
					$this->args['title'], 
					$this->args['menu_title'], 
					$this->args['capability'], 
					$this->slug, 
					array( $this, 'render_page' ) 
				);
				break;
			default:
				add_options_page( 
					$this->args['title'], 
					$this->args['menu_title'], 
					$this->args['capability'], 
					$this->slug, 
					array( $this, 'render_page' ) 
				);
				break;
		}
	}

	public function save() {

		if ( empty( $this->args['group_options_key'] ) ) {
			return null;
		}
		
		$fields = $this->get_fields();
		$values = array();

		if ( is_array( $fields ) && ! empty( $fields ) ) {

			foreach ( $fields as $field ) {

				if ( isset( $_POST[$field['name']] ) ) {

					$value = $this->sanitize_field( $field, $_POST[$field['name']] );

					$values[$field['name']] = $value;

				} elseif ( in_array( $field['type'], array( 'checkbox', 'checkbox_set', 'radio' ) ) ) {

					$value = ( $field['type'] === 'radio' ) ? '' : false;

					$values[$field['name']] = $value;

				} 

			}

		}

		return $values;

	} 

	/**
	 * Add Settings
	 * 
	 * @since   0.0.1
	 * @return  void 
	 */
	public function add_settings() {

		$sections = $this->get_sections();

		if ( ! empty( $this->args['group_options_key'] ) ) {

			register_setting(
				$this->slug, 
				$this->args['group_options_key'], 
				array(
					'description'       => wp_kses( $this->args['description'], $this->kses_p ), 
					'show_in_rest'      => $this->args['show_in_rest'], // TODO: Maybe make per field rest option?
					'sanitize_callback' => array( $this, 'save' ), 
				)
			);

		}

		if ( is_array( $sections ) && ! empty( $sections ) ) {
			
			foreach ( $sections as $section ) {

				add_settings_section( 
					$section['id'], 
					$section['title'], 
					array( $this, 'render_section_description' ), 
					$this->slug 
				);

				if ( is_array( $section['fields'] ) && ! empty( $section['fields'] ) ) {

					foreach ( $section['fields'] as $field ) {

						$field = wp_parse_args( $field, $this->default_field_args );
						$field_id = sanitize_title_with_dashes( $field['name'] );
						$field['value'] = get_option( $field['name'] );
						$field['show_label'] = false;
						$input_class = isset( $field['input_attrs']['class'] ) ? $field['input_attrs']['class'] : '';

						if ( ! in_array( $field['type'], $this->non_regular_text_fields ) ) {
							$field['input_attrs']['class'] = sprintf( 'regular-text %1$s', $input_class );
						}

						if ( in_array( $field['type'], $this->textarea_control_fields ) ) {
							$default_rows = ( $field['type'] === 'editor' ) ? 15 : 5;
							$field['input_attrs']['rows'] = isset( $field['input_attrs']['rows'] ) ? $field['input_attrs']['rows'] : $default_rows;
							$field['input_attrs']['cols'] = isset( $field['input_attrs']['cols'] ) ? $field['input_attrs']['cols'] : 90;
						}

						register_setting(
							$this->slug, 
							$field['name'], 
							array(
								'description'       => wp_kses( $field['description'], $this->kses_p ), 
								'show_in_rest'      => $this->args['show_in_rest'], // TODO: Maybe make per field rest option?
								'sanitize_callback' => array( $this, $this->get_sanitize_callback( $field ) ), 
							)
						);

						add_settings_field( 
							$field['name'],  
							$field['label'], 
							array( $this, 'render_setting' ), 
							$this->slug, 
							$section['id'], 
							array( 
								'label_for' => ! in_array( $field['type'], $this->remove_label_for_fields ) ? $field_id : false, 
								'class'     => '', 
								'field'     => $field, 
							)
						);

					}

				}

			}

		}

	}

	public function render_setting( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'label_for' => '', 
			'class'     => '', 
			'field'     => array(), 
		) );

		$field = apply_filters( $this->format_field_action( 'args' ), $args['field'] );

		do_action( $this->format_field_action( 'before' ), $field );

		$this->render_field_by_type( $field );

		do_action( $this->format_field_action( 'after' ), $field );

	}

	/**
	 * Get Sections By
	 * 
	 * @since   0.0.1
	 * @return  array  the sections if found, or an empty array.
	 */
	protected function get_sections_by( $key = '', $value = null, $number = 0 ) {

		$sections = $this->get_sections();
		$result = array();

		if ( ! empty( $key ) && ( is_array( $sections ) && ! empty( $sections ) ) ) {

			$i = 0;

			foreach ( $sections as $section ) {

				if ( isset( $section[$key] ) && ( $section[$key] === $value ) ) {

					$result[] = $section;

					if ( ( $number > 0 ) && ( $number === ( $i + 1 ) ) ) {
						break;
					}

					$i++;

				}

			}

		}

		return $result;

	}

	/**
	 * Get Section By
	 * 
	 * @since   0.0.1
	 * @return  array  the first section if found, or null.
	 */
	protected function get_section_by( $key = '', $value = null ) {

		$sections = $this->get_sections_by( $key, $value, 1 );
		$result = null;

		if ( is_array( $sections ) && ! empty( $sections ) ) {

			$result = $sections[0];

		}

		return $result;

	}

	public function render_section_description( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'id'       => '', 
			'title'    => '', 
			'callback' => array(), 
		) );

		if ( ! empty( $args['id'] ) ) {

			$section = $this->get_section_by( 'id', $args['id'] );

			if ( ! empty( $section ) ) { ?>

				<p class="description"><?php

					echo wp_kses( $section['description'], $this->kses_p );

				?></p>

			<?php }

		}

	}

	public function render_page() { ?>

		<div class="wrap">

			<h1><?php 

				echo wp_kses( $this->args['title'], $this->kses_p ); 

			?></h1>

			<?php if ( ! empty( $this->args['description'] ) ) { ?>

				<p class="description"><?php 

					echo wp_kses( $this->args['description'], $this->kses_p ); 

				?></p>

			<?php } ?>

			<form method="POST" action="options.php"> <?php 

				settings_fields( $this->slug );

				do_settings_sections( $this->slug );

				submit_button();

			?></form>

		</div>
		
	<?php }

	/**
	 * Get Meta Boxes
	 * 
	 * @since   0.0.1
	 * @return  array  
	 */
	protected function get_sections() {

		$sections = array();

		if ( is_array( $this->args['sections'] ) && ! empty( $this->args['sections'] ) ) {
			
			foreach ( $this->args['sections'] as $section ) {
			
				$sections[] = wp_parse_args( $section, $this->default_section_args );
			
			}
		
		}

		return $sections;

	}

	/**
	 * Get Fields
	 * 
	 * @since   0.0.1
	 * @return  array  
	 */
	protected function get_fields() {

		$sections = $this->get_sections();
		$fields = array();

		if ( is_array( $sections ) && ! empty( $sections ) ) {
			
			foreach ( $sections as $section ) {
			
				if ( is_array( $section['fields'] ) && ! empty( $section['fields'] ) ) {

					foreach ( $section['fields'] as $field ) {

						$fields[] = wp_parse_args( $field, $this->default_field_args );

					}

				}
			
			}
		
		}

		return $fields;

	}

}