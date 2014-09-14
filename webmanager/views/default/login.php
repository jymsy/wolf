<h1 style="text-align:center;">Wolf</h1>
<div class="form login">
<?php $form=$this->beginWidget('Sky\web\widgets\ActiveForm'); ?>
	<p>Enter password</p>
	<div class="form-group">
		<?php echo $form->passwordField($model,'password',array('class'=>"form-control",'placeholder'=>"Password")); ?>
	</div>
	<?php echo $form->error($model,'password'); ?>
	<?php echo Sky\help\Html::submitButton('Login', array('class'=>"btn btn-primary")); ?>

<?php $this->endWidget(); ?>
</div><!-- form -->
