<?php
/**
 * AdaSdk.php
 *
 * @package        ada-php-sdk
 * @author         Giorgio Consorti <g.consorti@lynxlab.com>         
 * @copyright      Copyright (c) 2014, Lynx s.r.l.
 * @license        http://www.gnu.org/licenses/gpl-2.0.html GNU Public License v.2
 * @link           AdaSdk
 * @version        0.1
 * 
 * This class has been inspired by the work of Gonzalo Ayuso
 * described here:
 * http://gonzalo123.com/2010/02/06/building-a-rest-client-with-asynchronous-calls-using-php-and-curl/
 * and here:
 * https://code.google.com/p/gam-http/
 * 
 * It does not yet implement asynchronous calls, but one day it'll grow up
 * 
 */
namespace AdaSdk;

/**
 * JSON is needed for OAuth2 implementation
 */
if (!function_exists('json_decode')) {
	throw new AdaSdkException('ADAsdk needs the JSON PHP extension.',AdaSdk::ADASDK_ERROR);
}

/**
 * cURL is needed. Period.
 */
if (!@include_once('cURL.inc.php')) {
	throw new AdaSdkException('ADAsdk needs the cURL.inc.php file.',AdaSdk::ADASDK_ERROR);
}

/**
 * AdaSdkException own class.
 * 
 * Needed just to catch the correct exception
 * when used in php actual code
 * 
 * @author giorgio
 */
class AdaSdkException extends \Exception {};

/**
 * Provides developer facilities such as requesting and storing
 * in php session the access_token, checking its expiration and
 * requesting a new one if needed.
 * 
 * Provides methods for quickly accessing the API endpoints:
 * - get
 * - post
 * - delete
 * - put
 * 
 * @author giorgio
 */
class AdaSdk 
{
	/**
	 * Version number
	 */
	const VERSION = '0.1';
	
	/**
	 * API version to use
	 */
	const API_VERSION = 'v1';
	
	/**
	 * session name
	 */
	const ADASDK_SESSION_NAME = 'adasdk';
	
	/**
	 * verbs constants
	 */
	const POST   = 'POST';
	const GET    = 'GET';
	const DELETE = 'DELETE';
	const PUT    = 'PUT';
	
	/**
	 * HTTP status codes
	 */
	const HTTP_OK           = 200;
	const HTTP_CREATED      = 201;
	const HTTP_ACEPTED      = 202;
	const HTTP_UNAUTHORIZED = 400;
	
	/**
	 * Error codes
	 */
	const OAUTH2_ERROR = 666;
	const ADASDK_ERROR = 667;
	
	/**
	 * Domains used when calling OAuth2 or api
	 * 
	 * @var array
	 */
	private $_domains = null;
	
	/**
	 * The Client ID
	 * 
	 * @var string
	 */
	private $_clientID = null;
	
	/**
	 * Client secret
	 * 
	 * @var string
	 */
	private $_clientSecret = null;
	
	/**
	 * The OAuth2 access token
	 * 
	 * * @var string
	 */
	private $_accessToken = null;
	
	/**
	 * Type of OAuth2 access token
	 * (bearer, etc...)
	 * 
	 * @var string
	 */
	private $_tokenType = null;
	
	/**
	 * The HTTP optional headers
	 * 
	 * @var string
	 */
	private $_headers = null;
	
	/**
	 * Silent mode: if true, will not throw and exception on http error
	 * 
	 * @var bool
	 */
	private $_silentMode = false;
	
	/**
	 * Instantiates a new AdaSdk object with the passed configuration array
	 * 
	 * @param array $config configuration array, must contain at least clientID and clientSecret keys, url of ADA installation
	 * @throws AdaSdkException if an invalid configuration array is passed
	 */
	public function __construct($config) {
		
		if (!is_array($config) || is_null($config['clientID']) || is_null($config['clientSecret']) || is_null($config['url'])) {
			throw new AdaSdkException(__CLASS__.': must provide a valid config array',self::ADASDK_ERROR);
		} else {
			/**
			 * Sets class properties from config array, thwroing
			 * exceptions if values are not correctly passed
			 */
			if (strlen($config['clientID'])>0) {
				$this->setClientID($config['clientID']);
			} else throw new AdaSdkException(__CLASS__.': must provide a clientID in the config array',self::ADASDK_ERROR);
			
			if (strlen($config['clientSecret'])) {
				$this->setClientSecret($config['clientSecret']);
			} else throw new AdaSdkException(__CLASS__.': must provide a clientSecret in the config array',self::ADASDK_ERROR);
			
			if (strlen($config['url'])>0) {
				$this->_domains = array (
					'oauth2' => str_replace('http://', 'http://', $config['url']).'/api', // https in second str_replace param
					'api'    => $config['url'].'/api'
				);
			} else throw new AdaSdkException(__CLASS__.': must provide an url in the config array',self::ADASDK_ERROR);
			
			if (isset($config['silentMode']) && is_bool($config['silentMode'])) {
				$this->silentMode($config['silentMode']);
			}
		}
	}
	
	/**
	 * clientID setter
	 * 
	 * @param string $clientID the client ID
	 * @return \ADAsdk\AdaSdk
	 */
	public function setClientID ($clientID) {
		
		$this->_clientID = $clientID;
		return $this;
	}
	
	/**
	 * clientID getter
	 * 
	 * @return string the client ID
	 */
	public function getClientID () {
		
		return $this->_clientID;
	}
	
	/**
	 * clientSecret setter
	 * 
	 * @param string $clientSecret the client secret
	 * @return \ADAsdk\AdaSdk
	 */
	public function setClientSecret ($clientSecret) {
		
		$this->_clientSecret = $clientSecret;
		return $this;
	}
	
	/**
	 * clientSecret getter
	 * 
	 * @return string the client secret
	 */
	public function getClientSecret () {
		
		return $this->_clientSecret;
	}
	
	/**
	 * OAuth2 access token setter for API calls
	 * Stores the passed access_token both in object and session
	 * 
	 * @param string $accessToken the access token
	 * @return \ADAsdk\AdaSdk
	 */
	public function setAccessToken ($accessToken) {
		
		// stores the token in object
		$this->_accessToken = $accessToken;
		// stores the token in session
		$_SESSION[self::ADASDK_SESSION_NAME]['access_token'] = $this->_accessToken; 
		return $this;
	}
	
	/**
	 * OAuth2 access token getter for API calls
	 * If a session is not there, start it then get the access_token
	 * 
	 * @return string the access token to be used
	 */
	public function getAccessToken () {
		
		$this->_initFromSession();
		return $this->_accessToken;
	}
	
	/**
	 * OAuth2 access token type setter for API calls
	 * Stores the passed type both in object and session
	 * 
	 * @param string $tokenType the type of the access token
	 * @return \AdaSdk\AdaSdk
	 */
	public function setTokenType($tokenType) {
		
		// stores the type in object
		$this->_tokenType = $tokenType;
		// stores the type in session
		$_SESSION[self::ADASDK_SESSION_NAME]['token_type'] = $this->_tokenType;
		return $this;
	}
	
	/**
	 * OAuth2 access token type getter for API calls
	 * 
	 * @return string the type of the access token
	 */
	public function getTokenTpye() {

		$this->_initFromSession();		
		return $this->_tokenType;
	}
	
	/**
	 * headers setter
	 * 
	 * @param string $headers
	 * @return \ADAsdk\AdaSdk
	 */
	public function setHeaders ($headers) {
		
		if (!is_array($this->_headers)) $this->_headers = array();
		
		if (is_array($headers)) {
			foreach ($headers as $key=>$value) $this->_headers[$key] = $value;
		} else if (is_null($headers)) {
			$this->_headers = null;
		}
		return $this;
	}
	
	/**
	 * headers getter 
	 * 
	 * @return string
	 */
	public function getHeaders () {
		
		return (is_null($this->_headers) ? array() : $this->_headers);
	}
	
	/**
	 * sets the silent mode
	 *
	 * @param bool $mode
	 * @return \ADAsdk\AdaSdk
	 */
	public function silentMode($mode=TRUE) {
		
		$this->_silentMode = $mode;
		return $this;
	}
	
	/**
	 * Performs a GET request 
	 * 
	 * @param string $endpoint the api endpoint
	 * @param array $params parameters to be passed
	 * @return mixed the result on success and on failure if in silent mode
	 */
	public function get ($endpoint, $params = array()) {
		
		return $this->_execRequest(self::GET, $this->_buildURL($endpoint), $params);
	}

	/**
	 * Performs a POST request
	 *
	 * @param string $endpoint the api endpoint
	 * @param array $params parameters to be passed
	 * @return mixed the result on success and on failure if in silent mode
	 */
	public function post ($endpoint, $params = array()) {
		
		return $this->_execRequest(self::POST, $this->_buildURL($endpoint), $params);
	}

	/**
	 * Performs a DELETE request
	 *
	 * @param string $endpoint the api endpoint
	 * @param array $params parameters to be passed
	 * @return mixed the result on success and on failure if in silent mode
	 */
	public function delete ($endpoint, $params = array()) {
		
		return $this->_execRequest(self::DELETE, $this->_buildURL($endpoint), $params);
	}	
	
	/**
	 * Performs a PUT request
	 *
	 * @param string $endpoint the api endpoint
	 * @param array $params parameters to be passed
	 * @return mixed the result on success and on failure if in silent mode
	 */
	public function put ($endpoint, $params = array()) {
		
		return $this->_execRequest(self::PUT, $this->_buildURL($endpoint), $params);
	}
	
	/**
	 * Actually execute the request with a call to the cURL object
	 * and merging the passed params array with the access_token
	 * 
	 * @param string $type the type of the request as defined by class constants
	 * @param string $url the url to be called
	 * @param array $params parameters to be passed
	 * @throws AdaSdkException if an non-ok http status is returned and is not is silent mode
	 * @return mixed the result on success and on failure if in silent mode
	 */
	private function _execRequest ($type, $url, $params) {

		if (is_object($params) && in_array($type, array(self::POST, self::PUT) )) {
			$params = json_encode($params);
			$this->setHeaders(array('ContentType','Content-Type: application/json'));
		} else {		
			$params = http_build_query($params);
		}
		
		/**
		 * add the access token to the headers, setting a key
		 * will cause the OAuth2 to be overridden rather 
		 * than added in subsequent API method calls 
		 */
		$this->setHeaders(array( 'OAuth2'=>
					    			'Authorization: '.ucfirst(strtolower($this->getTokenTpye()).
									' '.$this->getAccessToken())));
		
		/**
		 * get a new curl object and set its defaults
		 */
		$cURL = new cURL($url);
		$cURL->setopt(CURLOPT_RETURNTRANSFER, TRUE);
		$cURL->setopt(CURLOPT_USERAGENT, __CLASS__.' v'.self::VERSION);
		
		switch ($type) {
			case self::GET:
				$cURL->setopt (CURLOPT_URL, $url . '?' . $params);
				break;
			case self::POST:
				$cURL->setopt (CURLOPT_URL, $url);
				$cURL->setopt (CURLOPT_POST, TRUE);
				$cURL->setopt (CURLOPT_POSTFIELDS, $params);
				$this->setHeaders(array('ContentLength'=>'Content-Length: '.strlen($params)));
				break;
			case self::DELETE:
				$cURL->setopt (CURLOPT_URL, $url . '?' . $params);
				$cURL->setopt (CURLOPT_CUSTOMREQUEST, self::DELETE);
				break;
			case self::PUT:
				$cURL->setopt (CURLOPT_URL, $url);
				$cURL->setopt (CURLOPT_CUSTOMREQUEST, self::PUT);
				$cURL->setopt (CURLOPT_POSTFIELDS, $params);
				$this->setHeaders(array('ContentLength'=>'Content-Length: '.strlen($params)));
				break;
		}
		
		/**
		 * set request headers, if any
		 */
		if (!is_null($this->getHeaders()) && is_array($this->getHeaders())) {			
			$cURL->setopt(CURLOPT_HTTPHEADER, $this->getHeaders());
		}
				
		/**
		 * execute the request
		 */
		$cURLResult = $cURL->exec();
		
		/**
		 * Reset Headers for next execution
		 */
		$this->setHeaders(null);
		
		/**
		 * read http status code
		 */
		$status = $cURL->getinfo(CURLINFO_HTTP_CODE);
		
		/**
		 * close curl
		 */
		$cURL->close();
		unset ($cURL);
		
		/**
		 * Return according to the status
		 */
		switch ($status) {
			case self::HTTP_OK:
			case self::HTTP_ACEPTED:
			case self::HTTP_CREATED:
				return $cURLResult;
				break;
			default:
				if (!$this->_silentMode) {
					throw new AdaSdkException($cURLResult, $status);
				} else return $cURLResult;
				break;
		}
	}
	
	/**
	 * Builds an ADA API url from the DOMAINS array and the passed endpoint
	 * NOTE: the endpoint may or may not have a leading slash, depending on your taste
	 * 
	 * @param string $endpoint the API endpoint to access
	 * @return string the url to be used
	 */
	private function _buildURL ($endpoint) {
		
		// make $endpoint agnostic to leading slash
		if ($endpoint{0}!=='/') $endpoint = '/' . $endpoint;
		
		return $this->_domains['api'].'/'.self::API_VERSION.$endpoint;
	}
	
	/**
	 * Inits the access token from the session:
	 * If an unexpired access token is stored in session, sets it
	 * else ask for a new OAuth2 access token
	 */
	private function _initFromSession() {
		
		if (self::_is_session_started() === FALSE) {
			session_start();
		}
		
		if (isset   ($_SESSION[self::ADASDK_SESSION_NAME]['token_expire_time']) && 
		    isset   ($_SESSION[self::ADASDK_SESSION_NAME]['access_token'])      &&
		    strlen  ($_SESSION[self::ADASDK_SESSION_NAME]['access_token'])>0    && 
		    !is_null($_SESSION[self::ADASDK_SESSION_NAME]['token_expire_time']) &&
		    time() < intval($_SESSION[self::ADASDK_SESSION_NAME]['token_expire_time'])) {			
			/**
			 * if session stored access_token has not expired
			 * set it to the current token, else ask for a new one
			 */			 
			$this->setAccessToken($_SESSION[self::ADASDK_SESSION_NAME]['access_token']);
			$this->setTokenType($_SESSION[self::ADASDK_SESSION_NAME]['token_type']);
		} else {
			$this->setAccessToken($this->_getOAuth2AccessToken());		
		}
	}
	
	/**
	 * Asks for a new OAuth2 access token and stores it in
	 * the session together with its expiration time.
	 * If some error occours always throws an exception
	 * regardless of the _silentMode value
	 * 
	 * @throws AdaSdkException OAUTH2_ERROR|HTTP status code
	 * @return string the obtained access_token
	 */
	private function _getOAuth2AccessToken() {
		
		$cURL = new cURL($this->_domains['oauth2'].'/token');
		$cURL->setopt (CURLOPT_USERPWD, $this->getClientID().':'.$this->getClientSecret());
		$cURL->setopt (CURLOPT_POST, TRUE);
		$cURL->setopt (CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
		$cURL->setopt (CURLOPT_HEADER, 0);
		$cURL->setopt (CURLOPT_RETURNTRANSFER, 1);
		
		/**
		 * access_token expire_time is calculated as:
		 * timestamp just before sending out the http request
		 * plus the returned 'expires_in' value
		 */
		$startTime = time();
		$cURLResult = $cURL->exec();
		$status = $cURL->getinfo(CURLINFO_HTTP_CODE);
		$cURL->close();
		
		if ($status === self::HTTP_OK) {
			$responseObj = json_decode($cURLResult);
			if (is_object($responseObj)) {
				// set expire time in session
				$_SESSION[self::ADASDK_SESSION_NAME]['token_expire_time'] = $startTime + $responseObj->expires_in;
				// stores the token type
				$this->setTokenType($responseObj->token_type);
				// return the access_token
				return $responseObj->access_token;
			} else {
				throw new AdaSdkException('token endpoint did not returned an object', self::OAUTH2_ERROR );
			}		
		} else if ($status === self::HTTP_UNAUTHORIZED) {
			$responseObj = json_decode($cURLResult);
			if (is_object($responseObj)) {
				$message = $responseObj->error_description;
			} else {
				$message = "Unknown OAuth2 error ".$responseObj;
			}
			throw new AdaSdkException($message, $status);
		} else {
			throw new AdaSdkException('http error in OAuth2 '.$status, $status);			
		}
	}
	
	/**
	 * Universal function for checking session status.
	 * From https://php.net/manual/en/function.session-status.php
	 * 
	 * @return bool
	 */
	private static function _is_session_started() {
		
		if ( php_sapi_name() !== 'cli' ) {
			if ( version_compare(phpversion(), '5.4.0', '>=') ) {
				return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
			} else {
				return session_id() === '' ? FALSE : TRUE;
			}
		}
		return FALSE;
	}
}
?>
