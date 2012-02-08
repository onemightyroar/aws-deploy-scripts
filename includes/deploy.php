<?php

	Class Deploy{
		
		//Files to modify
		private $assets;
		
		//AWS Specifics
		private $distribution;
		private $bucket;
		private $ses_subscription;
		
		//AWS Service Objects
		private $s3;
		private $cdn;
		private $sns;
		
		private $logging;
		private $table_row = '<tr><td>%s</td><td>%s</td></tr>';
		
		//Count mishaps
		private $error_count = 0;
		
		public function __construct($assets = array(), $options = array()){
			
			global $config;
			
			/*
				For each of the main options, check for an argument
				If there isn't one, default to the config file
			*/
			
			if(isset($options['distribution'])){
				$this->distribution = $options['distribution'];
			}else{
				$this->distribution = $config['distribution'];
			}
			
			if(isset($options['bucket'])){
				$this->bucket = $options['bucket'];
			}else{
				$this->bucket = $config['bucket'];
			}
			
			if(isset($options['ses_subscription'])){
				$this->ses_subscription = $options['ses_subscription'];
			}else{
				$this->ses_subscription = $config['ses_subscription'];
			}
			
			if(isset($assets)){
				$this->assets = $assets;
			}else{
				$this->assets = $config['assets'];
			}
			
			if(isset($options['logging'])){
				$this->logging = $options['logging'];
			}else{
				$this->logging = $config['logging'];
			}
			
			$this->s3 = new AmazonS3();
			$this->cdn = new AmazonCloudFront();
			$this->sns = new AmazonSNS();
			
			
			echo sprintf($this->table_row, 'START', '');
			
			$this->add_to_log('Script started');
			$this->add_to_log('Bucket: ' . $this->bucket . ' / ' . ' Distribution: ' . $this->distribution);
			
		}
		
		public function update_assets(){
			//Download the files for S3
			$this->download_assets();
			//We've uploaded the new stuff. Dump the old stuff.
			//$this->flush_cdn();
			//Send the results
			//$this->send_notification();
		}
		
		/*
			Download the files to the organized directories
		*/
		public function download_assets($compress = true){
			
			foreach($this->assets as $asset){
				
				$path_parts = pathinfo($asset);
				$current_asset = DEPLOY_BASEPATH . 'temp/' . $path_parts['basename'];
				
				$fp = fopen($current_asset, 'w+');
				$ch = curl_init($asset);
				curl_setopt($ch, CURLOPT_TIMEOUT, 50);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_exec($ch);
				curl_close($ch);
				fclose($fp);
				
				echo sprintf($this->table_row, 'DOWNLOAD', $asset . ' -> ' . $current_asset);
				$this->add_to_log('DOWNLOAD: ' . $asset . ' -> ' . $current_asset);
				
				//Compress and upload unless otherwise flagged
				if($compress == true){
					$compressed_file = $this->compress_asset($current_asset, $path_parts);
					if($compressed_file) $this->add_to_log('GZIP: ' . $current_asset . ' -> ' . $compressed_file);
					$this->upload_aws_asset($compressed_file, 'v2/');
				}
			}
			
		}
		
		/*
			Add to log file
		*/
		public function add_to_log($message){
			//Don't do anything if logging has been turned off.
			if($this->logging == false) return false;
			
			//Check if log file exists yet
			$filename = DEPLOY_BASEPATH . 'deploy_log';
    		
    		$logfile = fopen($filename, 'a');
    		
    		//Add timestamp to message before writing
    		fwrite($logfile, $message . ' [' . date("F j, Y, g:i a") . ']' . "\n");
    		fclose($logfile);
		}
		
		/*
			Save a gzipped version (make this step optional)
		*/
		public function compress_asset($current_asset, $path_parts){

			//dirname(__FILE__)
			// Name of the gz file we are creating
			$gzfile = DEPLOY_BASEPATH . 'output/' . $path_parts['filename'] . '.gz.' . $path_parts['extension'];

			// Open the gz file (w9 is the highest compression)
			$fp = gzopen($gzfile, 'w9');

			// Compress the file
			gzwrite($fp, file_get_contents($current_asset));

			// Close the gz file and we are done
			gzclose($fp);
			
			//Return the file location for AWS to upload
			return $gzfile;
			
		}
		
		/*
			Upload asset to S3
		*/
		public function upload_aws_asset($asset, $s3_path){
			
			$path_parts = pathinfo($asset);
			
			$file_name = $s3_path . $path_parts['extension'] . '/' . $path_parts['basename'];
			
			$file_details = array(
				'fileUpload' => DEPLOY_BASEPATH . 'output/' . $path_parts['basename'],
				'acl' => AmazonS3::ACL_PUBLIC,
				'headers' => array(
					'content-encoding' => 'gzip',
				)
			);
			
			$response = $this->s3->create_object($this->bucket, $file_name, $file_details);
			if($response->isOK()){
				echo 'File uploaded successfully';
				echo sprintf($this->table_row, 'UPLOAD', $file_name . ' -> ' . $this->bucket . ' in ' . $s3_path);
				$this->add_to_log('UPLOAD: ' . $file_name . ' to ' . $this->bucket . ' in ' . $s3_path);
				return true;
			}else{
				echo 'File was not uploaded';
				echo sprintf($this->table_row, 'ERROR', $file_name . ' -> ' . $this->bucket . ' in ' . $s3_path);
				$this->add_to_log('ERROR: ' . $file_name . ' was not uploaded');
				$this->error_count++;
				return false;
			}
		}
		
		/*
			Flush Cloudfront CDN (Doesn't work yet)
		*/
		public function flush_cdn(){
			
			//Flush everything for good measure. Clean this up later
			$invalidation_list = array();
			
			$invalidate_response = $this->cdn->create_invalidation($this->distribution_id, 'deploy-flush-' . time(), $invalidation_list);
			echo ($invalidate_response ? 'Invalidation Request Sent' : 'Invalidation Request Failed');
			if($invalidate_response == false){
				$this->add_to_log('ERROR: Invalidation request failed');
				$this->error_count++;
			}
		}
		
		/*
			Send success/failure messages to SNS subscriptions
		*/
		public function send_notification(){
					
			if($this->error_count > 0){
				$sns_subject = "Deploy Script Successful";
				$sns_message = "Deployment script ran successfully at " . date('F jS Y h:i:s A');
				echo sprintf($this->table_row, 'COMPLETE', 'Deployment finished successfully');
				$this->add_to_log("Completed deployment successfully");
			}else{
				$sns_subject = "Deploy Script Failed";
				$sns_message = "Deployment script ran with errors at " . date('F jS Y h:i:s A');
				echo sprintf($this->table_row, 'COMPLETE', 'Deployment finished with errors');
				$this->add_to_log("Completed deployment with errors");
			}
			
			$response = $this->sns->publish(
				$this->ses_subscription,
				$sns_message,
				array(
					'Subject' => $sns_subject
				)
			);
			
			if($response->isOK()){
				echo sprintf($this->table_row, 'NOTIFY', 'Sent deploy summary');
				$this->add_to_log('Sent deploy summary');
			}
			
			//Return if successful or not
			return $response->isOK();
		}
		
	}
?>