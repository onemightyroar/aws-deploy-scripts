<?php

	Class Deploy{
		
		//Files to modify
		private $assets;
		
		//Paths of files uploaded to S3
		private $uploaded_assets;
		
		//AWS Specifics
		private $distribution;
		private $flush_cdn;
		private $bucket;
		private $ses_subscription;
		private $s3_path;
		
		//File Handling
		private $compression;
		
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
				(These can probably be condensed better)
			*/
			
			if(isset($options['distribution'])){
				$this->distribution = $options['distribution'];
			}else{
				$this->distribution = $config['distribution'];
			}
			
			if(isset($options['flush_cdn'])){
				$this->flush_cdn = $options['flush_cdn'];
			}else{
				$this->flush_cdn = $config['flush_cdn'];
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
			
			if(isset($options['s3_path'])){
				$this->s3_path = $options['s3_path'];
			}else{
				$this->s3_path = $config['s3_path'];
			}
			
			if(isset($options['compression'])){
				$this->compression = $options['compression'];
			}else{
				$this->compression = $config['compression'];
			}
			
			$this->s3 = new AmazonS3();
			$this->cdn = new AmazonCloudFront();
			$this->sns = new AmazonSNS();
			
			echo sprintf($this->table_row, 'START', 'Bucket: ' . $this->bucket . ' <br/> ' . ' Distribution: ' . $this->distribution);
			
			$this->add_to_log('Script started');
			$this->add_to_log('Bucket: ' . $this->bucket . ' / ' . ' Distribution: ' . $this->distribution);
			
		}
		
		public function update_assets(){
			//Download the files for S3
			$this->download_assets();
			//We've uploaded the new stuff. Dump the old stuff.
			if($this->flush_cdn) $this->flush_cdn();
			//Send the results
			//$this->send_notification();
			echo sprintf($this->table_row, 'END', 'Deployment completed with ' . $this->error_count . ' error(s).');
		}
		
		/*
			Download the files to the organized directories
		*/
		public function download_assets(){
			
			$this->uploaded_assets = array();
			
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
				if($this->compression == 'gzip'){
					$compressed_file = $this->compress_asset($current_asset, $path_parts);
					if($compressed_file) $this->add_to_log('GZIP: ' . $current_asset . ' -> ' . $compressed_file);
					$this->upload_aws_asset($compressed_file);
				}else{
					//Don't compress
					$this->upload_aws_asset($current_asset);
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

			// Name of the gz file we are creating
			$gzfile = DEPLOY_BASEPATH . 'output/' . $path_parts['filename'] . '.gz.' . $path_parts['extension'];

			// Open the gz file (w9 is the highest compression)
			$fp = gzopen($gzfile, 'w9');

			// Compress the file
			gzwrite($fp, file_get_contents($current_asset));

			// Close the gz file and we are done
			gzclose($fp);
			
			//Logging
			echo sprintf($this->table_row, 'GZIP', $current_asset . ' -> ' . $gzfile);
			$this->add_to_log('GZIP: ' . $current_asset . ' -> ' . $gzfile);
			
			//Return the file location for AWS to upload
			return $gzfile;
			
		}
		
		/*
			Upload asset to S3
		*/
		public function upload_aws_asset($asset, $s3_path = null){
			
			$path_parts = pathinfo($asset);
			
			//Default to config file S3 path when none was given
			if($s3_path == null || empty($s3_path)){
				$s3_path = $this->s3_path;
			}
			
			$file_name =  $s3_path . $path_parts['basename'];
			$file_to_upload =  DEPLOY_BASEPATH . 'output/' . $path_parts['basename'];
			
			$file_headers = array();
			
			if($this->compression == 'gzip'){
				$file_headers['content-encoding'] = 'gzip';
			}
			
			$file_details = array(
				'fileUpload' => $file_to_upload,
				'acl' => AmazonS3::ACL_PUBLIC,
				'headers' => $file_headers
			);
			
			$response = $this->s3->create_object($this->bucket, $file_name, $file_details);
			
			if($response->isOK()){
				echo sprintf($this->table_row, 'UPLOAD', $file_to_upload . ' -> ' . $this->bucket . '/' . $file_name);
				$this->add_to_log('UPLOAD: ' . $file_to_upload . ' -> ' . $this->bucket . '/' . $file_name);
				array_push($this->uploaded_assets, $file_name);
				return true;
			}else{
				echo sprintf($this->table_row, 'ERROR', $file_to_upload . ' -> ' . $this->bucket . '/' . $s3_path);
				$this->add_to_log('ERROR: ' . $file_name . ' was not uploaded');
				$this->error_count++;
				return false;
			}
		}
		
		/*
			Flush Cloudfront CDN
		*/
		public function flush_cdn(){
			
			//Flush everything for good measure.
			$invalidation_list = $this->uploaded_assets;
			
			$invalidate_response = $this->cdn->create_invalidation($this->distribution, 'deploy-flush-' . time(), $invalidation_list);
			
			if($invalidate_response == true){
				echo sprintf($this->table_row, 'FLUSH', 'Invalidation Request Sent');
				$this->add_to_log('FLUSH: Invalidation request sent');
			}else{
				echo sprintf($this->table_row, 'ERROR', 'Invalidation Request Failed');
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