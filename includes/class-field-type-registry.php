<?php
/**
 * Field-type registry.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

use Webentwicklerin\WeFormkit\Fields\Abstract_Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry of field-type instances.
 */
final class Field_Type_Registry {

	/**
	 * Registered field types keyed by type id.
	 *
	 * @var array<string, Abstract_Field_Type>
	 */
	private $types = array();

	/**
	 * Register core field types and expose the registry to extensions.
	 */
	public function __construct() {
		$this->register( new Fields\Text_Field() );
		$this->register( new Fields\Email_Field() );
		$this->register( new Fields\Tel_Field() );
		$this->register( new Fields\Url_Field() );
		$this->register( new Fields\Textarea_Field() );
		$this->register( new Fields\Number_Field() );
		$this->register( new Fields\Select_Field() );
		$this->register( new Fields\Radio_Field() );
		$this->register( new Fields\Radio_Image_Field() );
		$this->register( new Fields\Checkbox_Field() );
		$this->register( new Fields\Checkboxes_Field() );
		$this->register( new Fields\Date_Field() );
		$this->register( new Fields\Time_Field() );
		$this->register( new Fields\Datetime_Field() );
		$this->register( new Fields\Consent_Field() );
		$this->register( new Fields\Html_Field() );
		$this->register( new Fields\Hidden_Field() );
		$this->register( new Fields\Upload_Field() );

		/**
		 * Allow third-party plugins to register custom field types.
		 *
		 * @param Field_Type_Registry $registry The registry instance.
		 */
		do_action( 'we_formkit_register_field_types', $this );
	}

	/**
	 * Register a field-type instance.
	 */
	public function register( Abstract_Field_Type $type ): void {
		$this->types[ $type->get_type() ] = $type;
	}

	/**
	 * Get a field-type instance by id, or null if not registered.
	 */
	public function get( string $type ): ?Abstract_Field_Type {
		return $this->types[ $type ] ?? null;
	}

	/**
	 * Get all registered field types.
	 *
	 * @return array<string, Abstract_Field_Type>
	 */
	public function all(): array {
		return $this->types;
	}

	/**
	 * Get all registered type identifiers.
	 *
	 * @return array<int, string>
	 */
	public function types(): array {
		return array_keys( $this->types );
	}
}
