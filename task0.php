<?php

include_once "vc_utility.php";

//---------------------------------------------------------
$udp_pids = array(0, 0, 0, 0, 0);

$sids = "";
$sids_length = 0;

$sid2network = array("", "", "", "", "", "", "", "", "", "", "", "", "", "");
//---------------------------------------------------------

//---------------------------------------------------------
// check CRC.
// 
if(system_crc_check() == FALSE)
{
	system_initialize();
}
//---------------------------------------------------------

//--------------------------------------------------------------------------------------
// SMART EXPANSION
//
spc_reset();
spc_sync_baud(115200);

$um0 = "";
$temp = "";
for($sid = 1;$sid <= 14;$sid++)
{
  $product_name = spc_scan_board($sid, false);
  
	printf("[sid %d]: %s(%d), ", $sid, $product_name, strlen($product_name));
  
  if($product_name == "PES-2406" || $product_name == "PES-2407")
  {
    echo spc_request_dev($sid, "get uart");
    $sids .= sprintf("%d,", $sid);
  }
  else if($product_name == "ERROR")
    echo "An error occured.";
  else
    echo "not found.";
	echo "\r\n";
  
	$um0 .= int2bin($sid, 1);
  $um0 .= int2bin(strlen($product_name), 1);
  if(strlen($product_name) > 0)
    $um0 .= $product_name;
}
um_write(0, 0, $um0);

if($sids != "")
{
  $sids = substr($sids, 0, strlen($sids) - 1);
  $sids = explode(",", $sids);
  $sids_length = count($sids);
}

for($i = 0;$i < $sids_length;$i++)
{
  $sid = (int)$sids[$i];
  $device = find_serial_device_setting($sid - 1);
  
  if($device[0] != 0)
  {
    if(strlen($device[5]) > 0)
    {
      $sid2network[$sid - 1] = $device[5];
    }
    
    $uart = (string)$device[0] . $device[1] . $device[2] . $device[3] . $device[4];
    spc_request_dev($sid, "set uart $uart");
  }
}
//--------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------
// TCP, UDP
//
for($i = 0; $i < 10;$i++)
{
  $device = find_network_device_setting($i);

  if($device[2] == 1)
  {
    if($device[0] == "T") // TCP
    {
      if($device[3] == 0)
        tcp_server($device[1], $device[6]);
      else if($device[3] == 1)
        tcp_client($device[1], $device[4], $device[5]);
    }
    else if($device[0] == "U") // UDP
    {
      $udp_pid = pid_open("/mmap/udp" . (string)$device[1]);
      $udp_pids[$device[1]] = $udp_pid;
      $ret = pid_bind($udp_pid, "", $device[6]);
      
      pid_ioctl($udp_pid, "set dstaddr %1", $device[4]);
      pid_ioctl($udp_pid, "set dstport %1", (string)$device[5]);
    }
  }
}
//--------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------
$rbuf = "";
$rlen = 0;
while(1)
{
  for($net_idx = 0;$net_idx < 5;$net_idx++)
  {
    if($sn_tcp_ac_pid[$net_idx] != 0)
    {
      $min_txfree = -1;
      if(tcp_state($net_idx) == TCP_CONNECTED)
      {
        for($sid_idx = 0;$sid_idx < $sids_length;$sid_idx++)
        {
          $sid = $sids[$sid_idx];
          if(strpos($sid2network[(int)$sid - 1], "T$net_idx") !== FALSE)
          {
            $txfree = (int)spc_request_dev($sid, "get txfree");
            if($sid_idx == 0 || ($txfree < $min_txfree))
              $min_txfree = $txfree;
          }
        }
      }
      $req_len = (($min_txfree == -1 || $min_txfree > 1024) ? 1024 : $min_txfree);
      
      $rlen = tcp_read($net_idx, $rbuf, $req_len);
      
      if($rlen > 0 && $min_txfree > 0)
      {
        // TCP -> SERIAL
        for($sid_idx = 0;$sid_idx < $sids_length;$sid_idx++)
        {
          $sid = $sids[$sid_idx];
          if(strpos($sid2network[(int)$sid - 1], "T$net_idx") !== FALSE)
          {
            spc_request($sid, 7, $rbuf);
          }
        }
      }
    }
    
    if($udp_pids[$net_idx] != 0)
    {
      $min_txfree = -1;
      for($sid_idx = 0;$sid_idx < $sids_length;$sid_idx++)
      {
        $sid = $sids[$sid_idx];
        if(strpos($sid2network[(int)$sid - 1], "U$net_idx") !== FALSE)
        {
          $txfree = (int)spc_request_dev($sid, "get txfree");
          if($sid_idx == 0 || ($txfree < $min_txfree))
            $min_txfree = $txfree;
        }
      }
      $req_len = (($min_txfree == -1 || $min_txfree > 1024) ? 1024 : $min_txfree);

      $rlen = pid_recvfrom($udp_pids[$net_idx], $rbuf, $req_len);
      
      if($rlen > 0 && $min_txfree > 0)
      {
        // UDP -> SERIAL
        for($sid_idx = 0;$sid_idx < $sids_length;$sid_idx++)
        {
          $sid = $sids[$sid_idx];
          if(strpos($sid2network[(int)$sid - 1], "U$net_idx") !== FALSE)
          {
            spc_request($sid, 7, $rbuf);
          }
        }
      }
    }
  }
  
  for($sid_idx = 0;$sid_idx < $sids_length;$sid_idx++)
  {
    //SERIAL -> NETWORK
    $sid = $sids[$sid_idx];
    $rlen = (int)spc_request_dev($sid, "get rxlen");
    if($rlen > 0)
    {
      $devices = $sid2network[(int)$sid - 1];
    
      $min_txfree = -1;
      for($idx = 0;$idx < 5;$idx++)
      {
        if(strpos($devices, "T$idx") !== FALSE && tcp_state($idx) == TCP_CONNECTED)
        {
          $txfree = tcp_txfree($idx);
          if($idx == 0 || ($txfree < $min_txfree))
            $min_txfree = $txfree;
        }
      }
      for($idx = 0;$idx < 5;$idx++)
      {
        if(strpos($devices, "U$idx") !== FALSE)
        {
          if($min_txfree == -1)
            $min_txfree = $rlen;
          break;
        }
      }
      $req_len = (($min_txfree == -1 || $min_txfree > $rlen) ? $rlen : $min_txfree);
      
      $rbuf = spc_request($sid, 6, "$req_len");
      
      if($rbuf !== false && strlen($rbuf) > 0 && $min_txfree > 0)
      {
        for($idx = 0;$idx < 5;$idx++)
        {
          if(strpos($devices, "T$idx") !== FALSE)
          {
            tcp_write($idx, $rbuf);
          }
          
          if(strpos($devices, "U$idx") !== FALSE)
          {
            pid_sendto($udp_pids[$idx], $rbuf);
          }
        }
      }
    }
  }
}
?>