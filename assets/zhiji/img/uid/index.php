<?php  
$img_array = glob('*.{gif,jpg,png,jpeg,webp,bmp}', GLOB_BRACE);  
if(count($img_array) == 0) die('没有找到图片文件。MuaOoO ~ ');  
  
// 生成一个唯一的请求ID  
$request_id = uniqid();  
  
header('Content-Type: image/webp');  
header('X-Requested-With-Id: ' . $request_id); // 返回这个ID给客户端  
echo(file_get_contents($img_array[array_rand($img_array)]));  
?>