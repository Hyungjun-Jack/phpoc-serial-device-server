<?php
set_time_limit(30);

include_once "/lib/sc_envs.php";
include_once "/lib/sd_340.php";
include_once "/lib/sd_spc.php";
include_once "vc_utility.php";

$web_password = find_admin_password();

if($web_password != "")
{
  $auth = _SERVER("HTTP_AUTHORIZATION");

  if($auth)
  {
    $input_password = str_replace("Basic ", "", $auth);
    $input_password_dec = explode(":", system("base64 dec %1", $input_password)); 

    if($input_password_dec[1] != $web_password)
    {
      send_401();
      return;
    }
  }
  else
  {
    send_401();
    return;
  }
}

define("IPV6_DHCP_DNS", 0x00000a0a);

$envs = envs_read();

//================================================
$wlan_ssid_env = envs_find($envs, ENV_CODE_WLAN, 0x01);
$wlan_ssid_pos = strpos($wlan_ssid_env, int2bin(0x00, 1));


$wlan_shared_key_env = envs_find($envs, ENV_CODE_WLAN, 0x08);	
$wlan_shared_key_pos = strpos($wlan_shared_key_env, int2bin(0x00, 1));
//================================================

//================================================
// WLAN STATUS.
$wlan_status = "";
$device_ip_address = "";
$device_6_addr = "";
$emac_id = "";

if(ini_get("init_net1") == "1")
{
  $pid_net1 = pid_open("/mmap/net1", O_NODIE);
  if($pid_net1 != -EBUSY && $pid_net1 != -ENOENT)
  {
    $wlan_status = pid_ioctl($pid_net1, "get mode");
    $emac_id = pid_ioctl($pid_net1, "get hwaddr");
    $emac_id = str_replace(":", "", $emac_id);
    $emac_id = substr($emac_id, 6);
    
    if($wlan_status != "")
    {
      $device_ip_address = pid_ioctl($pid_net1, "get ipaddr");
      $device_6_addr = pid_ioctl($pid_net1, "get ipaddr6");
    }
    pid_close($pid_net1);
  }
}

if($wlan_status == "")
{
  $pid_net0 = pid_open("/mmap/net0", O_NODIE);
  if($pid_net0 != -EBUSY && $pid_net0 != -ENOENT)
  {
    $device_ip_address = pid_ioctl($pid_net0, "get ipaddr");
    $device_6_addr = pid_ioctl($pid_net0, "get ipaddr6");
    pid_close($pid_net0);
  }
}
//================================================

//================================================
// 
//================================================
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PROJECT 2018-1</title>
<link rel="stylesheet" type="text/css" href="style.css">
<script language=javascript>

var headerMenu;
var headerOffsetTop;

window.addEventListener('load', function(){ 
    headerMenu = document.getElementById("Header__Menu");
    headerOffsetTop = headerMenu.offsetTop;
    window.onscroll =  function () {onScroll()};

  //------------------------------------------

  if(document.body.getAttribute('data-ipv4-type') == 0)
  {
    document.getElementById("ipv4_static").checked = true;
  }
  else
  {
    document.getElementById("ipv4_dhcp").checked = true;
  }
  document.getElementById("ipv4_address").value = document.body.getAttribute('data-ipv4-address');
  document.getElementById("ipv4_subnet_mask").value = document.body.getAttribute('data-ipv4-subnet-mask');
  document.getElementById("ipv4_gateway").value = document.body.getAttribute('data-ipv4-gateway');
  document.getElementById("ipv4_dns").value = document.body.getAttribute('data-ipv4-dns');
  
  if(document.body.getAttribute('data-ipv6-enable') == 1)
    document.getElementById("ipv6_enable").checked = true;
  else
    document.getElementById("ipv6_disable").checked = true;
  
  if(document.body.getAttribute('data-ipv6-type') == 1)
    document.getElementById("ipv6_static").checked = true;
  else
    document.getElementById("ipv6_dhcp").checked = true;
  
  document.getElementById("ipv6_eui").value = document.body.getAttribute('data-ipv6-eui');
  document.getElementById("ipv6_address").value = document.body.getAttribute('data-ipv6-address');
  document.getElementById("ipv6_prefix").value = document.body.getAttribute('data-ipv6-prefix');
  document.getElementById("ipv6_gateway").value = document.body.getAttribute('data-ipv6-gateway');
  document.getElementById("ipv6_dns").value = document.body.getAttribute('data-ipv6-dns');
  
  if(document.body.getAttribute('data-wlan-enable') == 1)
    document.getElementById("wlan_enable").checked = true;
  else
    document.getElementById("wlan_disable").checked = true;
  
  switch(document.body.getAttribute('data-wlan-type'))
  {
    case "0":
      document.getElementById("wlan_adhoc").checked = true;
      break;
    case "1":
      document.getElementById("wlan_infrastructure").checked = true;
      break;
    case "2":
      document.getElementById("wlan_soft_ap").checked = true;
      break;
  }
  
  document.getElementById("wlan_channel").value = document.body.getAttribute('data-wlan-channel');
  document.getElementById("wlan_ssid").value = document.body.getAttribute('data-wlan-ssid');
  document.getElementById("wlan_ssid_raw").value = document.body.getAttribute('data-wlan-ssid-raw');
  document.getElementById("wlan_shared_key").value = document.body.getAttribute('data-wlan-shared-key');
  
  switch(document.body.getAttribute('data-wlan-phy-mode'))
  {
    case "0":
      document.getElementById("phy_auto").checked = true;
      break;
    case "1":
      document.getElementById("phy_802_11").checked = true;
      break;
    case "2":
      document.getElementById("phy_802_11b").checked = true;
      break;
    case "3":
      document.getElementById("phy_802_11bg").checked = true;
      break;
  }
  
  if(document.body.getAttribute('data-wlan-short-preamble') == 1)
    document.getElementById("wlan_short_preamble").checked = true;
  
  if(document.body.getAttribute('data-wlan-short-slot') == 1)
    document.getElementById("wlan_short_slot").checked = true;
  
  if(document.body.getAttribute('data-wlan-cts-protection') == 1)
    document.getElementById("wlan_cts_protection").checked = true;
  //------------------------------------------
  for(sid = 1;sid <= 14;sid++)
  {
    var uart;
    var product;
    uart = document.body.getAttribute("data-sid" + sid + "-uart");
    
    if((uart = document.body.getAttribute("data-sid" + sid + "-uart")) != null)
    {
      product = document.body.getAttribute("data-sid" + sid + "-product");
      
      // baudrate
      var id = "sid" + sid + "_";
      document.getElementById(id + "baudrate").value = parseInt(uart);
      
      var temp = uart.substring((parseInt(uart) + "").length);      
      console.log(temp);
      // parity
      var elements = document.getElementsByName(id + "parity")
      switch(temp.substring(0, 1))
      {
        case "N": // none
          elements[0].checked = true;
          break;
        case "E": // even
          elements[1].checked = true;
          break;
        case "O": // odd
          elements[2].checked = true;
          break;
        case "M": // mark
          elements[3].checked = true;
          break;
        case "S": // space
          elements[4].checked = true;
          break;
      }
      
      // data bit
      var databit = temp.substring(1, 2);
      console.log(databit);
      elements = document.getElementsByName(id + "databit")
      switch(databit)
      {
        case "7":
          elements[0].checked = true;
          break;
        case "8":
          elements[1].checked = true;
          break;
      }
      
      // stop bit
      var stopbit = temp.substring(2, 3);
      console.log(stopbit);
      elements = document.getElementsByName(id + "stopbit")
      switch(stopbit)
      {
        case "1":
          elements[0].checked = true;
          break;
        case "2":
          elements[1].checked = true;
          break;
      }
      
      // flow control
      var flowctrl = temp.substring(3, 4);
      console.log(flowctrl);
      elements = document.getElementsByName(id + "flowctrl")
      if(product == "PES-2406")
      {
        switch(flowctrl)
        {
          case "N": // none
            elements[0].checked = true;
            break;
          case "H": // rts/cts
            elements[1].checked = true;
            break;
          case "S": // xon/xoff
            elements[2].checked = true;
            break;
        }
      }
      else if(product == "PES-2407")
      {
        switch(flowctrl)
        {
          case "N": // TxDE 사용 안 함
            elements[0].checked = true;
            break;
          case "T": // TxDE 사용
            elements[1].checked = true;
            break;
        }
      }
    }
  }
  
  document.getElementById("button_save").addEventListener("click", onClickSubmit);
  document.getElementById("button_restart").addEventListener("click", onClickRestart);
  
  onClickIpv4Type();
  onClickIpv6Enable();
  onClickWlanEnable();
  onClickWlanPhyMode();
  
  setInternetState();
  setWlanState();
  setAdminSettings();

  initTcpUdp();
  
  document.getElementById("Home__Menu__Open").click();
  document.getElementById("Ipv4__Network__Open").click();
  for(i = 1;i <= 14;i++)
  {
    if(document.getElementById("sid" + i + "__Open") != null)
    {
      document.getElementById("sid" + i + "__Open").click();
      break;
    }
  }
  document.getElementById("T0__Open").click();
  document.getElementById("Admin__Open").click();
});

function onScroll() {
    if(window.pageYOffset >= headerOffsetTop) {
        headerMenu.classList.add("Menu__Fixed");
    } else {
        headerMenu.classList.remove("Menu__Fixed");
    }
}

function onClickMenuIcon() {
    if (headerMenu.classList.contains("Icon__Click")) {
        headerMenu.classList.remove("Icon__Click")
    } else {
        headerMenu.classList.add("Icon__Click")
    }
}

function onClickRestart()
{
  if(!confirm("시스템을 다시 시작하시겠습니까?"))
    return;
  
  var xhttp = makeXHTTP();
  
  document.getElementById("Activity__Indicator").style.display = "block";
  
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4)
    {
      document.getElementById("Activity__Indicator").style.display = "none";
      
      if(this.status == 200) 
      {
        console.log(this.responseText);
        alert("시스템 재시작 요청을 보냈습니다. 잠시 후 다시 접속해보세요.");
      }
      else if(this.status >= 100)
      {
        var msg = "[" + this.status + "] " + this.statusText;
        alert(msg);
      }
    }
  };
  
  xhttp.onerror = function(e) {
    alert("[네트워크 에러] 서버와 통신할 수 없습니다.");
  };
  
  xhttp.ontimeout = function() {    
    alert("[네트워크 타임아웃] 서버와 통신할 수 없습니다.");
  };
  
  xhttp.open("GET", "restart.php?t=" + Math.random(), true);
  xhttp.send();
}

function onClickSubmit()
{
  if(!confirm("변경사항을 저장하시겠습니까?"))
    return;
  
  if((x = validCheckIPv4()).length != 0)
  {
    document.getElementById("Network__Menu__Open").click();
    document.getElementById("Ipv4__Network__Open").click();
    x[0].focus();
    return;
  }
  
  <?php
  if(ini_get("init_ip6") === "1")
  {
    echo "if((x = validCheckIPv6()).length != 0)\r\n";
    echo "{\r\n";
    echo "  document.getElementById(\"Network__Menu__Open\").click();\r\n";
    echo "  document.getElementById(\"Ipv6__Network__Open\").click();\r\n";
    echo "  x[0].focus();\r\n";
    echo "  return;\r\n";
    echo "}\r\n";
  }
  
  if(ini_get("init_net1") === "1")
  {
    echo "if((x = validCheckWlan()).length != 0)\r\n";
    echo "{\r\n";
    echo "  document.getElementById(\"Network__Menu__Open\").click();\r\n";
    echo "  document.getElementById(\"Wlan__Network__Open\").click();\r\n";
    echo "  x[0].focus();\r\n";
    echo "  return;\r\n";
    echo "}\r\n";
  }
  ?>
  var x;
  if((x = validCheckTcpUdp()).element.length != 0)
  {
    document.getElementById("Tcp__Menu__Open").click();
    document.getElementById(x.tcpudp_name + "__Open").click();
    x.element[0].focus();
    return;
  }

  if((x = validCheckAdmin()).element.length != 0)
  {
      document.getElementById("Admin__Menu__Open").click();
      document.getElementById(x.menu_name + "__Open").click();
      x.element[0].focus();
      return;
  }
  
  document.getElementById("smart_expantion_setup").target = "submit_target";
  document.getElementById("smart_expantion_setup").submit();
  
  document.getElementById("Activity__Indicator").style.display = "block";
}

function network_setup_finish()
{
  document.getElementById("tcp_udp_setup").target = "submit_target";
  document.getElementById("tcp_udp_setup").submit();
}

function smart_expansion_setup_finish()
{
  document.getElementById("network_setup").target = "submit_target";
  document.getElementById("network_setup").submit();
}

function tcp_udp_setup_finish()
{
  document.getElementById("Activity__Indicator").style.display = "none";
}

function clearListTable(table_id)
{
  var list_table = document.getElementById(table_id);
  var rows = list_table.rows;
  if(rows.length > 1)
  {
    var idx_max = rows.length - 1;
    for(var idx = idx_max;idx >= 1;idx--)
    {
      list_table.deleteRow(idx);
    }
  }
}

function onClickCloseList(div_id, table_id, erase)
{
  erase = typeof erase !== 'undefined' ? erase : true;
  
  document.getElementById(div_id).className = "List__Collapse";
  
  if(erase)
    clearListTable(table_id);
  
    document.getElementById("Wlan").classList.remove("Expand");
}

function onClickSearchAP()
{
    var xhttp = makeXHTTP();

    document.getElementById("Activity__Indicator").style.display = "block";

    xhttp.onreadystatechange = function() {
        if (this.readyState == 4)
        {
            var ap_list_table = document.getElementById("ap_list_table");
        
            if(document.getElementById("ap_list").className != "List__Expand")
            {
                document.getElementById("ap_list").className = "List__Expand";
                document.getElementById("Wlan").classList.add("Expand");
            }
        
            document.getElementById("Activity__Indicator").style.display = "none";
        
            if(this.status == 200) 
            {
                console.log(this.responseText);
                var response = JSON.parse(this.responseText);
                
                if(response.status == "")
                {
                    var row = ap_list_table.insertRow(1);
                    row.className = "Bottom__Line";
                    var cell0 = row.insertCell(0);
                    cell0.colSpan = 3;
                    cell0.innerHTML = "<div class='Error__Text'>무선랜을 사용할 수 없습니다.</div>";
                }
                else
                {
                var number_of_ap = response.item_count;
                if(number_of_ap > 0)
                {
                    for(var idx = 0;idx < number_of_ap;idx++)
                    {
                    var ap = response.ap[idx];
                    console.log(ap.ssid + ap.security + ap.rssi + ap.ssid_raw);
                    var row = ap_list_table.insertRow(1 + idx);
                    row.className = "Bottom__Line";
                    
                    var cell0 = row.insertCell(0);
                    var cell1 = row.insertCell(1);
                    var cell2 = row.insertCell(2);
                    //var cell3 = row.insertCell(3);
                    
                    var html = "";
                    html = "<div class=\"Wifi__Strength";
                    
                    if(ap.rssi <= 50)
                    {
                        html += " S4\">";
                    }
                    else if(ap.rssi > 50 && ap.rssi <= 60)
                    {
                        html += " S3\">";
                    }
                    else if(ap.rssi > 60 && ap.rssi <= 70)
                    {
                        html += " S2\">";
                    }
                    else if(ap.rssi > 70)
                    {
                        html += " S1\">";
                    }
                    html += "<div class=\"Wifi__Security";
                    if(ap.security != "None")
                        html += " Set";
                    html += "\"></div></div>";
                    cell0.innerHTML = html;
                    cell0.style = "width:16px;";
                    cell1.innerHTML = ap.ssid;
                    html = "<button type=\"button\" class=\"Black__Button\" onclick=\"onClickSelectAP('" + ap.ssid + "','" + ap.ssid_raw + "','" + ap.security + "');\">선택</button>";
                    cell2.innerHTML = html;
                    }
                }
                else
                {
                    var row = ap_list_table.insertRow(1);
                    row.className = "Bottom__Line";
                    var cell0 = row.insertCell(0);
                    cell0.colSpan = 3;
                    cell0.innerHTML = "<div class='Error__Text'>검색된 AP가 없습니다.</div>";
                }
                }
            }
            else if(this.status >= 100)
            {
                var msg = "[" + this.status + "] " + this.statusText;
                var row = ap_list_table.insertRow(1);
                row.className = "Bottom__Line";
                var cell0 = row.insertCell(0);
                cell0.colSpan = 3;
                cell0.innerHTML = msg;
            }
        }
    };
    
    xhttp.onerror = function(e) {
        var msg = "[" + this.status + "] " + this.statusText;
        var row = ap_list_table.insertRow(1);
        var cell0 = row.insertCell(0);
        row.className = "Bottom__Line";
        cell0.colSpan = 3;
        cell0.innerHTML = "[네트워크 에러] 서버와 통신할 수 없습니다.";
    };
    
    xhttp.ontimeout = function() {    
        var msg = "[" + this.status + "] " + this.statusText;
        var row = ap_list_table.insertRow(1);
        var cell0 = row.insertCell(0);
        row.className = "Bottom__Line";
        cell0.colSpan = 3;
        cell0.innerHTML = "[네트워크 타임아웃] 서버와 통신할 수 없습니다.";
    };
    
    xhttp.open("POST", "search_ap.php", true);
    xhttp.send();
    clearListTable("ap_list_table");
}

function onClickSelectAP(ssid, ssid_raw, security)
{
  document.getElementById("wlan_ssid").value = ssid;
  document.getElementById("wlan_ssid_raw").value = ssid_raw;
  console.log(document.getElementById("wlan_ssid_raw").value);
  document.getElementById("wlan_shared_key").value = "";
}

function onClickSearchChannel()
{
    var xhttp = makeXHTTP();

    document.getElementById("Activity__Indicator").style.display = "block";

    xhttp.onreadystatechange = function() {
        if (this.readyState == 4)
        {
            var channel_list_table = document.getElementById("channel_list_table");
            
            if(document.getElementById("channel_list").className != "List__Expand")
            {
                document.getElementById("channel_list").className = "List__Expand";
                document.getElementById("Wlan").classList.add("Expand");
            }
        
            document.getElementById("Activity__Indicator").style.display = "none";
        
            if(this.status == 200)
            {
                console.log(this.responseText);
                var response = JSON.parse(this.responseText);
                
                if(response.status == "")
                {
                    var row = channel_list_table.insertRow(1);
                    row.className = "Bottom__Line";
                    var cell0 = row.insertCell(0);
                    cell0.colSpan = 2;
                    cell0.innerHTML = "<div class='Error__Text'>무선랜을 사용할 수 없습니다.</div>";
                }
                else
                {
                    var number_of_channel = response.item_count;
                    for(var idx = 0;idx < number_of_channel - 1;idx++)
                    {
                        var channel = response.channels[idx];
                        var row = channel_list_table.insertRow(1 + idx);
                        row.className = "Bottom__Line";
                        
                        var cell0 = row.insertCell(0);
                        var cell1 = row.insertCell(1);
                        
                        var html = "<b>Channel " + channel.channel + "(" + channel.item_count + ")</b><br>";
                        html += channel.ssid;
                        
                        cell0.innerHTML = html;
                        cell1.innerHTML = "<button type=\"button\" class=\"Black__Button\" onclick=\"onClickSelectChannel('" + channel.channel + "')\">선택</button>";
                    }
                }
            }
            else if(this.status >= 100)
            {
                var msg = "[" + this.status + "] " + this.statusText;
                var row = channel_list_table.insertRow(1);
                row.className = "Bottom__Line";
                var cell0 = row.insertCell(0);
                cell0.colSpan = 2;
                cell0.innerHTML = msg;
            }
        }
    };
    
    xhttp.onerror = function() {
        var row = channel_list_table.insertRow(1);
        var cell0 = row.insertCell(0);
        row.className = "Bottom__Line";
        cell0.colSpan = 2;
        cell0.innerHTML = "[네트워크 에러] 서버와 통신할 수 없습니다.";
    };
    
    xhttp.ontimeout = function() {    
        var row = channel_list_table.insertRow(1);
        var cell0 = row.insertCell(0);
        row.className = "Bottom__Line";
        cell0.colSpan = 2;
        cell0.innerHTML = "[네트워크 타임아웃] 서버와 통신할 수 없습니다.";
    };
    
    xhttp.open("POST", "search_channel.php", true);
    xhttp.send();
    clearListTable("channel_list_table");

}

function onClickSelectChannel(channel)
{
  document.getElementById("wlan_channel").value = channel;
}

function onClickAdvancedOption()
{
  var advanced_option_list_table = document.getElementById("advanced_option_list_table");
  var tabcontent = document.getElementById("Wlan");
  
  if(document.getElementById("advanced_option_list").className != "List__Expand")
  {
      document.getElementById("advanced_option_list").className = "List__Expand";
      document.getElementById("Wlan").classList.add("Expand");
  }
}

function initTcpUdp()
{
  for(i = 0;i < 5;i++)
  {
    onClickTcpUdpEnable('T' + i);
    onClickTcpUdpEnable('U' + i);
  }
}

function onClickTcpUdpEnable(device)
{
  if(document.getElementById(device + "_enable").checked == true)
  {
    if(device.indexOf("T") == 0)
    {
      document.getElementById(device + "_tcp_server").disabled = false;
      document.getElementById(device + "_tcp_client").disabled = false;
      document.getElementById(device + "_udp").disabled = true;
    }
    else
    {
      document.getElementById(device + "_tcp_server").disabled = true;
      document.getElementById(device + "_tcp_client").disabled = true;
      document.getElementById(device + "_udp").disabled = false;
    }
    
    if(document.getElementById(device + "_tcp_server").checked == true)
    {
      document.getElementById(device + "_local_port").disabled = false;
      document.getElementById(device + "_peer_address").disabled = true;
      document.getElementById(device + "_peer_port").disabled = true;
    }
    else if(document.getElementById(device + "_tcp_client").checked == true)
    {
      document.getElementById(device + "_local_port").disabled = true;
      document.getElementById(device + "_peer_address").disabled = false;
      document.getElementById(device + "_peer_port").disabled = false;
    }
    else if(document.getElementById(device + "_udp").checked == true)
    {
      document.getElementById(device + "_local_port").disabled = false;
      document.getElementById(device + "_peer_address").disabled = false;
      document.getElementById(device + "_peer_port").disabled = false;
    }
  }
  else
  {
    document.getElementById(device + "_tcp_server").disabled = true;
    document.getElementById(device + "_tcp_client").disabled = true;
    document.getElementById(device + "_udp").disabled = true;
    document.getElementById(device + "_local_port").disabled = true;
    document.getElementById(device + "_peer_address").disabled = true;
    document.getElementById(device + "_peer_port").disabled = true;
  }
  
  for(sid = 1;sid <= 14;sid++)
  {
    if(document.body.getAttribute("data-sid" + sid + "-uart") != null)
    {
      var id = "sid" + sid + "_" + device;
      document.getElementById(id).disabled = document.getElementById(device + "_enable").checked == true ? false : true;
    }
  }
}

function onClickTcpUdpProtocol(device, protocol)
{
  onClickTcpUdpEnable(device);
}
</script>
</head>
<body>
<!-- Modal -->
<div id="Activity__Indicator" class="Activity__Indicator">
  <div class="Loader Small Big">
    <div id="Loader__Blade" class="Loader__Blade Small Big">
      <div class="Blade__Center Small Big">
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
        <div class="Blade Small Big"></div>
      </div>
    </div>
  </div>
</div>

<!-- Contents -->
<div class="Header">
    <div class="Header__Title">
        <h1><? echo system("uname -i") ?></h1>
    </div>
    <div class="Header__Menu" id="Header__Menu">
        <button type="button" class="Menu__Icon" onclick="onClickMenuIcon()">&#9776;</button>
        <button type="button" class="Menu__Links" onclick="openMenu(event, 'Home')" id="Home__Menu__Open">홈</button>
        <button type="button" class="Menu__Links" onclick="openMenu(event, 'Network__Settings')" id="Network__Menu__Open">인터넷</button>
        <button type="button" class="Menu__Links" onclick="openMenu(event, 'Tcp__Settings')" id="Tcp__Menu__Open">네트워크디바이스</button>
        <button type="button" class="Menu__Links" onclick="openMenu(event, 'Expansion__Settings')" id="Expansion__Menu__Open">스마트 확장보드</button>
        <button type="button" class="Menu__Links" onclick="openMenu(event, 'Admin__Settings')" id="Admin__Menu__Open">관리자</button>
    </div>
</div>

<div id="Home" class="MenuContent">
    <div class="Board">
        <div class="Board__Column">
            <button class="Folding__Button" onClick="foldingButton(this)">인터넷</button>
            <div class="Folding__Content">
                <div class="Board__Title">IPv4</div>
                <div class="Board__Text" id='IP4__Type'></div>
                <div class="Board__Text" id='IP4__State'></div>

                <div class="Board__Title">IPv6</div>
                <div class="Board__Text" id='IP6__Type'></div>
                <div class="Board__Text" id='IP6__State'></div>

                <div class="Board__Title">WLAN</div>
                <div class="Board__Text" id='Wlan__Type'></div>
                <div class="Board__Text" id='Wlan__State1'></div>

                <!--<div class="Board__Title">기타</div>
                <div class="Board__Text" id='Etc__Polling'></div>-->
            </div>
        </div>
        <div class="Board__Column">
            <button class="Folding__Button" onClick="foldingButton(this)">스마트 확장보드</button>
            <div class="Folding__Content">
            <?php
            $um0 = "";
            $offset = 0;
            $valid_slave = 0;
            $slave_count = 0;

            for($i = 0;$i < 14;$i++)
            {
                $offset += um_read(0, $offset, $um0, 1);
                $sid = bin2int($um0, 0, 1);
                $offset += um_read(0, $offset, $um0, 1);
                $length = bin2int($um0, 0, 1);
                $product = "";

                if($length > 0)
                {
                    $offset += um_read(0, $offset, $um0, $length);
                    $product = $um0;
                }
                
                $uart = "";
                if($product == "PES-2406" || $product == "PES-2407")
                {
                    $uart = spc_request_dev($sid, "get uart");
                    echo "<script language=javascript>\r\n";
                    echo "document.body.setAttribute('data-sid$sid-uart', \"" . $uart ."\");";
                    echo "document.body.setAttribute('data-sid$sid-product', \"" . $product ."\");";
                    echo "</script>\r\n";

                    $bps = (int)$uart;
                    $temp = substr($uart, strlen((string)$bps));
                
                    $valid_slave++;
                    $slave_count++;

                    echo "<div class=\"Board__Title\">SID $sid</div>";
                    echo "<div class=\"Board__Text\">$product ";
                    switch($product){
                        case "PES-2406":
                            echo "(RS232 보드)</div>";
                        break;
                        case "PES-2407":
                            echo "(RS422/RS485 보드)</div>";
                        break;
                    }
                    echo "<div class=\"Board__Text\">";
                    echo "    <table width=90%>";
                    echo "        <tr>";
                    echo "          <td width='55%'>통신속도</td><td width='45%'> $bps</td>";
                    echo "        </tr>";
                    echo "        <tr>";
                    echo "          <td width='55%'>패리티 비트</td><td width='45%'>";
                    switch(substr($temp, 0, 1))
                    {
                    case "N":
                        echo "NONE";
                    break;
                    case "E":
                        echo "EVEN";
                    break;
                    case "O":
                        echo "ODD";
                    break;
                    case "M":
                        echo "MARK";
                    break;
                    case "S":
                        echo "SPACE";
                    break;
                    }
                    echo "          </td>";
                    echo "        </tr>";
                    echo "        <tr>";
                    echo "          <td width='55%'>데이터 비트</td><td width='45%'> " . substr($temp, 1, 1) . "</td>";
                    echo "        </tr>";
                    echo "        <tr>";
                    echo "          <td width='55%'>정지 비트</td><td width='45%'> " . substr($temp, 2, 1) . "</td>";
                    echo "        </tr>";
                    echo "        <tr>";
                    echo "          <td width='55%'>흐름제어</td><td width='45%'> ";
                    if($product == "PES-2406")
                    {
                        switch(substr($temp, 3, 1))
                        {
                            case "N":
                                echo "NONE";
                            break;
                            case "H":
                                echo "RTS/CTS";
                            break;
                            case "S":
                                echo "Xon/Xoff";
                            break;
                        }
                    }
                    else if($product == "PES-2407")
                    {
                        switch(substr($temp, 3, 1))
                        {
                            case "N":
                                echo "TxDE 사용 안 함";
                            break;
                            case "T":
                                echo "TxDE 사용";
                            break;
                        }
                    }
                    echo "          </td>";
                    echo "        </tr>";

                    $device = find_serial_device_setting($sid - 1);
                    $device[5] = str_replace("T", "TCP", $device[5]);
                    $device[5] = str_replace("U", "UDP", $device[5]);
                    $udp_pos = strpos($device[5], "UDP");
                    $tcp_list = "";
                    $udp_list = "";
                    if($udp_pos !== FALSE && $udp_pos > 0)
                    {
                        $tcp_list = substr($device[5], 0, $udp_pos - 1);
                        $udp_list = substr($device[5], $udp_pos, strlen($device[5]) - $udp_pos);
                    }
                    else
                    {
                        $tcp_list = $device[5];
                    }

                    echo "        <tr>";
                    echo "          <td width='55%'>TCP 디바이스</td><td width='45%'> $tcp_list</td>";
                    echo "        </tr>";
                    echo "        <tr>";
                    echo "          <td width='55%'>UDP 디바이스</td><td width='45%'> $udp_list</td>";
                    echo "        </tr>";
                    echo "    </table>";
                    echo "</div>";                    
                }
                else if($product == "ERROR")
                {
                    $slave_count++;
                    echo "<div class=\"Board__Title\">SID $sid</div>";
                    echo "<div class=\"Board__Text error\">오류가 발생했습니다</div>";
                }
                else if($product != "")
                    {
                    $slave_count++;
                    echo "<div class=\"Board__Title\">SID $sid</div>";
                    echo "<div class=\"Board__Text Error__Text\">$product</div>";
                    echo "<div class=\"Board__Text Error__Text\">사용할 수 없는 스마트 확장보드입니다.</div>";
                }
                else
                {

                }
            }
            ?>
            </div>
        </div>
        <div class="Board__Column">
            <button class="Folding__Button" onClick="foldingButton(this)">네트워크디바이스</button>
            <div class="Folding__Content">
            <?php
            $enabled_tcpudp = 0;
            for($i = 0; $i < 10;$i++)
            {
                $device = find_network_device_setting($i);
                echo "<div class=\"Board__Title\">" . ($device[0] == "T" ? "TCP" : "UDP") . (string)$device[1] . "</div>";
                if($device[2] == 1)
                {
                    $enabled_tcpudp++;
                    switch($device[3]){
                        case 0:
                            echo "<div class=\"Board__Text\">TCP 서버</div>";
                            echo "<div class=\"Board__Text\">로컬포트 ". (string)$device[6] . "번</div>";
                        break;
                        case 1:
                            echo "<div class=\"Board__Text\">TCP 클라이언트</div>";
                            echo "<div class=\"Board__Text\">통신할 주소 ". $device[4] . "</div>";
                            echo "<div class=\"Board__Text\">통신할 포트 " . (string)$device[5] . "번</div>";
                        break;
                        case 2:
                            echo "<div class=\"Board__Text\">UDP</div>";
                            echo "<div class=\"Board__Text\">로컬포트 ". (string)$device[6] . "번</div>";
                            echo "<div class=\"Board__Text\">통신할 주소 ". $device[4] . "</div>";
                            echo "<div class=\"Board__Text\">통신할 포트 " . (string)$device[5] . "번</div>";
                        break;
                    }

                }
                else
                {
                    echo "<div class=\"Board__Text\"> 사용 안 함으로 되어있습니다.</div>";
                }
            }
            ?>
            </div>
        </div>
    </div>
</div>


<div id="Action__Buttons" class="MenuContent Action__Buttons">
  <iframe src="" height="0" width="0" style="border:none;" name="submit_target"></iframe>  
  <button type="button" class="Action" id="button_save">변경사항 저장</button>
  <button type="button" class="Action" id="button_restart">시스템 다시시작</button>
</div>

<div id="Network__Settings" class="MenuContent Network">
    <form id="network_setup" action="network_setup.php" method="post">
        <div class="Vertical">
            <div class="Vertical__Menu Network">
                <button type="button" class="Network__Button" onclick="openSubMenu('Vertical__Content Network', 'Network__Button', event, 'Ipv4')" id="Ipv4__Network__Open">IPv4</button>
                <button type="button" class="Network__Button" onclick="openSubMenu('Vertical__Content Network', 'Network__Button', event, 'Ipv6')" id="Ipv6__Network__Open">IPv6</button>
                <button type="button" class="Network__Button" onclick="openSubMenu('Vertical__Content Network', 'Network__Button', event, 'Wlan')" id="Wlan__Network__Open">무선랜</button>
            </div>
            <div id="Ipv4" class="Vertical__Content Network">
                <div class="Title">IPv4</div>
                <div class="Subtitle">
                    <input type="radio" value="1" name="ipv4_type" onclick="onClickIpv4Type();" id="ipv4_dhcp"> 자동으로 IP 주소 받기<br>
                    <input type="radio" value="0" name="ipv4_type" onclick="onClickIpv4Type();" id="ipv4_static"> 고정된 IP 주소 사용
                </div>
                <div class="Title">제품 IPv4 주소</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv4_address" id="ipv4_address" size="18" maxlength="15">
                    <div id="ipv4_address_error" class="Error__Text"></div>
                </div>
                <div class="Title">서브넷 마스크</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv4_subnet_mask" id="ipv4_subnet_mask" size="18" maxlength="15">
                    <div id="ipv4_subnet_mask_error" class="Error__Text"></div>
                </div>
                <div class="Title">게이트웨어 IPv4 주소</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv4_gateway" id="ipv4_gateway" size="18" maxlength="15">
                    <div id="ipv4_gateway_error" class="Error__Text"></div>
                </div>
                <div class="Title">DNS 서버 IPv4 주소</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv4_dns" id="ipv4_dns" size="18" maxlength="15"><br>
                    <div id="ipv4_dns_error" class="Error__Text"></div>
                    <input type="checkbox" name="ipv4_dhcp_dns" id="ipv4_dhcp_dns" onclick="onClickIpv4DhcpDns();" value="1">DNS 서버 IP주소 수동입력
                </div>
            </div>
            <div id="Ipv6" class="Vertical__Content Network">
                <div class="Title">IPv6</div>
                <div class="Subtitle">
                    <input type="radio" name="ipv6_enable" id="ipv6_enable" value="1" onclick="onClickIpv6Enable();">사용
                    <input type="radio" name="ipv6_enable" id="ipv6_disable" value="0" onclick="onClickIpv6Enable();">사용 안 함
                </div>
                <div class="Subtitle">
                    <input type="radio" name="ipv6_type" id="ipv6_dhcp" onclick="onClickIpv6Type();" value="0"> 자동으로 IP 주소 받기<br>
                    <input type="radio" name="ipv6_type" id="ipv6_static" onclick="onClickIpv6Type();" value="1"> 고정된 IP 주소 사용
                </div>
                <div class="Title">EUI</div>
                <div class="Subtitle">
                    <select name="ipv6_eui" id="ipv6_eui">
                        <option value="0">MAC 주소</option>
                        <option value="1">Random</option>
                    </select>
                </div>
                <div class="Title">제품 IPv6 주소</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv6_address" id="ipv6_address" size="30" maxlength="39"> 
                    / 
                    <div id="ipv6_address_error" class="Error__Text"></div>
                    <input type="number" class="style-text" style="width:40px;" name="ipv6_prefix" id="ipv6_prefix">
                    <div id="ipv6_prefix_error" class="Error__Text"></div>
                </div>
                <div class="Title">게이트웨이 IPv6 주소</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv6_gateway" id="ipv6_gateway" size="30" maxlength="39">
                </div>
                <div class="Title">DNS 서버 IPv6 주소</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="ipv6_dns" id="ipv6_dns" size="30" maxlength="39">
                    <div id="ipv6_dns_error" class="Error__Text"></div>
                    <input type="checkbox" name="ipv6_dhcp_dns" id="ipv6_dhcp_dns" onclick="onClickIpv6DhcpDns();" value="1">DNS 서버 IP주소 수동입력
                </div>
            </div>
            <div id="Wlan" class="Vertical__Content Network">
                <div class="Title">무선랜</div>
                <div class="Subtitle">
                    <input type="radio" name="wlan_enable" id="wlan_enable" value="1" onclick="onClickWlanEnable();">사용
                    <input type="radio" name="wlan_enable" id="wlan_disable" value="0" onclick="onClickWlanEnable();">사용 안 함
                </div>
                <div class="Title">무선랜 종류</div>
                <div class="Subtitle">
                    <input type="radio" name="wlan_type" id="wlan_adhoc" value="0" onclick="onClickWlanType();">애드혹<br>
                    <input type="radio" name="wlan_type" id="wlan_infrastructure" value="1" onclick="onClickWlanType();">인프라스트럭쳐&nbsp;
                    <button type="button" class="Black__Button" name="wlan_search_ap" id="wlan_search_ap" onclick="onClickSearchAP();">AP 검색</button><br>
                    <div id="ap_list" class="List__Collapse">
                        <table id="ap_list_table" class="Table__List">
                        <tr class="Bottom__Line">
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td style="width:60px;">
                                <button type="button" class="Black__Button" onclick="onClickCloseList('ap_list', 'ap_list_table');">닫기</button>
                            </td>
                        </tr>
                        </table>
                    </div>
                    <input type="radio" name="wlan_type" id="wlan_soft_ap" value="2" onclick="onClickWlanType();">Soft AP
                </div>
                <div class="Title">채널</div>
                <div class="Subtitle">
                    <select name="wlan_channel" id="wlan_channel">
                        <?php
                        for($i = 0;$i <= 13;$i++)
                        {
                        echo "<option value=\"$i\">";
                        $wlan_channel_value = $i == 0 ? "자동" : $i;
                        echo "$wlan_channel_value</option>\r\n";
                        }
                        ?>
                    </select>
                    <button type="button" class="Black__Button" name="wlan_search_channel" id="wlan_search_channel" onclick="onClickSearchChannel();">채널 검색</button>
                    <div id="channel_list" class="List__Collapse">
                        <table id="channel_list_table" class="Table__List">
                        <tr class="Bottom__Line">
                            <td>&nbsp;</td>
                            <td style="width:60px;">
                                <button type="button" class="Black__Button" onclick="onClickCloseList('channel_list', 'channel_list_table');">닫기</button>
                            </td>
                        </tr>
                        </table>
                    </div>
                </div>
                <div class="Title">SSID</div>
                <div class="Subtitle">
                    <input type="text" class="style-text" name="wlan_ssid" id="wlan_ssid" maxlength="32">
                    <input type="hidden" name="wlan_ssid_raw" id="wlan_ssid_raw">
                    <div id="wlan_ssid_error" class="Error__Text"></div>
                </div>
                <div class="Title">Shared Key</div>
                <div class="Subtitle">
                    <input type="password" class="style-text" name="wlan_shared_key" id="wlan_shared_key"><br>
                    <input type="checkbox" name="hide_wlan_shared_key" id="hide_wlan_shared_key" onclick="onClickHideWlanSharedKey()" checked>문자 숨기기
                </div>
                <div class="Title">무선 고급 설정</div>
                <div class="Subtitle">
                  <button type="button" class="Black__Button" name="wlan_advanced" id="wlan_advanced" onclick="onClickAdvancedOption();">무선 고급 설정</button>
                  <div id="advanced_option_list" class="List__Collapse">
                    <table id="advanced_option_list_table" class="Table__List">
                      <tr class="Bottom__Line">
                          <td>&nbsp;</td>
                          <td>&nbsp;</td>
                          <td style="width:60px;">
                              <button type="button" class="Black__Button" onclick="onClickCloseList('advanced_option_list', 'advanced_option_list_table', false);">닫기</button>
                          </td>
                      </tr>
                      <tr>
                          <td rowspan=4  class="Bottom__Line" style="width:75px;">Phy Mode</td>
                          <td colspan=2 >
                            <input type="radio" name="wlan_phy_mode" id="phy_auto" onclick="onClickWlanPhyMode();" value="0"> 자동
                          </td>
                      </tr>
                      <tr>  
                          <td colspan=2>
                            <input type="radio" name="wlan_phy_mode" id="phy_802_11" onclick="onClickWlanPhyMode();" value="1"> 802.11
                          </td>
                      </tr>
                      <tr>  
                          <td colspan=2>
                            <input type="radio" name="wlan_phy_mode" id="phy_802_11b" onclick="onClickWlanPhyMode();" value="2"> 802.11b
                          </td>
                      </tr>
                      <tr class="Bottom__Line">
                          <td colspan=2>
                            <input type="radio" name="wlan_phy_mode" id="phy_802_11bg" onclick="onClickWlanPhyMode();" value="3"> 802.11b/g
                          </td>
                      </tr>
                      <tr>
                          <td colspan=3>
                            <input type="checkbox" name="wlan_short_preamble" id="wlan_short_preamble" value="1"> Short Preamble
                          </td>
                      </tr>
                      <tr>
                          <td colspan=3>
                            <input type="checkbox" name="wlan_short_slot" id="wlan_short_slot" value="1"> Short Slot
                          </td>
                      </tr>
                      <tr>
                          <td colspan=3>
                            <input type="checkbox" name="wlan_cts_protection" id="wlan_cts_protection" value="1"> CTS Protection
                          </td>
                      </tr>
                    </table>
                  </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div id="Expansion__Settings" class="MenuContent Expansion">
  <form id="smart_expantion_setup" action="smart_expansion_setup.php" method="post">
    <div class="Vertical">
      <div class="Vertical__Menu Expansion">
      <?php
      if($valid_slave > 0)
      {
        $um0 = "";
        $offset = 0;    
        
        for($i = 0;$i < 14;$i++)
        {
          $offset += um_read(0, $offset, $um0, 1);
          $sid = bin2int($um0, 0, 1);
          $offset += um_read(0, $offset, $um0, 1);
          $length = bin2int($um0, 0, 1);
          $product = "";
          
          if($length > 0)
          {
            $offset += um_read(0, $offset, $um0, $length);
            $product = $um0;
          }
          
          if($product == "PES-2406" || $product == "PES-2407")
          {
            echo "<button type=\"button\" class=\"Sid__Button\" onclick=\"openSubMenu('Vertical__Content Expansion', 'Sid__Button', event, 'sid$sid')\" id=\"sid" . (string)$sid . "__Open\">SID $sid</button>";
          }
        }
      }
      ?>
      </div>
      <?php 
      if($valid_slave == 0)
      {
          echo "<div id=\"Sid1\" class=\"Vertical__Content Expansion\">";
          echo "  사용가능한 스마트 확장보드가 없습니다.";
          echo "</div>";
      }

      if($valid_slave > 0)
      {
        $um0 = "";
        $offset = 0;    

        for($i = 0;$i < 14;$i++) 
        {
          $offset += um_read(0, $offset, $um0, 1);
          $sid = bin2int($um0, 0, 1);
          $offset += um_read(0, $offset, $um0, 1);
          $length = bin2int($um0, 0, 1);
          $product = "";
          
          if($length > 0)
          {
            $offset += um_read(0, $offset, $um0, $length);
            $product = $um0;
          }
          
          if($product == "PES-2406" || $product == "PES-2407") 
          {
            echo "  <div id=\"sid$sid\" class=\"Vertical__Content Expansion\">";
            echo "    <div class=\"Title\">SID $sid</div>";
            echo "    <div class=\"Subtitle\">";
            echo "    </div>";

            $element_name = "sid$sid" . "_baudrate";
            echo "    <div class=\"Title\">통신속도</div>";
            echo "    <div class=\"Subtitle\">";
            echo "      <select name='$element_name' id='$element_name'>";
            echo "        <option value=\"1200\">1,200bps</option>";
            echo "        <option value=\"2400\">2,400bps</option>";
            echo "        <option value=\"4800\">4,800bps</option>";        
            echo "        <option value=\"9600\">9,600bps</option>";
            echo "        <option value=\"19200\">19,200bps</option>";
            echo "        <option value=\"38400\">38,400bps</option>";
            echo "        <option value=\"57600\">57,600bps</option>";
            echo "        <option value=\"115200\">115,200bps</option>";
            echo "      </select>";
            echo "    </div>";

            $element_name = "sid$sid" . "_parity";
            echo "    <div class=\"Title\">패리티 비트</div>";
            echo "    <div class=\"Subtitle\">";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"N\">없음<br>";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"E\">짝수<br>";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"O\">홀수<br>";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"M\">Mark<br>";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"S\">Space";
            echo "    </div>";

            $element_name = "sid$sid" . "_databit";
            echo "    <div class=\"Title\">데이터 비트</div>";
            echo "    <div class=\"Subtitle\">";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"7\">7<br>";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"8\">8";
            echo "    </div>";

            $element_name = "sid$sid" . "_stopbit";
            echo "    <div class=\"Title\">정지 비트</div>";
            echo "    <div class=\"Subtitle\">";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"1\">1<br>";
            echo "      <input type=\"radio\" name=\"$element_name\" value=\"2\">2";
            echo "    </div>";

            $element_name = "sid$sid" . "_flowctrl";
            echo "    <div class=\"Title\">흐름제어</div>";
            echo "    <div class=\"Subtitle\">";
            if($product == "PES-2406")
            {
              echo "      &nbsp;&nbsp;<input type=\"radio\" name=\"$element_name\" value=\"N\">없음<br>";
              echo "      &nbsp;&nbsp;<input type=\"radio\" name=\"$element_name\" value=\"H\">RTS/CTS<br>";         
              echo "      &nbsp;&nbsp;<input type=\"radio\" name=\"$element_name\" value=\"S\">Xon/Xoff";
            }
            else if($product == "PES-2407")
            {
              echo "      &nbsp;&nbsp;<input type=\"radio\" name=\"$element_name\" value=\"N\">TxDE제어 사용 안 함<br>";
              echo "      &nbsp;&nbsp;<input type=\"radio\" name=\"$element_name\" value=\"T\">TxDE제어 사용";
            }
            echo "    </div>";
            echo "    <div class=\"Title\">네트워크 디바이스 선택</div>";
            echo "    <div class=\"Subtitle\">";
            $device_map = find_device_map($sid - 1);
            for($idx = 0; $idx < 10;$idx++)
            {
              $device = find_network_device_setting($idx);
              $disabled = $device[2] == 1 ? "" : "disabled";
              
              $device_id = $device[0] . (string)$device[1];
              $device_name = ($device[0] == "T" ? "TCP" : "UDP") . (string)$device[1];
              $element_name = "sid$sid" . "_" . $device_id;
              
              $checked = strpos($device_map, $device_id) !== FALSE ? "checked" : "";
              echo "      <input type='checkbox' name='$element_name' id='$element_name' value='1' $disabled $checked>" . $device_name . "<br>";
            }
            echo "    </div>";
            echo "  </div>";
          } 
        }
      }
      ?>
    </div>
  </form>
</div>

<form id="tcp_udp_setup" action="tcp_udp_setup.php" method="post">
  

  <div id="Tcp__Settings" class="MenuContent Tcp">
    <div class="Vertical">
      <div class="Vertical__Menu Tcp">
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'T0')" id="T0__Open">TCP0</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'T1')" id="T1__Open">TCP1</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'T2')" id="T2__Open">TCP2</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'T3')" id="T3__Open">TCP3</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'T4')" id="T4__Open">TCP4</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'U0')" id="U0__Open">UDP0</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'U1')" id="U1__Open">UDP1</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'U2')" id="U2__Open">UDP2</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'U3')" id="U3__Open">UDP3</button>
        <button type="button" class="Tcp__Button" onclick="openSubMenu('Vertical__Content Tcp', 'Tcp__Button', event, 'U4')" id="U4__Open">UDP4</button>
      </div>
      <?php 
      for($i = 0; $i < 10;$i++)
      {
        $device = find_network_device_setting($i);
        $device_id = $device[0] . (string)$device[1];
        $device_name = ($device[0] == "T" ? "TCP" : "UDP") . (string)$device[1];

        echo "<div id=\"$device_id\" class=\"Vertical__Content Tcp\">";
        echo "  <div class=\"Title\">$device_name</div>";
        echo "  <div class=\"Subtitle\">";
        $element_name = $device_id . "_enable";
        $element_id = $element_name;
        $function_name = "onClickTcpUdpEnable('" . $device_id . "');";
        $checked = $device[2] == 1 ? "checked" : "";
        echo "    <input type=\"radio\" name=\"$element_name\" id=\"$element_id\" value=\"1\" onclick=\"$function_name\" $checked>사용";
        $checked = $device[2] == 0 ? "checked" : "";
        $element_id = $device_id . "_disable";
        echo "    <input type=\"radio\" name=\"$element_name\" id=\"$element_id\" value=\"0\" onclick=\"$function_name\" $checked>사용 안 함";
        echo "  </div>";

        echo "  <div class=\"Subtitle\">";
        $element_name = $device_id . "_protocol";
        $element_id = $device_id . "_tcp_server";
        $function_name = "onClickTcpUdpProtocol('" . $device_id . "', 0);";
        $checked = $device[3] == 0 ? "checked" : "";
        $disabled = $device[0] == "T" ? "" : "disabled";
        echo "    <input type=\"radio\" name=\"$element_name\" id=\"$element_id\" value=\"0\" onclick=\"$function_name\" $checked $disabled>TCP 서버<br>";

        $element_id = $device_id . "_tcp_client";
        $function_name = "onClickTcpUdpProtocol('" . $device_id . "', 1);";
        $checked = $device[3] == 1 ? "checked" : "";
        $disabled = $device[0] == "T" ? "" : "disabled";
        echo "    <input type=\"radio\" name=\"$element_name\" id=\"$element_id\" value=\"1\" onclick=\"$function_name\" $checked $disabled>TCP 클라이언트<br>";

        $element_id = $device_id . "_udp";
        $function_name = "onClickTcpUdpProtocol('" . $device_id . "', 2);";
        $checked = $device[3] == 2 ? "checked" : "";
        $disabled = $device[0] == "U" ? "" : "disabled";
        echo "     <input type=\"radio\" name=\"$element_name\" id=\"$element_id\" value=\"2\" onclick=\"$function_name\" $checked $disabled>UDP";
        echo "  </div>";

        echo "  <div class=\"Title\">로컬포트 번호</div>";
        echo "  <div class=\"Subtitle\">";
        $element_name = $device_id . "_local_port";
        $disabled = $device[3] == 1 ? "disabled" : "";
        echo "      <input type=\"number\" style=\"width:50px;\" name=\"$element_name\" id=\"$element_name\" value=\"" . (string)$device[6] . "\" $disabled>";
        echo "      <div id=\"" . $element_name . "_error\" class=\"Error__Text\"></div>";
        echo "  </div>";

        echo "  <div class=\"Title\">통신할 주소</div>";
        echo "  <div class=\"Subtitle\">";
        $element_name = $device_id . "_peer_address";
        $disabled = $device[3] == 0 ? "disabled" : "";
        echo "    <input type=\"text\" class=\"style-text\" name=\"$element_name\" id=\"$element_name\" value=\"" . $device[4] . "\" $disabled>";
        echo "    <div id=\"" . $element_name . "_error\" class=\"Error__Text\"></div>";
        echo "  </div>";

        echo "  <div class=\"Title\">통신할 포트 번호</div>";
        echo "  <div class=\"Subtitle\">";
        $element_name = $device_id . "_peer_port";
        echo "    <input type=\"number\" class=\"style-text\" style=\"width:50px;\" name=\"$element_name\" id=\"$element_name\" value=\"" . (string)$device[5] . "\" $disabled>";
        echo "    <div id=\"" . $element_name . "_error\" class=\"Error__Text\"></div>";
        echo "  </div>";
        echo "</div>";
      }
      ?>
    </div>
  </div>


  <div id="Admin__Settings" class="MenuContent Admin">
    <div class="Vertical">
      <div class="Vertical__Menu Admin">
        <button type="button" class="Admin__Button" onclick="openSubMenu('Vertical__Content Admin', 'Admin__Button', event, 'Admin')" id="Admin__Open">관리자계정</button>
      </div>
      <div id="Admin" class="Vertical__Content Admin">
          <div class="Title">관리자 계정</div>
          <div class="Subtitle">admin</div>
          <div class="Title">관리자 비밀번호</div>
          <div class="Subtitle">
              <input type="password" name="admin_pwd" id="admin_pwd" size="8" maxlength="8" value="<? echo $web_password ?>">&nbsp;(4~8자)<br>
              <input type="checkbox" id="hide_admin_pwd" onclick="onClickHideAdminPwd()" checked>문자 숨기기
              <div id="admin_pwd_error" class="Error__Text"></div>
          </div>
      </div>
    </div>
  </div>
</form>

<script>
function openMenu(evt, menu) {
    var i, menuContent, menuLinks;
    menuContent = document.getElementsByClassName("MenuContent");
    for(i = 0;i < menuContent.length;i++){
        menuContent[i].style.display = "none";
    }
    
    document.getElementById(menu).style.display = "block";

    var headerMenu = document.getElementById("Header__Menu");
    if (headerMenu.classList.contains("Icon__Click")) {
        headerMenu.classList.remove("Icon__Click")
    }

    document.getElementById("Action__Buttons").style.display = (menu == "Home" ? "none" : "block");
}

function openSubMenu(menuClassName, buttonClassName, evt, divId){
    divs = document.getElementsByClassName(menuClassName);
    for(i = 0; i < divs.length; i++) {
        divs[i].style.display = "none";
    }
    buttons = document.getElementsByClassName(buttonClassName);
    for(i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove("Active");
    }
    document.getElementById(divId).style.display = "block";
    evt.currentTarget.classList.add("Active");
}

function setInternetState(){
    var internet_status = "";
    if(document.body.getAttribute('data-ipv4-type') == 0)
    {
        document.getElementById("IP4__Type").innerHTML = "고정 IP 연결입니다."
    }
    else
    {
        document.getElementById("IP4__Type").innerHTML = "동적 IP 연결입니다."
    }
    document.getElementById("IP4__State").innerHTML = "주소는 <? echo $device_ip_address?>입니다.";


    if(document.body.getAttribute('data-ipv6-enable') == 1)
    {
        if(document.body.getAttribute('data-ipv6-type') == 1)
            document.getElementById("IP6__Type").innerHTML = "고정 IP 연결입니다."
        else
            document.getElementById("IP6__Type").innerHTML = "동적 IP 연결입니다."

        document.getElementById("IP6__State").innerHTML = "주소는 <? echo $device_6_addr?>입니다.";
    }
    else
    {
        document.getElementById("IP6__Type").style.display = "none";
        document.getElementById("IP6__State").innerHTML = "IPv6는 사용 중이 아닙니다.";
    }                                           
}

function setWlanState(){
    var wlan_status;
    if(document.body.getAttribute('data-wlan-status') == "")
    {
        document.getElementById("Wlan__Type").style.display = "none";
        document.getElementById("Wlan__State1").innerHTML = "무선은 연결이 안 되어있습니다.";
    }
    else
    {   
        wlan_status = "무선랜 종류는 ";
        switch(document.body.getAttribute('data-wlan-status'))
        {
        case "INFRA":
            wlan_status += "인프라스트럭처";
            break;
        case "IBSS":
            wlan_status += "애드혹";
            break;
        case "AP":
            wlan_status += "Soft AP";
            break;
        }
        wlan_status += "입니다.";
        document.getElementById("Wlan__Type").innerHTML = wlan_status;
        wlan_status = "SSID는 " + document.body.getAttribute('data-wlan-ssid') + "입니다.";
        wlan_status = wlan_status.replace("$emac_id", document.body.getAttribute('data-wlan-emac-id'));
        document.getElementById("Wlan__State1").innerHTML = wlan_status;
    }
}

function setAdminSettings(){
    
}

function foldingButton(obj) {
    obj.classList.toggle("Closed");
    var content = obj.nextElementSibling;

    if(content != null){
        if(content.style.maxHeight == "" || content.style.maxHeight != "0px")
            content.style.maxHeight = "0px";
        else
            content.style.maxHeight = content.scrollHeight + "px";
    }
}

function makeXHTTP()
{
    var xhttp;
    if (window.XMLHttpRequest) 
        xhttp = new XMLHttpRequest();
    else 
        xhttp = new ActiveXObject("Microsoft.XMLHTTP");

    return xhttp;
}

function checkIpForm(ip_addr_element)
	{
		var reg_expression = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;
	
		var constraint = new RegExp(reg_expression);
    
    console.log(constraint.test(ip_addr_element.value));
    
    return constraint.test(ip_addr_element.value);
	}	

function onClickIpv4Type() 
{
  if(document.getElementById("ipv4_dhcp").checked == true)
  {
    document.getElementById("ipv4_address").disabled = true;
    document.getElementById("ipv4_subnet_mask").disabled = true;
    document.getElementById("ipv4_gateway").disabled = true;
    
    document.getElementById("ipv4_dhcp_dns").disabled = false;
    document.getElementById("ipv4_dhcp_dns").checked = document.body.dataset.ipv4DhcpDns == 1 ? false : true;
    document.getElementById("ipv4_dns").disabled = !document.getElementById("ipv4_dhcp_dns").checked;
  }
  else
  {
    document.getElementById("ipv4_address").disabled = false;
    document.getElementById("ipv4_subnet_mask").disabled = false;
    document.getElementById("ipv4_gateway").disabled = false;
    document.getElementById("ipv4_dns").disabled = false;
    
    document.getElementById("ipv4_dhcp_dns").disabled = true;
    document.getElementById("ipv4_dhcp_dns").checked = false;
  }
  
  validCheckIPv4();
}
function onClickIpv4DhcpDns() 
{  
  document.body.dataset.ipv4DhcpDns = document.getElementById("ipv4_dhcp_dns").checked == true ? 0 : 1;
  document.getElementById("ipv4_dns").disabled = !document.getElementById("ipv4_dhcp_dns").checked;
  
  validCheckIPv4();
}

function onClickIpv6Enable() 
{
  if(document.getElementById("ipv6_enable").checked == true) 
  {
    document.getElementById("ipv6_dhcp").disabled = false;
    document.getElementById("ipv6_static").disabled = false;
    
    document.getElementById("ipv6_eui").disabled = false;
    
    if(document.getElementById("ipv6_dhcp").checked == true) 
    {
      document.getElementById("ipv6_address").disabled = true;
      document.getElementById("ipv6_prefix").disabled = true;
      document.getElementById("ipv6_gateway").disabled = true;
      
      document.getElementById("ipv6_dhcp_dns").disabled = false;
      document.getElementById("ipv6_dhcp_dns").checked = document.body.dataset.ipv6DhcpDns == 1 ? false : true;
      document.getElementById("ipv6_dns").disabled = !document.getElementById("ipv6_dhcp_dns").checked;;
    } 
    else if(document.getElementById("ipv6_static").checked == true)
    {
      document.getElementById("ipv6_address").disabled = false;
      document.getElementById("ipv6_prefix").disabled = false;
      document.getElementById("ipv6_gateway").disabled = false;
      document.getElementById("ipv6_dns").disabled = false;
      
      document.getElementById("ipv6_dhcp_dns").disabled = true;
    }
  } 
  else 
  {
    document.getElementById("ipv6_dhcp").disabled = true;
    document.getElementById("ipv6_eui").disabled = true;
    document.getElementById("ipv6_static").disabled = true;
    document.getElementById("ipv6_address").disabled = true;
    document.getElementById("ipv6_prefix").disabled = true;
    document.getElementById("ipv6_gateway").disabled = true;
    document.getElementById("ipv6_dns").disabled = true;
    document.getElementById("ipv6_dhcp_dns").disabled = true;
  }
  
  validCheckIPv6();
}

function onClickIpv6Type()
{
  onClickIpv6Enable();
}

function onClickIpv6DhcpDns() 
{  
  document.body.dataset.ipv6DhcpDns = document.getElementById("ipv6_dhcp_dns").checked == true ? 0 : 1;
  document.getElementById("ipv6_dns").disabled = !document.getElementById("ipv6_dhcp_dns").checked;
  validCheckIPv6();
}

function onClickWlanEnable() {
  if(document.getElementById("wlan_enable").checked == true)
  {
    document.getElementById("wlan_adhoc").disabled = false;
    document.getElementById("wlan_infrastructure").disabled = false;
    document.getElementById("wlan_soft_ap").disabled = false;
    
    if(document.getElementById("wlan_adhoc").checked == true)
    {
      document.getElementById("wlan_search_ap").disabled = true;
      
      document.getElementById("wlan_channel").disabled = false;
      document.getElementById("wlan_search_channel").disabled = false;
      
      if(document.getElementById("ap_list").className == "List__Expand")
        onClickCloseList('ap_list', 'ap_list_table');
    }
    else if(document.getElementById("wlan_infrastructure").checked == true)
    {
      document.getElementById("wlan_search_ap").disabled = false;
      
      document.getElementById("wlan_channel").disabled = true;
      document.getElementById("wlan_search_channel").disabled = true;
      
      if(document.getElementById("channel_list").className == "List__Expand")
        onClickCloseList('channel_list', 'channel_list_table');
    }
    else if(document.getElementById("wlan_soft_ap").checked == true)
    {
      document.getElementById("wlan_search_ap").disabled = true;
      
      document.getElementById("wlan_channel").disabled = false;
      document.getElementById("wlan_search_channel").disabled = false;
      
      if(document.getElementById("ap_list").className == "List__Expand")
        onClickCloseList('ap_list', 'ap_list_table');
    }
    
    document.getElementById("wlan_ssid").disabled = false;
    document.getElementById("wlan_shared_key").disabled = false;
    document.getElementById("hide_wlan_shared_key").disabled = false;
    document.getElementById("wlan_advanced").disabled = false;
  }
  else
  {
    document.getElementById("wlan_adhoc").disabled = true;
    document.getElementById("wlan_infrastructure").disabled = true;
    document.getElementById("wlan_search_ap").disabled = true;
    document.getElementById("wlan_soft_ap").disabled = true;
    document.getElementById("wlan_channel").disabled = true;
    document.getElementById("wlan_search_channel").disabled = true;
    document.getElementById("wlan_ssid").disabled = true;
    document.getElementById("wlan_shared_key").disabled = true;
    document.getElementById("hide_wlan_shared_key").disabled = true;
    document.getElementById("wlan_advanced").disabled = true;
    
    if(document.getElementById("ap_list").className == "List__Expand")
        onClickCloseList('ap_list', 'ap_list_table');
    if(document.getElementById("channel_list").className == "List__Expand")
        onClickCloseList('channel_list', 'channel_list_table');
    if(document.getElementById("advanced_option_list").className == "List__Expand")
        onClickCloseList('advanced_option_list', 'advanced_option_list_table', false);
  }
  
  validCheckWlan();
}

function onClickWlanType()
{
  onClickWlanEnable();
}

function onClickHideWlanSharedKey() 
{
  if(document.getElementById("hide_wlan_shared_key").checked == true)
    document.getElementById("wlan_shared_key").type = "password";
  else
    document.getElementById("wlan_shared_key").type = "text";
}

function onClickHideAdminPwd()
{
  if(document.getElementById("hide_admin_pwd").checked == true)
    document.getElementById("admin_pwd").type = "password";
  else
    document.getElementById("admin_pwd").type = "text";

}

function validCheckAdmin()
{
    var x = {};
    x.menu_name = "";
    x.element = [];
    
    document.getElementById("admin_pwd_error").innerHTML = "";
    element = document.getElementById("admin_pwd");
    element.value = element.value.trim();
    if(element.value == "" || element.value.length < 4 || element.value.length > 8)
    {
        document.getElementById("admin_pwd_error").innerHTML = "관리자 비밀번호를 4~8자로 입력하세요.";
        x.element.push(element);
        if(x.menu_name == "")
            x.menu_name = "Admin";
    }
    return x;
}

function onClickWlanPhyMode()
{
  if(document.getElementById("phy_auto").checked == true || document.getElementById("phy_802_11").checked == true)
  {
    document.getElementById("wlan_short_preamble").disabled = true;
    document.getElementById("wlan_short_slot").disabled = true;
    document.getElementById("wlan_cts_protection").disabled = true;
  }
  else if(document.getElementById("phy_802_11b").checked == true)
  {
    document.getElementById("wlan_short_preamble").disabled = false;
    document.getElementById("wlan_short_slot").disabled = true;
    document.getElementById("wlan_cts_protection").disabled = true;
  }
  else if(document.getElementById("phy_802_11bg").checked == true)
  {
    document.getElementById("wlan_short_preamble").disabled = false;
    document.getElementById("wlan_short_slot").disabled = false;
    document.getElementById("wlan_cts_protection").disabled = false;
  }
}

function validCheckIPv4()
{
  var x = [];
  //--------------------------------------------------------
  // IPv4
  document.getElementById("ipv4_address_error").innerHTML = "";
  document.getElementById("ipv4_subnet_mask_error").innerHTML = "";
  document.getElementById("ipv4_gateway_error").innerHTML = "";
  document.getElementById("ipv4_dns_error").innerHTML = "";
  
  if(document.getElementById("ipv4_dhcp").checked == true)
  {
    //dhcp
    if(document.getElementById("ipv4_dhcp_dns").checked == true)
    {
      var element = document.getElementById("ipv4_dns");
      if(element.value.trim() == "")
      {
        document.getElementById("ipv4_dns_error").innerHTML = "DNS 서버 IPv4 주소를 입력하세요.";
        x.push(element);
      }
      else if(element.value.trim() != "" && !checkIpForm(element))
      {
        document.getElementById("ipv4_dns_error").innerHTML = "DNS 서버 IPv4 주소가 올바르지 않습니다.";
        x.push(element);
      }
    }
  }
  else
  {
    //static
    var element = document.getElementById("ipv4_address");
    if(!checkIpForm(element))
    {
      document.getElementById("ipv4_address_error").innerHTML = "제품 IPv4 주소가 올바르지 않습니다.";
      x.push(element);
    }
    
    element = document.getElementById("ipv4_subnet_mask");
    if(!checkIpForm(element))
    {
      document.getElementById("ipv4_subnet_mask_error").innerHTML = "서브넷 마스크가 올바르지 않습니다.";
      x.push(element);
    }
    
    element = document.getElementById("ipv4_gateway");
    if(element.value.trim() != "" && !checkIpForm(element))
    {
      document.getElementById("ipv4_gateway_error").innerHTML = "게이트웨어 IPv4 주소가 올바르지 않습니다.";
      x.push(element);
    }
    
    element = document.getElementById("ipv4_dns");
    if(element.value.trim() != "" && !checkIpForm(element))
    {
      document.getElementById("ipv4_dns_error").innerHTML = "DNS 서버 IPv4 주소가 올바르지 않습니다.";
      x.push(element);
    }
  }  
  //--------------------------------------------------------
  return x;
}

function validCheckIPv6()
{
  var x = [];
  
  document.getElementById("ipv6_address_error").innerHTML = "";
  document.getElementById("ipv6_prefix_error").innerHTML = "";
  document.getElementById("ipv6_dns_error").innerHTML = "";
  
  if(document.getElementById("ipv6_enable").checked == true) 
  {
    if(document.getElementById("ipv6_dhcp").checked == true) 
    {
      if(document.getElementById("ipv6_dhcp_dns").checked == true)
      {
        var element = document.getElementById("ipv6_dns");
        if(element.value.trim() == "" || element.value.trim() == "::" || element.value.trim() == "::0")
        {
          document.getElementById("ipv6_dns_error").innerHTML = "DNS 서버 IPv6 주소를 입력하세요.";
          x.push(element);
        }
      }
    }
    else
    {
      var element = document.getElementById("ipv6_prefix");
      if(element.value < 1 || element.value > 128)
      {	
        document.getElementById("ipv6_prefix_error").innerHTML = "서브넷 접두사 길이를 1~128사이에서 입력하세요.";
        x.push(element);
      }
      
      element = document.getElementById("ipv6_address");
      if(element.value.trim() == "" || element.value.trim() == "::" || element.value.trim() == "::0")
      {	
        document.getElementById("ipv6_address_error").innerHTML = "제품 IPv6 주소를 입력하세요.";
        x.push(element);
      }
    }
  }
  return x;
}

function validCheckWlan()
{
  var x = [];
  
  document.getElementById("wlan_ssid_error").innerHTML = "";
  
  if(document.getElementById("wlan_enable").checked == true)
  {    
    var element = document.getElementById("wlan_ssid");
    if(element.value.trim() == "")
    {
      document.getElementById("wlan_ssid_error").innerHTML = "SSID를 입력하세요.";
      x.push(element);
    }
    else if(element.value.length > 32)
    {
      document.getElementById("wlan_ssid_error").innerHTML = "SSID는 최대 32자입니다.";
      x.push(element);
    }
  }
  
  return x;
}

function validCheckTcpUdp()
{
  var x = {};
  x.tcpudp_name = "";
  x.element = [];
  
  var device;
  
  for(i = 0;i < 10;i++)
  {
    switch(i)
    {
      case 0:
      case 1:
      case 2:
      case 3:
      case 4:
        device = "T" + i.toString();
        break;
      case 5:
      case 6:
      case 7:
      case 8:
      case 9:
        device = "U" + (i - 5).toString();
        break;
    }
    
    document.getElementById(device + "_local_port_error").innerHTML = "";
    document.getElementById(device + "_peer_address_error").innerHTML = "";
    document.getElementById(device + "_peer_port_error").innerHTML = "";
    
    if(document.getElementById(device + "_enable").checked == true)
    {
      if(document.getElementById(device + "_tcp_server").checked == true || document.getElementById(device + "_udp").checked == true)
      {
        var element = document.getElementById(device + "_local_port");
        element.value = element.value.trim();
        var element_value = parseInt(element.value);
        if(isNaN(element_value) || (element_value < 1 || element_value > 65535))
        {
          document.getElementById(device + "_local_port_error").innerHTML = "로컬 포트번호를 1~65535사이에서 입력하세요.";
          x.element.push(element);
          if(x.tcpudp_name == "")
            x.tcpudp_name = device;
        }
        
      }
      
      if(document.getElementById(device + "_tcp_client").checked == true || document.getElementById(device + "_udp").checked == true)
      {
        element = document.getElementById(device + "_peer_address");
        element.value = element.value.trim();
        if(element.value == "" || element.value.length > 64)
        {
          document.getElementById(device + "_peer_address_error").innerHTML = "통신할 주소를 최대 64자로 입력하세요.";
          x.element.push(element);
          if(x.tcpudp_name == "")
            x.tcpudp_name = device;
        }
        
        element = document.getElementById(device + "_peer_port");
        element.value = element.value.trim();
        var element_value = parseInt(element.value);
        if(isNaN(element_value) || (element_value < 1 || element_value > 65535))
        {
          document.getElementById(device + "_peer_port_error").innerHTML = "통신 할 포트 번호를 1~65535사이에서 입력하세요.";
          x.element.push(element);
          if(x.tcpudp_name == "")
            x.tcpudp_name = device;
        }
      }
    }
  }
  
  return x;
}

document.body.setAttribute('data-ipv4-type', <? echo envs_get_net_opt($envs, NET_OPT_DHCP) ?>);
document.body.setAttribute('data-ipv4-address', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x00), 0, 4)) ?>");
document.body.setAttribute('data-ipv4-subnet-mask', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x01), 0, 4)) ?>");

document.body.setAttribute('data-ipv4-gateway', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x02), 0, 4)) ?>");
document.body.setAttribute('data-ipv4-dns', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x03), 0, 4)) ?>");
document.body.setAttribute('data-ipv4-dhcp-dns', <? echo envs_get_net_opt($envs, NET_OPT_AUTO_NS) ?>);

document.body.setAttribute('data-ipv6-enable', <? echo envs_get_net_opt($envs, NET_OPT_IP6) ?>);
document.body.setAttribute('data-ipv6-type', <? echo envs_get_net_opt($envs, NET_OPT_IP6_GUA) ?>);
document.body.setAttribute('data-ipv6-dhcp-dns', <? echo envs_get_net_opt($envs, IPV6_DHCP_DNS) ?>);
document.body.setAttribute('data-ipv6-eui', <? echo envs_get_net_opt($envs, NET_OPT_IP6_EUI) ?>);
document.body.setAttribute('data-ipv6-address', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP6, 0x00), 0, 16)) ?>");
document.body.setAttribute('data-ipv6-prefix', "<? echo bin2int(substr(envs_find($envs, ENV_CODE_IP6, 0x00), 16, 2), 0, 2) ?>");
document.body.setAttribute('data-ipv6-gateway', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP6, 0x02), 0, 16)) ?>");
document.body.setAttribute('data-ipv6-dns', "<? inet_ntop(substr(envs_find($envs, ENV_CODE_IP6, 0x03), 0, 16)) ?>");

document.body.setAttribute('data-wlan-status', "<? echo $wlan_status ?>");
document.body.setAttribute('data-wlan-enable', <? echo envs_get_net_opt($envs, NET_OPT_WLAN) ?>);
document.body.setAttribute('data-wlan-type', <? echo envs_get_net_opt($envs, NET_OPT_TSF) ?>);
document.body.setAttribute('data-wlan-channel', <? echo envs_get_net_opt($envs, NET_OPT_CH) ?>);
document.body.setAttribute('data-wlan-ssid', "<? echo substr($wlan_ssid_env, 0, (int)$wlan_ssid_pos) ?>");
document.body.setAttribute('data-wlan-emac-id', "<? echo $emac_id ?>");
document.body.setAttribute('data-wlan-ssid-raw', "<? echo bin2hex(substr($wlan_ssid_env, 0, (int)$wlan_ssid_pos)) ?>");
document.body.setAttribute('data-wlan-shared-key', "<? echo substr($wlan_shared_key_env, 0, (int)$wlan_shared_key_pos) ?>");
document.body.setAttribute('data-wlan-phy-mode', <? echo envs_get_net_opt($envs, NET_OPT_PHY) ?>);
document.body.setAttribute('data-wlan-short-preamble', <? echo envs_get_net_opt($envs, NET_OPT_SHORT_PRE) ?>);
document.body.setAttribute('data-wlan-short-slot', <? echo envs_get_net_opt($envs, NET_OPT_SHORT_SLOT) ?>);
document.body.setAttribute('data-wlan-cts-protection', <? echo envs_get_net_opt($envs, NET_OPT_CTS_PROT) ?>);
</script>
     
</body>
</html> 

