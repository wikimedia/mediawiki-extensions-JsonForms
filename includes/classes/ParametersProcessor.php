<?php

/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2025-2026, https://wikisphere.org
 */

namespace MediaWiki\Extension\JsonForms;

/**
 * Generic parameter processor
 */
class ParametersProcessor {
	protected array $defaultParameters = [];
	protected array $named = [];
	protected array $unnamed = [];
	protected array $optionsSchema;

	/**
	 * @param array $argv
	 * @param stdClass|null $schema null
	 */
	public function __construct( array $argv = [], $schema = null ) {
		if ( $schema !== null ) {
			$this->defaultParameters = $this->buildDefaultParametersFromSchema( $schema );
		}

		$this->parse( $argv );
	}

	/**
	 * Build default parameters from schema object (stdClass only)
	 *
	 * @param stdClass $schema
	 */
	protected function buildDefaultParametersFromSchema( $schema ): array {
		$parameters = [];

		$required = property_exists( $schema, 'required' ) && is_array( $schema->required )
			? $schema->required
			: [];

		$properties = property_exists( $schema, 'properties' ) && is_object( $schema->properties )
			? $schema->properties
			: new \stdClass();

		foreach ( get_object_vars( $properties ) as $name => $definition ) {
			$hasNestedProperties = property_exists( $definition, 'properties' ) &&
				is_object( $definition->properties ) &&
				count( get_object_vars( $definition->properties ) ) > 0;

			$parameters[$name] = [
				'label' => property_exists( $definition, 'title' ) ? $definition->title : $name,
				'description' => property_exists( $definition, 'description' ) ? $definition->description : '',
				'type' => property_exists( $definition, 'type' ) ? $definition->type : 'string',
				'required' => in_array( $name, $required, true ),
				'default' => property_exists( $definition, 'default' ) ? $definition->default : null,
				'children' => !$hasNestedProperties ? [] :
					$this->buildDefaultParametersFromSchema( $definition )
			];
		}

		return $parameters;
	}

	/**
	 * @param array|null $named
	 */
	public function buildOptionsSchema( $named = null ) {
		$this->optionsSchema = $this->mergeDefaults( $this->defaultParameters, $named ?? $this->named );
	}

	/**
	 * Merge default values with provided values
	 *
	 * @param array $defaults The default configuration
	 * @param array $provided The provided values to merge
	 * @return array The merged result
	 */
	public function mergeDefaults( $defaults, $provided ) {
		$result = $defaults;

		foreach ( $provided as $key => $value ) {
			if ( !isset( $result[$key] ) ) {
				$result[$key] = [
					'value' => $value,
					'unknown' => true
				];
				continue;
			}

			$isNested = isset( $result[$key]['children'] ) &&
				is_array( $result[$key]['children'] );

			if ( $isNested && is_array( $value ) ) {
				$result[$key]['children'] = $this->mergeDefaults(
					$result[$key]['children'],
					$value
				);

			} else {
				// set value
				$result[$key]['user_value'] = self::castValueByType(
					$result[$key]['type'] ?? 'string',
					$value,
					$result[$key]['default'] ?? null
				);
			}
		}

		return $result;
	}

	/**
	 * @param stdClass $formDescriptor
	 * @return stdClass The merged result
	 */
	public function mergeFormDescriptor( $formDescriptor ) {
		if ( is_object( $formDescriptor ) ) {
			$formDescriptor = json_decode( json_encode( $formDescriptor ), true );
		}
		$ret = $this->getResult( $this->optionsSchema, $formDescriptor );
		return json_decode( json_encode( $ret ), false );
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->getResult( $this->optionsSchema );
	}

	/**
	 * @param array $defaults The default configuration
	 * @param array|null $provided The provided values to merge
	 * @return array The merged result
	 */
	public function getResult( $defaults, $provided = null ) {
		$result = [];
		$provided = is_array( $provided ) ? $provided : [];

		foreach ( $defaults as $key => $definition ) {
			$providedValue = $provided[$key] ?? null;

			if ( !empty( $definition['children'] ) ) {
				$result[$key] = $this->getResult( $definition['children'], $providedValue );
			} else {
				$result[$key] = $this->getFinalValue( $definition, $providedValue );
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	protected function getFinalValue( $definition, $provided = null ) {
		if ( isset( $definition['unknown'] ) && $definition['unknown'] === true ) {
			return $provided ?? $definition['value'] ?? null;
		}

		// Priority: user_value > provided > default > null
		return $definition['user_value']
			?? $provided
			?? $definition['default']
			?? null;
	}

	/**
	 * @param array $argv
	 */
	protected function parse( array $argv ): void {
		$unnamed = [];
		$named = [];
		$prevKey = null;

		foreach ( $argv as $key => $value ) {
			if ( strpos( $value, '+' ) === 0 ) {
				$argv[$prevKey] .= ' |+' . urlencode( substr( $value, 1 ) );
				unset( $argv[$key] );
			} else {
				$prevKey = $key;
			}
		}

		foreach ( $argv as $value ) {
			if ( strpos( $value, '=' ) !== false ) {
				[ $k, $v ] = explode( '=', $value, 2 );
				$k = trim( $k );
				$k_ = str_replace( ' ', '_', $k );
				$v = trim( $v );
				$named[ $k_ ] = $v;

			} else {
				$unnamed[] = $value;
			}
		}

		$this->named = SchemaUtils::flatToNested( $named, '.' );
		$this->unnamed = $unnamed;
	}

	/**
	 * @param array|stdClass $params
	 * @param array $paramsSchema
	 * @return array
	 */
	public static function applySchemaDefaults( $params, $paramsSchema ) {
		if ( is_object( $params ) ) {
			$params = json_decode( json_encode( $params ), true );
		}
		if ( !is_array( $params ) ) {
			$params = [];
		}

		$result = $params;
		foreach ( $paramsSchema as $key => [ $default, $type ] ) {
			$provided = $params[ $key ] ?? null;
			$type = strtolower( (string)$type );

			if ( $type === 'bool' || $type === 'boolean' ) {
				$result[ $key ] = $provided === null
					? (bool)$default
					: ( $provided === '' ? false : (bool)filter_var( $provided, FILTER_VALIDATE_BOOLEAN ) );
				continue;
			}

			$missing = $provided === null || ( $type !== 'string' && $provided === '' );
			$result[ $key ] = self::castValueByType(
				$type,
				$missing ? $default : $provided,
				$default
			);
		}

		return $result;
	}

	/**
	 * @param string $type
	 * @param mixed $value
	 * @param mixed $default null
	 * @return mixed
	 */
	protected static function castValueByType( ?string $type, $value, $default = null ) {
		if ( $value === null ) {
			return $default;
		}

		switch ( $type ) {
			case 'int':
			case 'integer':
				return filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );

			case 'float':
			case 'number':
			case 'numeric':
				return filter_var( $value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE );

			case 'bool':
			case 'boolean':
				$ret = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

				if ( $ret === null ) {
					$ret = filter_var( $default, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				}

				return $ret ?? false;

			case 'string':
				return (string)$value;

			case 'array':
				return is_array( $value ) ? $value : self::splitString( $value );

			case 'array-chunks':
				return is_array( $value ) ? $value : str_split( (string)$value );

			case 'array-string':
			case 'array-int':
			case 'array-integer':
			case 'array-float':
			case 'array-number':
			case 'array-bool':
			case 'array-boolean':
				// Convert to array if needed
				$values = is_array( $value )
					? $value
					: self::splitString( (string)$value );

				$subType = explode( '-', $type )[1] ?? null;
				$result = [];
				foreach ( $values as $v ) {
					$result[] = self::castValueByType( $subType, $v, $default );
				}
				return $result;

			default:
				return $value;
		}
	}

	/**
	 * @param string $str
	 * @return array
	 */
	protected static function splitString( string $str ): array {
		return array_map( 'trim', explode( ',', $str ) );
	}

	public function getValues(): array {
		return $this->unnamed;
	}

	public function getQuery(): array {
		$ret = [];
		foreach ( $this->optionsSchema as $key => $value ) {
			if ( !empty( $value['unknown'] ) ) {
				$ret[$key] = $value['value'];
			}
		}
		return $ret;
	}

}
