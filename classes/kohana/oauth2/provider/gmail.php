<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class Kohana_OAuth2_Provider_Gmail extends OAuth2_Provider {

	public $name = 'gmail';

	public function url_authorize()
	{
		return 'https://accounts.google.com/o/oauth2/auth';
	}

	public function url_access_token()
	{
		return 'https://www.googleapis.com/oauth2/v1/tokeninfo';
	}


}
