<?php
namespace webmanager\controllers;
use webmanager\models\Process;
use Sky\Sky;
use Sky\utils\Socket;
use Sky\web\Response;

class ProcessController extends \Sky\base\Controller{
    public $layout='process';

    public function getPageTitle()
    {
        return 'Wolf';
    }

    public function actionIndex()
    {
        $model=new Process();
        $resultMsg=array();
        $list=array('localhost'=>'localhost');
        if (isset($_POST['webmanager_models_Process']))
        {
            $model->attributes=$_POST['webmanager_models_Process'];
            $server=$model->attributes['server'];
            if(isset($_POST['start'])){
                $resultMsg=$this->startStop($_POST['server'],$_POST['name'],'true');
                $server = $_POST['server'];
                sleep(2);
            }elseif(isset($_POST['stop'])){
                $resultMsg=$this->startStop($_POST['server'],$_POST['name'],'false');
                $server = $_POST['server'];
            }

            $result = $this->getProcessInfo($server);
            if($result['code']!=500){
                $model->status = Process::STATUS_SUCCESS;
            }else{
                $model->status=Process::STATUS_ERROR;
            }
            $this->render('index',array('model'=>$model,'result'=>$result,'serverlist'=>$list,'resultMsg'=>$resultMsg));
            return ;
        }
        $this->render('index',array('model'=>$model,'serverlist'=>$list));
    }

    protected function startStop($server,$name,$start)
    {
        $result = array('code'=>500,'msg'=>'param error');
        $host = Sky::$app->params['wolf'][$server];
        $socket = new Socket();
        if(!$socket->connect($host[0],$host[1]))
        {
            $result['code']=500;
            $result['msg']='connect error@'.$host[0].':'.$host[1];
            return $result;
        }

        if($start=='true'){
            if(($ret=$socket->sendGet("start $name\n"))!==FALSE){
                if(strpos($ret,'success')===FALSE){
                    $result['code']=500;
                }else{
                    $result['code']=200;
                }
                $result['msg']=rtrim($ret,"\n");
            }else{
                $result['code']=500;
                $result['msg']='send command "start "'.$name.' error@'.$host[0].':'.$host[1];
            }
        }elseif($start=='false'){
            if(($ret=$socket->sendGet("stop $name\n"))!==FALSE){
                if(strpos($ret,'success')===FALSE){
                    $result['code']=500;
                }else{
                    $result['code']=200;
                }
                $result['msg']=rtrim($ret,"\n");
            }else{
                $result['code']=500;
                $result['msg']='send command "stop "'.$name.' error@'.$host[0].':'.$host[1];
            }
        }
        return $result;
    }

    /**
     * 从服务器获取进程信息
     * @param $server
     * @return array
     */
    protected function getProcessInfo($server)
    {
        $processInfo = array();
        $host = Sky::$app->params['wolf'][$server];
        $socket = new Socket();
        if(!$socket->connect($host[0],$host[1]))
        {
            $processInfo['code']=500;
            $processInfo['result']='connect error@'.$host[0].':'.$host[1];
            return $processInfo;
        }

        if(($ret=$socket->sendGet("status\n"))!==FALSE){
            $retArray = explode("\n",rtrim($ret,"\n"));
            $resultList = array();
            $length = count($retArray);
            if($length > 3){
                for($i=3;$i<$length;$i++){
                    $resultList[]=$this->formatInfo(trim($retArray[$i]));
                }
            }
            $processInfo['code']=200;
            $processInfo['result']=$resultList;
        }else{
            $processInfo['code']=500;
            $processInfo['result']='send command "status" error@'.$host[0].':'.$host[1];
        }
        return $processInfo;
    }

    /**
     * 格式化进程信息
     * @param $str
     * @return array
     */
    protected function formatInfo($str)
    {
        $result=array();
        $tempArr = explode("\t\t",$str);
        $result['name']=$tempArr[0];
        $tempDetailArr = explode("\t",$tempArr[1]);
        $result['status']=$tempDetailArr[0];
        if($result['status']=='RUNNING'){
            //cmdpid:14020, mem usage:22344 KB, up time:7h 12m 38s
            $ret=preg_match_all('/^cmdpid:(\d+), mem usage:(\d+) KB, up time:(.+)$/',$tempDetailArr[1],$matches);
            $result['pid']=empty($matches[1][0])?0:$matches[1][0];
            $result['mem']=empty($matches[2][0])?0:$matches[2][0];
            $result['up_time']=empty($matches[3][0])?0:$matches[3][0];
        }else{
            $result['stop_time']=$tempDetailArr[1];
        }
        return $result;
    }


}