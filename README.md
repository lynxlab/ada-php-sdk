# Ada PHP SDK

This is an _API_ wrapper _PHP_ library given as a facility to properly access _ADA API_. It automatically request an access token to the **OAuth2** _API_ endpoint and stores it in _PHP_ session, as well as requesting a new access token when the stored one is found expired.

------------------------------------
## Usage ##

Using the Ada PHP SDK is as simple as:

1. Clone, Fork or download the source code

2. Configure it with your Swithcer's client_id and secret provided by the _ADA_ platform:
    
    ```php    
    require_once 'src/AdaSdk.php';
    $config = array(
        'applicationName' => 'YOUR_OWN_APP_NAME',
        'clientID'=>'YOUR_CLIENT_ID',
        'clientSecret'=>'YOUR_CLIENT_SECRET',
        'url'=>'YOUR_ADA_INSTALLATION_URL',
        'silentMode'=>true
    );
    ```
    
	The `applicationName` is any string of your own that will identify your application in browser session and basically prevents session variables to overlap.
    You can comment or set `silentMode` to `false` to not have `AdaSdkException` thrown if an error is returned from the _API_ call.
    
3. Instantiate a new AdaSdk object and use it:
    ```php
    $adasdk = new \ADASdk\AdaSdk($config);	
	/**
	 * load a user in json format
	 */
	$user = $adasdk->get('users',array('id'=>A_USER_ID));
    ```

