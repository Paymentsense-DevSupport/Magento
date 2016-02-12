
<?php
 
/* Port Checking PHP Script
   Created by PaymentSense
   Released: 01 Aug, 2011  */
 
echo '<title>Port Availability Checker';
//Please leave the next line :)
echo ', Writen by PaymentSense';
echo '</title>';
 
$addr = $_SERVER["REMOTE_ADDR"];
$port = "4430";
if ($_GET["addr"]) {
  $addr = $_GET["addr"];
}
if ($_GET["port"]) {
  $port = $_GET["port"];
}
if ($_GET["port2"]) {
  $port2 = $_GET["port2"];
}
 
 
echo '<form action="' .$_SERVER["PHP_SELF"]. '" method="get">
  <div style="width:300px;background:#f1f1f1;padding:10px;font-family:arial;">
    <table width="100%" border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td colspan="2" style="font-size:12px;">Please enter the Address/IP and port of the website or IP address you wish to test (enter the second IP if you want so scan to that port range)</td>
      </tr>
      <tr>
        <td width="30%" style="font-size:12px;">Address/IP</td>
        <td width="80%"><input type="text" name="addr" value="gw1.paymentsensegateway.com"></td>
      </tr>
      <tr>
        <td width="30%" style="font-size:12px;">Port</td>
        <td width="80%"><input type="text" name="port" value="' .$port. '"></td>
      </tr>
      <tr>
        <td width="30%" style="font-size:12px;">-</td>
        <td width="80%"><input type="text" name="port2" value="' .$port2. '"></td>
      </tr>
        <td width="30%">&nbsp;</td>
        <td width="80%"><input type="submit" value="Check/Scan Port(s)"></td>
      </tr>
    </table>
  </div>
</form>
';
 
if ($_GET["addr"]) {
  if ($_GET["port"] && !$_GET["port2"]) {
    $fp = @fsockopen($addr, $port, $errno, $errstr, 2);
    $success = "#FF0000";
    $success_msg = "is closed and cannot be used at this time";
    if ($fp) {
      $success = "#99FF66";
      $success_msg = "is open and ready to be used";
    }
    @fclose($fp);
    echo '<div style="width:300px;background:' .$success. ';padding:10px;font-family:arial;font-size:12px;">
    The address <b>"' .$addr. ':' .$port. '"</b> ' .$success_msg. '
  </div>';
  }
  else if ($_GET["port"] && $_GET["port2"]) {
    $p1 = $_GET["port"];
    $p2 = $_GET["port2"];
    if ($p1 == $p2) {
      $fp = @fsockopen($addr, $port, $errno, $errstr, 2);
      $success = "#FF0000";
      $success_msg = "is closed and cannot be used at this time";
      if ($fp) {
        $success = "#99FF66";
        $success_msg = "is open and ready to be used";
      }
      @fclose($fp);
      echo '<div style="width:300px;background:' .$success. ';padding:10px;font-family:arial;font-size:12px;">
      The address <b>"' .$addr. ':' .$port. '"</b> ' .$success_msg. '
      </div>';
    }
    else {
      if ($p1 < $p2) {
        $s = $p1;
        $st = $p1;
        $e = $p2;
      }
      else if ($p2 < $p1) {
        $s = $p2;
        $st = $p2;
        $e = $p1;
      }
      while ($s <= $e) {
        $fp = @fsockopen($addr, $s, $errno, $errstr, 1);
        if ($fp) {
          $p_open = $p_open. " " .$s;
          $p_1 = 1;
        }
        @fclose($fp);
        $s++;
      }
      if ($p_1) {
        $c = "#99FF66";
        $m = "On the address <b>" .$addr. "</b> and port range <b>" .$st. "-" .$e. "</b> the following ports were open: " .$p_open;
      }
      else {
        $c = "#FF0000";
        $m = "No ports on the address <b>" .$addr. "</b> and port range <b>" .$st. "-" .$e. "</b> were open";
      }
      echo '<div style="width:300px;background:' .$c. ';padding:10px;font-family:arial;font-size:12px;">' .$m. '</div>';
    }
  }
}
?>