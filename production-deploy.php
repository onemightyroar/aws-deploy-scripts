<pre><?php

	require_once 'includes/deploy-config.php';
	
	if (class_exists('Deploy')){
		$options = array(
			'compression' 	=> 'gzip',
			'bucket'		=> 'wouldyourather',
			'notification'	=> true,
			'assets'		=> array(
				'http://localhost:8888/web-sandbox/filedump/bookmarklet.js',
				'http://localhost:8888/web-sandbox/filedump/alabama.jpg'
			)
		);
		$deploy = new Deploy($options);
	}else{
		die('Couldn\'t load deploy class');
	}
	
	$dry_run = (isset($_GET['dry_run']) && !empty($_GET['dry_run']) ? true : false);
	
	if($dry_run){
		echo 'Dry Run';
	}else{
		echo 'Live Run';
	}

?></pre>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Production Deploy Hook</title>
</head>
<body>
	
		
	<pre>
		<?php
			if(!$dry_run){
				//$deploy->update_assets();
				$deploy->download_assets(false);
			}
		?>
	</pre>

</body>	
</html>
