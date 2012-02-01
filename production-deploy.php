<pre><?php
	ini_set('display_errors',1); 
	error_reporting(E_ALL);

	require_once 'includes/deploy.php';
	
	if (class_exists('Deploy')){
		$options = array(
			'compression' 	=> 'gzip',
			'notification'	=> true
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
			}
		?>
	</pre>

</body>	
</html>
