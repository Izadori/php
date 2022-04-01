<?php

require_once __DIR__."/traits.php";

/*
["PHP_SELF"]=> string(20) "/index.php/dashboard"
["PATH_INFO"]=> string(10) "/dashboard"
["SCRIPT_NAME"]=> string(10) "/index.php"
["REQUEST_URI"]=> string(25) "/dashboard?state=category"


'PATH_INFO'（無い場合あり）
実際のスクリプトファイル名とクエリ文字列の間にある、クライアントが提供するパス名情報。
たとえば、現在のスクリプトに http://www.example.com/php/path_info.php/some/stuff?foo=bar
という URL でアクセスしていた場合の $_SERVER['PATH_INFO'] は /some/stuff となります。

'REQUEST_URI'
ページにアクセスするために指定された URI。例えば、 '/index.html'


["PHP_SELF"]=> string(20) "/md_editor/index.php"
["PATH_INFO"]=> undefined
["SCRIPT_NAME"]=> string(20) "/md_editor/index.php"
["REQUEST_URI"]=> string(11) "/md_editor/"

必要なこと
・ベースURLの取得
  →SCRIPT_NAMEとREQUEST_URIの前方共通部分がベースURL
  →スーパーグローバル変数として定義
・正しいルーティングの実施
  →ベースURLがREQUEST_URIに含まれることを考慮する。
  →評価前にREQUEST_URIを除去しておく必要あり
・パラメータの取得
  →Laravelライクに指定する"/user/{id}", "/user/{name?}"に対応
*/

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
    $baseURL = "";
    $minLength = min(strlen($_SERVER['REQUEST_URI']), strlen($_SERVER['SCRIPT_NAME']));

    for($i = 0; $i < $minLength; $i++) {
      if($_SERVER['REQUEST_URI'][$i] === $_SERVER['SCRIPT_NAME'][$i]){
        $baseURL .= $_SERVER['REQUEST_URI'][$i];
      }
      else{
        break;
      }
    }

    if(strlen($baseURL) != 0) {
      $GLOBALS['BASE_URL'] = substr($baseURL, 0, strlen($baseURL) - 1);
    }
    else{
      $GLOBALS['BASE_URL'] = "";
    }
//    var_dump($GLOBALS['BASE_URL']);
  }

  public function __destruct()
  {
    // get/postListから'route'が最大一致の関数を実行する
    self::getInstance()->callFunctions($_SERVER['REQUEST_URI']);
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
    $base_tmp = preg_replace("/([\\/\\,\\.\\-\\~\\^])/", "\\\\$1", $GLOBALS['BASE_URL']);
    $pattern = '/^'.$base_tmp.'(\/[^\?]*)\??.*$'.'/';
    preg_match($pattern, $uri, $matches);
    $path = $matches[1];
    $matched = array();
    $varName  = array();
    $varParam = array();

    // 最長一致URIの探索
    foreach(self::getInstance()->routeList as $route => $value) {
      $route_tmp = preg_replace("/\\//", "\\\\/", $route);

      $varParam[$route] = array();
      $varName[$route]  = $this->getParamName($route_tmp);
//      print("+ "); var_dump($varName); print("<br>\n");

      $route_tmp = preg_replace("/\{[^\}]+}/", "([^\\/]+)", $route_tmp);
      $pattern = '/^'.$base_tmp.$route_tmp.'(.*)$/';

      if(preg_match($pattern, $uri, $matches)) {

        print("route: {$route}<br>\n");
        print("pattern: {$pattern}<br>\n");
        print("uri: {$uri}<br>\n");

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
    // 最長一致URIが"/"の時、$uriと異なれば404を返す
    if($longest_uri ==="/" && $longest_uri !== $path) {
      header('HTTP/1.1 404 Not Found');

      if(self::$file404 !== ""){
        include self::$file404;
      }
      else{
        self::getInstance()->show404();
      }

      exit();
    }

    $script_name = $_SERVER['SCRIPT_NAME'] ?? "";

    // 関数の実行
    $method = $_SERVER['REQUEST_METHOD'];
    $run = false;

    if(isset(self::getInstance()->routeList[$longest_uri][$method])) {
      foreach(self::getInstance()->routeList[$longest_uri][$method] as $func){
        $f = is_callable($func) ? $func : array(new $func[0], $func[1]);

        $param = [];
        for($i = 0; $i < sizeof($varParam[$longest_uri]); $i++){
          $param[$varName[$longest_uri][$i]] = $varParam[$longest_uri][$i];
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
          $param[$varName[$longest_uri][$i]] = $varParam[$longest_uri][$i];
        }
//        print("? "); var_dump($param); print("<br>\n");

        call_user_func_array($f, $param);
        $run = true;
      }
    }

    if(!$run){
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
