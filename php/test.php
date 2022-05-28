#!/usr/bin/php
<?php
/* SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Copyright 2021 Erez Geva
 *
 * testing for php wrapper of libptpmgmt
 *
 * @author Erez Geva <ErezGeva2@@gmail.com>
 * @copyright 2021 Erez Geva
 *
 */

require("ptpmgmt.php");

class myDisp extends MessageDispatcher {
    function PRIORITY1_h($msg, $tlv, $tlv_id) {
        echo "Get reply for $tlv_id\n";
        echo "priority1: " . $tlv->priority1 . "\n";
    }
    function USER_DESCRIPTION_h($msg, $tlv, $tlv_id) {
        echo "Get reply for $tlv_id\n";
        echo "get user desc: " . $tlv->userDescription->textField . "\n";
    }
}
class myBuild extends MessageBulder {
    public int $pr;
    function PRIORITY1_b($msg, $tlv) {
        $tlv->priority1 = $this->pr;
        return true;
    }
}

const DEF_CFG_FILE = "/etc/linuxptp/ptp4l.conf";
$sk = new SockUnix();
$msg = new Message();
$buf = new Buf(1000);
$opt = new Options();
$dispacher = new myDisp();
$builder = new myBuild($msg);
$sequence = 0;

function nextSequence()
{
  # Ensure sequence in in range of unsigned 16 bits
  global $sequence;
  if(++$sequence > 0xffff)
    $sequence = 1;
  return $sequence;
}

function setPriority1($newPriority1)
{
  global $sk, $msg, $buf, $dispacher, $builder;
  $id = ptpmgmt::PRIORITY1;
  $builder->pr = $newPriority1;
  $builder->buildTlv(ptpmgmt::SET, $id);
  $seq = nextSequence();
  $err = $msg->build($buf, $seq);
  if($err != ptpmgmt::MNG_PARSE_ERROR_OK) {
    $txt = Message::err2str_c($err);
    echo "build error $txt\n";
  }
  if(!$sk->send($buf, $msg->getMsgLen())) {
    echo "send fail";
    return -1;
  }
  if(!$sk->poll(500)) {
    echo "timeout";
    return -1;
  }
  $cnt = $sk->rcvBuf($buf);
  if($cnt <= 0) {
    echo "rcv error $cnt";
    return -1;
  }
  $err = $msg->parse($buf, $cnt);
  if($err != ptpmgmt::MNG_PARSE_ERROR_OK || $msg->getTlvId() != $id ||
     $seq != $msg->getSequence()) {
    echo "set fails";
    return -1;
  }
  echo "set new priority $newPriority1 success\n";
  $msg->setAction(ptpmgmt::GET, $id);
  $seq = nextSequence();
  $err = $msg->build($buf, $seq);
  if($err != ptpmgmt::MNG_PARSE_ERROR_OK) {
    $txt = Message::err2str_c($err);
    echo "build error $txt\n";
  }
  if(!$sk->send($buf, $msg->getMsgLen())) {
    echo "send fail";
    return -1;
  }
  if(!$sk->poll(500)) {
    echo "timeout";
    return -1;
  }
  $cnt = $sk->rcvBuf($buf);
  if($cnt <= 0) {
    echo "rcv error $cnt";
    return -1;
  }
  $err = $msg->parse($buf, $cnt);
  if($err == ptpmgmt::MNG_PARSE_ERROR_MSG) {
    echo "error message";
  } else if($err != ptpmgmt::MNG_PARSE_ERROR_OK) {
    $txt = Message::err2str_c($err);
    echo "parse error $txt\n";
  } else {
    $dispacher->callHadler($msg);
  }
  return -1;
}

function main($cfg_file)
{
  global $sk, $msg, $buf, $dispacher;
  if(!$buf->isAlloc()) {
    echo "buffer allocation failed\n";
    return -1;
  }
  $cfg = new ConfigFile();
  if(!$cfg->read_cfg($cfg_file)) {
    echo "fail reading configuration file";
    return -1;
  }
  if(!$sk->setDefSelfAddress() || !$sk->init() || !$sk->setPeerAddress($cfg)) {
    echo "fail init socket";
    return -1;
  }
  $prms = $msg->getParams();
  $prms->self_id->portNumber = posix_getpid() & 0xffff;
  # Verify we can use implementSpecific_e;
  $prms->implementSpecific = ptpmgmt::linuxptp;
  $prms->domainNumber = $cfg->domainNumber();
  $msg->updateParams($prms);
  $msg->useConfig($cfg);
  $id = ptpmgmt::USER_DESCRIPTION;
  $msg->setAction(ptpmgmt::GET, $id);
  $seq = nextSequence();
  $err = $msg->build($buf, $seq);
  if($err != ptpmgmt::MNG_PARSE_ERROR_OK) {
    $txt = Message::err2str_c($err);
    echo "build error $txt\n";
    return -1;
  }
  if(!$sk->send($buf, $msg->getMsgLen())) {
    echo "send fail";
    return -1;
  }
  # You can get file descriptor with $sk->fileno() and use select;
  if(!$sk->poll(500)) {
    echo "timeout";
    return -1;
  }
  $cnt = $sk->rcvBuf($buf);
  if($cnt <= 0) {
    echo "rcv error $cnt\n";
    return -1;
  }
  $err = $msg->parse($buf, $cnt);
  if($err == ptpmgmt::MNG_PARSE_ERROR_MSG) {
    echo "error message";
  } else if($err != ptpmgmt::MNG_PARSE_ERROR_OK) {
    $txt = Message::err2str_c($err);
    echo "parse error $txt\n";
  } else {
    $dispacher->callHadler($msg);
  }

  # test setting values;
  $clk_dec = new CLOCK_DESCRIPTION_t();
  $clk_dec->clockType = 0x800;
  $physicalAddress = new Binary();
  $physicalAddress->setBin(0, 0xf1);
  $physicalAddress->setBin(1, 0xf2);
  $physicalAddress->setBin(2, 0xf3);
  $physicalAddress->setBin(3, 0xf4);
  echo "physicalAddress: " . $physicalAddress->toId() . "\n";
  echo "physicalAddress: " . $physicalAddress->toHex() . "\n";
  $clk_dec->physicalAddress->setBin(0, 0xf1);
  $clk_dec->physicalAddress->setBin(1, 0xf2);
  $clk_dec->physicalAddress->setBin(2, 0xf3);
  $clk_dec->physicalAddress->setBin(3, 0xf4);
  echo "clk.physicalAddress: " . $clk_dec->physicalAddress->toId() . "\n";
  echo "clk.physicalAddress: " . $clk_dec->physicalAddress->toHex() . "\n";;
  echo "manufacturerIdentity: " .
      Binary::bufToId($clk_dec->manufacturerIdentity, 3) . "\n";
  $clk_dec->revisionData->textField = "This is a test";
  echo "revisionData: " . $clk_dec->revisionData->textField . "\n";

  # test send;
  setPriority1(147);
  setPriority1(153);

  $event = new SUBSCRIBE_EVENTS_NP_t();
  $event->setEvent(ptpmgmt::NOTIFY_TIME_SYNC_get());
  echo "maskEvent(NOTIFY_TIME_SYNC)=" .
       $event->maskEvent(ptpmgmt::NOTIFY_TIME_SYNC_get()) .
       ", getEvent(NOTIFY_TIME_SYNC)=" .
       ($event->getEvent(ptpmgmt::NOTIFY_TIME_SYNC_get()) ? 'have' : 'not') . "\n" .
       "maskEvent(NOTIFY_PORT_STATE)=" .
       $event->maskEvent(ptpmgmt::NOTIFY_PORT_STATE_get()) .
       ", getEvent(NOTIFY_PORT_STATE)=" .
       ($event->getEvent(ptpmgmt::NOTIFY_PORT_STATE_get()) ? 'have' : 'not') . "\n";

  return 0;
}

if($opt->parse_options($argv) == Options_OPT_DONE) {
  $cfg_file = $opt->val('f');
  if($cfg_file == "") {
    $cfg_file = DEF_CFG_FILE;
  }
  echo "Use configuration file $cfg_file\n";
  main($cfg_file);
} else {
  echo "fail parsing command line";
}
$sk->close();

/*********************************************/
// Use "Dynamically loaded extensions"
// Enable "enable_dl = On" in /etc/php/*/cli/php.ini
//
// Run ./php_ini.sh to create php.ini
//
// If libptpmgmt and php wrapper libraries are not installed in system, run with:
// LD_LIBRARY_PATH=.. PHPRC=. ./test.php
?>
