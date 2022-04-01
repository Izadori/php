<?php

require_once __DIR__."/traits.php";

class SimpleController
{
  // View管理用トレイトの追加
  use ViewManager;

  public function __construct()
  {

  }

  public function load(string|array $file, ?array $parameter = null): void
  {
    $this->loadMain($file, $parameter);
  }

  public function redirect(string $uri, ?array $parameter = null): void
  {
    $this->redirectMain($uri, $parameter);
  }
}
?>
