<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <title>PPk小工具DEBUG清理缓存</title>
    <meta content="ppkpub.org" name="author" />
    <meta content="PPk tool for MircoMsg include Scan&Login,ODIN register" name="description" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    

   
</head>
<script>
var input = prompt("请输入清理代码(clear)");
if( input =='clear' ){
    if(typeof(Storage)!=="undefined")
    {
        localStorage.clear();
        alert("缓存已清理");
    }
}else{
    alert("错误！代码不正确");
}
</script>
</body>
</html>
