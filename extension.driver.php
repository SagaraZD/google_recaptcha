<?php
Class extension_google_recaptcha extends Extension {
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
	public function about() {
		return array( 'name' => 'google_recaptcha',
			'version' => '0.1',
			'release-date' => '2017-04-27',
			'author' => array( 'name' => 'Sagara Dayananda',
				'website' => 'http://www.eyes-down.net/',
				'email' => 'sagara@eyes-down.net' ),
			'description' => 'Insert and process reCaptcha field for form submission.'
		);
	}

	public function uninstall() {
		# Remove preferences
		Symphony::Configuration()->remove( 'google_recaptcha' );
		Administration::instance()->saveConfig();
	}

	public function install() {
		return true;
	}

	public function getSubscribedDelegates() {
		return array(

			array(
				'page' => '/blueprints/events/new/',
				'delegate' => 'AppendEventFilter',
				'callback' => 'addFilterToEventEditor'
			),
			array(
				'page' => '/blueprints/events/edit/',
				'delegate' => 'AppendEventFilter',
				'callback' => 'addFilterToEventEditor'
			),

			array(
				'page' => '/system/preferences/',
				'delegate' => 'Save',
				'callback' => 'save_preferences'
			),
			array(
				'page' => '/system/preferences/success/',
				'delegate' => 'Save',
				'callback' => 'save_preferences'
			),
			array(
				'page' => '/system/preferences/',
				'delegate' => 'AddCustomPreferenceFieldsets',
				'callback' => 'append_preferences'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendParamsResolve',
				'callback' => 'addReCaptchaParams'
			),

			array(
				'page' => '/frontend/',
				'delegate' => 'EventPreSaveFilter',
				'callback' => 'processEventData'
			),

		);
	}

	public function addFilterToEventEditor( $context ) {
		$context[ 'options' ][] = array( 'google_recaptcha', @in_array( 'google_recaptcha', $context[ 'selected' ] ), 'Google reCaptcha Verification' );
	}

	/*-------------------------------------------------------------------------
		Append reCaptcha Params 
		-------------------------------------------------------------------------*/

	public function addReCaptchaParams( array $context = null ) {
		
		$context[ 'params' ][ 'recaptcha-sitekey' ] = $this->_get_sitekey();
	}

	/*-------------------------------------------------------------------------
		Preferences
		-------------------------------------------------------------------------*/

	public function append_preferences( $context ) {
		# Add new fieldset
		$group = new XMLElement( 'fieldset' );
		$group->setAttribute( 'class', 'settings' );
		$group->appendChild( new XMLElement( 'legend', 'Google reCaptcha' ) );

		# Add reCaptcha secret ID field
		$label = Widget::Label( 'Google reCaptcha secret key' );
		$label->appendChild( Widget::Input( 'settings[google_recaptcha][recaptcha-secret-id]', General::Sanitize( $this->_get_secret() ) ) );


		$group->appendChild( $label );
		$group->appendChild( new XMLElement( 'p', 'The secret ID from your reCaptcha settings.', array( 'class' => 'help' ) ) );

		# Add reCaptcha site key field
		$label = Widget::Label( 'Google reCaptcha site key' );
		$label->appendChild( Widget::Input( 'settings[google_recaptcha][recaptcha-sitekey]', General::Sanitize( $this->_get_sitekey() ) ) );
		$group->appendChild( $label );
		$group->appendChild( new XMLElement( 'p', 'The site key from your reCaptcha settings.', array( 'class' => 'help' ) ) );

		$context[ 'wrapper' ]->appendChild( $group );
	}


	/*-------------------------------------------------------------------------
		Helpers
	-------------------------------------------------------------------------*/

	private function _get_secret() {
		return Symphony::Configuration()->get( 'recaptcha-secret-id', 'google_recaptcha' );
	}

	private function _get_sitekey() {
		return Symphony::Configuration()->get( 'recaptcha-sitekey', 'google_recaptcha' );
	}
	


	/**
	 * perform event filter
	 */

	public function processEventData( $context ) {
		
		 if (in_array('google_recaptcha', $context['event']->eParamFILTERS)) { 
			//Get response code
			$user_response = $context['fields']['google_recaptcha'];
		
			//Get recaptcha-secret-id from config
			$s_id = Symphony::Configuration()->get( 'recaptcha-secret-id', 'google_recaptcha' );
				
			//Google api call for check reCaptcha
			$fields_string = '';
			$fields = array(
				'secret' => $s_id, 
				'response' => $user_response
			);
			foreach($fields as $key=>$value)
			$fields_string .= $key . '=' . $value . '&';
			$fields_string = rtrim($fields_string, '&');

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
			curl_setopt($ch, CURLOPT_POST, count($fields));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);

			$result = curl_exec($ch);
			curl_close($ch);
		
			$finale_result = json_decode($result, true);

		    //Check $result
		
			if (!$finale_result['success']) {
				$status = false;
			} else {

				$status = true;
			}
			
			$context['messages'][] = array('google_recaptcha', $status, (!$status ? 'reCAPTCHA field is required.' : NULL));
		
	}
	
	}
}
