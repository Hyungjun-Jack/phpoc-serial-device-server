<?php
$referer = explode("/", _SERVER("HTTP_REFERER"));

if(!_SERVER("HTTP_REFERER") || ($referer[3] != "" && $referer[3] != "index.php"))
{
	exit "<h4>ERROR : You were refered to this page from a unauthorised source.</h4></body>\r\n</html>\r\n";
}

set_time_limit(30);

include_once "vc_utility.php";

$settings = "";
get_settings($settings);

//---------------------------------------------------------------------------
// TCP, UDP
//
for($i = 0;$i < 10;$i++)
{
  $setting = find_setting(CODE_NETWORK_DEVICE, $i);
 
  $device_id = ($i >= 5 ? "U" : "T") . (string)($i >= 5 ? $i - 5 : $i);
  $enabled = (int)_POST($device_id . "_enable");
  $setting = substr_replace($setting, int2bin($enabled, 1), 2, 1);
  if($enabled == 1)
  {
    $protocol = (int)_POST($device_id . "_protocol");
    $setting = substr_replace($setting, int2bin($protocol, 1), 3, 1);
    
    if($protocol == 1 || $protocol == 2)
    {
      // 통신할 주소.
      $peer_address = trim(_POST($device_id . "_peer_address"));
      $copy_len = strlen($peer_address) > 64 ? 64 : strlen($peer_address);
      if($copy_len < 64)
        $peer_address .= str_repeat("\x00", 64 - $copy_len);
      $setting = substr_replace($setting, $peer_address, 4, 64);
      
      // 통신할 포트
      $peer_port = (int)_POST($device_id . "_peer_port");
      $setting = substr_replace($setting, int2bin($peer_port, 2), 68, 2);
    }
    
    if($protocol == 0 || $protocol == 2)
    {
      // 제품 로컬포트
      $local_port = (int)_POST($device_id . "_local_port");
      $setting = substr_replace($setting, int2bin($local_port, 2), 70, 2);
    }
    
    if($protocol == 0 || $protocol == 1)
    {
      $ssl = (int)_POST($device_id . "_ssl");
      $setting = substr_replace($setting, int2bin($ssl, 1), 72, 1);
      //$ssh = (int)_POST($device_id . "_ssh");
      //$setting = substr_replace($setting, int2bin($ssh, 1), 73, 1);
    }
  }
  
  update_setting($settings, CODE_NETWORK_DEVICE, $i, $setting);
}

//---------------------------------------------------------------------------
// ADMIN PWD
//
$web_password = _POST("admin_pwd");

$setting = find_setting(CODE_ADMIN, 0x00);

$copy_len = strlen($web_password) > 8 ? 8 : strlen($web_password);
if($copy_len < 8)
  $web_password .= str_repeat("\x00", 8 - $copy_len);
$setting = substr_replace($setting, $web_password, 0, 8);

update_setting($settings, CODE_ADMIN, 0, $setting);
//---------------------------------------------------------------------------

$crc = (int)system("crc 16 %1 0000 a001 lsb", $settings . PROJECT_ID);
$settings = int2bin($crc, 2) . $settings;

$wkey = system("nvm wkey envu");
system("nvm write envu $wkey %1", $settings);
//---------------------------------------------------------------------------
?>

<script>
parent.tcp_udp_setup_finish();
</script>