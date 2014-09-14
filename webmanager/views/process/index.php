<h1>查看进程状态</h1>
<div class="form man">
    <?php $form=$this->beginWidget('Sky\web\widgets\ActiveForm',array('htmlOptions'=>array('class'=>"form-horizontal"))); ?>
    <div class="form-group">
        <?php echo $form->labelEx($model,'server',array('class'=>"col-sm-3 control-label")); ?>
        <div class="col-xs-3">
            <?php echo $form->dropDownList($model,'server',$serverlist,array('class'=>"form-control")); ?>
        </div>
        <?php echo $form->error($model,'server'); ?>
        <div class="buttons">
            <?php echo Sky\help\Html::submitButton('确定',array('name'=>'submit','class'=>"btn btn-primary")); ?>
        </div>
    </div>

    <?php echo Sky\help\Html::hiddenField('server',$model->attributes['server'])?>
    <div id="proc_name"></div><div id="action"></div>
<?php if($model->status===webmanager\models\Process::STATUS_SUCCESS):?>
    <table class="table table-condensed table-bordered">
        <thead>
            <tr>
                <th>进程名</th>
                <th>状态</th>
                <th>参数</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($result['result'] as $procList):?>
            <?php if($procList['status']=='RUNNING'):?>
                <tr class="success">
            <?php else: ?>
                <tr class="danger">
            <?php endif;?>
                <td><?php echo $procList['name'];?></td>
                <td><?php echo $procList['status'];?></td>
                <?php if($procList['status']=='RUNNING'):?>
                    <td><?php echo "进程号:".$procList['pid']." 内存:".$procList['mem']."Kb 运行时间:".$procList['up_time']?></td>
                <td>
                    <?php echo Sky\help\Html::submitButton('停止',array('id'=>$procList['name'],'name'=>'stop','class'=>"btn btn-danger btn-xs proc")); ?>
                </td>
                <?php else: ?>
                    <td><?php echo "停止时间:".$procList['stop_time']?></td>
                <td>
                    <?php echo Sky\help\Html::submitButton('启动',array('id'=>$procList['name'],'name'=>'start','class'=>"btn btn-success btn-xs proc")); ?>
                </td>
                <?php endif;?>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
<?php endif;?>
<?php if($model->status===webmanager\models\Process::STATUS_ERROR): ?>
    <div class="alert alert-danger alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <strong>Error!</strong> <?php echo $result['result'];?>
    </div>
<?php endif;?>
    <?php if(isset($resultMsg['code']) && $resultMsg['code']==500): ?>
        <div class="alert alert-danger alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <strong>Error!</strong> <?php echo $resultMsg['msg'];?>
        </div>
    <?php endif;?>
<?php $this->endWidget(); ?>
</div>
<script>
       $("form").submit(function(){
           $('.proc').attr('disabled','disabled');
       });

       $('.proc').click(function(){
           $('#proc_name').html('<input type="hidden" value="'+$(this).attr('id')+'" name="name">');
           $('#action').html('<input type="hidden" value="" name="'+$(this).attr('name')+'">');
       });
</script>


