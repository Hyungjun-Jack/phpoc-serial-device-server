<?php
$referer = explode("/", _SERVER("HTTP_REFERER"));

if(!_SERVER("HTTP_REFERER") || ($referer[3] != "" && $referer[3] != "index.php"))
{
	exit "<h4>ERROR : You were refered to this page from a unauthorised source.</h4></body>\r\n</html>\r\n";
}

set_time_limit(30);

include_once "/lib/sc_envs.php";
include_once "vc_utility.php";

define("IPV6_DHCP_DNS", 0x00000a0a);

$envs = envs_read();

//---------------------------------------------------------------------------
// IPv4
//
$ipv4_type = (int)_POST("ipv4_type"); // 1: DHCP, 0: STATIC
envs_set_net_opt($envs, NET_OPT_DHCP, $ipv4_type);

if($ipv4_type == 1)
{
  $ipv4_dhcp_dns = (int)_POST("ipv4_dhcp_dns"); // 1: 수동입력, 0: 자동
  
  if($ipv4_dhcp_dns == 1)
  {
    envs_set_net_opt($envs, NET_OPT_AUTO_NS, 0);
    $ipv4_dns = _POST("ipv4_dns");
    envs_update($envs, ENV_CODE_IP4, 0x03, inet_pton($ipv4_dns));
  }
  else
  {
    envs_set_net_opt($envs, NET_OPT_AUTO_NS, 1);
  }
}
else if($ipv4_type == 0)
{
  $ipv4_address = inet_pton(_POST("ipv4_address"));
  $ipv4_subnet_mask = inet_pton(_POST("ipv4_subnet_mask"));
  $ipv4_gateway = inet_pton(_POST("ipv4_gateway"));
  $ipv4_dns = inet_pton(_POST("ipv4_dns"));

  $ipv4_gateway = $ipv4_gateway === FALSE ? "" : $ipv4_gateway;
  $ipv4_dns = $ipv4_dns === FALSE ? "" : $ipv4_dns;
  
  envs_update($envs, ENV_CODE_IP4, 0x00, $ipv4_address);
	envs_update($envs, ENV_CODE_IP4, 0x01, $ipv4_subnet_mask);
	envs_update($envs, ENV_CODE_IP4, 0x02, $ipv4_gateway);
	envs_update($envs, ENV_CODE_IP4, 0x03, $ipv4_dns);
}
//---------------------------------------------------------------------------

//---------------------------------------------------------------------------
// IPv6
//
if(ini_get("init_ip6") === "1")
{
  $ipv6_enable = (int)_POST("ipv6_enable"); //1: Enable, 0: Disable
  envs_set_net_opt($envs, NET_OPT_IP6, $ipv6_enable);
  
  if($ipv6_enable == 1)
  {
    $ipv6_type = (int)_POST("ipv6_type"); // 1: STATIC, 0: DHCP
    $ipv6_eui = (int)_POST("ipv6_eui");
    
    envs_set_net_opt($envs, NET_OPT_IP6_GUA, $ipv6_type);
    envs_set_net_opt($envs, NET_OPT_IP6_EUI, $ipv6_eui);
    
    if($ipv6_type == 0)
    {
      $ipv6_dhcp_dns = (int)_POST("ipv6_dhcp_dns"); // 1: 수동입력, 0: 자동
      
      if($ipv6_dhcp_dns == 1)
      {
        envs_set_net_opt($envs, IPV6_DHCP_DNS, 0);
        
        $ipv6_dns = _POST("ipv6_dns");
        envs_update($envs, ENV_CODE_IP6, 0x03, inet_pton($ipv6_dns));
      }
      else
      {
        envs_set_net_opt($envs, IPV6_DHCP_DNS, 1);
      }
    }
    else if($ipv6_type == 1)
    {
      $ipv6_address = _POST("ipv6_address");
      $ipv6_prefix = (int)_POST("ipv6_prefix");
      $ipv6_gateway = inet_pton(_POST("ipv6_gateway"));
      $ipv6_dns = inet_pton(_POST("ipv6_dns"));
  
      $ipv6_gateway = $ipv6_gateway === FALSE ? "" : $ipv6_gateway;
      $ipv6_dns = $ipv6_dns === FALSE ? "" : $ipv6_dns;
  
      envs_update($envs, ENV_CODE_IP6, 0x00, inet_pton($ipv6_address) . int2bin($ipv6_prefix, 2));
      envs_update($envs, ENV_CODE_IP6, 0x02, $ipv6_gateway);
      envs_update($envs, ENV_CODE_IP6, 0x03, $ipv6_dns);
    }
  }
}
//---------------------------------------------------------------------------

//---------------------------------------------------------------------------
// WLAN
//
if(ini_get("init_net1") === "1")
{
  $wlan_enable = (int)_POST("wlan_enable"); //1: Enable, 0: Disable
  envs_set_net_opt($envs, NET_OPT_WLAN, $wlan_enable);
  
  if($wlan_enable == 1)
  {
    $wlan_type = (int)_POST("wlan_type"); //0: Ad-Hoc, 1: Infrastructure, 2: SoftAP
    envs_set_net_opt($envs, NET_OPT_TSF, $wlan_type);
    
    if($wlan_type == 0 || $wlan_type == 2)
    {
      $wlan_channel = (int)_POST("wlan_channel");
      envs_set_net_opt($envs, NET_OPT_CH, $wlan_channel);
    }
    
    $wlan_ssid = bin2hex(_POST("wlan_ssid"));
    $wlan_ssid_raw = _POST("wlan_ssid_raw");
    $wlan_shared_key = _POST("wlan_shared_key");

    if($wlan_ssid != $wlan_ssid_raw)
      $wlan_ssid = hex2bin($wlan_ssid);
    else
      $wlan_ssid = hex2bin($wlan_ssid_raw);
    
    if($wlan_ssid != rtrim(envs_find($envs, ENV_CODE_WLAN, 0x01)))
      $comp_psk = true;
    else if($wlan_shared_key != rtrim(envs_find($envs, ENV_CODE_WLAN, 0x08)))
      $comp_psk = true;
    else
      $comp_psk = false;

    if($comp_psk)
    {
      // psk generation take 0.5 second on STM32F407 168MHz
      $wpa_psk = hash_pbkdf2("sha1", $wlan_shared_key, $wlan_ssid, 4096, 32, true);
      envs_update($envs, ENV_CODE_WLAN, 0x09, $wpa_psk);
    }

    envs_update($envs, ENV_CODE_WLAN, 0x01, $wlan_ssid);	
    envs_update($envs, ENV_CODE_WLAN, 0x08, $wlan_shared_key);
    
    $wlan_phy_mode = (int)_POST("wlan_phy_mode");
    $wlan_short_preamble = (int)_POST("wlan_short_preamble");
    $wlan_short_slot = (int)_POST("wlan_short_slot");
    $wlan_cts_protection = (int)_POST("wlan_cts_protection");
    
    envs_set_net_opt($envs, NET_OPT_PHY, $wlan_phy_mode);
    if($wlan_phy_mode == 2)
    {
      envs_set_net_opt($envs, NET_OPT_SHORT_PRE, $wlan_short_preamble);
    }
    else if($wlan_phy_mode == 3)
    {
      envs_set_net_opt($envs, NET_OPT_SHORT_PRE, $wlan_short_preamble);
      envs_set_net_opt($envs, NET_OPT_SHORT_SLOT, $wlan_short_slot);
      envs_set_net_opt($envs, NET_OPT_CTS_PROT, $wlan_cts_protection);
    }
  }
}
//---------------------------------------------------------------------------

$wkey = envs_get_wkey(); 
envs_write($envs, $wkey);
?>

<script>
parent.network_setup_finish();
</script>