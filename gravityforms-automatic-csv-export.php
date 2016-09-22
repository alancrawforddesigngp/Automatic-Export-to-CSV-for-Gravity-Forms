<?php
/*
Plugin Name: Automatic Export to CSV for Gravity Forms 
Plugin URI: http://gravitycsv.com
Description: Automatically send an email containing a CSV export of your Gravity Form entries on a schedule.
Version: 0.3.2
Author: Alex Cavender
Author URI: http://alexcavender.com/
Text Domain: gravityforms-automatic-csv-export
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();

define( 'GF_AUTOMATIC_CSV_VERSION', '0.3.2' );



class GravityFormsAutomaticCSVExport {

	public function __construct() {

		if ( class_exists( 'GFAPI' ) ) {

			add_filter( 'cron_schedules', array($this, 'add_weekly' ) ); 
			add_filter( 'cron_schedules', array($this, 'add_monthly' ) ); 
			add_action( 'admin_init', array($this, 'gforms_create_schedules' ) );

			$forms = GFAPI::get_forms();

			foreach ( $forms as $form ) {

				$form_id = $form['id'];
				$enabled = $form['gravityforms-automatic-csv-export']['enabled'];

				if ( $enabled == 1 ) {
					add_action( 'csv_export_' . $form_id , array($this, 'gforms_automated_export' ) );
				}	
			
			}

		}
	}
	/**
		* Set up weekly schedule as an interval
		*
		* @since 0.1
		*
		* @param array $schedules.
		* @return array $schedules.
	*/
	public function add_weekly( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __('Once Weekly')
		);
		return $schedules;
	}
	

	/**
		* Set up monthly schedule as an interval
		*
		* @since 0.1
		*
		* @param array $schedules.
		* @return array $schedules.
	*/
	public function add_monthly( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['monthly'] = array(
			'interval' => 604800 * 4,
			'display' => __('Once Monthly')
		);
		return $schedules;
	}
	

	/**
		* Create schedules for each enabled form 
		*
		* @since 0.1
		*
		* @param void
		* @return void
	*/
	public function gforms_create_schedules(){

		$forms = GFAPI::get_forms();

		foreach ( $forms as $form ) {

			$form_id = $form['id'];

			$enabled = $form['gravityforms-automatic-csv-export']['enabled'];

			if ( $enabled == 1 ) {

				if ( ! wp_next_scheduled( 'csv_export_' . $form_id ) ) {
					
					$form = GFAPI::get_form( $form_id ); 

					$frequency = $form['gravityforms-automatic-csv-export']['csv_export_frequency'];
					
					wp_schedule_event( time(), $frequency, 'csv_export_' . $form_id );
					
				}

			}

			else {

				$timestamp = wp_next_scheduled( 'csv_export_' . $form_id );
				
				wp_unschedule_event( $timestamp, 'csv_export_' . $form_id );

			}

		}

	}

	
	/**
		* Run Automated Exports
		*
		* @since 0.1
		*
		* @param void
		* @return void 
	*/
	public function gforms_automated_export() {

		$output = "";
		$form_id = explode('_', current_filter())[2];
		$form = GFAPI::get_form( $form_id ); // get form by ID 
		$search_criteria = array();

		if ( $form['gravityforms-automatic-csv-export']['search_criteria'] == 'all' ) {
			$search_criteria = array();
		}

		if ( $form['gravityforms-automatic-csv-export']['search_criteria'] == 'previous_day' ) {

			$search_criteria['start_date'] = date('Y-m-d', time() - 60 * 60 * 24 );
		
			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 ); 

		}

		if ( $form['gravityforms-automatic-csv-export']['search_criteria'] == 'previous_week' ) {

			$search_criteria['start_date'] = date('Y-m-d', time() - 604800000 );
		
			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 ); 

		}

		if ( $form['gravityforms-automatic-csv-export']['search_criteria'] == 'previous_month' ) {

			$search_criteria['start_date'] = date('Y-m-d', time() - 2678400000 );
		
			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 ); 

		}
		
	
		//get the total number of entries that the form has
		$total_entries = GFAPI::count_entries( $form_id );
		//set paging to the total number of entries for the form
		$paging = array( 'offset' => 0, 'page_size' => $total_entries );
		//Pass a non-null value to get the total count in the results
		$total_count = 0;

		$all_form_entries = GFAPI::get_entries( $form_id , $search_criteria, null, $paging, $total_count);
		
		 // Create an array of field ids
		$field_ids = array();

		foreach( $form['fields'] as $field ) {

		// Documentation is missing 'page' as a field type, not sure if there are any others that aren't in the documentation.  https://www.gravityhelp.com/documentation/article/field-object/
		if(in_array($field->type, array('section','html','page'))
			continue;

        	// handle name fields
		if( $field->type == 'name' ) {
			// TODO: Use field options to remove some fields from export
			// Adds values for name extended 
			array_push($field_ids, $field['id'] . '.2');
			array_push($field_ids, $field['id'] . '.3');
			array_push($field_ids, $field['id'] . '.4');
			array_push($field_ids, $field['id'] . '.6');
			array_push($field_ids, $field['id'] . '.8');

			foreach($field->inputs as $input) {
				$noStrip = array();
				$str = $field->label . " " . $input['label'];
				$str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $str);
				$str = trim($str);
				$str = ucwords($str);
				$str = str_replace(" ", "", $str);
				$str = lcfirst($str);
				$str =  substr( $str, 0, 28 );
			
				$output .= preg_replace('/[,]/', '', $str) . ','; 
			}
			continue;
		}
		
		// handle address fields
		if( $field->type == 'address' ) {
			// Adds values for name extended 
			array_push($field_ids, $field['id'] . '.1');
			array_push($field_ids, $field['id'] . '.2');
			array_push($field_ids, $field['id'] . '.3');
			array_push($field_ids, $field['id'] . '.4');
			array_push($field_ids, $field['id'] . '.5');
			array_push($field_ids, $field['id'] . '.6');
			
			foreach($field->inputs as $input) {
				$noStrip = array();
				$str = $field->label . " " . $input['label'];
				$str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $str);
				$str = trim($str);
				$str = ucwords($str);
				$str = str_replace(" ", "", $str);
				$str = lcfirst($str);
				$str =  substr( $str, 0, 28 );
				
				$output .= preg_replace('/[,]/', '', $str) . ','; 
			}
			continue;
		}
		
		// Add field ids to array
		array_push($field_ids, $field->id);

            $output .= preg_replace('/[,]/', '', $field->label) . ','; 
        }

        $output .= "\r\n";

        //loop over form entries
        if ( !empty( $all_form_entries ) ){
	        foreach ( $all_form_entries as $entry ) {

	            foreach($entry as $key => $val) {
			foreach($field_ids as $field_id) {
				$val = rgar($entry, $field_id);
				$output .= preg_replace('/[,]/', '', sanitize_text_field($val));
				$output .= ',';
			}
		   }

	            $output .= ',';
	            $output .= "\r\n";
	        }
    	}
		
		$upload_dir = wp_upload_dir();

		$baseurl = $upload_dir['baseurl'];
		
		$path = $upload_dir['path'];

		$myfile = fopen( $path . "/form_" . $form_id . '_' . date('Y-m-d-giA') . ".csv", "w") or die("Unable to open file!");
		$csv_contents = $output;
		
		fwrite($myfile, $csv_contents);
		fclose($myfile);

		// To-Do = add to media library

		$server = $_SERVER['HTTP_HOST'];

		$email_address = $form['gravityforms-automatic-csv-export']['email_address'];

		// Send an email using the latest csv file
		$attachments = $path . '/form_' . $form_id . '_' . date('Y-m-d-giA') . '.csv';
		$headers[] = 'From: WordPress <wordpress@' . $server . '>';
		//$headers[] = 'Bcc: bcc@yourdomain.com';
		wp_mail( $email_address , 'Automatic Form Export', 'CSV export is attached to this message', $headers, $attachments);
	}

}

$automatedexportclass = new GravityFormsAutomaticCSVExport();



add_action( 'gform_loaded', array( 'GF_Automatic_Csv_Bootstrap', 'load' ), 5 );

class GF_Automatic_Csv_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

         require_once( 'class-gf-automatic-csv-addon.php' );

        GFAddOn::register( 'GFAutomaticCSVAddOn' );
    }

}

function gf_simple_addon() {
    return GFAutomaticCSVAddOn::get_instance();
}
