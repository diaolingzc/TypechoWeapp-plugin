# TpyechoWeapp-plugin #

## 安装 ##

1. 下载文件并解压上传至 `Typecho` 程序 `/usr/plugins/` 目录下
2. 将 `TypechoWeapp-plugin-master` 文件夹重命名为 `TypechoWeapp`
3. 进入 `Typecho` 后台插件管理（控制台->插件)，启用 `TypechoWeapp` 插件

## 设置 ## 
1. 点击 `TypechoWeapp` 插件的设置键
2. 配置 `首页轮播`, `API秘钥`, `微信小程序的APPID`, `微信小程序的APP secret ID`, `关于页面CID`, `博客作者昵称`, `显示头像` 等，保存设置。

> `API秘钥` 要与小程序端config.js中API_SECRET字段保持一致，否则无法从服务器读取数据
> `微信小程序的APPID`, `微信小程序的APP secret ID` 请参考[微信小程序-申请账号](https://developers.weixin.qq.com/miniprogram/dev/framework/quickstart/getstart.html#%E7%94%B3%E8%AF%B7%E5%B8%90%E5%8F%B7)

## 鸣谢 ##
本插件部分参考 [WeTypecho](https://github.com/MingliangLu/WeTypecho)。