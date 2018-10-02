<?php

include_once "/lib/sd_spc.php";
include_once "/lib/sd_340.php";
include_once "/lib/sn_tcp_ac.php";

define("PROJECT_ID", "PROJECT_2018_1");
define("DEFAULT_ADMIN_PWD", "admin\x00\x00\x00");

define("ENVU_LENGTH", 1520);

define("CODE_NETWORK_DEVICE", 0x01);
define("CODE_SERIAL_DEVICE", 0x02);
define("CODE_ADMIN", 0x04);

function spc_scan_board($sid, $verbose = false)
{
  $product = "";
  $pid = sd_spc_pid_open_nodie("/mmap/spc0", "spc_scan");
  $rbuf = "";

  pid_ioctl($pid, "sets $sid crc 1");

  pid_write($pid, "get uid");
  pid_ioctl($pid, "spc $sid 0");

  while(pid_ioctl($pid, "get state"))
    ;

  usleep(20000); // wait response from duplicated sid slave

  if(pid_ioctl($pid, "get error"))
  {
    if(pid_ioctl($pid, "get error sto"))
    {
      error_log(sprintf("sid%d: slave timeout.\r\n", $sid));
    }
    else
    {
      error_log(sprintf("sid%02d: ", $sid));

      if(pid_ioctl($pid, "get error mbit"))
        error_log("Mbit error");

      if(pid_ioctl($pid, "get error csum"))
        error_log("csum mismatch");

      if(pid_ioctl($pid, "get error urg"))
        error_log("Ubit error ");

      if(pid_ioctl($pid, "get error sid"))
        error_log("sid mismatch ");

      if(pid_ioctl($pid, "get error addr"))
        error_log("address mismatch");

      error_log("\r\n");
      
      $product = "ERROR";
    }
  }
  else
  {
    pid_read($pid, $rbuf);

    $resp = explode(",", $rbuf);

    if(count($resp) == 2)
    {
      $uid_hex = $resp[1];

      if(strlen($uid_hex) != 24)
        $uid_hex = "";
    }
    else
      $uid_hex = "";

    if($uid_hex)
    {
      $uid_bin = spc_decrypt_uid($uid_hex);

      if($uid_bin)
      {
        pid_write($pid, "get did");
        pid_ioctl($pid, "spc $sid 0");

        while(pid_ioctl($pid, "get state"))
          ;

        pid_read($pid, $rbuf);

        if($verbose)
          error_log(sprintf("%s\r\n", $rbuf));
        
        $resp = explode(",", $rbuf);

        if($verbose)
          error_log(sprintf("sid%d: %s %12x\r\n", $sid, $resp[2], bin2int($uid_bin, 5, 6, true)));

        $product = $resp[2];
      }
      else
      {
        if($verbose)
          error_log(sprintf("sid%d: invalid uid\r\n", $sid));
        $product = "ERROR";
      }
    }
    else
    {
      if($verbose)
        error_log(sprintf("sid%d: invalid 'get uid' response\r\n", $sid));
      $product = "ERROR";
    }
  }

  pid_ioctl($pid, "sets $sid crc 0");

  pid_close($pid);
  
  return $product;
}

function system_crc_check()
{
  error_log("checking crc of settings.\r\n");
  
  $envu_pid = pid_open("/mmap/envu");
  $crc = 0;
  $result = TRUE;
  
  pid_read($envu_pid, $crc, 2);
  
  if($crc == 0)
  {
    $result = FALSE;  
  }
  else
  {
    $rbuf = "";
    $read = pid_read($envu_pid, $rbuf, ENVU_LENGTH - 2);
    $rbuf .= PROJECT_ID; // 환경변수내용에 PROJECT_ID를 더해서 CRC를 계산한다.
    $crc16 = (int)system("crc 16 %1 0000 a001 lsb", $rbuf);
    if($crc != $crc16)
    {
      error_log("crc is not match.\r\n");
      $result = FALSE;
    }
  }
  pid_close($envu_pid);
  
  return $result;
}

function make_setting_block($code, $id, $setting_data)
{
  $pad_size = (strlen($setting_data) + 2/*crc*/) % 4;
  if($pad_size != 0)
    $pad_size = 4 - $pad_size;
  $block_length = 4 + strlen($setting_data) + $pad_size + 2;
  $setting_block = int2bin($code, 1) . int2bin($id, 1) . int2bin($block_length, 2) . $setting_data;
  
  if($pad_size > 0)
    $setting_block .= str_repeat("\x00", $pad_size);
    
  $crc = (int)system("crc 16 %1", $setting_block);
  $setting_block .= int2bin($crc, 2);
  
  return $setting_block;
}

function settings_write($envu, $wkey)
{
  system("nvm write envu $wkey %1", $envu);
}

function system_initialize()
{
  $settings = "";
  for($i = 0;$i < 5;$i++)
  {
    // device_type
    $setting_block = "T"; // TCP
    // device_id
    $setting_block .= int2bin($i, 1); // 0 ~
    // enabled
    $setting_block .= int2bin(0, 1);
    // protocol
    $setting_block .= int2bin(0, 1);
    // peer_address
    $setting_block .= str_repeat("\x00", 64);
    //peer_port
    $setting_block .= int2bin(1470 + $i, 2);
    //local_port
    $setting_block .= int2bin(1470 + $i, 2);
    //ssl
    $setting_block .= int2bin(0, 1);
    //ssh
    //$setting_block .= int2bin(0, 1);
    
    $settings .= make_setting_block(CODE_NETWORK_DEVICE, $i, $setting_block);
  }
  
  for($i = 0;$i < 5;$i++)
  {
    // device_type
    $setting_block = "U"; // UDP
    // device_id
    $setting_block .= int2bin($i, 1); // 0 ~
    // enabled
    $setting_block .= int2bin(0, 1);
    // protocol
    $setting_block .= int2bin(2, 1);
    // peer_address
    $setting_block .= str_repeat("\x00", 64);
    //peer_port
    $setting_block .= int2bin(1470 + $i, 2);
    //local_port
    $setting_block .= int2bin(1470 + $i, 2);
    //ssl
    $setting_block .= int2bin(0, 1);
    //ssh
    $setting_block .= int2bin(0, 1);
    
    $settings .= make_setting_block(CODE_NETWORK_DEVICE, $i + 5, $setting_block);
  }
  
  for($i = 0;$i < 14;$i++)
  {
    $setting_block = int2bin(0, 4); // baudrate
    $setting_block .= "\x00"; // parity bit
    $setting_block .= "\x00"; // data bit
    $setting_block .= "\x00"; // stop bit
    $setting_block .= "\x00"; // flow control
    $setting_block .= str_repeat("\x00", 30); // device map.
    
    $settings .= make_setting_block(CODE_SERIAL_DEVICE, $i, $setting_block);
  }
  
  /*for($i = 0;$i < 14;$i++)
  {
    $setting_block = str_repeat("\x00", 20);
    $settings .= make_setting_block(CODE_DEVICE_MAP, $i, $setting_block);
  }*/
  
  //----------------------------------------------------------------------
  // ADMIN...
  $settings .= make_setting_block(CODE_ADMIN, 0x00, DEFAULT_ADMIN_PWD);
  //----------------------------------------------------------------------
  
  // ENV_CODE_EOE
  $setting_block = "";
  $settings .= make_setting_block(0xff, 0xff, $setting_block);
  
  $pad_length = ENVU_LENGTH - strlen($settings) - 2;
  if($pad_length > 0)
    $settings .= str_repeat("\x00", $pad_length);
    
  $crc = (int)system("crc 16 %1 0000 a001 lsb", $settings . PROJECT_ID);
  
  $settings = int2bin($crc, 2) . $settings;
  
  $wkey = system("nvm wkey envu");
  system("nvm write envu $wkey %1", $settings);

  error_log("settings has been initialized\r\n");
}

function settings_dump()
{
  error_log("/mmap/envu\r\n");

  $env = "";
  $code = 0;
  $id = 0;

  $envu_pid = pid_open("/mmap/envu");

  pid_lseek($envu_pid, 2, SEEK_CUR);
  
  $offset = 0;

  $settings = "";
  $read = pid_read($envu_pid, $settings, ENVU_LENGTH - 2);
  
  while($offset < $read)
  {
    $code = bin2int($settings, $offset, 1);
    $id = bin2int($settings, $offset + 1, 1);
    $len = bin2int($settings, $offset + 2, 2);
    $setting_block = substr($settings, $offset, $len);
    
    $sub_block = substr($setting_block, 0, $len - 2);
    $crc2 = (int)system("crc 16 %1", $sub_block);
    
    $crc = substr($setting_block, $len - 2, 2);
    if($crc2 != bin2int($crc, 0, 2))
    {
      error_log("crc fail.....\r\n");
      hexdump($setting_block);
    }
  
    error_log(sprintf("code - %02x, id - %02x, len - $len\r\n", $code, $id));
    
    $offset += $len;
    
    if($code == 0xff)
      break;
  }

  pid_close($envu_pid);

  error_log( "\r\n");
}

function find_setting($req_code, $req_id)
{
  $envu_pid = pid_open("/mmap/envu");
  pid_lseek($envu_pid, 2, SEEK_CUR);
  
  //$offset_end = strlen($settings);
  $offset = 2;
  $rbuf = "";
  while($offset < ENVU_LENGTH)
  {
    pid_read($envu_pid, $rbuf, 1); $code = bin2int($rbuf, 0, 1);
    pid_read($envu_pid, $rbuf, 1); $id = bin2int($rbuf, 0, 1);
    pid_read($envu_pid, $rbuf, 2); $len = bin2int($rbuf, 0, 2);

    if(($code == $req_code) && ($id == $req_id))
    {
      $block = "";
      pid_lseek($envu_pid, $offset, SEEK_SET);
      pid_read($envu_pid, $block, $len - 2); // block
      pid_read($envu_pid, $rbuf, 2); $crc = bin2int($rbuf, 0, 2); // CRC

      if($crc != (int)system("crc 16 %1", $block))
        die("setting crc error\r\n");
      
      pid_close($envu_pid);
      return substr($block, 4, $len - 4 - 2);
    }
    else
    {
      pid_lseek($envu_pid, $len - 4, SEEK_CUR);
    }

    if($code == 0xff)
      break;

    $offset += $len;
  }
  
  pid_close($envu_pid);
  return "";
}

function update_setting(&$settings, $req_code, $req_id, $setting_data)
{
  $offset_end = strlen($settings);
  $offset = 0;
  
  while($offset < $offset_end)
  {
    $envu_head = substr($settings, $offset, 4);
    
    $envu_code = bin2int($envu_head, 0, 1);
    $envu_id   = bin2int($envu_head, 1, 1);
    $envu_len  = bin2int($envu_head, 2, 2);
    
    if(($envu_code == $req_code) && ($envu_id == $req_id))
    {
      if(strlen($setting_data) > ($envu_len - 4 - 2))
        exit("envu_update: The length of setting_data is not match of ENVU.\r\n");
      
      $pad_size = $envu_len - (4 + strlen($setting_data) + 2);
      
      if($pad_size > 0)
				$setting_data .= str_repeat("\x00", $pad_size);
      
      $setting_data = $envu_head . $setting_data;
      $envu_crc = (int)system("crc 16 %1", $setting_data);
      $setting_data .= int2bin($envu_crc, 2);
      
      $settings = substr_replace($settings, $setting_data, $offset, $envu_len);
      
      return $envu_len;
    }
    
    if($envu_code == 0xff)
      break;
    
    $offset += $envu_len;
  }
}

function get_settings(&$settings)
{
  $envu_pid = pid_open("/mmap/envu");

  pid_lseek($envu_pid, 2, SEEK_CUR);

  $read = pid_read($envu_pid, $settings, ENVU_LENGTH - 2);
  
  pid_close($envu_pid);
}

function find_network_device_setting($id)
{
  $device = array("", 0, 0, 0, "", 0, 0, 0);
  
  $setting = find_setting(CODE_NETWORK_DEVICE, $id);
  if($setting != "")
  {
    $device[0] = substr($setting, 0, 1); // device type
    $device[1] = bin2int($setting, 1, 1); // device id
    $device[2] = bin2int($setting, 2, 1); // enabled.
    $device[3] = bin2int($setting, 3, 1); // protocol
    $device[4] = rtrim(substr($setting, 4, 64), "\x00"); // 통신할 주소.
    $device[5] = bin2int($setting, 68, 2); // 통신할 포트
    $device[6] = bin2int($setting, 70, 2); // 제품 로컬포트
    //$device[7] = bin2int($setting, 72, 1); // ssl
    //$device[8] = bin2int($setting, 73, 1); // ssh
  }
  
  return $device;
}

function find_serial_device_setting($id)
{
  $device = array(0, "", "", "", "", "");
    
  $setting = find_setting(CODE_SERIAL_DEVICE, $id);
  if($setting != "")
  {
    $device[0] = bin2int($setting, 0, 4); // baudrate
    $device[1] = rtrim(substr($setting, 4, 1), "\x00"); // parity bit
    $device[2] = rtrim(substr($setting, 5, 1), "\x00"); // data bit
    $device[3] = rtrim(substr($setting, 6, 1), "\x00"); // stop bit
    $device[4] = rtrim(substr($setting, 7, 1), "\x00"); // flow control
    $device[5] = rtrim(substr($setting, 8, 30), "\x00"); // device map
  }
  
  return $device;
}

function find_device_map($id)
{
  $device_map = "";
    
  $setting = find_setting(CODE_SERIAL_DEVICE, $id);
  if($setting != "")
  {
    $device_map = rtrim(substr($setting, 8, 30), "\x00"); // device map
  }
  
  return $device_map;
}

function find_admin_password()
{
  $pwd = "";
  
  $setting = find_setting(CODE_ADMIN, 0);
  if($setting != "")
  {
    $pwd = rtrim($setting, "\x00");
  }
  
  return $pwd;
}

function send_401()
{
  header('HTTP/1.0 401 Unauthorized');
  header('WWW-Authenticate: Basic realm="PHPoC Authorization"');
  header('Cache-Control: no-cache, no-store, max-age=1, must-revalidate');

  echo "<html>\r\n" ,
    "<head><title>PHPoC Authorization</title>\r\n" ,
    "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\r\n" ,
    "<style>* {box-sizing: border-box}body {font-family: \"Lato\", sans-serif;}</style></head>" ,
    "<body>\r\n" ,
    "<h3>비밀번호를 입력하세요.</h3>\r\n" ,
    "<h4>기본 사용자 이름은 admin입니다.</h4>\r\n" ,
    "</body></html>\r\n";
}

?>