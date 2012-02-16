<?php
	require_once 'includes/deploy-config.php'; 
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Production Deploy Hook</title>
	<style>
		body{
			font-family:"Panic Sans", "Monaco", "Courier New", serif;
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
		.live{
			background:#6FBF4D;
			color:#FFF;
			padding:5px 10px;
		}
		.tabs a{
			color: #888888;
			text-decoration:none;
		}
		.tabs a.selected{
			color: #943D10;
			border-bottom:1px dotted #943D10;
		}
	</style>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
	<script>
		$(document).ready(function(){
			$('.panel').hide();
			$('.panel:eq(0)').show();
			
			$('.tabs a').click(function(e){
				e.preventDefault();
				var target_id;
				target_id = $(this).attr('href');
				$('.panel').hide();
				$('.tabs a').removeClass('selected');
				$(this).addClass('selected');
				$(target_id).show();
			});
		});
	</script>
</head>
<body>
	
	<h1>Deploy Script</h1>
	
	<p class="tabs"><a href="#activity" class="selected">Activity</a> / <a href="#config">Config</a></p>
	
	<?php 
		$dry_run = (isset($_GET['dry_run']) && !empty($_GET['dry_run']) ? true : false);
		
		if($dry_run){
			echo '<p class="dry">Dry Run</p>';
		}else{
			echo '<p class="live">Live Run</p>';
		}
	?>
	
	<div id="activity" class="panel">
	
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
	
			if(!$dry_run){
				$deploy->update_assets();
			}
			?>
		</table>
		
	</div>
	
	<div id="config" class="panel">
		<?php $deploy->show_config(); ?>
	</div>
	

</body>	
</html>
