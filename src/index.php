<?php
require_once "config.inc.php";
require_once "jssdk.php";

//检查是否HTTPS安全访问
$current_url=getCurrentUrl(true);
if( strtolower(substr($current_url,0,5)) == 'http:' ){
    $https_url='https'.substr($current_url,4);
    header("location: ".$https_url);
    exit(-1);
}

//自动检测来源客户端类型
$now_app_type = getClientType();

$jssdk = new JSSDK(WEIXIN_APP_ID, WEIXIN_APP_SECRET); //初始化微信JSSDK接口,以触发扫一扫功能
$signPackage = $jssdk->GetSignPackage();

//获取当前页面url
function getCurrentUrl($includeHost=true) 
{
   $url='';
   if($includeHost)
   {
       $arrayTmp=explode('/',$_SERVER['SERVER_PROTOCOL']);
       $url.= (isHttps()?'https':'http') .'://'.$_SERVER['HTTP_HOST'];
   }
   if (isset($_SERVER['REQUEST_URI'])) {
       $url .= $_SERVER['REQUEST_URI'];
   }
   else {
       $url .= $_SERVER['PHP_SELF'];
       $url .= empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING'];
   }
   return $url;
}

function isHttps(){
    if ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    {
        return TRUE;
    }
    elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    {
        return TRUE;
    }
    elseif ( ! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
    {
        return TRUE;
    }

    return FALSE;
}

function getClientType(){
    global $_SERVER;
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $now_app_type = '';
    if( strpos($ua, 'MicroMessenger') ) {
        $now_app_type='wepay';
    }elseif( strpos($ua, 'Alipay') ) {
        $now_app_type='alipay';
    }elseif( strpos($ua, 'QQ/') ) {
        $now_app_type='qqpay';
    }elseif( strpos($ua, 'imToken') ) {
        $now_app_type='imtoken'; 
    }elseif( strpos($ua, 'TokenPocket') ) {
        $now_app_type = 'tokenpocket';
    }elseif( strpos($ua, 'mathwallet') ) {
        $now_app_type = 'mathwallet';
    }elseif( strpos($ua, 'bitpie') ) {
        $now_app_type = 'bitpie';
    }else{
        //其它条件判断
        $array_headers = getallheaders();

        $x_requester = @$array_headers['X-Requested-With'];
        $x_cookie = @$array_headers['Cookie'];
        
        if ( strpos($ua, 'ppkpub') || strpos($x_requester, 'ppkpub')   ) {
            $now_app_type = 'ppkpub';
        }else if ( strpos($ua, 'bycoin') || strpos($x_requester, 'bycoin') || strpos($x_cookie, 'from=Bycoin;')!==false  ) {
            $now_app_type = 'bycoin';
        }
    }
    return $now_app_type;
}
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <title>PPk小工具 - PPk MicroTool v3</title>
    <meta content="ppkpub.org" name="author" />
    <meta content="PPk tool for MircoMsg include Scan&Login,ODIN register" name="description" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta content="IE=edge" http-equiv="X-UA-Compatible">

    <link rel="stylesheet" href="https://cdn.bootcss.com/weui/1.1.3/style/weui.min.css">
    <link rel="stylesheet" href="https://cdn.bootcss.com/jquery-weui/1.2.1/css/jquery-weui.min.css">
    <link rel="stylesheet" href="css/ppktool.css?20201226">
</head>
<body ontouchstart >

<div class="weui-tab">
  <div class="weui-tab__bd">
    <div id="tab_pay"  class="weui-tab__bd-item weui-tab__bd-item--active">
      <h1 class="demos-title">奥丁号收付款</h1>
      <br>
      <div id="history_odins_area" style="display:none;">
        <div id="history_odins" align="center"></div>
      </div>
      
      <input type="hidden" id="select_dest_odin" value="">
        
      <center>
      <div id="qrcode_payto">
        <center><img src="https://tool.ppkpub.org/image/blank.png" width="100" height="100"></center>
      </div>

      <div style="border: 0px solid; margin: 0 auto;" onclick="selectHistoryODIN();">
        <img id="qrCodeIcoPayTo" style="vertical-align:middle;width:32px;height:32px;border-radius:6px;" src="https://tool.ppkpub.org/image/user.png" alt="" >
        <span style="display: inline-block;vertical-align: middle;padding: 5px 0;font-size:12px;text-align: left;"><span id="dest_title" ></span><br><span id="payto_odin_uri" style="font-weight:bold;"></span><img src="images/edit.png" style="vertical-align:middle;" width="16" height="16"   alt="点击更换" ></span>
      </div>

      <h3>请使用数字钱包客户端扫码付款</h3>
      <p class="weui-msg__desc">可用以太坊imToken、比原Bycoin、支付宝、微信等APP</p>
      <p>
      <button class="weui-btn weui-btn_mini  weui-btn_primary" style="width: 45%;" id="btn_shareQrCode" onclick="shareQrCode( );">分享收款码</button> 
      <button class="weui-btn weui-btn_mini ppk-bg" style="width: 45%;"  id="btn_testQrCode" onclick="testQrCode( );">向奥丁号付款</button>
      <br>
      <a href="javascript:gotoSetting( );" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 32%;" target="_top">注册奥丁号</a> 
      <a href="javascript:gotoURL('https://tool.ppkpub.org/swap/','正在打开奥丁号拍卖交换工具' );" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 30%;"  target="_top">拍卖交换</a> 
      <a href="javascript:fastLoginPNS();" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 32%;" target="_top">关联多钱包</a>
      <br>
      <a href="javascript:gotoURL('http://47.114.169.156:9876/','正在打开奥丁号查询工具' );" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 32%;" target="_top">查询奥丁号</a> 
      <a href="https://ppk001.sinaapp.com/docs/help_ppkbrowser/" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 30%;"  target="_top">安卓应用</a> 
      <a href="javascript:openPPkBrowser();" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 32%;" target="_top">更多应用</a>
      </p>
    
      </center>
     
      <p><br></p>
      <p align="center" class="weui-footer__text">
      <a href="https://ppk001.sinaapp.com/docs/help_odin/" target="_blank">★ 奥丁号是数字资产和区块链应用的通用名和门牌号，点击了解</a>
      <br><br>
      <a href="https://ppk001.sinaapp.com/docs/help_odintool/" target="_blank">★ 如何注册和让自己的奥丁号关联多个钱包地址?</a>
      <br><br>
      <!--<a href="https://ppk001.sinaapp.com/docs/help_pns/" target="_blank">★ 如何托管奥丁号获得更多功能?</a>
      <br><br>-->
      <a href="https://ppk001.sinaapp.com/docs/help_ppkbrowser/" target="_blank">★ 通过PPk安卓应用来管理自己的奥丁号包括转移过户</a>
      </p>
      
      <br>
      <p align="center" class="weui-msg__desc">
      本应用来自开放分享，实际使用请遵守当地法律法规。<br>
      Released under the MIT License.<br>
      Please abide by local laws and regulations！
      </p>
      <br><br>

    </div>

    <div id="tab_scan" class="weui-tab__bd-item">
      <header class='demos-header'>
        <h1 class="demos-title">以奥丁号登录</h1>
      </header>
      
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">我的奥丁号</label>
        </div>
        <div class="weui-cell__bd">
          <input id="loginODIN"  class="weui-input" type="text" placeholder="请设置登录使用的奥丁号" readonly  onclick="javascript:changeCurrentODIN();">
        </div>
        <div class="weui-cell__ft">
          <button class="weui-vcode-btn" onclick="javascript:changeCurrentODIN();">换一个</button>
        </div>
      </div>
      
      <p><br><br></p>

      <a id="btn_scan_login" href="javascript:scanForLogin();" class="weui-btn weui-btn_primary" style="width: 80%;">扫一扫用奥丁号登录</a>
      <br>
      
      <a id="btn_test_pns" href="javascript:fastLoginPNS();" class="weui-btn weui-btn_default" style="width: 80%;">快速体验奥丁号托管服务(PNS)</a>
      <br>
      <p align="center" class="weui-footer__text"><a href="https://ppk001.sinaapp.com/docs/help_pns/">通过PNS+区块链，快速创建你的个人或企业链上名片，还有更多...</a></p>
      <!--<a id="btn_test_pns" href="https://ppk001.sinaapp.com/ap2/" class="weui-btn weui-btn_default" style="width: 80%;">快速体验标识托管服务(PNS)</a>-->
  
      
    </div>
    
    
    
    <div id="tab_setting" class="weui-tab__bd-item">
      <h1 class="demos-title">我的设置</h1>
      <br>
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">我的奥丁号</label>
        </div>
        <div class="weui-cell__bd">
          <input id="currentODIN"  class="weui-input" type="text" placeholder="请设置默认使用的奥丁号" readonly  onclick="javascript:changeCurrentODIN();">
        </div>
        <div class="weui-cell__ft">
          <button class="weui-vcode-btn" onclick="javascript:changeCurrentODIN();">换一个</button>
        </div>
      </div>
      
      <div class="weui-cell">
        <div class="weui-cell__hd">
          <label class="weui-label"></label>
        </div>
        <div class="weui-cell__bd">
          <img id="current_avatar" style="vertical-align:middle;width:32px;height:32px;border-radius:6px;" src="https://tool.ppkpub.org/image/user.png" alt=""><span id="current_title"></span> <img src="images/edit.png" style="vertical-align:middle;" width="16" height="16"   alt="编辑" onClick="fastLoginPNS();">
        </div>
      </div>
      
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">比特币地址</label>
        </div>
        <div class="weui-cell__bd">
          <input id="currentBtcAddress" class="weui-input weui-footer__text" type="text" placeholder="请设置你的比特币钱包地址" readonly>
        </div>
      </div>
      <div class="button_sp_area" align="right">
        <a href="javascript:newPrvKey();" class="weui-btn weui-btn_mini weui-btn_primary">生成新地址</a>
        <a href="javascript:importPrvkey(true);" class="weui-btn weui-btn_mini weui-btn_primary" id="btn_importPrvkey">导入已有地址</a>
        <a href="javascript:backupPrvkey();" class="weui-btn weui-btn_mini weui-btn_primary">备份地址私钥</a>
      </div>
      
      
      <div id="inputPrvkeyArea" style="display:none">
        <div>导入比特币地址私钥（以5,K或L起始的字符串）</div>
        <div class="weui-cells">
          <div class="weui-cell">
            <div class="weui-cell__bd">
              <input id="inputPrvkey" class="weui-input" type="text" placeholder="请在这里输入或粘贴要导入的比特币地址私钥" autocomplete="off" >
            </div>
          </div>
          
          <div align="center">
            <a href="javascript:confirmImportPrvkey();" class="weui-btn weui-btn_mini weui-btn_warn" id="btn_confirmImportPrvkey">确认导入</a>
          </div>
        </div>
      </div>
      
      <p class="weui-msg__desc">注意：请备份保存好自己的比特币地址私钥！退出后重新打开时，网页缓存可能会过期失效，需要重新导入所备份的比特币地址私钥才能使用。</p>
      
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">可用余额</label>
        </div>
        <div class="weui-cell__bd">
          <input id="addressBalance"  class="weui-input" type="text" value="" readonly  onclick="javascript:refreshAddressInfo();">
        </div>
        <div class="weui-cell__ft">
          <button class="weui-vcode-btn" onclick="javascript:refreshAddressInfo();">刷新</button>
        </div>
      </div>
      <p class="weui-msg__desc">提示：注册奥丁号只需要花费很少的矿工费用，余额有0.0001BTC就足够体验了。</p>
      
      <input id="newOdinTitle" class="weui-input" type="hidden" value="" >
      <!--
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">附注名称</label>
        </div>
        <div class="weui-cell__bd">
          <input id="newOdinTitle" class="weui-input" type="text" placeholder="请填写附注信息，也可以不填" >
        </div>
      </div>
      -->
      
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">交易费用</label>
        </div>
        <div class="weui-cell__bd">
          <input id="txFee"  class="weui-input" type="number" value="0.000005"  readonly>
        </div>
        <div class="weui-cell__ft">
          <button class="weui-vcode-btn" onclick="javascript:setTxFee();">修改</button>
        </div>
      </div>

      <p class="weui-msg__desc">提示：该交易费用支付给比特币的矿工以确认交易。一般情况下，注册交易被确认需要等待30分钟左右，如需更快确认可以将该费用适当调高。</p>
      <p align="center">
      <a id="btn_register" href="javascript:registerNewODIN();" class="weui-btn weui-btn_mini ppk-bg" style="width: 80%;" disabled=true>快速在比特币链上注册一个新奥丁号</a>     
      </p>
      <div class="weui-cells__title">当前地址相关奥丁号信息 <a href="javascript:viewRegisteredODIN();">查看更多>></a></div>
      <div class="weui-cells weui-cells_form">
          <div class="weui-cell">
            <div class="weui-cell__bd">
              <textarea id="addressSummary" class="weui-textarea weui-footer__text" placeholder="" rows="5" readonly></textarea>
            </div>
          </div>
      </div>
      <!--
      <p align="center"><a href="javascript:viewRegisteredODIN();" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 80%;">查看更多注册信息</a></p>
      -->
      <div id="hidden_data_area" style="display:none;">
          <textarea id="txUnspent" class="weui-textarea" placeholder="" rows="2"></textarea>
          <textarea id="txJSON" class="weui-textarea" placeholder="" rows="2"></textarea>
          <textarea id="txHex" class="weui-textarea" placeholder="" rows="2"></textarea>
      </div>

      <!--
      <p><br><br></p>
      
      <a id="btn_set_secinfo" href="javascript:setSecInfo();" class="weui-btn weui-btn_primary" style="width: 80%;">设置安全提示</a>
      <p><font size="-2">注：设置个人自定义的安全提示，有助于区分假冒工具和网页。</font></p>
      -->
      
      <div class="weui-cells__title">更多设置</div>
      <p align="center">
      <a id="btn_set_unlock" href="javascript:setUnlockPassword();" class="weui-btn  weui-btn_mini weui-btn_warn" style="width: 80%;">设置解锁密码</a>
      </p>
      <p class="weui-msg__desc">提示：你的个人数据（如比特币私钥）只在本机保存，设置解锁密码可以提高本地保存数据的安全性。设置解锁密码后，在管理比特币钱包、注册奥丁号和授权登录时，需要输入正确的解锁密码才能完成操作。</font></p>
      <p align="center" class="weui-footer__text"><br>
      <a href="https://ppk001.sinaapp.com/docs/help_odintool/" class="weui-btn weui-btn_mini weui-btn_plain-default" style="width: 80%;">关于本应用</a><br>
      PPk小工具网页版 - PPkTool Micro V0.3.20201226<br>
      <a href="http://ppkpub.org" class="weui-footer__link">PPk技术社区 PPkPub.org</a>
      <br>
      <br>
      <br>
      <br>
      </p>
    </div>
    
  </div>
  
<?php 
if($now_app_type == 'ppkpub'){
//在PPk浏览器里显示底部固定按钮会有问题,暂不显示
?>
  <!--<hr>
  <p align="center">
    <a href="#tab_pay">
    <img src="./images/icon_nav_pay.png" alt="收付款" width="32" height="32">
    </a> 
    <a href="#tab_scan">
    <img src="./images/icon_nav_login.png" alt="登录" width="32" height="32">
    </a> 
    <a href="javascript:openPPkBrowser();">
    <img src="./images/icon_nav_browser.png" alt="浏览" width="32" height="32">
    </a> 
    <a href="#tab_setting" >
    <img src="./images/icon_nav_user.png" alt="" width="32" height="32">
    </a>
  </p>
  -->
<?php 
}else{
?>
  <div class="weui-tabbar">
    <a href="#tab_pay" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_pay.png" alt="">
      </div>
      <p class="weui-tabbar__label">奥丁号收付款</p>
    </a>
    <a href="#tab_scan" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_login.png" alt="">
      </div>
      <p class="weui-tabbar__label">以奥丁号登录</p>
    </a>
    <a href="javascript:openPPkBrowser();" class="weui-tabbar__item" target="_top">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_browser.png" alt="">
      </div>
      <p class="weui-tabbar__label">浏览PPk网络</p>
    </a>
    
    <a href="#tab_setting" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img id="nav_icon_me" style="border-radius:6px;" src="./images/icon_nav_user.png" alt="">
      </div>
      <p class="weui-tabbar__label">我</p>
    </a>
  </div>
<?php  
}
?>
</div>



<script src="https://cdn.bootcss.com/jquery/2.1.4/jquery.min.js"></script>
<script src="js/bitcoinjs-min.js"></script>
<script src="js/rfc1751.js"></script>
<script src="js/mnemonic.js"></script>
<!--
<script src="js/armory.js"></script>
<script src="js/electrum.js"></script>
-->
<script src="js/tx.js"></script>
<script src="js/bitcoinsig.js"></script>
<script src="js/secure-random.js"></script>
<script src="js/asn1.js"></script>
<!--<script src="js/odinwallet.js"></script>-->
<script src="js/js.js"></script>
<script src="js/strength.js"></script>

<script src="js/crypt-aes.js"></script>

<script src="js/fastclick.js"></script>

<script src="js/crypt-md5.js"></script>
<script src="https://ppk001.sinaapp.com/ppk-lib2/js/0.1.2b/common_func.js"></script>
<script src="https://ppk001.sinaapp.com/ppk-lib2/js/0.1.2b/ppk.js"></script>

<script src="https://ppk001.sinaapp.com/ppk-lib2/js/common/jquery.qrcode.min.js"></script>

<script>
  $(function() {
    FastClick.attach(document.body);
  });
</script>
<script src="https://cdn.bootcss.com/jquery-weui/1.2.1/js/jquery-weui.min.js"></script>

<script src="https://res.wx.qq.com/open/js/jweixin-1.4.0.js"></script>
<script>
  const HISTORY_KEY="history-appdemo-ppk001-dest";
  const HISTORY_MAX_SIZE=5;
  const APPDEMO_MARK = "PPkAppDemo(http://ppk001.sinaapp.com/odin/)";
  const APP_PAY_PREFIX = "https://ppk001.sinaapp.com/demo/pay/";
  
  const IMG_LOADING_SVG = "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcgdD0iMTYwODg5NzMwOTI1NCIgY2xhc3M9Imljb24iIHZpZXdCb3g9IjAgMCAxMDI0IDEwMjQiIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiBwLWlkPSIyMDM3IiB3aWR0aD0iMzIiIGhlaWdodD0iMzIiPjxwYXRoIGQ9Ik04NDMuMzA3IDc0Mi4yNGMwIDMuMjE3IDIuNjA3IDUuODI0IDUuODI0IDUuODI0czUuODI0LTIuNjA3IDUuODI0LTUuODI0YTUuODIzIDUuODIzIDAgMCAwLTUuODI0LTUuODI0IDUuODIzIDUuODIzIDAgMCAwLTUuODI0IDUuODI0ek03MTQuNzMxIDg3NC45MTJjMCA2LjM5OCA1LjE4NiAxMS41ODQgMTEuNTg0IDExLjU4NHMxMS41ODQtNS4xODYgMTEuNTg0LTExLjU4NC01LjE4Ni0xMS41ODQtMTEuNTg0LTExLjU4NC0xMS41ODQgNS4xODYtMTEuNTg0IDExLjU4NHpNNTQxLjQxOSA5NDMuMmMwIDkuNjE0IDcuNzk0IDE3LjQwOCAxNy40MDggMTcuNDA4czE3LjQwOC03Ljc5NCAxNy40MDgtMTcuNDA4LTcuNzk0LTE3LjQwOC0xNy40MDgtMTcuNDA4LTE3LjQwOCA3Ljc5NC0xNy40MDggMTcuNDA4eiBtLTE4Ni41Ni05LjE1MmMwIDEyLjc5NSAxMC4zNzMgMjMuMTY4IDIzLjE2OCAyMy4xNjhzMjMuMTY4LTEwLjM3MyAyMy4xNjgtMjMuMTY4LTEwLjM3My0yMy4xNjgtMjMuMTY4LTIzLjE2OC0yMy4xNjggMTAuMzczLTIzLjE2OCAyMy4xNjh6TTE4OS4zNTUgODQ5LjEyYzAgMTYuMDEyIDEyLjk4IDI4Ljk5MiAyOC45OTIgMjguOTkyczI4Ljk5Mi0xMi45OCAyOC45OTItMjguOTkyLTEyLjk4LTI4Ljk5Mi0yOC45OTItMjguOTkyLTI4Ljk5MiAxMi45OC0yOC45OTIgMjguOTkyek03NC43MzEgNzA0LjczNmMwIDE5LjIyOCAxNS41ODggMzQuODE2IDM0LjgxNiAzNC44MTZzMzQuODE2LTE1LjU4OCAzNC44MTYtMzQuODE2LTE1LjU4OC0zNC44MTYtMzQuODE2LTM0LjgxNi0zNC44MTYgMTUuNTg4LTM0LjgxNiAzNC44MTZ6IG0tNDMuMDA4LTE3Ny4yOGMwIDIyLjQxIDE4LjE2NiA0MC41NzYgNDAuNTc2IDQwLjU3NnM0MC41NzYtMTguMTY2IDQwLjU3Ni00MC41NzYtMTguMTY2LTQwLjU3Ni00MC41NzYtNDAuNTc2LTQwLjU3NiAxOC4xNjYtNDAuNTc2IDQwLjU3NnogbTM1LjM5Mi0xNzYuMTI4YzAgMjUuNjI2IDIwLjc3NCA0Ni40IDQ2LjQgNDYuNHM0Ni40LTIwLjc3NCA0Ni40LTQ2LjRjMC0yNS42MjYtMjAuNzc0LTQ2LjQtNDYuNC00Ni40LTI1LjYyNiAwLTQ2LjQgMjAuNzc0LTQ2LjQgNDYuNHogbTEwNi4xNzYtMTQyLjAxNmMwIDI4Ljg0MyAyMy4zODEgNTIuMjI0IDUyLjIyNCA1Mi4yMjRzNTIuMjI0LTIzLjM4MSA1Mi4yMjQtNTIuMjI0YzAtMjguODQzLTIzLjM4MS01Mi4yMjQtNTIuMjI0LTUyLjIyNC0yOC44NDMgMC01Mi4yMjQgMjMuMzgxLTUyLjIyNCA1Mi4yMjR6IG0xNTUuOTA0LTgxLjM0NGMwIDMyLjAyNCAyNS45NiA1Ny45ODQgNTcuOTg0IDU3Ljk4NHM1Ny45ODQtMjUuOTYgNTcuOTg0LTU3Ljk4NC0yNS45Ni01Ny45ODQtNTcuOTg0LTU3Ljk4NC01Ny45ODQgMjUuOTYtNTcuOTg0IDU3Ljk4NHogbTE3NS4xMDQtNS4wNTZjMCAzNS4yNCAyOC41NjggNjMuODA4IDYzLjgwOCA2My44MDhzNjMuODA4LTI4LjU2OCA2My44MDgtNjMuODA4YzAtMzUuMjQtMjguNTY4LTYzLjgwOC02My44MDgtNjMuODA4LTM1LjI0IDAtNjMuODA4IDI4LjU2OC02My44MDggNjMuODA4eiBtMTYwLjMyIDcyLjEyOGMwIDM4LjQyMSAzMS4xNDcgNjkuNTY4IDY5LjU2OCA2OS41NjhzNjkuNTY4LTMxLjE0NyA2OS41NjgtNjkuNTY4LTMxLjE0Ny02OS41NjgtNjkuNTY4LTY5LjU2OC02OS41NjggMzEuMTQ3LTY5LjU2OCA2OS41Njh6IG0xMTMuOTIgMTM1LjQ4OGMwIDQxLjYzOCAzMy43NTQgNzUuMzkyIDc1LjM5MiA3NS4zOTJzNzUuMzkyLTMzLjc1NCA3NS4zOTItNzUuMzkyLTMzLjc1NC03NS4zOTItNzUuMzkyLTc1LjM5Mi03NS4zOTIgMzMuNzU0LTc1LjM5MiA3NS4zOTJ6IG00NS4zMTIgMTc1LjQ4OGMwIDQ0Ljg1NCAzNi4zNjIgODEuMjE2IDgxLjIxNiA4MS4yMTZzODEuMjE2LTM2LjM2MiA4MS4yMTYtODEuMjE2YzAtNDQuODU0LTM2LjM2Mi04MS4yMTYtODEuMjE2LTgxLjIxNi00NC44NTQgMC04MS4yMTYgMzYuMzYyLTgxLjIxNiA4MS4yMTZ6IiBmaWxsPSIjMTI5NmRiIiBwLWlkPSIyMDM4Ij48L3BhdGg+PC9zdmc+";

  var mObjWallets;
  var mCurrentQrCodeText="";
  var mSelectingAddress=false;
  var mRefreshAfterCloseAddessList=false;

  var gen_from = 'pass';
  var gen_compressed = true;
  var gen_eckey = null;
  var gen_pt = null;
  var gen_ps_reset = false;
  var TIMEOUT = 600;
  var gObjTimeout = null;

  var PUBLIC_KEY_VERSION = 0;
  var PRIVATE_KEY_VERSION = 0x80;
  var ADDRESS_URL_PREFIX = 'http://blockchain.info'

  var gBoolWeixinScanEnabled=false;
  
  var gStrCurrentODIN="";
  var gStrCurrentAddress;
  var gStrCurrentPrvkeyEncrypted="";
  
  var gBoolEncrypted=false;
  var gStrUnlockPassword="";
  
  var gStrTempPrvkey="";
  var gObjTempKey=null;

  var gStrLastRegisteredODIN="";


   /*
   * 注意：
   * 1. 涉及微信JS接口只能在公众号绑定的域名下调用，公众号开发者需要先登录微信公众平台进入“公众号设置”的“功能设置”里填写“JS接口安全域名”。
   * 2. 如果发现在 Android 不能分享自定义内容，请到官网下载最新的包覆盖安装，Android 自定义分享接口需升级至 6.0.2.58 版本及以上。
   * 3. 常见问题及完整 JS-SDK 文档地址：http://mp.weixin.qq.com/wiki/7/aaa137b55fb2e0456bf8dd9148dd613f.html
   */
  wx.config({
    debug: false,
    appId: '<?php echo $signPackage["appId"];?>',
    timestamp: <?php echo $signPackage["timestamp"];?>,
    nonceStr: '<?php echo $signPackage["nonceStr"];?>',
    signature: '<?php echo $signPackage["signature"];?>',
    jsApiList: [
      'checkJsApi', 'scanQRCode'
    ]
  });
  
  wx.error(function(res) {
        alert("出错了：" + res.errMsg);//这个地方的好处就是wx.config配置错误，会弹出窗口哪里错误，然后根据微信文档查询即可。
    });

    
  wx.ready(function() {
        wx.checkJsApi({
            jsApiList : ['scanQRCode'],
            success : function(res) {
                //alert("支持在这里扫一扫");
                gBoolWeixinScanEnabled=true;
            }
        });
        /*
        wx.updateTimelineShareData({ 
            title: 'title', // 分享标题
            link: 'https://ppk001.sinasapp.com/odin/', // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
            imgUrl: 'https://ppk001.sinaapp.com/odin/images/avatar3.jpg', // 分享图标
            success: function () {
              // 设置成功
              commonAlert("设置成功");
            }
        });
        */
    });
  
  
 
  $(document).ready( function() {
      restoreLocalSetting();

      var login_confirm_url=getQueryString('login_confirm_url');
      //alert("login_confirm_url="+login_confirm_url);
      if(login_confirm_url!=null && login_confirm_url.length>0){//传入有效登录参数不需要再扫码获取的情况
          promptConfirmLogin(login_confirm_url);
      }
      
      var my_odin_uri = getQueryString('me');
      if(my_odin_uri!=null && my_odin_uri.length>0){
        my_odin_uri=PPKLIB.formatPPkURI(my_odin_uri,true);
        if(my_odin_uri!=null){
            gStrCurrentODIN = my_odin_uri;
            $('#currentODIN').val(gStrCurrentODIN);
            $('#loginODIN').val(gStrCurrentODIN);
        }
      }
      /*
      //控制头像图标的位置
      var qrcode_convas_rect = $('#qrcode_payto')[0].children[0].getBoundingClientRect();
      var left = qrcode_convas_rect.left + qrcode_convas_rect.width/2 - 32/2;
      var top = qrcode_convas_rect.top + qrcode_convas_rect.height/2 - 32/2-2;
      $("#dest_title").before('<img id="qrCodeIcoPayTo" style="vertical-align:middle;position:absolute;z-index:999;width:32px;height:32px;left:'+left+'px;top:'+top+'px;border-radius:3px;background-color: #fff;background-blend-mode: multiply;" src="https://tool.ppkpub.org/image/user.png" alt="">');
      */
      $("#select_dest_odin").select({
        title: "选择收款人",
        input:"",
        items: [{
                title: "请先输入有效的收款人奥丁号",
                value: "",
               }],
        onOpen: function ( ) {
          mSelectingAddress = true;
        },
        onChange: function(d) {
          if( typeof(d.values)!='undefined' ){
              console.log("onChange dest_wallet_address:  ", d.values);
              if(d.values==""){
                  inputPaytoODIN();
              }else{
                  $("#payto_odin_uri").html( d.values );
                  refreshPaytoInfo( );  
              }
          }
        },
        onClose: function (d) {
          /*if( typeof(d.data.values)!='undefined' ){
              console.log('selected dest_wallet_address:', d.data.values);
              document.getElementById("payto_odin_uri").value= d.data.values;
          }*/

          mSelectingAddress = false;

          //refreshPaytoInfo( );  
        }
      });
              
     meAsDest();
  });
  
function openPPkBrowser(){
  //判断当前微信环境是否为小程序
  if(window.__wxjs_environment==='miniprogram'){   
    console.log("在小程序");
    $.alert("小程序里暂时无法浏览PPk网络。<br>请等待后续新版本升级...");
  }else{
    $.alert("正在打开PPk浏览工具...");
    location.href="http://ppk001.sinaapp.com/demo/browser/?back=https://ppk001.sinaapp.com/odin/";
  }  
}

function refreshPaytoInfo( ){
    mCurrentQrCodeText = "";

    //$("#dest_title").html("");
    $("#dest_title").html("请输入奥丁号，以生成收款码") ;

    var str_dest = $("#payto_odin_uri").html();
    if(str_dest.length==0){
        return;
    }
    
    var dest_odin_uri=PPKLIB.formatPPkURI(str_dest,true);
    
    if(dest_odin_uri==null){
        $("#dest_title").html("请输入有效的奥丁号！") ;
        return;
    }
    
    $("#payto_odin_uri").val(dest_odin_uri)
    

    //更新输入历史
    try{
        var historyArray = getHistoryDest();

        var exist = historyArray.indexOf(dest_odin_uri);
        if(exist<0){
            historyArray.push(dest_odin_uri);
        }
        
        if(historyArray.length>HISTORY_MAX_SIZE){
            historyArray.splice(1, historyArray.length-HISTORY_MAX_SIZE);
        }

        saveLocalConfigData(HISTORY_KEY,JSON.stringify(historyArray));
    } catch (error) {
        console.error(error);
    }
    
    genQrCode();
}

function genQrCode(  ){
    //waitingButton("btn_refreshQrCode");
    var dest_odin_uri = $("#payto_odin_uri").html();
    var dest_title = $("#dest_title").html().trim();
    if( dest_title.trim().length==0 ){
        dest_title="收款人";
    }

    //重置二维码
    clearOldPaytoCode(true);
    
    $("#dest_title").html("二维码生成中...")
    //$("#qrcode_payto").html("二维码生成中...");
    
    var use_cache=false;
    PPKLIB.getPPkData(dest_odin_uri,paytoPPkDataCallback, use_cache);

    //finishedButton("btn_refreshQrCode");
}

function clearOldPaytoCode(boolShowLoading){
    if(boolShowLoading){
        $("#qrCodeIcoPayTo").attr('src',IMG_LOADING_SVG);
        
        $('#qrcode_payto').html('<center><img src="https://tool.ppkpub.org/image/blank.png" width="100" height="100"></center>');
    }else{
        if(typeof($('#qrcode_payto')[0].children[0]) != 'undefined'){
            $('#qrcode_payto')[0].children[0].remove();
        }
    }  
}

function paytoPPkDataCallback(status,result){
    var bool_get_data_ok = false;
    var dest_title = "收款人";
    var dest_avatar = "https://tool.ppkpub.org/image/user.png";
    if('OK'==status){
        try{
            var obj_pttp_data = parseJsonObjFromAjaxResult(result);
            //document.getElementById("debug_data").value=JSON.stringify(obj_pttp_data);
            var tmp_content = PPKLIB.getContentFromData(obj_pttp_data);
            //document.getElementById("debug_data").value=tmp_str;
            
            var obj_content = JSON.parse( tmp_content );
            
            if(typeof(obj_content) == 'undefined' || obj_content==null){
                dest_title = "不存在的标识或者解析有误，请刷新下试试！";
            }else if(typeof(obj_content.x_did) != 'undefined' ){
                var obj_did = obj_content.x_did;
                if(typeof(obj_did.name) != 'undefined' )
                    dest_title = obj_did.name;
                
                if(typeof(obj_did.avatar) != 'undefined' ){
                    console.log("obj_did.avatar="+obj_did.avatar);
                    
                    dest_avatar = obj_did.avatar;
                }
                
                bool_get_data_ok=true;
            }
        }catch(error){
            console.log("paytoPPkDataCallback() error:"+error);
            dest_title = "收款人信息有误，请重试！";
        }
    }else{
        dest_title = "获取收款人信息出错了，请重试！";
    }

    $("#dest_title").html(dest_title);
    $("#qrCodeIcoPayTo").attr('src',dest_avatar);
    
    if(bool_get_data_ok){
        refreshQrCode();
        
        if($("#payto_odin_uri").html()==gStrCurrentODIN){
            $('#current_avatar').attr("src",dest_avatar);
            $('#nav_icon_me').attr("src",dest_avatar);
            $('#current_title').html(dest_title);
        }
    }
}

function refreshQrCode(  ){
    //waitingButton("btn_refreshQrCode");
    var dest_odin_uri = $("#payto_odin_uri").html();
    var dest_title = $("#dest_title").html().trim();
    if( dest_title.trim().length==0 ){
        dest_title="收款人";
    }

    //清除旧二维码
    clearOldPaytoCode(false);
    
    //生成二维码
    mCurrentQrCodeText = APP_PAY_PREFIX + '?ppkpayto=' + encodeURIComponent(dest_odin_uri) + '&title=' + encodeURIComponent(dest_title)  ;

    $('#qrcode_payto').qrcode({width: 100,height: 100,text: mCurrentQrCodeText});
    
    $("#dest_title").html(dest_title);
    
}


function inputPaytoODIN(){
  $.prompt({
      title: '换一个收款奥丁号',
      text: '请输入数字或英文名称的奥丁号',
      input: "",
      empty: false, // 是否允许为空
      
      onOK: function (input) {
        var dest_odin_uri=PPKLIB.formatPPkURI(input.trim(),true);
        if( dest_odin_uri.startsWith("ppk:")){
            $("#payto_odin_uri").html(dest_odin_uri);
            refreshPaytoInfo();
            return true;
        }else{
            alert("请输入正确的奥丁号");
            return false;
        }
      },
      onCancel: function () {
        //点击取消
      }
    });   
}

function inputPaytoTitle(){
  var dest_odin_uri = $("#payto_odin_uri").html();
  if( dest_odin_uri.trim().length==0 ){
      //尚未设置有效奥丁号
      inputPaytoODIN(); 
  }else{
      $.prompt({
          title: '修改显示的收款人名称',
          text: '请输入临时名称，方便截图和分享',
          input: $("#dest_title").html(),
          empty: false, // 是否允许为空
          onOK: function (input) {
            $("#dest_title").html(input.trim());
            refreshQrCode();
            return true;
          },
          onCancel: function () {
            //点击取消
          }
        });   
  }
}

function selectHistoryODIN(){
    if(mSelectingAddress){
       $("#select_dest_odin").select("close");
       return false;   
    }

    var tmp_address_array = [];
    
    tmp_address_array[0]={
                    title:'输入新的收款奥丁号',
                    value:""
                };
                
    if( gStrCurrentODIN!=null && gStrCurrentODIN.length>0 ) {
        tmp_address_array[1]={
                    title:'我( ' + gStrCurrentODIN +' )',
                    value:gStrCurrentODIN
                };
    }
    
    var historyArray=getHistoryDest();
    
    for(kk=historyArray.length-1;kk>=0;kk--){
        var tmp_odin = historyArray[kk];
        
        if( tmp_odin != gStrCurrentODIN ) {
            tmp_address_array[tmp_address_array.length]={
                    title:tmp_odin,
                    value:tmp_odin
                };
        }
        
        
    }
    
    $("#select_dest_odin")
        .select(
            "update", 
            {
                input:"点击选择奥丁号生成收款二维码",
                items: tmp_address_array 
            }
        );
    
    $("#select_dest_odin").select("open");
}

function testQrCode(){
    if(mCurrentQrCodeText.length>0){
        $("#btn_testQrCode").html("正在打开...");
        location.href = mCurrentQrCodeText;
    }else
        commonAlert("请先输入有效的收款人奥丁号");
}

function shareQrCode(){
    if(mCurrentQrCodeText.length>0){
        $("#btn_shareQrCode").html("正在生成...");
        location.href = APP_PAY_PREFIX + "qr/index32.html?ppkpayto="+ encodeURIComponent($("#payto_odin_uri").html()) + "&title=" + encodeURIComponent($("#dest_title").html())+ "&avatar=" + encodeURIComponent($("#qrCodeIcoPayTo").attr("src"));
    }else
        commonAlert("请先输入有效的收款人奥丁号");
}

function gotoSetting(){
    //<div id="tab_pay"  class="weui-tab__bd-item weui-tab__bd-item--active">
    $("#tab_pay").removeClass("weui-tab__bd-item--active");
    $("#tab_scan").removeClass("weui-tab__bd-item--active");
    $("#tab_setting").addClass("weui-tab__bd-item--active");
}

/*
function generateQrCodeImg(str_qr_code){
    var typeNumber = 0;
    var errorCorrectionLevel = 'H';
    var qr = qrcode(typeNumber, errorCorrectionLevel);
    qr.addData(str_qr_code);
    qr.make();
    document.getElementById('qrcode_payto').innerHTML = qr.createImgTag();
}
*/

function meAsDest(){
    $("#payto_odin_uri").html( gStrCurrentODIN );
    refreshPaytoInfo( );
}


function useHistoryDest(str_old_odin){
    document.getElementById('history_odins_area').style.display="none";
    $("#payto_odin_uri").html( str_old_odin );
    refreshPaytoInfo(false);
}

function getHistoryDest( ){
    try {
        var historyStr=getLocalConfigData(HISTORY_KEY);
        //myAlert(historyStr);
        if(historyStr==null){
            return new Array();
        }else{
            return JSON.parse(historyStr);
        }
    } catch (error) {
      console.error(error);
      return new Array();
    }
}

  function scanForLogin(){
      if(!gBoolWeixinScanEnabled){
        alert("请切换使用微信主界面的扫一扫！");
        return;
      }
      
      var loginODIN=$('#loginODIN').val();
      if(loginODIN.length==0){
          changeCurrentODIN();
          return;
      }
      
      wx.scanQRCode({
        needResult : 1, // 默认为0，扫描结果由微信处理，1则直接返回扫描结果，
        scanType : [ "qrCode"], // 可以指定扫二维码还是一维码，默认二者都有
        success : function(res) {
          var loginURL = res.resultStr; // 当needResult 为 1 时，扫码返回的结果
          
          //$('#loginURL').val(result);
          //$('#sgMsg').val(result+", login as "+$('#loginODIN').val());
          
          /*
          $.login({
              title: '请确认登录下述网址',
              text: '内容文案',
              username: 'tom',  // 默认用户名
              password: 'tom',  // 默认密码
              onOK: function (username, password) {
                //点击确认
              },
              onCancel: function () {
                //点击取消
              }
            });
            */
           promptConfirmLogin(loginURL);
        }
      });
  }
  
  function registerNewODIN(){
      var currentBtcAddressess=$('#currentBtcAddress').val();
      if(currentBtcAddressess.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币钱包地址',
              onOK: function () {
                importPrvkey();
              },
              onCancel: function () {
              }
            });
            
         return;
      }
      
      var balance = parseFloat($('#addressBalance').val());
      if(isNaN(balance)){
          $.alert('请等待获得有效余额后重试','未获得余额');
          return;
      }

      if(balance<0.00003){
         $.alert('当前地址的比特币余额不足0.00003 BTC<br>请确认或充值 0.0001 BTC后重试','余额不足');
      }else if(balance>0.0002){
         $.alert('当前地址余额超过了0.0002 BTC<br>请调低余额或使用新地址再重试！<br>提示：注册奥丁号只花费很少的矿工费用，一般有0.0001BTC就足够体验，注册多个奥丁号了。<br>出于安全考虑，在此工具里所使用地址的余额不要超过0.0002BTC，且单个地址不要注册太多奥丁号（不超过20个为宜）！ 如有更多功能需求，建议使用PPk安卓应用。','安全提示');
      }else{
        unlockLocalPrivateData(function(){
              buildRegsiterTransaction();
            }
        );
        
      }
  }
  
  function buildRegsiterTransaction() {
    var addr = $('#currentBtcAddress').val();
    var unspent = $('#txUnspent').val();
    var balance = parseFloat($('#addressBalance').val());
    var fee = parseFloat('0'+$('#txFee').val());
    
    if(isNaN(balance) || balance < 0.00003){
      $.alert('请确认余额足够后重试','无效余额');
      return;
    }
    
    if(isNaN(fee) || fee < 0.000005){
      $.alert('请设置有效的矿工费用！','无效的交易费用');
      return;
    }

    try {
        var res = parseBase58Check(gStrTempPrvkey); 
        var version = res[0];
        var payload = res[1];
    } catch (err) {
        $('#txJSON').val('');
        $('#txHex').val('');
        return;
    }

    var compressed = false;
    if (payload.length > 32) {
        payload.pop();
        compressed = true;
    }

    var eckey = new Bitcoin.ECKey(payload);

    eckey.setCompressed(compressed);

    TX.init(eckey);
    
    //var curve = getSECCurveByName("secp256k1");
    //var gen_pt = curve.getG().multiply(eckey.priv);
    //var pubkey = getEncoded(gen_pt, compressed);
    var pubkey_hex=Crypto.util.bytesToHex(eckey.getPub());
    //console.log.log('pubkey_hex=',pubkey_hex);

    var fval = 0;
    var o = txGetODINOutputScripts(pubkey_hex);
    for (i in o) {
        TX.addOutputScript(o[i].script, o[i].fval);
        fval += o[i].fval;
    }

    // send change back or it will be sent as fee
    if (balance > fval + fee) {
        var change = balance - fval - fee;
        TX.addOutput(addr, change);
    }

    //try {
        var sendTx = TX.construct();
        var txJSON = TX.toBBE(sendTx);
        var buf = sendTx.serialize();
        var txHex = Crypto.util.bytesToHex(buf);
        //setErrorState($('#txJSON'), false, '');
        $('#txJSON').val(txJSON);
        $('#txHex').val(txHex);
    /*} catch(err) {
        alert('err=',err);
        $('#txJSON').val('');
        $('#txHex').val('');
    }*/
    if($('#txHex').val()==""){
        //$('#txSend').attr('disabled', true);
    }else{
        //$('#txSend').attr('disabled', false);
        sendTX();
    }
  }
  
  function sendTX() {
    var txAddr = $('#currentBtcAddress').val();

    var r = '';
    if (txAddr!='' && txAddr!=TX.getAddress())
        r += 'Warning! Source address does not match private key.\n\n';

    var tx = $('#txHex').val();

    url = 'https://blockchain.info/pushtx?cors=true';
    
    // alternatives are:
    // http://eligius.st/~wizkid057/newstats/pushtxn.php (supports non-standard transactions)
    // http://bitsend.rowit.co.uk (defunct)
    // https://btc.com/tools/tx/publish
    // https://insight.bitpay.com/tx/send

    //url = prompt(r + 'Press OK to send transaction to:', url);

    if (url != null && url != "") {
        $('#btn_register').html('<div class="weui-loadmore"><i class="weui-loading"></i><span class="weui-loadmore__tips">正在发送注册交易</span></div>');
        
        $.post(url, { "tx": tx }, function(data) {
          $('#btn_register').html('注  册');
          
          $.alert( data.responseText? data.responseText:'',"注册交易已发送，请等待30分钟后查看");
          
          setTimeout(refreshAddressInfo, 3000); //等待3秒刷新地址信息
        }).fail(function(jqxhr, textStatus, error) {
          $('#btn_register').html('注  册');
           
          $.alert(
            typeof(jqxhr.responseText)=='undefined' ? jqxhr.statusText
            : ( jqxhr.responseText!='' ? jqxhr.responseText : 'No data, probably Access-Control-Allow-Origin error.'),
            "注册交易发送出错，请确认余额大于0.00003 BTC后重试"
          );
          
          refreshAddressInfo();
        });

    }

    return false;
  }

  //将指定ODIN数据字符串构建为多重签名输出数据块
  function txGetODINOutputScripts( register_pubkey_hex ){
    var res = [];

    PPK_ODIN_MARK_PUBKEY_HEX="0320a0de360cc2ae8672db7d557086a4e7c8eca062c0a5a4ba9922dee0aacf3e12"; //Mainet marker

    PPK_PUBKEY_TYPE_FLAG_HEX='03';  //ODIN协议承载消息内容使用的公钥类型前缀字符（16进制）
    PPK_PUBKEY_LENGTH=33;  //ODIN协议承载消息内容使用的单条公钥长度
    PPK_PUBKEY_EMBED_DATA_MAX_LENGTH=31;  //ODIN协议在单条公钥中最多嵌入的消息数据长度

    MAX_MULTISIG_TX_NUM = 1; //一条交易里能支持的最大数量多重签名条目
    MAX_N = 2;  //单个1ofN多重签名输出中最多允许的公钥数量N取值
    MIN_UNSPENT_NUM = 1;  //最少作为输入需要的未使用交易记录数量
    MIN_DUST_AMOUNT = 1000;  //最小有效交易金额,单位satoshi，即0.00000001 BTC
    MIN_DUST_FLOAT_VALUE = 0.00001000;  //最小有效交易金额,单位BTC
    MAX_OP_RETURN_LENGTH = 75;   //OP_RETURN能存放数据的最大字节数
    MAX_ODIN_DATA_LENGTH=(MAX_N-2)*PPK_PUBKEY_EMBED_DATA_MAX_LENGTH+(MAX_N-1)*PPK_PUBKEY_EMBED_DATA_MAX_LENGTH*(MAX_MULTISIG_TX_NUM-1)+MAX_OP_RETURN_LENGTH;  //支持嵌入的ODIN数据最大字节数

    OP_1=81;
    OP_RETURN=106;
    OP_CHECKMULTISIG=174;      

    //组织ODIN注册信息数据块
    var max_user_input_length = MAX_OP_RETURN_LENGTH - 'RTX{"ver":1,"title":"","auth":"0"}'.length;
    console.log('max_user_input_length=',max_user_input_length);

    var str_title_encoded=encodeURI($('#newOdinTitle').val(),"utf-8");
    if(str_title_encoded.length>max_user_input_length){
        str_title_encoded=str_title_encoded.substr(0,max_user_input_length);
    }

    var str_odin_setting='{"ver":1,"title":"'+str_title_encoded+'","auth":"0"}';
    console.log('str_odin_setting=',str_odin_setting);
    var str_odin_msg = 'RT'
          + String.fromCharCode(str_odin_setting.length%253)
          + str_odin_setting;  //组织ODIN注册信息
    console.log('str_odin_msg=',str_odin_msg);
    console.log('LENGTH=',str_odin_msg.length);
        
    //if(str_odin_msg.length>MAX_ODIN_DATA_LENGTH){
    //  console.log('str_odin_msg should be less than ',MAX_ODIN_DATA_LENGTH);
    //  return;
    //}

    //将原始字节字符串转换为用16进制表示
    var str_odin_hex=stringToHex(str_odin_msg);
    //console.log.log('str_odin_hex=',str_odin_hex);

    //构建1ofN多重签名输出来嵌入自定义的ODIN标识注册数据(N取值由配置参数MAX_N决定)
    var multisig_tx_num=0;
    var multisig_txs_hex="";

    //生成一条多重交易来嵌入ODIN数据
    //多重签名输出的第一个公钥固定为标识注册者对应公钥
    var tmp_script = new Bitcoin.Script;
    tmp_script.writeOp(OP_1);
    tmp_script.writeBytes( Crypto.util.hexToBytes(register_pubkey_hex) );

    //第一条多重签名输出的第二个公钥为ODIN特征前缀
    tmp_script.writeBytes( Crypto.util.hexToBytes(PPK_ODIN_MARK_PUBKEY_HEX) );

    var from=0;
    var kk=0;
    for(kk=0;kk < MAX_N-2 && from<str_odin_hex.length;kk++){
     var split_pubkey_length=PPK_PUBKEY_EMBED_DATA_MAX_LENGTH;
     var tmp_pubkey=str_odin_hex.substr(from,split_pubkey_length*2);
     //console.log.log('kk=',kk,',from=', from,',tmp_pubkey[',kk,']=',tmp_pubkey);
     
     var tmp_script_hex = PPK_PUBKEY_TYPE_FLAG_HEX + byteToHex(tmp_pubkey.length/2) + tmp_pubkey;

     for(var pp=tmp_pubkey.length;pp<split_pubkey_length*2;pp=pp+2){ //对于长度不足的字符串用空格(0x20)补足
          tmp_script_hex+='20';
     }
     
     tmp_script.writeBytes( Crypto.util.hexToBytes(tmp_script_hex) );
     
     from+=split_pubkey_length*2;
    }
    tmp_script.writeOp( OP_1 + kk +  1), tmp_script.writeOp(OP_CHECKMULTISIG);

    //console.log('tmp_script:(',typeof(tmp_script),')', tmp_script);
    res.push( {"script":tmp_script, "fval":MIN_DUST_FLOAT_VALUE } );

    //使用op_return对应的备注脚本空间来嵌入剩余ODIN数据
    if(from < str_odin_hex.length){
      var tmp_pubkey=str_odin_hex.substr(from,MAX_OP_RETURN_LENGTH*2);
      
      tmp_script = new Bitcoin.Script;
      tmp_script.writeOp(OP_RETURN);
      tmp_script.writeBytes( Crypto.util.hexToBytes(tmp_pubkey) );

      res.push( {"script":tmp_script, "fval":0 } );
    } 
    //console.log('res:', res);
    return res;
  }
       
  //Ascii/Unicode字符串转换成16进制表示
  function stringToHex(str){
    var val="";
    for(var i = 0; i < str.length; i++){
        var tmpstr=str.charCodeAt(i).toString(16);  //Unicode
        val += tmpstr.length==1? '0'+tmpstr : tmpstr;  
    }
    return val;
  }

  //1字节整数转换成16进制字符串
  function byteToHex(val){
    var resultStr='';
    var tmpstr=parseInt(val%256).toString(16); 
    resultStr += tmpstr.length==1? '0'+tmpstr : tmpstr;  
    
    return resultStr;
  }

  
  function viewRegisteredODIN(){
      var currentBtcAddressess=$('#currentBtcAddress').val();
      if(currentBtcAddressess.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币钱包地址',
              onOK: function () {
                importPrvkey();
              },
              onCancel: function () {
              }
            });
      }else{
          gotoURL('http://47.114.169.156:9876/odin?address='+currentBtcAddressess,'正在打开查询工具，请稍等' );
      }
  }
  
  function gotoURL(url,message){
    if( typeof( message ) != undefined )
        $.alert(message);
    else
        $.alert("正跳转到 "+ url);
    
    window.location.href=url;
  }
  
  function fastLoginPNS(){
    $.ajax({
        type: "GET",
        url: "https://ppk001.sinaapp.com/ap2/login_uuid.php?page=pns_set_localdb",
        xhrFields:{
            withCredentials:true  //允许客户端带上cookie，这样才能保证session_id跨域一致
        },
        data: {},
        success: function (result) {
            var obj_resp = (typeof(result)=='string') ? JSON.parse(result) : result ;

            if (obj_resp.code == 0) {
                //在后端登记成功，获得相应的登录事务号
                var qruuid=obj_resp.data.qruuid;
                var confirm_url=obj_resp.data.confirm_url;
                
                promptConfirmLogin(confirm_url);  
            }else{
                //不能直接登录，则采用跳转页面的方式
                window.location.href = 'https://ppk001.sinaapp.com/ap2/';
            }
        }
    });
  }
  
  function promptConfirmLogin(loginURL){
      if(gStrCurrentODIN.length==0 || gStrCurrentPrvkeyEncrypted.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币地址和奥丁号',
              onOK: function () {
                gotoSetting();
              },
              onCancel: function () {
              }
            });
          
          return;
      }
      
      $.confirm({
          title: '确认授权 '+gStrCurrentODIN+' 登录下述网址吗？',
          text: loginURL,
          onOK: function (input) {
              unlockLocalPrivateData(function(){
                 confirmLogin(loginURL,gStrCurrentODIN);
              });
          },
          onCancel: function () {
            //点击取消
          }
      });
          
      
        
      
      
        
      /*
      $.prompt({
          title: '确认授权 '+gStrCurrentODIN+' 登录下述网址吗？',
          text: loginURL,
          input: '请输入解锁密码，未设可忽略',
          empty: false, // 是否允许为空
          onOK: function (input) {
            confirmLogin(loginURL,gStrCurrentODIN);
          },
          onCancel: function () {
            //点击取消
          }
        });*/
  }
  
  function formatOldPPkURI(ppk_uri){
      if(ppk_uri==null)
          return null;
      
      var old_resoure_mark_posn=ppk_uri.lastIndexOf("#");
      if(old_resoure_mark_posn==ppk_uri.length-1) {//自动替换旧版URI中的后缀标志符#
        ppk_uri = ppk_uri.substring(0, old_resoure_mark_posn)+"*";;
      }
      return ppk_uri;
  }
  
  function restoreLocalSetting(){ 
    var local_encrypted=getLocalConfigData('local_encrypted');
    var local_prvkey_encrypted=getLocalConfigData('local_prvkey_encrypted');
    var local_address=getLocalConfigData('local_address');
    var local_odin=formatOldPPkURI(getLocalConfigData('local_odin'));
    var local_txfee=getLocalConfigData('local_txfee');
    //console.log("local_odin="+local_odin+"\nlocal_encrypted="+local_encrypted+"\nlocal_prvkey_encrypted="+local_prvkey_encrypted+"\nlocal_address="+local_address+"\nlocal_txfee="+local_txfee);
    
    gBoolEncrypted = (local_encrypted=='ON');

    if(local_odin!=null){
        gStrCurrentODIN=local_odin;
        $('#currentODIN').val(gStrCurrentODIN);
        $('#loginODIN').val(gStrCurrentODIN);
    }
    
    if(local_prvkey_encrypted!=null){
        gStrCurrentPrvkeyEncrypted=local_prvkey_encrypted;
        //gObjTempKey = getEcKey(gStrTempPrvkey);
        
        if(local_address!=null){
            gStrCurrentAddress = local_address;
            setCurrentAddress(gStrCurrentAddress);
        }

        //test
        //var sgSig = "bitcoin_secp256k1:"+sign_message(p.key, "test_message", p.compressed, p.addrtype);
        //alert('sgSig='+sgSig);
        //sgOnChangeSec();
    }
    
    if(local_txfee!=null){
        $('#txFee').val(local_txfee);
    }
  }
  
  function setCurrentAddress(address){
      $('#currentBtcAddress').val(address);
      
      refreshAddressInfo();
  }
  
  function refreshAddressInfo() {
    $('#addressBalance').val("获取中...");
    $('#addressSummary').val("获取中...");
    clearTimeout(gObjTimeout);
    gObjTimeout = setTimeout(getAddressInfo, TIMEOUT);
  }
  
  
  
  function getAddressInfo() {
    var addr = $('#currentBtcAddress').val();

    //var urlUnspent = 'https://blockchain.info/unspent?cors=true&active=' + addr ;
    var urlUnspent = 'https://tool.ppkpub.org/ppkapi2/proxy.php?url=https%3A%2F%2Fblockchain.info%2Funspent%3Fcors%3Dtrue%26active%3D' + addr ;
            //'http://btc.blockr.io/api/v1/address/unspent/'+ addr + '?multisigs=1'
            //'https://blockexplorer.com/api/addr/'+ addr + '/utxo' ;

    $('#txUnspent').val('');

    $.getJSON(urlUnspent, function(data) {
      setUnspent ( JSON.stringify(data, null, 2) );
    }).fail(function(jqxhr, textStatus, error) {
      console.log( typeof(jqxhr.responseText)=='undefined' ? jqxhr.statusText 
        : ( jqxhr.responseText!='' ? jqxhr.responseText : 'No unpent data, probably Access-Control-Allow-Origin error.') );
    });
    
    var urlOdinSummary = 'https://tool.ppkpub.org/odin/summary.php?address=' + addr ;
    
    $.getJSON(urlOdinSummary, function(data) {
      setSummary( JSON.stringify(data, null, 2) );
    }).fail(function(jqxhr, textStatus, error) {
      console.log( typeof(jqxhr.responseText)=='undefined' ? jqxhr.statusText 
        : ( jqxhr.responseText!='' ? jqxhr.responseText : 'No odin summary data, probably Access-Control-Allow-Origin error.') );
    });
  }
  
  function setSummary(text) {
    if (text=='' || text=='{}') {
         $('#addressSummary').val("未获得，请稍后刷新");
        return;
    }
    
    var r = JSON.parse(text);
    if(r.status!='OK'){
         $('#addressSummary').val("查询出错，请稍后刷新");
        return;
    }
    
    if(r.balance_satoshi==0)
        $('#addressBalance').val("0");

    var addressSummary="";

    addressSummary +="已注册数："+r.register_num+"\n";
    if(r.register_num>0){
        addressSummary +="最近注册的奥丁号：ppk:"+r.last_register_odin.short_odin+"*\n";
        gStrLastRegisteredODIN = "ppk:"+r.last_register_odin.short_odin+"*";
        
        if(gStrCurrentODIN.length==0){ //默认使用用户比特币地址最新注册的奥丁号作为身份
           updateCurrentODIN(gStrLastRegisteredODIN);  
        }
    }
    
    if(r.unconfirmed_tx_count){
        addressSummary +="待确认交易数："+r.unconfirmed_tx_count+"\n";
        addressSummary +="注：待确认交易数包括注册奥丁号、普通转账等多类交易在内，仅供参考。";
    }
    
    $('#addressSummary').val(addressSummary);
  }
  
  function setUnspent(text) {
    if (text=='' || text=='{}') {
         $('#addressBalance').val("查询有误，请稍后刷新");
        return;
    }
    var r = JSON.parse(text);
    txUnspent = JSON.stringify(r, null, 4);
    $('#txUnspent').val(txUnspent);
    var address = $('#currentBtcAddress').val();
    TX.parseInputs(txUnspent, address);
    var value = TX.getBalance();
    var fval = Bitcoin.Util.formatValue(value);
    $('#addressBalance').val(fval);
    
    //var fee = parseFloat($('#txFee').val());
    //var value = Math.floor((fval-fee)*1e8)/1e8;
    //$('#txValue').val(value);
    //txRebuild();
    
    $('#btn_register').attr('disabled', $('#txUnspent').val()=="");
  }
  
  function confirmLogin(loginURL,loginODIN) {
      if ( loginODIN==null || loginODIN.length==0 ){
        //alert("Please set your ODIN first!");
        $.alert("请先设置登录使用的奥丁号", "提示");
        return;
      }
      console.log("loginODIN:"+loginODIN);
      
      if ( loginURL==null || loginURL.length==0 ){
        //alert("Please scan a qrcode first then confirm!");
        $.alert("请先扫码", "提示");
        return;
      }
      console.log("loginURL:"+loginURL);
      
      var timestamp=new Date().getTime()/1000; 
      var sgMsg = loginURL+","+loginODIN +","+timestamp;
      //alert('sgMsg='+sgMsg);

      if ( !sgMsg || gObjTempKey==null ){
        //alert("Invalid signature");
        $.alert("不能生成有效签名，请确认设置是否正确后重试", "出错了");
        return;
      }
      
      sgMsg = fullTrim(sgMsg);

      var sgSig = "BitcoinSignMsg:"+sign_message(gObjTempKey.key, sgMsg, gObjTempKey.compressed, gObjTempKey.addrtype);
      //alert('sgSig='+sgSig);
      //$('#sgSig').val(sgSig);
      
       
      $('#btn_scan_login').html('<div class="weui-loadmore"><i class="weui-loading"></i><span class="weui-loadmore__tips">正在登录</span></div>');
      //$('#btn_scan_login').className += ' weui-btn_loading';
      //$('#btn_scan_login').disabled = true;
      //$('#btn_scan_login').loading = true;
      
      var confirmUrl=vrPermalink(loginURL, loginODIN, sgMsg, sgSig);
      window.location.href = confirmUrl;
  }
  
  function changeCurrentODIN(){
      var currentBtcAddressess=$('#currentBtcAddress').val();
      if(currentBtcAddressess.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币钱包地址',
              onOK: function () {
                importPrvkey();
              },
              onCancel: function () {
              }
            });

          return;
      }
      $.prompt({
          title: '请输入用作身份标识的奥丁号',
          text: '输入用下述地址注册的奥丁号：<br>'+currentBtcAddressess+'<br>如该比特币地址尚未注册奥丁号，请先注册后再设置使用。',
          input: gStrLastRegisteredODIN.length>0 ? gStrLastRegisteredODIN:gStrCurrentODIN,
          empty: false, // 是否允许为空
          onOK: function (input) {
            input=input.trim();
            if( !input.startsWith("ppk:")){
                if(Math.round(input)==input){
                    input="ppk:"+input+"*";  //对于数字自动补全
                }else{
                    alert("请输入正确的奥丁号（以 ppk: 起始）");
                    return;
                }
            }

            updateCurrentODIN(input);
       
          },
          onCancel: function () {
            //点击取消
          }
        });
      /*
     $.modal({
          title: "请输入用作身份标识的奥丁号",
          text: '请输入用下述比特币地址注册的奥丁号：<br>'+currentBtcAddressess+"<br>如该比特币地址尚未注册奥丁号，请先注册后再设置使用。",
          buttons: [
            { text: "取消", className: "default", onClick: function(){ console.log(3)} },
            { text: "确认", onClick: function(){ console.log(1)} },
            { text: "注册新奥丁号", onClick: function(){ console.log(2)} },
            
          ]
        });*/
  }
  
  function updateCurrentODIN(odin){
      gStrCurrentODIN = odin;
      if(!gStrCurrentODIN.endsWith("*")) //自动补全标识后缀
          gStrCurrentODIN += "*";
                
      $('#currentODIN').val(gStrCurrentODIN);
      $('#loginODIN').val(gStrCurrentODIN);
      saveLocalConfigData('local_odin',gStrCurrentODIN);

      $.toptip("你的身份奥丁号已设为<br>"+gStrCurrentODIN,'success');
      
      meAsDest();
  }
  
  function setTxFee(){
      var fee = parseFloat($('#txFee').val());
      
      $.prompt({
          title: '设置交易费用',
          text: '请输入支付给比特币矿工的交易费用（默认为0.000005 BTC, 将该费用适当调高可以获得更快确认）',
          input: fee,
          empty: false, // 是否允许为空
          onOK: function (input) {
            fee = parseFloat(input);
            if(fee<0.000005){
                $.alert("不能小于0.000005","提示");
            }else if(fee>0.0001){
                $.alert("不能大于0.0001","提示");
            }else{
                $('#txFee').val(fee);
                saveLocalConfigData('local_txfee',fee);
            }
          },
          onCancel: function () {
            //点击取消
          }
        });
  }
  
  function newPrvKey(){
      var currentBtcAddressess=$('#currentBtcAddress').val();
      if(currentBtcAddressess.length!=0){
          $.confirm({
              title: '确认生成新地址吗？',
              text: '注意：已有地址数据将被覆盖，请确认已备份好下述地址的私钥！<br>'+currentBtcAddressess,
              onOK: function () {
                  unlockLocalPrivateData(function(){
                    generatePrvkey();
                  });
              },
              onCancel: function () {
              }
          });
      }else{
          generatePrvkey();
      }
  }
  
  function generatePrvkey(){
    var payload = secureRandom(32);
   
    if (gen_compressed)
        payload.push(0x01);

    var sec = new Bitcoin.Address(payload);
    sec.version = PRIVATE_KEY_VERSION;
    var str_prvkey = ""+sec;
    
    var p=getEcKey(str_prvkey);
    if(p==null){
        $.alert("无效的私钥","生成地址密钥出错了，请重试！");
    }else{
        setLocalPrvkey(str_prvkey,p);
    }
  }
  
  function importPrvkey(useInputPrvkeyArea){
     if($('#currentBtcAddress').val().length == 0){
         promptImportPrvKey(useInputPrvkeyArea);
     }else{
         unlockLocalPrivateData( function(){promptImportPrvKey(useInputPrvkeyArea);} );
     }
     return false;
  }
  
  function promptImportPrvKey(useInputPrvkeyArea){
      if( typeof(useInputPrvkeyArea)!=undefined && useInputPrvkeyArea) {
          //启用私钥输入栏拉粘贴导入私钥，避免ios里对话框方式无法粘贴的问题
          $("#inputPrvkeyArea").show();
      }else{
          //注意：默认弹出WEUI的输入对话框到来导入私钥，但在IOS版本下可能无法粘贴
          $.prompt({
              title: '导入比特币地址',
              text: '请输入要导入的比特币地址私钥（以5,K或L起始）<br>如果无法复制粘贴，请换到“设置”界面里导入。',
              input: "",
              empty: false, // 是否允许为空
              onOK: function (input) {
                var p=getEcKey(input);
                if(p==null){
                    alert("请输入正确的比特币地址私钥（以5,K或L起始的字符串）");
                }else{
                    setLocalPrvkey(input,p);
                }
              },
              onCancel: function () {
                //点击取消
              }
            });
      }   
  }

  function confirmImportPrvkey(){
     var input = $("#inputPrvkey").val().trim();
     
     var p=null;
     if(input.length>0){
         p=getEcKey(input);
     }

     if(p==null){
        alert("请输入正确的比特币地址私钥（以5,K或L起始的字符串）");
     }else{
        setLocalPrvkey(input,p);
        $("#inputPrvkey").val("");
        $("#inputPrvkeyArea").hide();
     }
  }
  
  function setLocalPrvkey(str_prvkey,obj_prvkey){
    var encrypted = aesEncrypteData(gStrUnlockPassword,str_prvkey);
    if(encrypted==null || encrypted.length == 0){
        $.alert("加密数据保存时出错，请重试","出错了");
        return;
    }
    
    gStrCurrentPrvkeyEncrypted = encrypted;
    gStrTempPrvkey = str_prvkey;
    gObjTempKey = obj_prvkey;

    saveLocalConfigData('local_address',gObjTempKey.address);
    saveLocalConfigData('local_prvkey_encrypted',gStrCurrentPrvkeyEncrypted);
    
    //比特币地址更改后，奥丁号身份自动重置
    gStrCurrentODIN = "";
    gStrLastRegisteredODIN="";
    $('#currentODIN').val(gStrCurrentODIN);
    $('#loginODIN').val(gStrCurrentODIN);
    saveLocalConfigData('local_odin',gStrCurrentODIN);
    
    setCurrentAddress(gObjTempKey.address);
    
    //changeCurrentODIN();
    $.toast("地址已更新");
  }
  
  function backupPrvkey(){
      unlockLocalPrivateData(function(){
          $.alert(gStrTempPrvkey, "请复制和备份下述私钥");
        }
      );
  }
  
  function setUnlockPassword(){
      var currentBtcAddressess=$('#currentBtcAddress').val();
      if(currentBtcAddressess.length==0){
          $.alert('请先设置有效的比特币钱包地址后，再设置解锁密码','提示');
          return;
      }
      
      unlockLocalPrivateData(function(){
          $.prompt({
              title: '修改解锁密码',
              text: '请输入新的解锁密码（长度需6个字符以上）',
              input: '',
              empty: false, // 是否允许为空
              onOK: function (input) {
                if(input.length<6){
                    alert("新密码的长度需6个字符以上！");
                    setUnlockPassword( );
                }else{
                    confirmUnlockPassword(input);
                }
              },
              onCancel: function () {
                //点击取消
              }
            });
        }
      );
      
  }
  
  function confirmUnlockPassword(newPassword){
      $.prompt({
          title: '确认解锁密码',
          text: '请再输入一遍新的解锁密码以确认',
          input: '',
          empty: false, // 是否允许为空
          onOK: function (input) {
            if(input!=newPassword){
                $.alert("两次输入的解锁密码不一致，请重试！", "出错了");
            }else{
                gStrUnlockPassword = newPassword;
                gStrCurrentPrvkeyEncrypted = aesEncrypteData(newPassword,gStrTempPrvkey);
                saveLocalConfigData('local_encrypted','ON');
                saveLocalConfigData('local_prvkey_encrypted',gStrCurrentPrvkeyEncrypted);
                
                gBoolEncrypted=true;
                gStrUnlockPassword="";
                gStrTempPrvkey="";
                gObjTempKey=null;
            }
          },
          onCancel: function () {
            //点击取消
          }
        });
  }
  
  function fullTrim(message)
  {
    message = message.replace(/^\s+|\s+$/g, '');
    message = message.replace(/^\n+|\n+$/g, '');
    return message;
  }

  function vrPermalink(loginURL,odinURI,msg,sig)
  {
      return loginURL+'&user_odin_uri='+encodeURIComponent(odinURI)+'&auth_txt_hex='+stringToHex(msg)+'&user_sign='+encodeURIComponent(sig)+'&response_type=html';
  }
  
  function getEcKey(sec) {
    var addr = '';
    var eckey = null;
    var compressed = false;
    try {
        var res = parseBase58Check(sec); 
        var privkey_version = res[0];
        var payload = res[1];

        if (payload.length!=32 && payload.length!=33)
          throw ('Invalid payload (must be 32 or 33 bytes)');

        if (payload.length > 32) {
            payload.pop();
            compressed = true;
        }
        eckey = new Bitcoin.ECKey(payload);
        var curve = getSECCurveByName("secp256k1");
        var pt = curve.getG().multiply(eckey.priv);
        eckey.pub = getEncoded(pt, compressed);
        eckey.pubKeyHash = Bitcoin.Util.sha256ripe160(eckey.pub);
        addr = new Bitcoin.Address(eckey.getPubKeyHash());
        addr.version = PUBLIC_KEY_VERSION;

        if (privkey_version!=PRIVATE_KEY_VERSION)
        {
            var wif = new Bitcoin.Address(payload);
            wif.version = PRIVATE_KEY_VERSION;
        }
    } catch (err) {
        alert("生成比特币地址时出错:"+err);

        return null;
    }
    return {"key":eckey, "compressed":compressed, "addrtype":PUBLIC_KEY_VERSION, "address":addr};
  }

  
  function parseBase58Check(address) {
    var bytes = Bitcoin.Base58.decode(address);
    var end = bytes.length - 4;
    var hash = bytes.slice(0, end);
    var checksum = Crypto.SHA256(Crypto.SHA256(hash, {asBytes: true}), {asBytes: true});
    if (checksum[0] != bytes[end] ||
        checksum[1] != bytes[end+1] ||
        checksum[2] != bytes[end+2] ||
        checksum[3] != bytes[end+3])
            throw new Error("Wrong checksum");
    var version = hash.shift();
    return [version, hash];
  }
  
  function getEncoded(pt, compressed) {
   var x = pt.getX().toBigInteger();
   var y = pt.getY().toBigInteger();
   var enc = integerToBytes(x, 32);
   if (compressed) {
     if (y.isEven()) {
       enc.unshift(0x02);
     } else {
       enc.unshift(0x03);
     }
   } else {
     enc.unshift(0x04);
     enc = enc.concat(integerToBytes(y, 32));
   }
   return enc;
  }
  
  //AES加解密
  function aesEncrypteData(password,str){
    if(password==null||password.length==0 ) //未提供有效密码时直接返回原文
         return str;
    
    var passwordHash = Crypto.util.bytesToHex( Crypto.SHA256(password, { asBytes: true }) );
    //console.log('passwordHash:'+passwordHash);
    
    // 密钥 16 位
    var key = passwordHash.substr(0,16) ;
    // 初始向量 initial vector 16 位
    var iv = passwordHash.substr(16,16);
     
    key = CryptoJS.enc.Utf8.parse(key);
    iv = CryptoJS.enc.Utf8.parse(iv);
    
    //console.log('key:'+key);
    //console.log('iv:'+iv);
    
    // mode 支持 CBC、CFB、CTR、ECB、OFB, 默认 CBC
    // padding 支持 Pkcs7、AnsiX923、Iso10126
    // 、NoPadding、ZeroPadding, 默认 Pkcs7, 即 Pkcs5
    var encrypted = CryptoJS.AES.encrypt(str, key, {
        iv: iv,
        mode: CryptoJS.mode.CBC,
        padding: CryptoJS.pad.Pkcs7
    });
     
    // 转换为字符串
    return encrypted.toString();
  }
  
  function aesDencrypteData(password,str){
    if(password==null||password.length==0) //未提供有效密码时直接返回原文
         return str;
         
    var passwordHash = Crypto.util.bytesToHex( Crypto.SHA256(password, { asBytes: true }) );
    
    // 密钥 16 位
    var key = passwordHash.substr(0,16) ;
    // 初始向量 initial vector 16 位
    var iv = passwordHash.substr(16,16);
     
    key = CryptoJS.enc.Utf8.parse(key);
    iv = CryptoJS.enc.Utf8.parse(iv);
     
    // mode 支持 CBC、CFB、CTR、ECB、OFB, 默认 CBC
    // padding 支持 Pkcs7、AnsiX923、Iso10126
    // 、NoPadding、ZeroPadding, 默认 Pkcs7, 即 Pkcs5
    var decrypted = CryptoJS.AES.decrypt(str, key, {
        iv: iv,
        mode: CryptoJS.mode.CBC,
        padding: CryptoJS.pad.Pkcs7
    });
     
    // 转换为 utf8 字符串
    return CryptoJS.enc.Utf8.stringify(decrypted);  
  }
  
  function unlockLocalPrivateData(callback_success){
      if(gStrTempPrvkey.length>0 && gObjTempKey!=null){ //已经解锁过
          callback_success();
          return;
      }
      
      if(gBoolEncrypted){
        //需要输入解锁密码
        $.prompt({
          title: '请输入解锁密码',
          text: '',
          input: '',
          empty: false, // 是否允许为空
          onOK: function (input) {
            if(input.length==0){
                alert("需要输入正确的解锁密码，请重试");
            }else{
                var dencrypted = aesDencrypteData(  input , gStrCurrentPrvkeyEncrypted )
                if(dencrypted==null || dencrypted.length==0){
                    alert("解锁密码不正确，请重试","出错了");
                }else{
                    gStrUnlockPassword = input;
                    gStrTempPrvkey = dencrypted;
                    
                    if(gStrTempPrvkey.length>0){
                        gObjTempKey = getEcKey(gStrTempPrvkey);
                        
                        callback_success();
                    }
                }
            }
          },
          onCancel: function () {
            //点击取消
            return false;
          }
        });
        /*
        var input = prompt("请输入解锁密码", "");
        if( input==null ){ //取消
           return false;
        }else if( input.length==0 ){
           $.alert("需要输入正确的解锁密码，请重试","提示");
           return false;
        }
        var dencrypted = aesDencrypteData(  input , gStrCurrentPrvkeyEncrypted )
        if(dencrypted==null || dencrypted.length==0){
            $.alert("解锁密码不正确，请重试","出错了");
            return false;
        }
        
        gStrUnlockPassword = input;
        gStrTempPrvkey = dencrypted;
        */
        
        
      }else{
        gStrUnlockPassword = "";
        gStrTempPrvkey=gStrCurrentPrvkeyEncrypted;
        
        if(gStrTempPrvkey.length>0){ //BTC地址有效
            gObjTempKey = getEcKey(gStrTempPrvkey);
            
            callback_success();
        }
      }
  }
</script>
</body>
</html>
