# IP签名档

项目介绍及使用教程：[https://wxsnote.cn/2762.html](https://wxsnote.cn/2762.html)

修改自：[https://github.com/xhboke/IP](https://github.com/xhboke/IP)

## 基本修改说明

1.删除原有失效API

2.新增三种API(高德，腾讯，太平洋)

3.判断请求头中IP，如套了CDN也能获取用户IP

4.删除多余无效功能

5.适配更高PHP版本

6.增加轮番查询，直至查询到定位归属地

## 介绍

IP签名档显示归属地、日期、操作系统、IP地址。用于放在网站某个地方的欢迎标签，基于开源程序：[IP签名档](https://github.com/xhboke/IP) 修改

本站提供的是[王先生笔记]二开后的归属地签名档，因为原程序已经不能正常使用了，所以修改了这个程序。

所有信息可自定义修改：背景图、文字颜色、字体、文字内容等(压缩包内有说明修改哪里)。

2024年6月18日：修改API为高德，删除多余失效功能

2024年5月21日：新增两种API，变为三种API轮询，直至查询到归属地，太平洋API无需配置KEY，直接部署也可用

2025年7月30日：支持redis，需要手动开启，优化配置，支持get传入'os_version''os_name'

## 环境要求

|需求|支持范围|推荐|
|:--:|:--:|:--:|
|web服务|全部|nginx1.20|
|PHP|全部|php7.2|

## 使用教程

部署到php网站的二级目录或者新建一个支持php的网站

源码解压

## 配置项目

修改index.php的内容

47行和48填入高德申请的key

50和51行填入腾讯申请的key

需使用自己的key，或删除其中内容

高德apikey获取教程：[https://wxsnote.cn/2770.html](https://wxsnote.cn/2770.html)

腾讯apikey获取教程：[https://wxsnote.cn/4819.html](https://wxsnote.cn/4819.html)

200行后自定义背景，颜色，文字，字体等
