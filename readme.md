tenjin 是基于 FreeSWITCH 的开源呼叫中心系统，管理系统主要使用PHP开发，核心控制模块使用C语言编写，单台服务器可多租户使用。

### 主要功能和特性
* 座席队列监控
* 3种外呼模式
* 简单的订单系统
* 分机注册及状态监控
* 商品管理和语音管理
* 通话录音查询
* 通话记录和通话数据报表
* 集成VOS账户余额查询
* 可定制简单的呼入队列

### 3种外呼模式
1. 群呼转座席自动模式
2. 群呼转座席固定模式
3. 半自动一对一外呼

### 相关依赖包安装
##### mod_bcg729 语音编码
```shell
$ tar -zxvf mod_bcg729.tar.gz
$ cd mod_bcg729
$ make
$ make install
```

##### phpredis PHP的redis数据库扩展
```shell
$ tar -zxvf pgbouncer-1.7.2.tar.gz
$ cd pgbouncer-1.7.2
$ phpize
$ ./configure
$ make
$ make install
```

##### pgbouncer 数据库连接池
1. 安装libevent依赖包
```shell
$ yum install -y libevent libevent-devel
```

2. 安装 pbgbouncer
```shell
$ tar -zxvf pgbouncer-1.7.2.tar.gz
$ cd pgbouncer-1.7.2
$ ./configure
$ make
$ make install
$ cp etc/pgbouncer.ini /etc
$ mkdir -p /etc/pgbouncer
$ mkdir -p /var/log/pgbouncer
$ mkdir -p /var/run/pgbouncer
$ chown -R postgres:postgres /var/log/pgbouncer
$ chown -R postgres:postgres /var/run/pgbouncer
```
### FreeSWITCH 中文语音包 (只包含部分中文语音)
github 下载地址: [freeswitch-sound-cn](https://github.com/log2k/freeswitch-sound-cn/archive/master.zip) 或者 git clone
```
git clone git@github.com:log2k/freeswitch-sound-cn.git sounds
cp -R sounds /usr/local/freeswitch
```
