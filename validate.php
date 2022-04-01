<?php

// htmlspecialchars()の簡略化関数

function h(?string $str) : string
{
  return is_null($str) ? "" : htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// CSRF対策のバリデーション
function checkCsrf() : bool
{
    if(isset($_POST['csrf']) && isset($_SESSION['csrf'])){
      return ($_POST['csrf'] === $_SESSION['csrf']);
    }

    $headers = getallheaders();

    if(isset($headers['X-CSRF-TOKEN']) && isset($_SESSION['csrf'])){
      return ($headers['X-CSRF-TOKEN'] === $_SESSION['csrf']);
    }

    return false;
}

// 入力フォームのバリデーション(new)
/**
 * function validate(array &$data, array $valid): array
 *
 * 「変数名=>フラグ」で処理する。フラグはarrayで与える
 *
 * type: 次のいずれか
 *   bool, int, float, string, array, email, url, domain, ip, mac
 * option: required or nullable
 * maxLength: 数値
 * minLength: 数値
 * default: 初期値
 *
 * 戻り値: 検証結果を格納した連想配列
 *
 * 対象の変数名が存在しないときの動作
 * required：falseをセット
 * nullable：nullをセット
 * その他：初期値をセットする。無ければfalsyな値をセットする
 */
function validate(array $data, array $valid) : array
{
  $result = array();
  $error = array();

  foreach($valid as $variable => $flags){
    if(!isset($data[$variable])
      || (is_string($data[$variable]) && trim($data[$variable]) === "")
      || (is_array($data[$variable])) && sizeof($data[$variable]) == 0){
      if(isset($flags['option'])){
        if($flags['option'] === 'required'){
          $error[$variable]['required'] = true;
          $result[$variable] = false;
          continue;
        }
        elseif($flags['option'] === 'nullable'){
          $result[$variable] = null;
          continue;
        }
      }

      if(isset($flags['type'])){
        switch($flags['type']){
        case "bool":
          $result[$variable] = (isset($flags['default'])) ? (bool)$flags['default'] : false;
          break;
        case "int":
          $result[$variable] = (isset($flags['default'])) ? (int)$flags['default'] : 0;
          break;
        case "float":
          $result[$variable] = (isset($flags['default'])) ? (float)$flags['default'] : 0;
          break;
        case "array":
          $result[$variable] = (isset($flags['default'])) ? $flags['default'] : array();
          break;
        default:
          $result[$variable] = (isset($flags['default'])) ? (string)trim($flags['default']) : "";
        }
      }
      else{
        $result[$variable] = (isset($flags['default'])) ? (string)trim($flags['default']) : "";
      }
    }
    elseif(isset($flags['type'])){
      switch($flags['type']){
      case "bool":
        $result[$variable] = (bool)filter_var($data[$variable], FILTER_VALIDATE_BOOLEAN);
        break;
      case "int":
        $result[$variable] = (int)filter_var($data[$variable], FILTER_VALIDATE_INT);
        break;
      case "float":
        $result[$variable] = (float)filter_var($data[$variable], FILTER_VALIDATE_FLOAT);
        break;
      case "array":
        if(is_array($data[$variable])){
          $result[$variable] = array_filter($data[$variable], 'is_string');
        }
        else{
          $result[$variable] = false;
        }
        break;
      case "domain":
        $result[$variable] = filter_var(trim($data[$variable]), FILTER_VALIDATE_DOMAIN);
        break;
      case "url":
        $result[$variable] = filter_var(trim($data[$variable]), FILTER_VALIDATE_URL);
        break;
      case "email":
        $result[$variable] = filter_var(trim($data[$variable]), FILTER_VALIDATE_EMAIL);
        break;
      case "ip":
        $result[$variable] = filter_var(trim($data[$variable]), FILTER_VALIDATE_IP);
        break;
      case "mac":
        $result[$variable] = filter_var(trim($data[$variable]), FILTER_VALIDATE_MAC);
        break;
      default:
        if(is_string($data[$variable])){
          $result[$variable] = trim($data[$variable]);
        }
        else{
          $result[$variable] = false;
        }
        break;
      }

      switch($flags['type']){
      case "domain":
      case "url":
      case "email":
        $flags['maxLength'] = (isset($flags['maxLength']) ? $flags['maxLength'] : 255);
        // fall through
      case "string":
        $flags['minLength'] = (isset($flags['minLength']) ? $flags['minLength'] : 0);

        if(isset($flags['maxLength']) && strlen($result[$variable]) > (int)$flags['maxLength']){
          $error[$variable]['maxLength'] = true;
        }
        if(strlen($result[$variable]) < (int)$flags['minLength'] || strlen($result[$variable]) == 0){
          $error[$variable]['minLength'] = true;
        }
        break;
      }

      if(!$result[$variable]){
        $error[$variable]['type'] = true;
      }
    }
    else{
      // 文字列として処理する
        $result[$variable] = trim($data[$variable]);
    }
  }

  return array($result, $error);
}

// $errorが空でないか
function anyError(array $error): bool
{
  return !empty($error);
}
?>
