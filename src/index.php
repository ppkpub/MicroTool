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

?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <title>PPk小工具微信版v2 - PPk tool for MicroMsg</title>
    <meta content="ppkpub.org" name="author" />
    <meta content="PPk tool for MircoMsg include Scan&Login,ODIN register" name="description" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta content="IE=edge" http-equiv="X-UA-Compatible">

    <link rel="stylesheet" href="https://cdn.bootcss.com/weui/1.1.3/style/weui.min.css">
    <link rel="stylesheet" href="https://cdn.bootcss.com/jquery-weui/1.2.1/css/jquery-weui.min.css">
    <link rel="stylesheet" href="css/ppktool.css">
    

   
</head>
<body ontouchstart>

<div class="weui-tab">
  <div class="weui-tab__bd">
    <div id="tab_register" class="weui-tab__bd-item">
      <header class='demos-header'>
        <h1 class="demos-title">注册新的奥丁号</h1>
      </header>
      
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">比特币地址</label>
        </div>
        <div class="weui-cell__bd">
          <input id="registerAdddress"  class="weui-input" type="text" placeholder="请设置注册使用的比特币地址" readonly >
        </div>
        <div class="weui-cell__ft">
          <button class="weui-vcode-btn" onclick="javascript:importPrvkey();">设置</button>
        </div>
      </div>

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
      <p><font size="-2">提示：注册奥丁号只需要花费很少的矿工费用，余额有0.0001BTC就足够体验了。</font></p>
      
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

      <p><font size="-2">提示：该交易费用支付给比特币的矿工以确认交易。一般情况下，注册交易被确认需要等待30分钟左右，如需更快确认可以将该费用适当调高。</font></p>
      <a id="btn_register" href="javascript:registerNewODIN();" class="weui-btn weui-btn_warn" style="width: 80%;" disabled=true>注  册</a>     
      
      <div class="weui-cells__title">当前地址相关奥丁号信息 <a href="javascript:viewRegisteredODIN();">查看更多>></a></div>
        <div class="weui-cells weui-cells_form">
          <div class="weui-cell">
            <div class="weui-cell__bd">
              <textarea id="addressSummary" class="weui-textarea" placeholder="" rows="5" readonly></textarea>
            </div>
          </div>
      </div>
      
      <a href="javascript:viewRegisteredODIN();" class="weui-btn weui-btn_plain-default" style="width: 80%;">查看更多注册信息</a>

      <div id="hidden_data_area" style="display:none;">
          <textarea id="txUnspent" class="weui-textarea" placeholder="" rows="2"></textarea>
          <textarea id="txJSON" class="weui-textarea" placeholder="" rows="2"></textarea>
          <textarea id="txHex" class="weui-textarea" placeholder="" rows="2"></textarea>
      </div>
      
    </div>
    <div id="tab_scan" class="weui-tab__bd-item weui-tab__bd-item--active">
      <header class='demos-header'>
        <h1 class="demos-title">以奥丁号登录</h1>
      </header>
      
      <div class="weui-cell weui-cell_vcode">
        <div class="weui-cell__hd">
          <label class="weui-label">你的奥丁号</label>
        </div>
        <div class="weui-cell__bd">
          <input id="loginODIN"  class="weui-input" type="text" placeholder="请设置登录使用的奥丁号" readonly  onclick="javascript:changeCurrentODIN();">
        </div>
        <div class="weui-cell__ft">
          <button class="weui-vcode-btn" onclick="javascript:changeCurrentODIN();">换一个</button>
        </div>
      </div>
      
      
      
      <p><br><br></p>

      <a id="btn_scan_login" href="javascript:scanQRCode();" class="weui-btn weui-btn_primary" style="width: 80%;">扫一扫用奥丁号登录</a>
      <br>
      
      <a id="btn_test_pns" href="javascript:fastLoginPNS();" class="weui-btn weui-btn_default" style="width: 80%;">快速体验奥丁号托管服务(PNS)</a>
      <br>
      <p align="center" class="weui-footer__text"><a href="https://www.chainnode.com/post/434454">通过PNS+区块链，快速创建你的个人或企业链上名片，还有更多...</a></p>
      <!--<a id="btn_test_pns" href="http://tool.ppkpub.org/ap2/" class="weui-btn weui-btn_default" style="width: 80%;">快速体验标识托管服务(PNS)</a>-->
  
      <p><br><br><br></p>
  
      <div class="weui-footer ">
        <p class="weui-footer__text">PPk小工具微信版 - PPk tool for MicroMsg V0.1.20200531 </p>
        <p class="weui-footer__links">
          <a href="http://ppkpub.org" class="weui-footer__link">PPk技术社区 PPkPub.org</a>
        </p>
        
      </div>
    </div>
    
    <div id="tab_setting" class="weui-tab__bd-item">
      <header class='demos-header'>
        <h1 class="demos-title">设置</h1>
      </header>
      
      <div class="weui-cells__title">比特币地址</div>
      <div class="weui-cells">
          <div class="weui-cell">
            <div class="weui-cell__bd">
              <input id="currentAddr" class="weui-input" type="text" placeholder="请设置你的比特币钱包地址" readonly>
            </div>
          </div>
          
          <div class="button_sp_area" align="right">
            <a href="javascript:newPrvKey();" class="weui-btn weui-btn_mini weui-btn_primary">生成新地址</a>
            <a href="javascript:importPrvkey();" class="weui-btn weui-btn_mini weui-btn_primary">导入已有地址</a>
            <a href="javascript:backupPrvkey();" class="weui-btn weui-btn_mini weui-btn_default">备份地址私钥</a>
          </div>
      </div>
      <p><font size="-2">注意：请备份保存好自己的比特币地址私钥！退出微信重新登录时，微信客户端会删除网页缓存，需要重新导入所备份的比特币地址私钥才能使用。</font></p>

      <!--
      <p><br><br></p>
      
      <a id="btn_set_secinfo" href="javascript:setSecInfo();" class="weui-btn weui-btn_primary" style="width: 80%;">设置安全提示</a>
      <p><font size="-2">注：设置个人自定义的安全提示，有助于区分假冒工具和网页。</font></p>
      -->
      
      <p><br><br></p>     
      
      <a id="btn_set_unlock" href="javascript:setUnlockPassword();" class="weui-btn weui-btn_warn" style="width: 80%;">设置解锁密码</a>
      <p><font size="-2">提示：你的个人数据（如比特币私钥）只在本机保存，设置解锁密码可以提高本地保存数据的安全性。设置解锁密码后，在注册奥丁号和扫码登录时，需要输入正确的解锁密码才能完成操作。</font></p>
      
      <div class="weui-footer ">
        <p class="weui-footer__text"></p>
        <p class="weui-footer__links">
          <a href="https://www.chainnode.com/post/386612" class="weui-footer__link">更多说明</a>
        </p>
        
      </div>
      
    </div>
    
  </div>

  <div class="weui-tabbar">
    <a href="#tab_register" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_msg.png" alt="">
      </div>
      <p class="weui-tabbar__label">注册奥丁号</p>
    </a>
    <a href="#tab_scan" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_user.png" alt="">
      </div>
      <p class="weui-tabbar__label">以奥丁号登录</p>
    </a>
    <a id="app_browser_url" href="http://tool.ppkpub.org/demo/browser/?back=https://ppk001.sinaapp.com/odin/" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_browser.png" alt="">
      </div>
      <p class="weui-tabbar__label">浏览PPk网络</p>
    </a>
    <a id="app_pay_url" href="https://tool.ppkpub.org/demo/pay/?back=https://ppk001.sinaapp.com/odin/" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_pay.png" alt="">
      </div>
      <p class="weui-tabbar__label">用奥丁号转账</p>
    </a>
    <a href="#tab_setting" id="btn_tab_setting" class="weui-tabbar__item">
      <div class="weui-tabbar__icon">
        <img src="./images/icon_nav_cell.png" alt="">
      </div>
      <p class="weui-tabbar__label">设置</p>
    </a>
  </div>
</div>

<script src="https://cdn.bootcss.com/jquery/2.1.4/jquery.min.js"></script>
<script src="js/bitcoinjs-min.js"></script>
<script src="js/qrcode.js"></script>
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
<script>
  $(function() {
    FastClick.attach(document.body);
  });
</script>
<script src="https://cdn.bootcss.com/jquery-weui/1.2.1/js/jquery-weui.min.js"></script>

<script src="https://res.wx.qq.com/open/js/jweixin-1.4.0.js"></script>
<script>
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

  var gBoolWeixinEnabled=false;
  
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

             }
        });
        
        gBoolWeixinEnabled=true;
    });
  
  
 
  $(document).ready( function() {
      restoreLocalSetting();
      
      document.getElementById('app_pay_url').href = "https://tool.ppkpub.org/demo/pay/?to="+encodeURIComponent(gStrCurrentODIN)+"&back="+encodeURIComponent("https://ppk001.sinaapp.com/odin/");

      var login_confirm_url=getQueryString('login_confirm_url');
      //alert("login_confirm_url="+login_confirm_url);
      if(login_confirm_url!=null && login_confirm_url.length>0){//传入有效登录参数不需要再扫码获取的情况
          promptConfirmLogin(login_confirm_url);
      }
  });
  
  function scanQRCode(){
      if(!gBoolWeixinEnabled){
        alert("请在微信客户端里打开此工具！");
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
      var currentAddress=$('#currentAddr').val();
      if(currentAddress.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币钱包地址',
              onOK: function () {
                //$("btn_tab_setting").click();
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
        if(!unlockLocalPrivateData()){
          return;
        }
        buildRegsiterTransaction();
      }
  }
  
  function buildRegsiterTransaction() {
    var addr = $('#currentAddr').val();
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
    var txAddr = $('#currentAddr').val();

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
      var currentAddress=$('#currentAddr').val();
      if(currentAddress.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币钱包地址',
              onOK: function () {
                //$("btn_tab_setting").click();
                importPrvkey();
              },
              onCancel: function () {
              }
            });
      }else{
          window.location.href = "http://tool.ppkpub.org:9876/odin?address="+currentAddress;
      }
  }
  
  function fastLoginPNS(){
      $.ajax({
        type: "GET",
        url: "https://tool.ppkpub.org/ap2/login_uuid.php",
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
                window.location.href = 'https://tool.ppkpub.org/ap2/';
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
                //$("btn_tab_setting").click();
                importPrvkey();
              },
              onCancel: function () {
              }
            });
          
          return;
      }
      
      if(!unlockLocalPrivateData()){
          return;
      }
      
      $.confirm({
          title: '确认授权 '+gStrCurrentODIN+' 登录下述网址吗？',
          text: loginURL,
          onOK: function (input) {
            confirmLogin(loginURL,gStrCurrentODIN);
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
  
  function formatPPkURI(ppk_uri){
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
    var local_odin=formatPPkURI(getLocalConfigData('local_odin'));
    var local_txfee=getLocalConfigData('local_txfee');
    console.log("local_odin="+local_odin+"\nlocal_encrypted="+local_encrypted+"\nlocal_prvkey_encrypted="+local_prvkey_encrypted+"\nlocal_address="+local_address+"\nlocal_txfee="+local_txfee);
    
    gBoolEncrypted = (local_encrypted=='ON');

    if(local_odin!=null){
        gStrCurrentODIN=local_odin;
        $('#loginODIN').val(local_odin);
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
      $('#currentAddr').val(address);
      $('#registerAdddress').val( address );
      
      refreshAddressInfo();
  }
  
  function refreshAddressInfo() {
    $('#addressBalance').val("获取中...");
    $('#addressSummary').val("获取中...");
    clearTimeout(gObjTimeout);
    gObjTimeout = setTimeout(getAddressInfo, TIMEOUT);
  }
  
  
  
  function getAddressInfo() {
    var addr = $('#currentAddr').val();

    var urlUnspent = 'https://blockchain.info/unspent?cors=true&active=' + addr ;
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
    var address = $('#currentAddr').val();
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
  
  function getLocalConfigData(key){
    if(typeof(Storage)!=="undefined")
    {
        // 是的! 支持 localStorage  sessionStorage 对象!
        return localStorage.getItem(key);
    } else {
        // 抱歉! 不支持 web 存储。
        return null;
    }
  }

  function saveLocalConfigData(key,value){
    if(typeof(Storage)!=="undefined")
    {
        // 是的! 支持 localStorage  sessionStorage 对象!
        return localStorage.setItem(key,value);
    } else {
        // 抱歉! 不支持 web 存储。
        return false;
    }
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
      var currentAddress=$('#currentAddr').val();
      if(currentAddress.length==0){
          $.confirm({
              title: '提示',
              text: '请先设置你的比特币钱包地址',
              onOK: function () {
                //$("btn_tab_setting").click();
                importPrvkey();
              },
              onCancel: function () {
              }
            });

          return;
      }
      $.prompt({
          title: '请输入用作身份标识的奥丁号',
          text: '输入用下述地址注册的奥丁号：<br>'+currentAddress+'<br>如该比特币地址尚未注册奥丁号，请先注册后再设置使用。',
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
          text: '请输入用下述比特币地址注册的奥丁号：<br>'+currentAddress+"<br>如该比特币地址尚未注册奥丁号，请先注册后再设置使用。",
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
                
      $('#loginODIN').val(gStrCurrentODIN);
      saveLocalConfigData('local_odin',gStrCurrentODIN);

      $.toptip("你的身份奥丁号已设为<br>"+gStrCurrentODIN,'success');
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
      var currentAddress=$('#currentAddr').val();
      if(currentAddress.length!=0){
          if(!unlockLocalPrivateData()){
              return;
          }
          $.confirm({
              title: '确认生成新地址吗？',
              text: '注意：已有地址数据将被覆盖，请确认已备份好下述地址的私钥！<br>'+currentAddress,
              onOK: function () {
                generatePrvkey();
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
  
  function importPrvkey(){
      if(!unlockLocalPrivateData()){
          return false;
      }
      
     /*
      //注意：WEUI的这个输入对话框，在IOS版本下可能无法粘贴
      $.prompt({
          title: '导入比特币地址',
          text: '请输入要导入的比特币地址私钥（以5,K或L起始）',
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
    */
     
    var input_str = prompt("请输入要导入的比特币地址私钥（以5,K或L起始）", "");
    if( input_str!=null && input_str.length>0 ){
        var p=getEcKey(input_str);
        if(p==null){
            $.alert("请输入正确的比特币地址私钥（以5,K或L起始的字符串）","出错了");
        }else{
            setLocalPrvkey(input_str,p);
        }
     }
     
     return false;
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
    $('#loginODIN').val(gStrCurrentODIN);
    saveLocalConfigData('local_odin',gStrCurrentODIN);
    
    setCurrentAddress(gObjTempKey.address);
    
    //changeCurrentODIN();
    $.toast("地址已更新");
  }
  
  function backupPrvkey(){
      if(!unlockLocalPrivateData()){
          return;
      }
      $.alert(gStrTempPrvkey, "请复制和备份下述私钥");
  }
  
  function setUnlockPassword(){
      var currentAddress=$('#currentAddr').val();
      if(currentAddress.length==0){
          $.alert('请先设置有效的比特币钱包地址后，再设置解锁密码','提示');
          return;
      }

      if(!unlockLocalPrivateData()){
          return;
      }
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
                saveLocalConfigData('local_encrypted','ON');
                saveLocalConfigData('local_prvkey_encrypted',aesEncrypteData(newPassword,gStrTempPrvkey));
            }
          },
          onCancel: function () {
            //点击取消
          }
        });
  }
  
  function getQueryString(name) {
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
    var reg_rewrite = new RegExp("(^|/)" + name + "/([^/]*)(/|$)", "i");
    var r = window.location.search.substr(1).match(reg);
    var q = window.location.pathname.substr(1).match(reg_rewrite);
    if(r != null){
        return unescape(r[2]);
    }else if(q != null){
        return unescape(q[2]);
    }else{
        return null;
    }
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
  
  function unlockLocalPrivateData(){
      if(gStrTempPrvkey.length>0 && gObjTempKey!=null) //已经解锁过
          return true;
      
      if(gBoolEncrypted){
        //需要输入解锁密码
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
      }else{
        gStrUnlockPassword = "";
        gStrTempPrvkey=gStrCurrentPrvkeyEncrypted;
      }
      
      if(gStrTempPrvkey.length>0)
        gObjTempKey = getEcKey(gStrTempPrvkey);
     
      //setCurrentAddress(gObjCurrentKey.address);
      return true;
  }
</script>
</body>
</html>
