<?php

ini_set('display_errors',1); 
error_reporting(E_ALL);

//Remove time limit for upload actions
set_time_limit(0);
			
/*
	Set path to deploy script folder
*/
define("DEPLOY_BASEPATH", $_SERVER['DOCUMENT_ROOT'] . '/aws-deploy-scripts/');

/*
	Config Options
*/

$config = array();

$config['distribution'] 	= '';		//Target Cloudfront Distribution
$config['bucket']			= '';		//Target S3 Bucket
$config['s3_path']			= '';					//Default path for S3 bucket uploads
$config['compression']		= '';		//Type of compression to use (none, gzip)
$config['ses_subscription']	= '';		//ARN of SES subscription for notifications
$config['notification']		= false;	//Send status updates (requires SES active)
$config['logging']			= true;		//Turn on/off logging
$config['assets']			= array();				//Assets to download and publish (Phase out?)

// AWS PHP SDK
require_once DEPLOY_BASEPATH . 'aws/sdk/sdk.class.php';

// Main Deploy Class
require_once DEPLOY_BASEPATH . 'includes/deploy.php';

?>