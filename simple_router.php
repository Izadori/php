<?php

require_once __DIR__."/traits.php";

class SimpleRouter
{
  // View管理用トレイトの追加
  use ViewManager;

  // 404を返すページのファイル名
  static public $file404 = "";

  /**
   * array( 'route' => array(
   *  'method' => array(
   *    'get' => array( func1, func2, ...),
   *    'post' => array( func1, func2, ...),
   *    'request' => array( func1, func2, ...),
   *  )
   * )
   */
  private$routeList = array();

  private function __construct()
  {
    // ベースURLの抽出
    // SCRIPT_NAMEからファイル名([^\/]+\.php)を探索する
    // その前の部分がベースURLになる
    $baseURL = "";
    preg_match("/\/[^\/]+\.php/", $_SERVER['SCRIPT_NAME'], $matches);
//    print("<br>\n"); var_dump($matches); print("<br>\n");
    $baseURL = substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], $matches[0]));

    if(strlen($baseURL) != 0) {
      $GLOBALS['BASE_URL'] = $baseURL;
    }
    else{
      $GLOBALS['BASE_URL'] = "";
    }
//    var_dump($GLOBALS['BASE_URL']);
  }

  public function __destruct()
  {
    // スクリプトファイル名とベースURLを除去する
    $uri = preg_replace("/[^\/]+\.php/", "", $_SERVER['REQUEST_URI']);
    $uriPattern = "/".preg_replace("/\//", "\\/", $GLOBALS['BASE_URL'])."/";
//    var_dump($uriPattern); print("<br>\n");
    $uri = preg_replace($uriPattern, "", $uri);
//    var_dump($uri); print("<br>\n");

    // routetListから'route'が最大一致の関数を実行する
    self::getInstance()->callFunctions($uri);
  }

  static private function getInstance(): SimpleRouter
  {
    static $routing;

    if(empty($routing)){
      $routing = new SimpleRouter();
    }

    return $routing;
  }

  // ルートからパラメータ名を取得する
  private function getParamName(string $route) : array
  {
    $ret = array();

    if(preg_match_all("/\{([^\}]*)}/", $route, $matches)){
      for($i = 0; $i < sizeof($matches[1]); $i++){
        $ret[$i] = $matches[1][$i];
      }
    }

    return $ret;
  }

  // ルーティングに従って関数の呼び出し
  private function callFunctions(string  $uri) : void
  {
    $path = $uri;
    $matched = array();
    $varName  = array();
    $varParam = array();

    // 最長一致URIの探索
    foreach(self::getInstance()->routeList as $route => $value) {
      $route_tmp = preg_replace("/\\//", "\\\\/", $route);

      $varParam[$route] = array();
      $varName[$route]  = $this->getParamName($route_tmp);
//      print("+ "); var_dump($varName); print("<br>\n");

      $route_tmp = preg_replace("/\{[^\}\?]+}/", "([^\\/]+)", $route_tmp);
      $route_tmp = preg_replace("/\{[^\}]+\?}/", "([^\\/]+)?", $route_tmp);
      $pattern = '/^'.$route_tmp.'(.*)$/';

      if(preg_match($pattern, $uri, $matches)) {
/*
        print("route: {$route}<br>\n");
        print("pattern: {$pattern}<br>\n");
        print("uri: {$uri}<br>\n");
*/
        for($i = 1; $i < sizeof($matches) - 1; $i++){
//          print("matched: {$matches[$i]}<br>\n");
          $varParam[$route][$i-1] = $matches[$i];
        }
        $matched[$route] = $matches[0];
      }

//      print("* "); var_dump($varParam[$route]); print("<br>\n");
    }

    $maxlength = 0;
    $longest_uri = "";
    foreach($matched as $route => $param) {
      $length = strlen($route);
      if($length > $maxlength) {
        $maxlength = $length;
        $longest_uri = $route;
      }
    }
/*
    print("uri:{$uri}<br>\n");
    print("path:{$path}<br>\n");
    print("longest uri: {$longest_uri}<br>\n");
*/

    $script_name = $_SERVER['SCRIPT_NAME'] ?? "";

    // 関数の実行
    $method = $_SERVER['REQUEST_METHOD'];
    $run = false;

    if(isset(self::getInstance()->routeList[$longest_uri][$method])) {
      foreach(self::getInstance()->routeList[$longest_uri][$method] as $func){
        $f = is_callable($func) ? $func : array(new $func[0], $func[1]);

        $param = [];
        for($i = 0; $i < sizeof($varParam[$longest_uri]); $i++){
          $varNameTmp = preg_replace("/\?$/", "", $varName[$longest_uri][$i]);
          $param[$varNameTmp] = $varParam[$longest_uri][$i];
        }
//        print("? "); var_dump($param); print("<br>\n");

        call_user_func_array($f, $param);
        $run = true;
      }
    }

    if(($method === 'GET' || $method === 'POST')
      && isset(self::getInstance()->routeList[$longest_uri]['REQUEST'])) {
      foreach(self::getInstance()->routeList[$longest_uri]['REQUEST'] as $func){
        $f = is_callable($func) ? $func : array(new $func[0], $func[1]);

        $param = [];
        for($i = 0; $i < sizeof($varParam[$longest_uri]); $i++){
          $varNameTmp = preg_replace("/\?$/", "", $varName[$longest_uri][$i]);
          $param[$varNameTmp] = $varParam[$longest_uri][$i];
        }
//        print("? "); var_dump($param); print("<br>\n");

        call_user_func_array($f, $param);
        $run = true;
      }
    }

    if(!$run){
      header("HTTP/1.0 404 Not Found");

      if(self::$file404 !== ""){
        include self::$file404;
      }
      else{
        self::getInstance()->show404();
      }

      exit();
    }
  }

  private function show404()
  {
    $date = date("Y-n-d(D) H:i:s", $_SERVER['REQUEST_TIME']);
    $addr = $_SERVER['SERVER_NAME'] === "localhost" ? "127.0.0.1" : $_SERVER['SERVER_ADDR'];

    echo "<html>\n<head>\n  <title>404 Not Found</title>\n</head>\n";
    echo "<body>\n  <h1>404 Not Found</h1>\n";
    echo "  <p>\n    指定されたページは見つかりません。\n  </p>\n";
    echo "  <hr>\n  <footer>\n";
    echo "{$_SERVER['SERVER_NAME']}({$addr}:{$_SERVER['SERVER_PORT']}) ";
    echo "at {$date}";
    echo "  </footer>\n</body>\n</html>";
  }

  static public function get(string $route, array|callable $func): void
  {
    self::getInstance()->routeList[$route]['GET'][] = $func;
  }

  static public function post(string $route, array|callable $func): void
  {
    self::getInstance()->routeList[$route]['POST'][] = $func;
  }

  static public function request(string $route, array|callable $func): void
  {
    self::getInstance()->routeList[$route]['REQUEST'][] = $func;
  }

  static public function put(string $route, array|callable $func): void
  {
    self::getInstance()->routeList[$route]['PUT'][] = $func;
  }

  static public function delete(string $route, array|callable $func): void
  {
    self::getInstance()->routeList[$route]['DELETE'][] = $func;
  }

  static public function redirect(string $uri, ?array $parameter = null): void
  {
    self::getInstance()->redirectMain($uri, $parameter);
  }

  static public function load(string|array $file, ?array $parameter = null): void
  {
    self::getInstance()->loadMain($file, $parameter);
  }
}
?>
