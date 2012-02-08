<?php
/*
	Set path to deploy script folder
*/
define("DEPLOY_BASEPATH", $_SERVER['DOCUMENT_ROOT'] . '/aws-deploy-scripts/');

/*
	Load the deploy class
*/
require_once 'includes/deploy.php';

?>