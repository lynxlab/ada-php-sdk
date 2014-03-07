<?php
namespace AdaSdk;

/**
 * cURL Object Oriented API
 * 
 * @author Stefano Azzolini <lastguest@gmail.com>
 * @link https://gist.github.com/lastguest/4740772
 */
class cURL {
	/**
	 * The cURL resource descriptor
	 * @var Resource
	 */
	private $curl = null;

	/**
	 * Call curl_init and store the resource internally.
	 * @param string $url The URL (default:null)
	 */
	public function __construct($url = null){
		if (!function_exists('curl_init')) {
			throw new Exception(__CLASS__.' needs the CURL PHP extension.');
		} else {
			return $this->init($url);
		}
	}

	/**
	 * Magic Metjod for proxying calls for this class to curl_* procedurals
	 * @param  string $n Function name
	 * @param  array $p Call parametrs
	 * @return mixed    The called function return value
	 */
	public function __call($n,$p){
		if($n=='init' || $n=='multi_init'){
			// Close the connection if it was opened.
			if($this->curl) curl_close($this->curl);
			// Save the resource internally
			return $this->curl = call_user_func_array('curl_'.$n,$p);
		} else {
			// Inject the current resource to the function call
			array_unshift($p,$this->curl);
			return call_user_func_array('curl_'.$n,$p);
		}
	}
}
?>
