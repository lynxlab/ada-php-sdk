<?php
require_once 'src/AdaSdk.php';

	$config = array(
			'clientID'=>'YOUR_CLIENT_ID',
			'clientSecret'=>'YOUR_CLIENT_SECRET',
			'url'=>'YOUR_ADA_INSTALLATION_URL'
// 			'silentMode'=>true
	);

	try {
		$adasdk = new \ADASdk\AdaSdk($config);	
		/**
		 * load a user in json format
		 */
		$user = json_decode($adasdk->get('users',array('id'=>27)));
		/**
		 * dump it
		 */
		echo '<h1>Loaded from ADA</h1>';		
		var_dump($user);
		
		/**
		 * gets testers associated with authorized user as php serialized
		 */
		$testers = unserialize($adasdk->get('testers.php'));
		/**
		 * dump 'em
		 */
		echo '<h1>Auth Switcher belongs to following testers</h1>';
		var_dump($testers);
		
		/**
		 * Build a user array to be saved as new student
		 */
		$userArray['name'] = 'John';
		$userArray['surname'] = 'Smith';
		$userArray['email'] = 'johnsmith@foobar.com';
		$userArray['birthdate'] = '01/10/1960'; // in DD/MM/YYYY format only
		
		/**
		 * sends a post request to save the user and gets the response
		 */
		$saveResponse = $adasdk->post('users',$userArray);
		
		/**
		 * dump the response, holding the status and the saved user_id
		 * if it's SUCCESS
		 */
		var_dump ($saveResponse);
		
		/**
		 * Subscribe a user to a course instance
		 */
		$adasdk->post('subscriptions', array('id_course_instance'=>1,'username'=>$userArray['email']));
		
	} catch (\ADAsdk\AdaSdkException $e) {
		echo '<pre>ADASDK error at '.__FILE__.': '.__LINE__." this is fatal, I've given up\r\n";
		echo 'Error Code: '.$e->getCode().' - Error Message: '.$e->getMessage().'</pre>';
		die();
	}
?>
