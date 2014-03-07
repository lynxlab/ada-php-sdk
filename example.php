<?php
require_once 'src/AdaSdk.php';

	$config = array(
			'clientID'=>'YOUR_CLIENT_ID',
			'clientSecret'=>'YOUR_CLIENT_SECRET',
// 			'silentMode'=>true
	);

	try {
		$adasdk = new \ADAsdk\AdaSdk($config);	
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

		
		
		
	} catch (\ADAsdk\AdaSdkException $e) {
		echo '<pre>ADASDK error at '.__FILE__.': '.__LINE__." this is fatal, I've given up\r\n";
		echo 'Error Code: '.$e->getCode().' - Error Message: '.$e->getMessage().'</pre>';
		die();
	}
?>
