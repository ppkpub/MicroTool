(function($){

    var gen_from = 'pass';
    var gen_compressed = true;
    var gen_eckey = null;
    var gen_pt = null;
    var gen_ps_reset = false;
    var TIMEOUT = 600;
    var timeout = null;

    var PUBLIC_KEY_VERSION = 0;
    var PRIVATE_KEY_VERSION = 0x80;
    var ADDRESS_URL_PREFIX = 'http://blockchain.info'

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

    encode_length = function(len) {
        if (len < 0x80)
            return [len];
        else if (len < 255)
            return [0x80|1, len];
        else
            return [0x80|2, len >> 8, len & 0xff];
    }
    
    encode_id = function(id, s) {
        var len = encode_length(s.length);
        return [id].concat(len).concat(s);
    }

    encode_integer = function(s) {
        if (typeof s == 'number')
            s = [s];
        return encode_id(0x02, s);
    }

    encode_octet_string = function(s)  {
        return encode_id(0x04, s);
    }

    encode_constructed = function(tag, s) {
        return encode_id(0xa0 + tag, s);
    }

    encode_bitstring = function(s) {
        return encode_id(0x03, s);
    }

    encode_sequence = function() {
        sequence = [];
        for (var i = 0; i < arguments.length; i++)
            sequence = sequence.concat(arguments[i]);
        return encode_id(0x30, sequence);
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

    function getDER(eckey, compressed) {
        var curve = getSECCurveByName("secp256k1");
        var _p = curve.getCurve().getQ().toByteArrayUnsigned();
        var _r = curve.getN().toByteArrayUnsigned();
        var encoded_oid = [0x06, 0x07, 0x2A, 0x86, 0x48, 0xCE, 0x3D, 0x01, 0x01];

        var secret = integerToBytes(eckey.priv, 32);
        var encoded_gxgy = getEncoded(curve.getG(), compressed);
        var encoded_pub = getEncoded(gen_pt, compressed);

        return encode_sequence(
            encode_integer(1),
            encode_octet_string(secret),
            encode_constructed(0,
                encode_sequence(
                    encode_integer(1),
                    encode_sequence(
                        encoded_oid, //encode_oid(*(1, 2, 840, 10045, 1, 1)), //TODO
                        encode_integer([0].concat(_p))
                    ),
                    encode_sequence(
                        encode_octet_string([0]),
                        encode_octet_string([7])
                    ),
                    encode_octet_string(encoded_gxgy),
                    encode_integer([0].concat(_r)),
                    encode_integer(1)
                )
            ),
            encode_constructed(1, 
                encode_bitstring([0].concat(encoded_pub))
            )
        );
    }

    function pad(str, len, ch) {
        padding = '';
        for (var i = 0; i < len - str.length; i++) {
            padding += ch;
        }
        return padding + str;
    }

    function setErrorState(field, err, msg) {
        var group = field.closest('.controls').parent();
        if (err) {
            group.addClass('has-error');
            group.attr('title',msg);
        } else {
            group.removeClass('has-error');
            group.attr('title','');
        }
    }
    
    function restoreLocalWallet(){ //20191017
        var local_prvkey=getLocalConfigData('local_prvkey');
        var local_odin=getLocalConfigData('local_odin');
        //alert("local_odin="+local_odin+"\nlocal_prvkey="+local_prvkey);
        
        if(local_odin!=null){
            $('#loginODIN').val(local_odin);
        }
        
        if(local_prvkey==null){
            genRandom();
        }else{
            $('#sec').val(local_prvkey);
            $('#sgSec').val(local_prvkey);
            $('#odinSec').val(local_prvkey);
            txOnChangeOdinSec();
            sgOnChangeSec();
        }
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

    
    function genRandomForODIN() {
        genRandom();
        
        //added by ppkpub,20181225
        $('#odinSec').val($('#sec').val());
        txOnChangeOdinSec();
    }

    function genRandom() {
        $('#pass').val('');
        $('#hash').focus();
        gen_from = 'hash';
        $('#from_hash').click();
        genUpdate();
        var bytes = secureRandom(32);
        $('#hash').val(Crypto.util.bytesToHex(bytes));
        generate();
    }

    function genUpdate() {
        setErrorState($('#hash'), false);
        setErrorState($('#sec'), false);
        setErrorState($('#der'), false);
        $('#pass').attr('readonly', gen_from != 'pass');
        $('#hash').attr('readonly', gen_from != 'hash');
        $('#sec').attr('readonly', gen_from != 'sec');
        $('#der').attr('readonly', gen_from != 'der');
        $('#sec').parent().parent().removeClass('error');
    }

    function genUpdateFrom() {
        gen_from = $(this).attr('id').substring(5);
        genUpdate();
        if (gen_from == 'pass') {
            if (gen_ps_reset) {
                gen_ps_reset = false;
                onChangePass();
            }
            $('#pass').focus();
        } else if (gen_from == 'hash') {
            $('#hash').focus();
        } else if (gen_from == 'sec') {
            $('#sec').focus();
        } else if (gen_from == 'der') {
            $('#der').focus();
        }
    }

    function generate() {
        var hash_str = pad($('#hash').val(), 64, '0');
        var hash = Crypto.util.hexToBytes(hash_str);
       
        eckey = new Bitcoin.ECKey(hash);
        gen_eckey = eckey;

        try {
            var curve = getSECCurveByName("secp256k1");
            gen_pt = curve.getG().multiply(eckey.priv);
            gen_eckey.pub = getEncoded(gen_pt, gen_compressed);
            gen_eckey.pubKeyHash = Bitcoin.Util.sha256ripe160(gen_eckey.pub);
            setErrorState($('#hash'), false);
        } catch (err) {
            //console.info(err);
            setErrorState($('#hash'), true, 'Invalid secret exponent (must be non-zero value)');
            return;
        }

        gen_update();
    }

    function genOnChangeCompressed() {
        setErrorState($('#hash'), false);
        setErrorState($('#sec'), false);
        gen_compressed = $(this).attr('name') == 'compressed';
        gen_eckey.pub = getEncoded(gen_pt, gen_compressed);
        gen_eckey.pubKeyHash = Bitcoin.Util.sha256ripe160(gen_eckey.pub);
        gen_update();
    }

    function getAddressURL(addr)
    {
        if (ADDRESS_URL_PREFIX.indexOf('explorer.dot-bit.org')>=0 )
          return ADDRESS_URL_PREFIX+'/a/'+addr;
        else if (ADDRESS_URL_PREFIX.indexOf('address.dws')>=0 )
          return ADDRESS_URL_PREFIX+ "?" + addr;
        else if (ADDRESS_URL_PREFIX.indexOf('chainbrowser.com')>=0 )
          return ADDRESS_URL_PREFIX+'/address/'+addr+'/';
        else
          return ADDRESS_URL_PREFIX+'/address/'+addr;
    }

    function gen_update() {

        var eckey = gen_eckey;
        var compressed = gen_compressed;

        var hash_str = pad($('#hash').val(), 64, '0');
        var hash = Crypto.util.hexToBytes(hash_str);

        var hash160 = eckey.getPubKeyHash();

        var h160 = Crypto.util.bytesToHex(hash160);
        $('#h160').val(h160);

        var addr = new Bitcoin.Address(hash160);
        addr.version = PUBLIC_KEY_VERSION;
        $('#addr').val(addr);

        var payload = hash;

        if (compressed)
            payload.push(0x01);

        var sec = new Bitcoin.Address(payload);
        sec.version = PRIVATE_KEY_VERSION;
        $('#sec').val(sec);

        var pub = Crypto.util.bytesToHex(getEncoded(gen_pt, compressed));
        $('#pub').val(pub);

        var der = Crypto.util.bytesToHex(getDER(eckey, compressed));
        $('#der').val(der);

        var qrCode = qrcode(3, 'M');
        var text = $('#addr').val();
        text = text.replace(/^[\s\u3000]+|[\s\u3000]+$/g, '');
        qrCode.addData(text);
        qrCode.make();

        $('#genAddrQR').html(qrCode.createImgTag(4));
        $('#genAddrURL').attr('href', getAddressURL(addr));
        $('#genAddrURL').attr('title', addr);
    }

    function genCalcHash() {
        var hash = Crypto.SHA256($('#pass').val(), { asBytes: true });
        $('#hash').val(Crypto.util.bytesToHex(hash));
    }

    function onChangePass() {
        genCalcHash();
        clearTimeout(timeout);
        timeout = setTimeout(generate, TIMEOUT);
    }

    function onChangeHash() {
        $('#pass').val('');
        gen_ps_reset = true;
        clearTimeout(timeout);

        if (/[^0123456789abcdef]+/i.test($('#hash').val())) {
            setErrorState($('#hash'), true, 'Erroneous characters (must be 0..9-a..f)');
            return;
        } else {
            setErrorState($('#hash'), false);
        }

        timeout = setTimeout(generate, TIMEOUT);
    }

    function setCompressed(compressed) {
      gen_compressed = compressed; // global
      // toggle radio button without firing an event
      $('#gen_comp label input').off();
      $('#gen_comp label input[name='+(gen_compressed?'compressed':'uncompressed')+']').click();
      $('#gen_comp label input').on('change', genOnChangeCompressed);
    }

    function genOnChangePrivKey() {

        clearTimeout(timeout);

        $('#pass').val('');
        gen_ps_reset = true;

        var sec = $('#sec').val();

        try { 
            var res = parseBase58Check(sec);
            var version = res[0];
            var payload = res[1];
        } catch (err) {
            setErrorState($('#sec'), true, 'Invalid private key checksum');
            return;
        };

        if (version != PRIVATE_KEY_VERSION) {
            setErrorState($('#sec'), true, 'Invalid private key version');
            return;
        } else if (payload.length != 32 && payload.length != 33) {
            setErrorState($('#sec'), true, 'Invalid payload (must be 32 or 33 bytes)');
            return;
        }

        setErrorState($('#sec'), false);

        if (payload.length > 32) {
            payload.pop();
            setCompressed(true);
        } else {
            setCompressed(false);
        }

        $('#hash').val(Crypto.util.bytesToHex(payload));

        timeout = setTimeout(generate, TIMEOUT);
    }

    function genUpdateDER() {
      var s = $('#der').val();
      s = s.replace(/[^A-Fa-f0-9]+/g, '');
      var bytes = Crypto.util.hexToBytes(s);
      try {
        var asn1 = ASN1.decode(bytes);
        var r = asn1.sub[1];
        if (r.length!=32)
          throw('key length mismatch');
        var ofs = r.header + r.stream.pos;
        var priv = r.stream.enc.slice(ofs, ofs + r.length);
        var hex = Crypto.util.bytesToHex(priv);
        $('#hash').val(hex);

        // get public key
        r = asn1.sub[2].sub[0].sub[3];
        ofs = r.header + r.stream.pos;
        var pub = r.stream.enc.slice(ofs, ofs + r.length);
        setCompressed(pub[0]!=0x04);

        setErrorState($('#der'), false);
        $('#pass').val('');

        generate();
      } catch (err) {
        setErrorState($('#der'), true, err);
      }
    }

    function genOnChangeDER() {
      timeout = setTimeout(genUpdateDER, TIMEOUT);
    }

    function genRandomPass() {
        // chosen by fair dice roll
        // guaranted to be random
        $('#from_pass').button('toggle');
        $('#pass').focus();
        gen_from = 'pass';
        genUpdate();
        genCalcHash();
        generate();
    }

    // --- converter ---

    var from = '';
    var to = 'hex';

    function update_enc_from() {
        $(this).addClass('active');
        from = $(this).attr('id').substring(5);
        translate();
    }

    function update_enc_to() {
        to = $(this).attr('id').substring(3);
        translate();
    }

    // stringToBytes, exception-safe
    function stringToBytes(str) {
      try {
        var bytes = Crypto.charenc.UTF8.stringToBytes(str);
      } catch (err) {
        var bytes = [];
        for (var i = 0; i < str.length; ++i)
           bytes.push(str.charCodeAt(i));
      }
      return bytes;
    }

    // bytesToString, exception-safe
    function bytesToString(bytes) {
      try {
        var str = Crypto.charenc.UTF8.bytesToString(bytes);
      } catch (err) {
        var str = '';
        for (var i = 0; i < bytes.length; ++i)
            str += String.fromCharCode(bytes[i]);
      }
      return str;
    }


    function isHex(str) {
        return !/[^0123456789abcdef]+/i.test(str);
    }

    function isBase58(str) {
        return !/[^123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]+/.test(str);
    }

    function isBase64(str) {
        return !/[^ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=]+/.test(str) && (str.length % 4) == 0;
    }

    function isBin(str) {
      return !/[^01 \r\n]+/i.test(str);
    }

    function isDec(str) {
      return !/[^0123456789]+/i.test(str);
    }

    function issubset(a, ssv, min_words) {
        var b = ssv.trim().split(' ');
        if (min_words>b.length)
            return false;
        for (var i = 0; i < b.length; i++) {
            if (a.indexOf(b[i].toLowerCase()) == -1 
                && a.indexOf(b[i].toUpperCase()) == -1)
            return false;
        }
        return true;
    }

    function isEasy16(str) {
      return !/[^asdfghjkwertuion \r\n]+/i.test(str);
    }

    function autodetect(str) {
        var enc = [];
        var bstr = str.replace(/[ :,\n]+/g,'').trim();
        if ( isBin(bstr) )
            enc.push('bin');
        if (isDec(bstr) )
            enc.push('dec');
        if (isHex(bstr))
            enc.push('hex');
        if (isBase58(bstr)) {
            // push base58check first (higher priority)
            try {
                var res = parseBase58Check(bstr);
                enc.push('base58check');
            } catch (err) {};
        }
        if (issubset(mn_words, str, 3))
            enc.push('mnemonic');
        if (issubset(rfc1751_wordlist, str, 6))
            enc.push('rfc1751');
        if (isEasy16(bstr))
          enc.push('easy16');
        if (isBase64(bstr))
            enc.push('base64');
        if (str.length > 0) {
            enc.push('text');
            enc.push('rot13');
        }
        if (isBase58(bstr)) {
          // arbitrary text should have higher priority than base58
          enc.push('base58');
        }
        return enc;
    }

    function update_toolbar(enc_list) {
        var reselect = false;

        $.each($('#enc_from').children(), function() {
            var enc = $(this).children().attr('id').substring(5);
            var disabled = (enc_list && enc_list.indexOf(enc) == -1);
            if (disabled && $(this).hasClass('active')) {
                $(this).removeClass('active');
                reselect = true;
            }
            $(this).attr('disabled', disabled);
        });

        if (enc_list && enc_list.length > 0) {
            if (reselect || from=='') {
              from = enc_list[0];
              $('#from_' + from).click();
            }
        }
    }

    function rot13(str) {
        return str.replace(/[a-zA-Z]/g, function(c) {
          return String.fromCharCode((c <= 'Z' ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26);
        });
    }

    function fromEasy16(str) {
      var keys = str.split('\n');
      var res = [];
      for (var i = 0; i < keys.length; i++) {
        var k = keys[i].replace(' ','');
        var raw = Crypto.util.hexToBytes(armory_map(k, armory_f, armory_t));
        data = raw.slice(0, 16);
        res = res.concat(data);
      }
      return res;
    }

    function toEasy16(bytes) {
        var keys = armory_encode_keys(bytes,[]);
        var lines = keys.split('\n');
        var res = [];
        for (var i in lines) {
          if (lines[i].trim(' ').split(' ').length==9)
            res.push(lines[i]);
        }
        return res.join('\n');
    }

    function toBin(bytes)
    {
      var arr = [];
      for (var i=0; i<bytes.length;i++)
      {
        var s = (bytes[i]).toString(2);
        arr.push(('0000000' + s).slice(-8));
      }
      return arr.join(' ');
    }

    function fromBin(str)
    {
      var arr = str.trim().split(/[\r\n ]+/);
      arr = [arr.join('')]; // this line actually kills separating bytes with spaces (people get confused), comment it out if you want
      var res = [];
      for (var i=0; i<arr.length; i++)
      {
        var bstr = arr[i];
        var s = ('0000000'+bstr).slice(-Math.ceil(bstr.length/8)*8); // needs padding
        var chunks = s.match(/.{1,8}/g);
         for (var j=0;j<chunks.length;j++)
          res.push(parseInt(chunks[j], 2));
      }
      return res;
    }

    function fromDec(str)
    {
        var h = new BigInteger(str).toString(16);
        return Crypto.util.hexToBytes(h.length%2?'0'+h:h);
    }

    function toDec(bytes)
    {
        var h = Crypto.util.bytesToHex(bytes);
        return new BigInteger(h,16).toString(10);
    }

    function enct(id) {
        return $('#from_'+id).parent().text();
    }

    function pad_array(bytes, n)
    {
      if (n==0) // remove padding
      {
        var res = bytes.slice(0);
        while (res.length>1 && res[0]==0)
          res.shift();
        return res;
      }

      // align to n bytes
      var len = bytes.length;
      var padding = Math.ceil(len/n)*n - len;
      var res = bytes.slice(0);
      for (i=0;i<padding;i++)
        res.unshift(0);
      return res;
    }

    function translate() {

        var str = $('#src').val();

        if (str.length == 0) {
          update_toolbar(null);
          $('#hint_from').text('');
          $('#hint_to').text('');
          $('#dest').val('');
          return;
        }

        text = str;

        var enc = autodetect(str);

        update_toolbar(enc);

        bytes = stringToBytes(str);

        var type = '';
        var addVersionByte = true; // for base58check

        if (bytes.length > 0) {
            var bstr = str.replace(/[ :,\n]+/g,'').trim();

            if (from == 'base58check') {
                try {
                    var res = parseBase58Check(bstr);
                    type = ' ver. 0x' + Crypto.util.bytesToHex([res[0]]);
                    bytes = res[1];
                    if (!addVersionByte)
                      bytes.unshift(res[0]);
                } catch (err) {};
            } else if (from == 'base58') {
                bytes = Bitcoin.Base58.decode(bstr);
            } else if (from == 'hex') {
                bytes = Crypto.util.hexToBytes(bstr.length%2?'0'+bstr:bstr); // needs padding
            } else if (from == 'rfc1751') {
                try { bytes = english_to_key(str); } catch (err) { type = ' ' + err; bytes = []; };
            } else if (from == 'mnemonic') {
                bytes = Crypto.util.hexToBytes(mn_decode(str.trim()));
            } else if (from == 'base64') {
                try { bytes = Crypto.util.base64ToBytes(bstr); } catch (err) {}
            } else if (from == 'rot13') {
                bytes = stringToBytes(rot13(str));
            } else if (from == 'bin') {
                bytes = fromBin(str);
            } else if (from == 'easy16') {
                bytes = fromEasy16(str);
            } else if (from == 'dec') {
                bytes = fromDec(bstr);
            }

            var ver = '';
            if (to == 'base58check') {
               var version = bytes.length <= 20 ? PUBLIC_KEY_VERSION : PRIVATE_KEY_VERSION;
               var buf = bytes.slice();
               if (!addVersionByte)
                version = buf.shift();
               var addr = new Bitcoin.Address(buf);
               addr.version = version;
               text = addr.toString();
               ver = ' ver. 0x' + Crypto.util.bytesToHex([addr.version]);
            } else if (to == 'base58') {
                text = Bitcoin.Base58.encode(bytes);
            } else if (to == 'hex') {
                text = Crypto.util.bytesToHex(bytes);
            } else if (to == 'text') {
                text = bytesToString(bytes);
            } else if (to == 'rfc1751') {
                text = key_to_english(pad_array(bytes,8));
            } else if (to == 'mnemonic') {
                text = mn_encode(Crypto.util.bytesToHex(pad_array(bytes,4)));
            } else if (to == 'base64') {
                text = Crypto.util.bytesToBase64(bytes);
            } else if (to == 'rot13') {
                text = rot13(bytesToString(bytes));
            } else if (to == 'bin') {
                text = toBin(bytes);
            } else if (to == 'easy16') {
                text = toEasy16(pad_array(bytes,32));
            } else if (to == 'dec') {
                text = toDec(bytes);
            }
        }

        $('#hint_from').text(enct(from) + type + ' (' + bytes.length + ' byte' + (bytes.length == 1 ? ')' : 's)'));
        $('#hint_to').text(enct(to) + ver + ' (' + text.length + ' character' + (text.length == 1 ? ')' : 's)'));
        $('#dest').val(text);
    }

    function onChangeFrom() {
        clearTimeout(timeout);
        timeout = setTimeout(translate, TIMEOUT);
    }

    function onInput(id, func) {
        $(id).bind("input keyup keydown keypress change blur", function() {
            if ($(this).val() != jQuery.data(this, "lastvalue")) {
                func();
            }
            jQuery.data(this, "lastvalue", $(this).val());
        });
        $(id).bind("focus", function() {
           jQuery.data(this, "lastvalue", $(this).val());
        });
    }

    // --- chain ---
    var chMode = 'csv';
    var chAddrList = [];
    var chRange = 1;
    var chType = 'armory';

    function chOnChangeType() {
        var id = $(this).attr('id');

        if (chType != id) {
            $('#chCode').val('');
            $('#chRoot').val('');
            $('#chBackup').val('');
            $('#chMsg').text('');
            $('#chList').text('');
            chOnStop();
        }

        $('#chChange').attr('disabled', id != 'electrum');

        chType = id;
    }

    function chOnChangeFormat() {
        chMode = $(this).attr('id');
        chUpdate();
    }

    function chAddrToCSV(i, r) {
        return i + ', "' + r[0] +'", "' + r[1] +'"\n';
    }

    function chUpdate() {
        if (chAddrList.length == 0)
            return;
        var str = '';
        if (chMode == 'csv') {
            for (var i = 0; i < chAddrList.length; i++)
                str += chAddrToCSV(i+1, chAddrList[i]);

        } else if (chMode == 'json') {

            var w = {};
            w['keys'] = [];
            for (var i = 0; i < chAddrList.length; i++)
                w['keys'].push({'addr':chAddrList[i][0],'sec':chAddrList[i][1]});
            str = JSON.stringify(w, null, 4);
        }
        $('#chList').text(str);

        chRange = parseInt($('#chRange').val());

        var c = (chType == 'electrum') ? parseInt($('#chChange').val()) : 0;

        if (chAddrList.length >= chRange+c)
            chOnStop();

    }

    function chOnChangeCode() {
        $('#chRoot').val('');
        $('#chMsg').text('');
        chOnStop();
        $('#chBackup').val( mn_encode(chRoot) );
        clearTimeout(timeout);
        timeout = setTimeout(chGenerate, TIMEOUT);
    }

    function chUpdateBackup() {
        var str =  $('#chBackup').val();

        if (str.length == 0) {
            chOnStop();
            $('#chCode').val('');
            $('#chRoot').val('');
            $('#chBackup').val('');
            $('#chMsg').text('');
            $('#chList').text('');
            return;
        }

        if (chType == 'electrum') {
            str = str.trim();
            if (issubset(mn_words, str, 12))  {
                var seed = mn_decode(str);
                $('#chRoot').val(seed);
                var words = str.split(' ');
                if (words.length!=12)
                {
                  $('#chList').text('');
                  return;
                }
            } else {
              $('#chRoot').val('');
              $('#chCode').val('');
              chOnStop();
            }
        }

        if (chType == 'armory') {
            var keys = armory_decode_keys(str);
            if (keys != null) {
                var pk = keys[0];
                var cc = keys[1];
                $('#chRoot').val(Crypto.util.bytesToHex(pk));
                $('#chCode').val(Crypto.util.bytesToHex(cc));

                var lines = str.split('\n');
                var text = lines.join(' ');
                var words = text.split(/\s+/);
                if (words.length!=9*2 && words.length!=9*4)
                {
                  $('#chList').text('');
                  return;
                }
            }
        }

        clearTimeout(timeout);
        timeout = setTimeout(chGenerate, TIMEOUT);
    }

    function chOnChangeBackup() {
        clearTimeout(timeout);
        timeout = setTimeout(chUpdateBackup, TIMEOUT);
    }

    function chOnRandom() {
        var pk = secureRandom(32);

        if (chType == 'armory') {
            var cc = armory_derive_chaincode(pk);
            $('#chRoot').val(Crypto.util.bytesToHex(pk));
            $('#chCode').val(Crypto.util.bytesToHex(cc));
            $('#chBackup').val(armory_encode_keys(pk, cc).split('\n').slice(0,2).join('\n'));
        }

        if (chType == 'electrum') {
            var seed = Crypto.util.bytesToHex(pk.slice(0,16));
            //nb! electrum doesn't handle trailing zeros very well
            if (seed.charAt(0) == '0') seed = seed.substr(1);
            $('#chRoot').val(seed);
            $('#chBackup').val(mn_encode(seed));
        }
        chGenerate();
    }

    function chOnStop() {
        Armory.stop();
        Electrum.stop();
        if (chType == 'electrum') {
            $('#chMsg').text('');
        }
    }

    function chOnChangeRange()
    {
        if ( chAddrList.length==0 )
          return;
        clearTimeout(timeout);
        timeout = setTimeout(chUpdateRange, TIMEOUT);
    }

    function chCallback(r) {
        chAddrList.push(r);
        $('#chList').append(chAddrToCSV(chAddrList.length,r));
    }

    function chElectrumUpdate(r, seed) {
        $('#chMsg').text('key stretching: ' + r + '%');
        $('#chCode').val(Crypto.util.bytesToHex(seed));
    }

    function chElectrumSuccess(privKey) {
        $('#chMsg').text('');
        $('#chCode').val(Crypto.util.bytesToHex(privKey));
        var addChange = parseInt($('#chChange').val());
        Electrum.gen(chRange, chCallback, chUpdate, addChange);
    }

    function chUpdateRange() {
        chRange = parseInt($('#chRange').val());
        chAddrList = [];

        $('#chList').text('');

        if (chType == 'electrum') {
            var addChange = parseInt($('#chChange').val());
            Electrum.stop();
            Electrum.gen(chRange, chCallback, chUpdate, addChange);
        }

        if (chType == 'armory') {
            var codes = $('#chBackup').val();
            Armory.gen(codes, chRange, chCallback, chUpdate);
        }
    }

    function chGenerate() {
        clearTimeout(timeout);

        var seed = $('#chRoot').val();
        var codes = $('#chBackup').val();

        chAddrList = [];

        $('#chMsg').text('');
        $('#chList').text('');

        Electrum.stop();

        if (chType == 'electrum') {
           if (seed.length == 0)
               return;
            Electrum.init(seed, chElectrumUpdate, chElectrumSuccess);
        }

        if (chType == 'armory') {
            var uid = Armory.gen(codes, chRange, chCallback, chUpdate);
            if (uid)
                $('#chMsg').text('uid: ' + uid);
            else
                return;
        }
    }

    // -- transactions --

    var txType = 'txBCI';
    var txFrom = 'txFromSec';

    function txGenSrcAddr() {
        var updated = updateAddr ($('#odinSec'), $('#txAddr'));

        $('#txBalance').val('0.00');

        if (updated && txFrom=='txFromSec')
            txGetUnspent();
    }
    
    function txOnChangeOdin() {
        saveLocalConfigData('local_odin',$('#loginODIN').val());
    }

    function txOnChangeOdinSec() {
        saveLocalConfigData('local_prvkey',$('#odinSec').val());
        
        clearTimeout(timeout);
        timeout = setTimeout(txGenSrcAddr, TIMEOUT);
    }

    function txOnChangeAddr() {
        clearTimeout(timeout);
        timeout = setTimeout(txGetUnspent, TIMEOUT);
    }

    function txSetUnspent(text) {
        var r = JSON.parse(text);
        txUnspent = JSON.stringify(r, null, 4);
        $('#txUnspent').val(txUnspent);
        var address = $('#txAddr').val();
        TX.parseInputs(txUnspent, address);
        var value = TX.getBalance();
        var fval = Bitcoin.Util.formatValue(value);
        var fee = parseFloat($('#txFee').val());
        $('#txBalance').val(fval);
        var value = Math.floor((fval-fee)*1e8)/1e8;
        //$('#txValue').val(value);
        //txRebuild();
        
        $('#txBuildTranaction').attr('disabled', $('#txUnspent').val()=="");
    }

    function txUpdateUnspent() {
        txSetUnspent($('#txUnspent').val());
    }

    function txOnChangeUnspent() {
        clearTimeout(timeout);
        timeout = setTimeout(txUpdateUnspent, TIMEOUT);
    }

    function txParseUnspent(text) {
        if (text=='' || text=='{}') {
            alert('No data');
            return;
        }
        txSetUnspent(text);
    }

    function txGetUnspent() {
        var addr = $('#txAddr').val();

        var url = (txType == 'txBCI') ? 'https://blockchain.info/unspent?cors=true&active=' + addr :
            'http://btc.blockr.io/api/v1/address/unspent/'+ addr + '?multisigs=1'
            //'https://blockexplorer.com/api/addr/'+ addr + '/utxo' ;

        //url = prompt('Press OK to download transaction history:', url);

        if (url != null && url != "") {

            $('#txUnspent').val('');

            $.getJSON(url, function(data) {
              txParseUnspent ( JSON.stringify(data, null, 2) );
            }).fail(function(jqxhr, textStatus, error) {
              console.log( typeof(jqxhr.responseText)=='undefined' ? jqxhr.statusText 
                : ( jqxhr.responseText!='' ? jqxhr.responseText : 'No data, probably Access-Control-Allow-Origin error.') );
            });

        } else {
          txSetUnspent($('#txUnspent').val());
        }
    }

    function txOnChangeJSON() {
        var str = $('#txJSON').val();
        try {
          var sendTx = TX.fromBBE(str);
          $('txJSON').removeClass('has-error');
          var bytes = sendTx.serialize();
          var hex = Crypto.util.bytesToHex(bytes);
          $('#txHex').val(hex);
          if (!TX.getBalance().equals(BigInteger.ZERO))
            $('#txFee').val(Bitcoin.Util.formatValue(TX.getFee(sendTx)));
          setErrorState($('#txJSON'), false, '');
        } catch (err) {
          setErrorState($('#txJSON'), true, 'syntax error');
        }

        $('#txSend').attr('disabled', $('#txHex').val()=="");
    }

    function txOnChangeHex() {
        var str = $('#txHex').val();
        str = str.replace(/[^0-9a-fA-f]/g,'');
        $('#txHex').val(str);
        var bytes = Crypto.util.hexToBytes(str);
        var sendTx = TX.deserialize(bytes);
        var text = TX.toBBE(sendTx);
        $('#txJSON').val(text);
        $('#txSend').attr('disabled', $('#txHex').val()=="");
    }

    function txOnAddDest() {
        var list = $(document).find('.txCC');
        var clone = list.last().clone();
        clone.find('.help-inline').empty();
        clone.find('.control-label').text('Cc');
        var dest = clone.find('#txDest');
        var value = clone.find('#txValue');
        clone.insertAfter(list.last());
        onInput(dest, txOnChangeDest);
        onInput(value, txOnChangeDest);
        dest.val('');
        value.val('');
        $('#txRemoveDest').attr('disabled', false);
        return false;
    }

    function txOnRemoveDest() {
        var list = $(document).find('.txCC');
        if (list.size() == 2)
            $('#txRemoveDest').attr('disabled', true);
        list.last().remove();
        return false;
    }

    function txSent(text) {
        alert(text ? text : 'OK , Transaction boardcasted!');
    }

    function txSend() {
        var txAddr = $('#txAddr').val();

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

            $.post(url, { "tx": tx }, function(data) {
              txSent(data.responseText);
            }).fail(function(jqxhr, textStatus, error) {
              alert( typeof(jqxhr.responseText)=='undefined' ? jqxhr.statusText
                : ( jqxhr.responseText!='' ? jqxhr.responseText : 'No data, probably Access-Control-Allow-Origin error.') );
            });

        }

        return false;
    }

    function txRebuild() {
        var sec = $('#odinSec').val();
        var addr = $('#txAddr').val();
        var unspent = $('#txUnspent').val();
        txUpdateUnspent();
        var balance = parseFloat($('#txBalance').val());
        var fee = parseFloat('0'+$('#txFee').val());

        try {
            var res = parseBase58Check(sec); 
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
        var o = txGetOutputs();
        for (i in o) {
            TX.addOutput(o[i].dest, o[i].fval);
            fval += o[i].fval;
        }
        
        o = txGetODINOutputScripts(pubkey_hex);
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
            setErrorState($('#txJSON'), false, '');
            $('#txJSON').val(txJSON);
            $('#txHex').val(txHex);
        /*} catch(err) {
            alert('err=',err);
            $('#txJSON').val('');
            $('#txHex').val('');
        }*/
        if($('#txHex').val()==""){
            $('#txSend').attr('disabled', true);
        }else{
            $('#txSend').attr('disabled', false);
            txSend();
        }
    }
    
    //将指定ODIN数据字符串构建为多重签名输出数据块
    function txGetODINOutputScripts( register_pubkey_hex ){
      var res = [];

      PPK_ODIN_MARK_PUBKEY_HEX=$('#txOdinMarkPubkey').val();

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
      
      var str_title_encoded=encodeURI($('#txOdinTitle').val(),"utf-8");
      if(str_title_encoded.length>max_user_input_length){
        str_title_encoded=str_title_encoded.substr(0,max_user_input_length);
      }
      
      var str_email_encoded=',"email":'+JSON.stringify($('#txOdinEmail').val());
      if(str_email_encoded.length+str_title_encoded.length>max_user_input_length){
        str_email_encoded="";
      }
      
      var str_odin_setting='{"ver":1,"title":"'+str_title_encoded+'"'+str_email_encoded+',"auth":"'+$('#txOdinAuth').val()+'"}';
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

    function txSign() {
        if (txFrom=='txFromSec')
        {
          txRebuild();
          return;
        }

        var str = $('#txJSON').val();
        TX.removeOutputs();
        var sendTx = TX.fromBBE(str);

        try {
            sendTx = TX.resign(sendTx);
            $('#txJSON').val(TX.toBBE(sendTx));
            $('#txHex').val(Crypto.util.bytesToHex(sendTx.serialize()));
            $('#txFee').val(Bitcoin.Util.formatValue(TX.getFee(sendTx)));
        } catch(err) {
            $('#txJSON').val('');
            $('#txHex').val('');
        }
        $('#txSend').attr('disabled', $('#txHex').val()=="");
    }

    function txOnChangeDest() {
        var balance = parseFloat($('#txBalance').val());
        var fval = parseFloat('0'+$('#txValue').val());
        var fee = parseFloat('0'+$('#txFee').val());

        if (fval + fee > balance) {
            fee = balance - fval;
            $('#txFee').val(fee > 0 ? fee : '0.00');
        }

        clearTimeout(timeout);
        //timeout = setTimeout(txRebuild, TIMEOUT);
    }

    function txShowUnspent() {
        var div = $('#txUnspentForm');

        if (div.hasClass('hide')) {
            div.removeClass('hide');
            $('#txShowUnspent').text('Hide Outputs');
        } else {
            div.addClass('hide');
            $('#txShowUnspent').text('Show Outputs');
        }
    }
    
    function openOdinTool(){
        var odin_tool_url='http://tool.ppkpub.org:9876/odin';
        
        var current_address=$('#txAddr').val();
        
        if( current_address.length ==0 ){
            alert('浏览器钱包还没有可用的比特币地址，请先创建或导入一个比特币地址！');
            return;
        }
        
        window.open(odin_tool_url+"?address="+current_address);
    }

    function txChangeType() {
        txType = $(this).attr('id');
    }

    function txChangeFrom() {
      txFrom = $(this).attr('id');
      var bFromKey = txFrom=='txFromSec' || txFrom=='txFromPass';
      $('#txJSON').attr('readonly', txFrom!='txFromJSON');
      $('#txHex').attr('readonly', txFrom!='txFromRaw');
      $('#txFee').attr('readonly', !bFromKey);
      $('#txAddr').attr('readonly', !bFromKey);

      $.each($(document).find('.txCC'), function() {
        $(this).find('#txDest').attr('readonly', !bFromKey);
        $(this).find('#txValue').attr('readonly', !bFromKey);
      });

      if ( txFrom=='txFromRaw' )
        $('#txHex').focus();
      else if ( txFrom=='txFromJSON' )
        $('#txJSON').focus();
      else if ( bFromKey )
        $('#odinSec').focus();
    }

    function txOnChangeFee() {

        var balance = parseFloat($('#txBalance').val());
        var fee = parseFloat('0'+$('#txFee').val());

        var fval = 0;
        var o = txGetOutputs();
        for (i in o) {
            TX.addOutput(o[i].dest, o[i].fval);
            fval += o[i].fval;
        }

        if (fval + fee > balance) {
            fval = balance - fee;
            //$('#txValue').val(fval < 0 ? 0 : fval);
        }

        if (fee == 0 && fval == balance - 0.0001) {
        //    $('#txValue').val(balance);
        }

        clearTimeout(timeout);
        //timeout = setTimeout(txRebuild, TIMEOUT);
    }

    function txGetOutputs() {
        var res = [];
        $.each($(document).find('.txCC'), function() {
            var dest = $(this).find('#txDest').val();
            var fval = parseFloat('0' + $(this).find('#txValue').val());
            res.push( {"dest":dest, "fval":fval } );
        });
        return res;
    }

    // -- sign --
    function updateAddr(from, to, bUpdate) {
        setErrorState(from, false);
        var sec = from.val();
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
                from.val(wif.toString());
            }
        } catch (err) {
            if (from.val())
              setErrorState(from, true, err);
            return false;
        }
        to.val(addr);
        return {"key":eckey, "compressed":compressed, "addrtype":PUBLIC_KEY_VERSION, "address":addr};
    }

    function sgGenAddr() {
        updateAddr($('#sgSec'), $('#sgAddr'));
    }

    function sgOnChangeSec() {
        saveLocalConfigData('local_prvkey',$('#sgSec').val());
        
        $('#sgSig').val('');
        $('#sgLabel').html('');
        clearTimeout(timeout);
        timeout = setTimeout(sgGenAddr, TIMEOUT);
    }

    function sgOnChangeMsg() {
        $('#sgSig').val('');
        $('#sgLabel').html('');
    }

    function fullTrim(message)
    {
        message = message.replace(/^\s+|\s+$/g, '');
        message = message.replace(/^\n+|\n+$/g, '');
        return message;
    }

    var sgHdr = [
      "-----BEGIN BITCOIN SIGNED MESSAGE-----",
      "-----BEGIN SIGNATURE-----",
      "-----END BITCOIN SIGNED MESSAGE-----"
    ];

    var qtHdr = [
      "-----BEGIN BITCOIN SIGNED MESSAGE-----",
      "-----BEGIN BITCOIN SIGNATURE-----",
      "-----END BITCOIN SIGNATURE-----"
    ];

    function joinMessage(type, addr, msg, sig)
    {
      if (type=='inputs_io')
        return sgHdr[0]+'\n'+msg +'\n'+sgHdr[1]+'\n'+addr+'\n'+sig+'\n'+sgHdr[2];
      else if (type=='multibit')
        return qtHdr[0]+'\n'+msg +'\n'+qtHdr[1]+'\nVersion: Bitcoin-qt (1.0)\nAddress: '+addr+'\n\n'+sig+'\n'+qtHdr[2];
      else
        return sig;
    }

    function sgSign() {
      if ( $('#loginODIN').val().length==0 ){
        alert("Please set your ODIN forst!");
        return;
      }
      console.log("11111:"+$('#loginURL').val());
      if ( $('#loginURL').val().length==0 ){
        alert("Please scan a qrcode first then confirm!");
        return;
      }
      
      var sgMsg = $('#sgMsg').val();
      var p = updateAddr($('#sgSec'), $('#sgAddr'));

      if ( !sgMsg || !p ){
        alert("invalid signature");
        return;
      }
      
      sgMsg = fullTrim(sgMsg);

      var label = '';

      var sgSig = "bitcoin_secp256k1:"+sign_message(p.key, sgMsg, p.compressed, p.addrtype);
      $('#sgSig').val(sgSig);
      
      var confirmUrl=vrPermalink($('#loginURL').val(), $('#loginODIN').val(), sgMsg, sgSig);
      label = '(<a href="'+confirmUrl+'" target=_blank>Confirm login</a>)';

      $('#sgLabel').html(label);
      
      window.location.href = confirmUrl;
    }

    // -- verify --

    function stringToHex(str){
      var val="";
      for(var i = 0; i < str.length; i++){
          if(val == "")
              val = str.charCodeAt(i).toString(16);
          else
              val += str.charCodeAt(i).toString(16);
      }
      return val;
    }

    function vrPermalink(loginURL,odinURI,msg,sig)
    {
      return loginURL+'&user_odin_uri='+encodeURIComponent(odinURI)+'&auth_txt_hex='+stringToHex(msg)+'&user_sign='+encodeURIComponent(sig)+'&response_type=html';
    }

    function splitSignature(s)
    {
      var addr = '';
      var sig = s;
      if ( s.indexOf('\n')>=0 )
      {
        var a = s.split('\n');
        addr = a[0];

        // always the last
        sig = a[a.length-1];

        // try named fields
        var h1 = 'Address: ';
        for (i in a) {
          var m = a[i];
          if ( m.indexOf(h1)>=0 )
            addr = m.substring(h1.length, m.length);
        }

        // address should not contain spaces
        if (addr.indexOf(' ')>=0)
          addr = '';

        // some forums break signatures with spaces
        sig = sig.replace(" ","");
      }
      return { "address":addr, "signature":sig };
    }

    function splitMessage(s)
    {
      var p = armory_split_message(s);
      if (p)
        return p;

      s = s.replace('\r','');

      for (var i=0; i<2; i++ )
      {
        var hdr = i==0 ? sgHdr : qtHdr;
        var type = i==0 ? "inputs_io" : "multibit";

        var p0 = s.indexOf(hdr[0]);
        if ( p0>=0 )
        {
          var p1 = s.indexOf(hdr[1]);
          if ( p1>p0 )
          {
            var p2 = s.indexOf(hdr[2]);
            if ( p2>p1 )
            {
              var msg = s.substring(p0+hdr[0].length+1, p1-1);
              var sig = s.substring(p1+hdr[1].length+1, p2-1);
              var m = splitSignature(sig);
              msg = fullTrim(msg); // doesn't work without this
              return { "message":msg, "address":m.address, "signature":m.signature, "type":type };
            }
          }
        }
      }
      return false;
    }

    function vrVerify() {

        var vrMsg = $('#vrMsg').val();
        var vrAddr = $('#vrAddr').val();
        var vrSig = $('#vrSig').val();

        console.log(vrMsg, vrAddr, vrSig);

        var vrVer = PUBLIC_KEY_VERSION;

        var bSplit = $('#vrFromMessage').parent().hasClass('active');

        if (bSplit && !vrMsg)
          return;

        if (!bSplit && (!vrMsg || !vrSig))
          return;

        var addr = null;
        var p = null;

        if (bSplit) {
          p = splitMessage(vrMsg);
          vrAddr = p.address;
          vrMsg = p.message;
          vrSig = p.signature;

          // try armory first
          addr = armory_verify_message(p);
        }

        if (!addr) {
          try { vrVer = parseBase58Check(vrAddr)[0]; } catch (err) {};
          addr = verify_message(vrSig, vrMsg, vrVer);
        }

        var armoryMsg = "";
        if (p && p.type=="armory_base64" && p.message) {
          armoryMsg = p.message;
          console.log(armoryMsg);
        }

        $('#vrAlert').empty();

        var clone = $('#vrError').clone();

        // also check address was mentioned somewhere in the message (may be unsafe)
        if (!vrAddr && addr && vrMsg.search(addr)!=-1)
          vrAddr = addr;

        if (addr && (vrAddr==addr || !vrAddr)) {
          clone = vrAddr==addr ? $('#vrSuccess').clone() : $('#vrWarning').clone();

          var label = addr;

          // insert link here
          if (vrAddr==addr && p.type!="armory_hex")
            label = vrAddr +
              ' (<a href="#verify'+vrPermalink(vrAddr,vrMsg,vrSig)+'" target=_blank>permalink</a>)';

          clone.find('#vrAddrLabel').html(label);
        }

        clone.appendTo($('#vrAlert'));

        //if (armoryMsg) alert(armoryMsg);

        return false;
    }

    function vrOnInput() {
        $('#vrAlert').empty();
        vrVerify();
    }


    function vrOnChange() {
        clearTimeout(timeout);
        timeout = setTimeout(vrOnInput, TIMEOUT);
    }

    function crChange()
    {
      var p = $(this).attr('data-target').split(',',2);
      if (p.length>0)
        PUBLIC_KEY_VERSION = parseInt(p[0]);
      PRIVATE_KEY_VERSION = p.length>1 ? parseInt(p[1]) : ((PUBLIC_KEY_VERSION+128) & 255);
      ADDRESS_URL_PREFIX = $(this).attr('href');

      var name = $(this).text();
      var child = $(this).children();
      if (child.length)
        name = child.text();

      $('#crName').text(name);

      $('#crSelect').dropdown('toggle');
      gen_update();
      translate();

      updateAddr($('#sgSec'), $('#sgAddr'));
      updateAddr($('#odinSec'), $('#txAddr'));

      return false;
    }

    $(document).ready( function() {

        if ((window.location.host=='brainwallet.github.io' || window.location.host=='brainwallet.org') && window.location.protocol!="https:")
            window.location.protocol = "https";

        if (window.location.hash)
          $('#tab-' + window.location.hash.substr(1).split('?')[0]).tab('show');

        $('a[data-toggle="tab"]').on('click', function (e) {
            window.location.hash = $(this).attr('href');
        });


        $('#tab-gen').on('shown.bs.tab', function() { $('#'+gen_from).focus(); });
        $('#tab-chains').on('shown.bs.tab', function() { $('#chBackup').focus(); });
        $('#tab-tx').on('shown.bs.tab', function() { $('#odinSec').focus(); });
        $('#tab-converter').on('shown.bs.tab', function() { $('#src').focus(); });
        $('#tab-sign').on('shown.bs.tab', function() { $('#sgSec').focus(); });
        $('#tab-verify').on('shown.bs.tab', function() { $('#vrMsg').focus(); });
        
        //ODIN
        $('#genRandomForODIN').click(genRandomForODIN);
        $('#txRefreshRegisterBalance').click(txOnChangeOdinSec);
        
        // generator

        onInput('#pass', onChangePass);
        onInput('#hash', onChangeHash);
        onInput('#sec', genOnChangePrivKey);
        onInput('#der', genOnChangeDER);

        $('#genRandom').click(genRandom);

        $('#gen_from label input').on('change', genUpdateFrom );
        $('#gen_comp label input').on('change', genOnChangeCompressed);

        restoreLocalWallet();
        //genRandom();
        //genRandomPass();

        // chains

        $('#chRandom').click(chOnRandom);

        $('#chType label input').on('change', chOnChangeType);
        $('#chFormat label input').on('change', chOnChangeFormat);

        onInput($('#chRange'), chOnChangeRange);
        onInput($('#chCode'), chOnChangeCode);
        onInput($('#chBackup'), chOnChangeBackup);
        onInput($('#chChange'), chOnChangeRange);
        chRange = parseInt($('#chRange').val());

        // transactions

        //$('#odinSec').val(tx_sec);
        //$('#txAddr').val(tx_addr);
        //$('#txDest').val(tx_dest);

        //txSetUnspent(tx_unspent);

        $('#txGetUnspent').click(txGetUnspent);
        $('#txType label input').on('change', txChangeType);
        $('#txFrom label input').on('change', txChangeFrom);

        onInput($('#odinSec'), txOnChangeOdinSec);
        onInput($('#loginODIN'), txOnChangeOdin);
        onInput($('#txAddr'), txOnChangeAddr);
        onInput($('#txUnspent'), txOnChangeUnspent);
        onInput($('#txHex'), txOnChangeHex);
        onInput($('#txJSON'), txOnChangeJSON);
        onInput($('#txDest'), txOnChangeDest);
        onInput($('#txValue'), txOnChangeDest);
        onInput($('#txFee'), txOnChangeFee);

        $('#txAddDest').click(txOnAddDest);
        $('#txRemoveDest').click(txOnRemoveDest);
        $('#txBuildTranaction').click(txRebuild);
        $('#txBuildTranaction').attr('disabled', true);
        $('#openOdinTool').click(openOdinTool);
        $('#txSend').click(txSend);
        $('#txSign').click(txSign);
        $('#txSign').attr('disabled', true);
        $('#txSend').attr('disabled', true);

        // converter

        onInput('#src', onChangeFrom);

        $('#enc_from label input').on('change', update_enc_from );
        $('#enc_to label input').on('change', update_enc_to );

        // sign

        $('#sgSec').val($('#sec').val());
        $('#sgAddr').val($('#addr').val());
        $('#sgMsg').val("");

        onInput('#sgSec', sgOnChangeSec);
        onInput('#sgMsg', sgOnChangeMsg);

        //$('#sgType label input').on('change', function() { if ($('#sgSig').val()!='') sgSign(); } );

        $('#sgSign').click(sgSign);
        $('#sgForm').submit(sgSign);

        // verify

        $('#vrVerify').click(vrVerify);

        $('#vrFrom label input').on('change', function() {
          var bJoin = $(this).attr('id')=="vrFromMessage";
          $('.vrAddr').attr('hidden', bJoin);
          $('.vrSig').attr('hidden', bJoin);
          $('#vrMsg').attr('rows', bJoin ? 14:9);

          // convert from Bitcoin-QT to signed message and vice-versa
          if (bJoin) {
            var p = { "address": $('#vrAddr').val(), "message":$('#vrMsg').val(), "signature":$('#vrSig').val() };
            if (p.message && p.signature && $('#vrMsg'))
              $('#vrMsg').val(joinMessage("inputs_io", p.address, p.message, p.signature));
          } else {
            var p = splitMessage($('#vrMsg').val());
            if (p) {

              if (p.type=="armory_hex") {
                $('#vrAlert').empty();
                console.log('impossible to convert signature, message digest is incompatible with bitcoin-qt');
                p = { "message": $('#vrMsg').val() };
              }

              $('#vrAddr').val(p.address)
              $('#vrMsg').val(p.message)
              $('#vrSig').val(p.signature);
            }
          }

        });

        onInput($('#vrAddr'), vrOnChange);
        onInput($('#vrMsg'), vrOnChange);
        onInput($('#vrSig'), vrOnChange);

        // permalink support
        if ( window.location.hash && window.location.hash.indexOf('?')!=-1 ) {
          var args = window.location.hash.split('?')[1].split('&');
          var p = {};
          for ( var i=0; i<args.length; i++ ) {
            var arg = args[i].split('=');
            p[arg[0]] = decodeURIComponent(arg[1]);
          }
          if (p.vrMsg && p.vrSig) {
            $('#vrMsg').val(joinMessage( "inputs_io", (p.vrAddr||"<insert address here>"), p.vrMsg, p.vrSig ));
            vrVerify();
          }
        }

        // currency select

        $('#crCurrency ul li a').on('click', crChange);

        // init secure random
        try {
          var r = secureRandom(32);
          $('#genRandom').attr('disabled', false);
          $('#chRandom').attr('disabled', false);
        } catch (err) {
          console.log ('secureRandom is not supported');
        }

    });
})(jQuery);
