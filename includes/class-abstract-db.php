<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP_AMAZON_SES_ABSTRACT_DB DB class
 *
 * Sub-classes should define $table_name, $version, $id and $primary_key in __construct() method.
 *
 */
abstract class WP_AMAZON_SES_ABSTRACT_DB {
    
    /**
     * ID for this object.
     * @var int
     */
    public $id = 0;

	/**
	 * Database table name.
     * @var string
	 */
	public $table_name;

	/**
	 * Database table version.
     * @var string
	 */
	public $version;

	/**
	 * Primary key (unique field) for the database table.
     * @var string
	 */
	public $primary_key;

	/**
	 * Retrieves the list of columns for the database table.
	 * Sub-classes should define an array of columns here.
	 *
	 * @return array List of columns.
	 */
	public function get_columns() {
		return array();
	}

	/**
	 * Retrieves column defaults.
	 * Sub-classes can define default for any/all of columns defined in the get_columns() method.
	 *
	 * @return array All defined column defaults.
	 */
	public function get_column_defaults() {
		return array();
	}

	/**
	 * Retrieve a row by the primary key
	 *
	 * @return null|object
	 */
	public function get_row() {
		global $wpdb;
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->table_name WHERE $this->primary_key = %s",
				$this->id
			)
		);
		return $result;
	}

	/**
	 * Retrieves a row by a specific column and value
	 *
	 * @param string $column Column name.
	 * @param int|string $value.
	 *
	 * @return object|null|false Object|null on success and false on failure.
	 */
	public function get_row_by( $column, $value ) {
		if ( ! array_key_exists( $column, $this->get_columns() ) ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->table_name WHERE $column = %s",
				$value
			)
		);
		return $result;
	}

	/**
	 * Retrieve a specific column's value by the primary key
	 *
	 * @param string $column Column name.
	 *
	 * @return string|null|false String|null on success and false on failure.
	 */
	public function get_field( $column ) {
		if ( ! array_key_exists( $column, $this->get_columns() ) ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT $column FROM $this->table_name WHERE $this->primary_key = %s",
				$this->id
			)
		);
		return $result;
	}

	/**
	 * Retrieves one column value based on another given column and matching value. E.g. Get email of user with 1;
	 *
	 * @param string $column Column name.
	 * @param string $where Column to match against in the WHERE clause.
	 * @param string $value Value to match to the column in the WHERE clause.
	 *
	 * @return string|null|false String|null on success and false on failure.
	 */
	public function get_field_by( $column, $value, $where ) {
		if ( ! array_key_exists( $column, $this->get_columns() ) || ! array_key_exists( $where, $this->get_columns() ) ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT $column FROM $this->table_name WHERE $where = %s",
				$value
			)
		);
		return $result;
	}

	/**
	 * Inserts a new record into the database.
	 *
	 * @param array $data Column => Value pair.
	 *
	 * @return int|false ID of the newly inserted record or false.
	 */
	public function insert( $data ) {
	    
	    if ( empty( $data ) ) {
			return false;
		}

		global $wpdb;

		// Set default values.
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		// Initialise column format array.
		$column_formats = $this->get_columns();

		// Force fields to lower case.
		$data = array_change_key_case( $data );

		// White list columns.
		// array_intersect_key compares the keys of two or more arrays, and return an array that contains the entries from array1 that are present in array2, array3, etc.
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data.
		// array_keys returns an array containing the keys - keys become values.
		// array_flip() flips/exchanges all keys with their associated values.
		// array_merge() merges one or more arrays into one array. If two or more array elements have the same key, the last one overrides the others.
		$data_keys      = array_keys( $data );
		$column_formats = array_merge(array_flip($data_keys), $column_formats);

		$result = $wpdb->insert( $this->table_name, $data, $column_formats );
		
		if ( empty( $result ) ) {
			return false;
		}

		return $wpdb->insert_id;
	}
	
	/**
	 * Update a single field of an existing record.
	 * 
	 * @param string $column Column Name for the field to be updated.
	 * @param string $value Optional. Value of the column to update.
	 * 
	 * Affects all matching records
	 *
	 * @return bool
	 */
	public function update_field( $column, $value ) {

		global $wpdb;
		
		$data = array( $column => $value );

		// Initialise column format array.
		$column_formats = $this->get_columns();

		// White list columns.
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data.
		$data_keys      = array_keys( $data );
		$column_formats = array_merge(array_flip($data_keys), $column_formats);
		
		$updated = $wpdb->update( $this->table_name, $data, array( $this->primary_key => $this->id ), $column_formats );
		
		if ( empty( $updated ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Update an existing record in the database.
	 * 
	 * @param array $data Array of columns and associated data to update.
	 * 
	 * Affects all matching records
	 *
	 * @return bool
	 */
	public function update( $data ) {

		global $wpdb;

		// Initialise column format array.
		$column_formats = $this->get_columns();

		// Force fields to lower case.
		$data = array_change_key_case( $data );

		// White list columns.
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data.
		$data_keys      = array_keys( $data );
		$column_formats = array_merge(array_flip($data_keys), $column_formats);
		
		$updated = $wpdb->update( $this->table_name, $data, array( $this->primary_key => $this->id ), $column_formats );
		
		if ( empty( $updated ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Update by column => value.
	 * 
	 * @param array $data Array of columns and associated data to update.
	 * 
	 * Affects all matching records
	 *
	 * @return bool
	 */
	public function update_by( $data, $column, $value ) {

		global $wpdb;

		// Initialise column format array.
		$column_formats = $this->get_columns();

		// Force fields to lower case.
		$data = array_change_key_case( $data );

		// White list columns.
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data.
		$data_keys      = array_keys( $data );
		$column_formats = array_merge(array_flip($data_keys), $column_formats);
		
		$updated = $wpdb->update( $this->table_name, $data, array( $column => $value ), $column_formats );
		
		if ( empty( $updated ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete a row identified by the primary key.
	 * 
	 * Affects all matching records
	 *
	 * @return bool
	 */
	public function delete() {
		
		global $wpdb;
		
        $deleted = $wpdb->delete( $this->table_name, array( $this->primary_key => $this->id ) );

		if ( empty( $deleted )  ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete a record from the database by column.
	 *
	 * @param string $column
	 * @param int|string $value.
	 * 
	 * Affects all matching records
	 *
	 * @return bool
	 */
	public function delete_by( $column, $value ) {

		if ( ! array_key_exists( $column, $this->get_columns() ) ) {
			return false;
		}
		
		global $wpdb;		

        $deleted = $wpdb->delete($this->table_name, array($column => $value));

		if ( empty( $deleted ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Check if the given table exists.
	 *
	 * @param string $table The table name.
	 *
	 * @return bool
	 */
	public function table_exists( $table ) {

		global $wpdb;

		$table = sanitize_key( $table );

		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
	}
	
}