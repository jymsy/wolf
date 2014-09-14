<!DOCTYPE>
<html lang="zh-cn">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />	
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Bootstrap -->
    <link rel="stylesheet" href="http://cdn.bootcss.com/twitter-bootstrap/3.0.3/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="./assets/css/main.css" />
	<link rel="stylesheet" type="text/css" href="./assets/css/metro.css" />
    <script type="text/javascript" src="./assets/js/jquery-1.11.0.min.js"></script>
	<title><?php echo \Sky\help\Html::encode($this->getPageTitle()); ?></title>
	<script type="text/javascript" src="./assets/js/main.js"></script>
</head>
<body lang="zh-cn">
<div class="container" id="page">
	<div id="header">
		<div class="top-menus">
			<?php if(!\Sky\Sky::$app->getUser()->getIsGuest()): ?>
		    <?php echo \Sky\help\Html::link('注销',array('default/logout')); ?>
			<?php endif; ?>
		</div>
	</div><!-- header -->

	<?php echo $content; ?>

	<div id="footer">
		Powered by Sky Framework.
	</div><!-- footer -->
</div><!-- page -->

<script src="http://cdn.bootcss.com/twitter-bootstrap/3.0.3/js/bootstrap.min.js"></script>
<!--[if lt IE 9]>
<script src="http://cdn.bootcss.com/html5shiv/3.7.0/html5shiv.min.js"></script>
<script src="http://cdn.bootcss.com/respond.js/1.3.0/respond.min.js"></script>
<![endif]-->
<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->

<script type="text/javascript">
$(function(){
	var url = window.location.href;
	$('#mytab li').each(function(){
        var current_total = $(this).children('a').attr('href');
        var pos = current_total.lastIndexOf('/index');
        var current = current_total.substring(0,pos);
        if(url.indexOf(current)>=0)
		{
			$(this).attr('class','active');
		}
	});
});
</script>
</body>
</html>