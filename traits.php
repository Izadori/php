<?php

require_once __DIR__."/validate.php";

trait ViewManager
{
  public function loadMain(string|array $file, ?array $parameter = null): void
  {
    // パラメータのローカル変数化
    if(!is_null($parameter)){
      foreach($parameter as $key => $value){
        $$key = $value;
      }
    }

    if(is_string($file)){
      include $file;
    }
    else{
      foreach($file as $f) {
        include $f;
      }
    }
    exit();
  }

  public function redirectMain(string $uri, ?array $parameter = null): void
  {
    $tmp_param = $parameter ?? array();
    $tmp_param['script_name'] = $_SERVER['SCRIPT_NAME'] ?? "";
    $tmp_param['request_uri'] = $_SERVER['REQUEST_URI'] ?? "";
    $tmp_param['session'] = $_SESSION ?? array();
    $tmp_param['request'] = $_REQUEST ?? array();
    $tmp_param['rget'] = $_GET ?? array();
    $tmp_param['post'] = $_POST ?? array();
    $tmp_param['cookie'] = $_COOKIE ?? array();

    unset($GLOBALS['route_params']);
    $GLOBALS['route_params'] = $tmp_param;
    header("Location: ".$GLOBALS['BASE_URL'].$uri);

    exit();
  }
}

?>
