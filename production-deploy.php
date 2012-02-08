<?php

	require_once 'includes/deploy-config.php';

?></pre>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Production Deploy Hook</title>
	<style>
		body{
			font-family:"Georgia", Times, serif;
		}
		table{}
		table thead tr{
			background:#FFF !important;
			text-align:left;
		}
		table tr:nth-child(odd) td, table tr:nth-child(odd){
			background:#efefef;
			border-top:1px solid #CCC;
		}
		table td, table th{
			padding:15px;
		}
	</style>
</head>
<body>
	
	<h1>Deploy Script</h1>
	<table cellspacing="0">
		<thead>
			<th>Action</th>
			<th>Description</th>
		</thead>
		<?php
		if (class_exists('Deploy')){
			$assets = array(
				'http://localhost:8888/web-sandbox/filedump/bookmarklet.js',
				'http://localhost:8888/web-sandbox/filedump/alabama.jpg'
			);
			$deploy = new Deploy($assets);
		}else{ ?>
			<tr class="error">
				<td>Error</td>
				<td>Couldn't load deploy class</td>
			</tr>
		<? }
		
		$dry_run = (isset($_GET['dry_run']) && !empty($_GET['dry_run']) ? true : false);
		
		if($dry_run){
			echo '<p><em>Dry Run</em></p>';
		}else{
			echo '<p><em>Live Run</em></p>';
		}

		if(!$dry_run){
			//$deploy->update_assets();
			$deploy->download_assets(false);
		}
		?>
	</table>

</body>	
</html>
