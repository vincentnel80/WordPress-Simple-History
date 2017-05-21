<?php

defined( 'ABSPATH' ) or die();

/*
Dropin Name: WP CLI
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryWPCLIDropin {

	// Simple History instance
	private $sh;

	function __construct($sh) {

		$this->sh = $sh;
		#add_action( 'admin_menu', array($this, 'add_settings'), 50 );
		#add_action( 'plugin_row_meta', array($this, 'action_plugin_row_meta'), 10, 2);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_commands();
		}

	}

	private function register_commands() {
		$commandConfigurationOptions = array(
		    'shortdesc' => 'Lists the history log',
		    'synopsis' => array(
		        /*array(
		            'type'     => 'positional',
		            'name'     => 'name',
		            'optional' => true,
		            'multiple' => false,
		        ),*/
		        array(
		            'type'     => 'assoc',
		            'name'     => 'format',
		            'optional' => true,
		            'default'  => 'table',
		            'options'  => array( 'table', 'json', 'csv', 'yaml' ),
		        ),
		        array(
		            'type'     => 'assoc',
		            'name'     => 'count',
		            'optional' => true,
		            'default'  => '10',
		            //'options'  => array( 'table', 'json', 'csv', 'yaml' ),
		        ),
		    ),
		    'when' => 'after_wp_load',
		);

		WP_CLI::add_command( 'simple-history list', array($this, 'commandList'), $commandConfigurationOptions );
	}

	public function commandList( $args, $assoc_args ) {
		#print_r($assoc_args);exit;

		if ( ! is_numeric($assoc_args["count"]) ) {
			WP_CLI::error( __('Error: parameter "count" must be a number', 'simple-history' ) );
		}

        // Override capability check: if you can run wp cli commands you can read all loggers
        add_action( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 3);

		WP_CLI::log( sprintf( 'Showing %1$d events from Simple History', $assoc_args["count"] ) );

		$query = new SimpleHistoryLogQuery();

		$query_args = array(
			"paged" => 1,
			"posts_per_page" => $assoc_args["count"]
		);

		$events = $query->query( $query_args );

		// A cleaned version of the events, formatted for wp cli table output
		$eventsCleaned = array();

		foreach ($events["log_rows"] as $row) {
		    $header_output = $this->sh->getLogRowHeaderOutput($row);
		    $text_output = $this->sh->getLogRowPlainTextOutput($row);
		    // $details_output = $this->sh->getLogRowDetailsOutput($row);

			$header_output = strip_tags( html_entity_decode( $header_output, ENT_QUOTES, 'UTF-8') );
			$header_output = trim(preg_replace('/\s\s+/', ' ', $header_output));

			$text_output = strip_tags( html_entity_decode( $text_output, ENT_QUOTES, 'UTF-8') );

		    $eventsCleaned[] = array(
		    	"date" => get_date_from_gmt( $row->date ),
		    	"initiator" => $row->initiator,
		    	"logger" => $row->logger,
		    	"level" => $row->level,
		    	"who_when" => $header_output,
		    	"what" => $text_output,
		    	"count" => $row->subsequentOccasions
		    	// "details" => $details_output
		    );
		}

		#print_r($events);
		#print_r($eventsCleaned);
		/*
		[9] => stdClass Object
            (
                [id] => 735
                [logger] => AvailableUpdatesLogger
                [level] => notice
                [date] => 2017-05-19 12:45:13
                [message] => Found an update to plugin "{plugin_name}"
                [initiator] => wp
                [occasionsID] => 9a2d42eebea5c3cd2b16db0c38258016
                [subsequentOccasions] => 1
                [rep] => 1
                [repeated] => 10
                [occasionsIDType] => 9a2d42eebea5c3cd2b16db0c38258016
                [context_message_key] => plugin_update_available
                [context] => Array
                    (
                        [plugin_name] => WooCommerce
                        [plugin_current_version] => 3.0.6
                        [plugin_new_version] => 3.0.7
                        [_message_key] => plugin_update_available
                        [_server_remote_addr] => ::1
                        [_server_http_referer] => http://wp-playground.dev/wp/wp-cron.php?doing_wp_cron=1495197902.1593680381774902343750
                    )

            )
		*/

		$fields = array(
			'date',
			'initiator',
			#'who_when',
			'what',
			#'logger',
			'count',
			'level'
		);

		WP_CLI\Utils\format_items( $assoc_args['format'], $eventsCleaned, $fields );

    	// WP_CLI::success( "Done" );
	}

}
