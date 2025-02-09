<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * OAuth Library
 *
 * @package    Kohana/OAuth
 * @category    Base
 * @author     Kohana Team
 * @copyright  (c) 2010 Kohana Team
 * @license    http://kohanaframework.org/license
 * @since      3.0.7
 */
abstract class Kohana_OAuth {

	/**
	 * @var  string  OAuth complaince version
	 */
	public static $version = '1.0';

	/**
	 * Returns the output of a remote URL. Any [curl option](http://php.net/curl_setopt)
	 * may be used.
	 *
	 *     // Do a simple GET request
	 *     $data = Remote::get($url);
	 *
	 *     // Do a POST request
	 *     $data = Remote::get($url, array(
	 *         CURLOPT_POST       => TRUE,
	 *         CURLOPT_POSTFIELDS => http_build_query($array),
	 *     ));
	 *
	 * @param   string   remote URL
	 * @param   array    curl options
	 * @return  string
	 * @throws  Kohana_Exception
	 */
	public static function remote($url, array $options = NULL)
	{
		// The transfer must always be returned
		$options[CURLOPT_RETURNTRANSFER] = TRUE;

		// Open a new remote connection
		$remote = curl_init($url);
        
        curl_setopt($remote, CURLOPT_SSL_VERIFYPEER, false);
        
		// Set connection options
		if ( ! curl_setopt_array($remote, $options))
		{
			throw new Kohana_Exception('Failed to set CURL options, check CURL documentation: :url',
				array(':url' => 'http://php.net/curl_setopt_array'));
		}

		// Get the response
		$response = curl_exec($remote);

		// Get the response information
		$code = curl_getinfo($remote, CURLINFO_HTTP_CODE);

		if ($code AND $code < 200 OR $code > 299)
		{
			$error = $response;
		}
		elseif ($response === FALSE)
		{
			$error = curl_error($remote);
		}

		// Close the connection
		curl_close($remote);

		if (isset($error))
		{
			throw new Kohana_OAuth_Exception('Error fetching remote :url [ status :code ] :error :options',
				array(':url' => $url, ':code' => $code, ':error' => $error, ':options' => $options));
		}

		return $response;
	}
	/**
	 * RFC3986 compatible version of urlencode. Passing an array will encode
	 * all of the values in the array. Array keys will not be encoded.
	 *
	 *     $input = OAuth::urlencode($input);
	 *
	 * Multi-dimensional arrays are not allowed!
	 *
	 * [!!] This method implements [OAuth 1.0 Spec 5.1](http://oauth.net/core/1.0/#rfc.section.5.1).
	 *
	 * @param   mixed   input string or array
	 * @return  mixed
	 */
	public static function urlencode($input)
	{
		if (is_array($input))
		{
			// Encode the values of the array
			return array_map(array('OAuthfog', 'urlencode'), $input);
		}

		// Encode the input
		$input = rawurlencode($input);

		if (version_compare(PHP_VERSION, '<', '5.3'))
		{
			// rawurlencode() is RFC3986 compliant in PHP 5.3
			// the only difference is the encoding of tilde
			$input = str_replace('%7E', '~', $input);
		}

		return $input;
	}

	/**
	 * RFC3986 complaint version of urldecode. Passing an array will decode
	 * all of the values in the array. Array keys will not be encoded.
	 *
	 *     $input = OAuth::urldecode($input);
	 *
	 * Multi-dimensional arrays are not allowed!
	 *
	 * [!!] This method implements [OAuth 1.0 Spec 5.1](http://oauth.net/core/1.0/#rfc.section.5.1).
	 *
	 * @param   mixed  input string or array
	 * @return  mixed
	 */
	public static function urldecode($input)
	{
		if (is_array($input))
		{
			// Decode the values of the array
			return array_map(array('OAuthfog', 'urldecode'), $input);
		}

		// Decode the input
		return rawurldecode($input);
	}

	/**
	 * Normalize all request parameters into a string.
	 *
	 *     $query = OAuth::normalize_params($params);
	 *
	 * [!!] This method implements [OAuth 1.0 Spec 9.1.1](http://oauth.net/core/1.0/#rfc.section.9.1.1).
	 *
	 * @param   array   request parameters
	 * @return  string
	 * @uses    OAuth::urlencode
	 */
	public static function normalize_params(array $params = NULL)
	{
		if ( ! $params)
		{
			// Nothing to do
			return '';
		}

		// Encode the parameter keys and values
		$keys   = OAuthfog::urlencode(array_keys($params));
		$values = OAuthfog::urlencode(array_values($params));

		// Recombine the parameters
		$params = array_combine($keys, $values);

		// OAuth Spec 9.1.1 (1)
		// "Parameters are sorted by name, using lexicographical byte value ordering."
		uksort($params, 'strcmp');

		// Create a new query string
		$query = array();

		foreach ($params as $name => $value)
		{
			if (is_array($value))
			{
				// OAuth Spec 9.1.1 (1)
				// "If two or more parameters share the same name, they are sorted by their value."
				$value = natsort($value);

				foreach ($value as $duplicate)
				{
					$query[] = $name.'='.$duplicate;
				}
			}
			else
			{
				$query[] = $name.'='.$value;
			}
		}

		return implode('&', $query);
	}

	/**
	 * Parse the query string out of the URL and return it as parameters.
	 * All GET parameters must be removed from the request URL when building
	 * the base string and added to the request parameters.
	 *
	 *     // parsed parameters: array('oauth_key' => 'abcdef123456789')
	 *     list($url, $params) = OAuth::parse_url('http://example.com/oauth/access?oauth_key=abcdef123456789');
	 *
	 * [!!] This implements [OAuth Spec 9.1.1](http://oauth.net/core/1.0/#rfc.section.9.1.1).
	 *
	 * @param   string  URL to parse
	 * @return  array   (clean_url, params)
	 * @uses    OAuth::parse_params
	 */
	public static function parse_url($url)
	{
		if ($query = parse_url($url, PHP_URL_QUERY))
		{
			// Remove the query string from the URL
			list($url) = explode('?', $url, 2);

			// Parse the query string as request parameters
			$params = OAuthfog::parse_params($query);
		}
		else
		{
			// No parameters are present
			$params = array();
		}

		return array($url, $params);
	}

	/**
	 * Parse the parameters in a string and return an array. Duplicates are
	 * converted into indexed arrays.
	 *
	 *     // Parsed: array('a' => '1', 'b' => '2', 'c' => '3')
	 *     $params = OAuth::parse_params('a=1,b=2,c=3');
	 *
	 *     // Parsed: array('a' => array('1', '2'), 'c' => '3')
	 *     $params = OAuth::parse_params('a=1,a=2,c=3');
	 *
	 * @param   string  parameter string
	 * @return  array
	 */
	public static function parse_params($params)
	{
		// Split the parameters by &
		$params = explode('&', trim($params));

		// Create an array of parsed parameters
		$parsed = array();

		foreach ($params as $param)
		{
			// Split the parameter into name and value
			list($name, $value) = explode('=', $param, 2);

			// Decode the name and value
			$name  = OAuthfog::urldecode($name);
			$value = OAuthfog::urldecode($value);

			if (isset($parsed[$name]))
			{
				if ( ! is_array($parsed[$name]))
				{
					// Convert the parameter to an array
					$parsed[$name] = array($parsed[$name]);
				}

				// Add a new duplicate parameter
				$parsed[$name][] = $value;
			}
			else
			{
				// Add a new parameter
				$parsed[$name] = $value;
			}
		}

		return $parsed;
	}

} // End OAuth
