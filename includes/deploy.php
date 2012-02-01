<?php
	Class Deploy{
		
		//Path to deploy script (Needs fixing)
		private $base_url;
		
		//Files to modify
		private $assets = array(
		);
		
		//AWS Credentials
		private $access_key = 'YOUR ACCESS KEY';
		private $secret_key = 'YOUR SECRET KEY';
		private $distribution_id = 'CLOUDFRONT DISTRO ID';
		private $bucket = 'YOUR BUCKET NAME';
		
		//AWS Service Objects
		private $s3;
		private $cdn;
		private $sns;
		
		private $log = array();
		
		public function __construct($options = array()){
			
			$this->base_url = dirname(__FILE__);
			
			extract($options);
			var_dump($options);
			
			set_time_limit(0);
			
			//Assume things go well
			$this->log['deploy_success'] = true;
			
			// Populate AWS objects
			require_once $this->base_url . '/../aws/sdk/sdk.class.php';
			
			$this->s3 = new AmazonS3();
			$this->cdn = new AmazonCloudFront();
			$this->sns = new AmazonSNS();
			
		}
		
		public function update_assets(){
			$this->download_assets();
			//Send the results
			//$this->send_notification();
		}
		
		/*
			Download the files to the organized directories
		*/
		public function download_assets(){
			
			foreach($this->assets as $asset){
				
				$path_parts = pathinfo($asset);
				$current_asset = dirname(__FILE__) . '/temp/' . $path_parts['extension'] . '/' . $path_parts['basename'];
				
				$fp = fopen($current_asset, 'w+');
				$ch = curl_init($asset);
				curl_setopt($ch, CURLOPT_TIMEOUT, 50);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_exec($ch);
				curl_close($ch);
				fclose($fp);
				
				$compressed_file = $this->compress_asset($current_asset, $path_parts);
				$this->upload_aws_asset($compressed_file);
				
			}
			
		}
		
		/*
			Save a gzipped version
		*/
		public function compress_asset($current_asset, $path_parts){

			// Name of the gz file we are creating
			$gzfile = dirname(__FILE__) . '/output/' . $path_parts['extension'] . '/' . $path_parts['filename'] . '.gz.' . $path_parts['extension'];

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
		public function upload_aws_asset($asset){
			$path_parts = pathinfo($asset);
			
			$file_name = 'v2/' . $path_parts['extension'] . '/' . $path_parts['basename'];
			
			$file_details = array(
				'fileUpload' => 'output/' . $path_parts['basename'],
				'acl' => AmazonS3::ACL_PUBLIC,
				'headers' => array(
					'content-encoding' => 'gzip',
				)
			);
			$response = $this->s3->create_object($this->bucket, $file_name, $file_details);
			if($response->isOK()){
				echo 'File uploaded successfully';
				return true;
			}else{
				echo 'File was not uploaded';
				$this->log['deploy_success'] = false;
				return false;
			}
			//We've uploaded the new stuff. Dump the old stuff.
			$this->flush_cdn();
		}
		
		/*
			Flush Cloudfront CDN
		*/
		public function flush_cdn(){
			
			//Flush everything for good measure. Clean this up later
			$invalidation_list = array(
			);
			
			$invalidate_response = $this->cdn->create_invalidation($this->distribution_id, 'deploy-flush-' . time(), $invalidation_list);
			echo ($invalidate_response ? 'Invalidation Request Sent' : 'Invalidation Request Failed');
			if($invalidate_response == false){
				$this->log['deploy_success'] = false;
			}
		}
		
		/*
			Send success/failure messages to SNS subscriptions
		*/
		public function send_notification(){
					
			if($this->log['deploy_success']){
				$sns_subject = "Deploy Script Successful";
				$sns_message = "Deployment script ran successfully at " . date('F jS Y h:i:s A');
			}else{
				$sns_subject = "Deploy Script Failed";
				$sns_message = "Deployment script failed to run at " . date('F jS Y h:i:s A');
			}
			
			$response = $this->sns->publish(
				'SNS ARN ID',
				$sns_message,
				array(
					'Subject' => $sns_subject
				)
			);

			//Return if successful or not
			return $response->isOK();
		}
		
	}
?>