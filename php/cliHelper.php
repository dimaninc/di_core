<?php
if (empty($_SERVER['HTTP_HOST']) && isset($_SERVER['HOSTNAME']))
{
	$_SERVER['HTTP_HOST'] = $_SERVER['HOSTNAME'];
}

if (empty($_SERVER['DOCUMENT_ROOT']))
{
	$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(dirname(__FILE__)));

	if (($x = strpos($_SERVER['DOCUMENT_ROOT'], '/_core/php')) !== false)
	{
		$_SERVER['DOCUMENT_ROOT'] = substr($_SERVER['DOCUMENT_ROOT'], 0, $x);

		if (is_file($autoload = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'))
		{
			require $autoload;
		}
	}
	else
	{
		if (($x = strpos($_SERVER['DOCUMENT_ROOT'], '/vendor/dimaninc/di_core/php')) !== false)
		{
			$_SERVER['DOCUMENT_ROOT'] = substr($_SERVER['DOCUMENT_ROOT'], 0, $x) . '/htdocs';

			require $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php';;
		}
		else
		{
			throw new \Exception('Unknown location: ' . __FILE__);
		}
	}
}

require dirname(__FILE__) . '/functions.php';
require \diCore\Data\Config::getConfigurationFolder() . '_cfg/common.php';

if (empty($_SERVER['SERVER_PORT']))
{
	$_SERVER['SERVER_PORT'] = \diCore\Data\Config::getMainPort();
}

$_GET = \diRequest::convertFromCommandLine();
