<?php

/**
 * DataGrid example application bootstrap file.
 *
 * @author     Roman Sklenář
 * @package    DataGrid\Example
 */


// Step 1: Load Nette Framework
if (!is_dir(LIBS_DIR . '/Nette')) {
	die("Extract Nette Framework to library directory '" . realpath(LIBS_DIR) . "'.");
}
require_once LIBS_DIR . '/Nette/loader.php';



// Step 2: Configure environment

/** 2a) load configuration from config.ini file */
Environment::setName(Environment::DEVELOPMENT);
$config = Environment::loadConfig();


/** 2b) check if needed directories are writable */
if (!is_writable(Environment::getVariable('tempDir'))) {
	die("Make directory '" . realpath(Environment::getVariable('tempDir')) . "' writable!");
}

if (!is_writable(Environment::getVariable('logDir'))) {
	die("Make directory '" . realpath(Environment::getVariable('logDir')) . "' writable!");
}


/** 2c) Setup Nette\Debug for better exception and error visualisation */
//Debug::$productionMode = $_SERVER['REMOTE_ADDR'] !== '127.0.0.1';  // admin's computer IP
$mode = (!Environment::isProduction() && !Environment::getHttpRequest()->isAjax()) ? Debug::DEVELOPMENT : Debug::PRODUCTION;
Debug::enable($mode, NULL);
Debug::$strictMode = TRUE;
Debug::$showLocation = TRUE;


/** 2d) enable RobotLoader - this allows load all classes automatically */
$loader = new RobotLoader();
$loader->addDirectory(explode(';', $config->scanDirs));
$loader->autoRebuild = Environment::isProduction() ? FALSE : TRUE; // rebuild if class is not found?
$loader->register();


/** 2e) load extension methods */
if (is_file(APP_DIR . '/extensions.php')) {
	include_once APP_DIR . '/extensions.php';
}


/** 2f) enable DebugBar */
if ($mode == Debug::DEVELOPMENT) {
	Debug::$showBar = TRUE;
}


/** 2g) Session setup [optional] */
if (Environment::getVariable('sessionDir') !== NULL && !is_writable(Environment::getVariable('sessionDir'))) {
	die("Make directory '" . realpath(Environment::getVariable('sessionDir')) . "' writable!");
}
$session = Environment::getSession();
$session->setSavePath(Environment::getVariable('sessionDir'));



// Step 3: Configure application

/** 3a) Setup Application, ErrorPresenter & exceptions catching */
$application = Environment::getApplication();

Presenter::$invalidLinkMode = Environment::isProduction() ? Presenter::INVALID_LINK_SILENT : Presenter::INVALID_LINK_EXCEPTION;
Environment::setVariable('host', Environment::getHttpRequest()->getUri()->host);


/** 3b) establish database connection and initialize services */
$application->onStartup[] = 'Services::initialize';
$application->onStartup[] = 'BaseModel::initialize';
$application->onShutdown[] = 'BaseModel::disconnect';



// Step 4: Setup application router
$router = $application->getRouter();

$router[] = new Route('index.php', array(
	'presenter' => 'Example',
	'action' => 'default',
), Route::ONE_WAY);

$router[] = new Route('<presenter>/<action>/', array(
	'presenter' => 'Example',
	'action' => 'default',
));

$router[] = new SimpleRouter('Example:default');



// Step 5: Run the application!
$application->run();
