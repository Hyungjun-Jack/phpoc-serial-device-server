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
// SMART EXPANSION
for($i = 0;$i < 14;$i++)
{
  $setting = find_setting(CODE_SERIAL_DEVICE, $i);
  
  $baudrate = (int)_POST("sid" . (string)($i + 1) . "_baudrate");
  $setting = substr_replace($setting, int2bin($baudrate, 4), 0, 4); // baudrate
  $setting = substr_replace($setting, _POST("sid" . (string)($i + 1) . "_parity"), 4, 1); // parity
  $setting = substr_replace($setting, _POST("sid" . (string)($i + 1) . "_databit"), 5, 1); // data bit
  $setting = substr_replace($setting, _POST("sid" . (string)($i + 1) . "_stopbit"), 6, 1); // stop bit
  $setting = substr_replace($setting, _POST("sid" . (string)($i + 1) . "_flowctrl"), 7, 1); // flow control
  
  $device_map = "";
  for($n = 0;$n < 5;$n++)
  {
    $device_name = "T$n";
    $element_name = "sid" . (string)($i + 1) . "_" . $device_name;
    iF(_POST($element_name) == "1")
    {
      $device_map .= "$device_name,";
    }    
  }
  
  for($n = 0;$n < 5;$n++)
  {
    $device_name = "U$n";
    $element_name = "sid" . (string)($i + 1) . "_" . $device_name;
    iF(_POST($element_name) == "1")
    {
      $device_map .= "$device_name,";
    }
  }
    
  if(strlen($device_map) > 0)
    $device_map = substr($device_map, 0, strlen($device_map) - 1);
  
  if(strlen($device_map) < 30)
  {
    $device_map .= str_repeat("\x00", 30 - strlen($device_map));
  }
  $setting = substr_replace($setting, $device_map, 8, 30);
  
  update_setting($settings, CODE_SERIAL_DEVICE, $i, $setting);
}
//---------------------------------------------------------------------------

$crc = (int)system("crc 16 %1 0000 a001 lsb", $settings . PROJECT_ID);
$settings = int2bin($crc, 2) . $settings;

$wkey = system("nvm wkey envu");
system("nvm write envu $wkey %1", $settings);

settings_dump();
//---------------------------------------------------------------------------
?>

<script>
parent.smart_expansion_setup_finish();
</script>