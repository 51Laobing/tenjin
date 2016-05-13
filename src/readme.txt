systemctl disable auditd.service
systemctl disable firewalld.service
systemctl disable microcode.service
systemctl disable NetworkManager.service
systemctl disable postfix.service
systemctl disable tuned.service

// edit file /etc/sysctl.conf
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv4.ip_forward = 1
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_tw_recycle = 1
net.ipv4.tcp_fin_timeout = 30
fs.file-max = 2097152

// edit file /etc/security/limits.conf
* soft nofile 102400
* hard nofile 102400
* soft nproc unlimited
* hard nproc unlimited

yum -y install epel-release
rpm -Uvh http://files.freeswitch.org/freeswitch-release-1-6.noarch.rpm
yum makecache
yum install -y gcc gcc-c++ autoconf automake libtool wget python ncurses-devel zlib-devel libjpeg-devel openssl-devel e2fsprogs-devel
yum install -y sqlite-devel libcurl-devel pcre-devel speex-devel ldns-devel libedit-devel libxml2-devel
yum install -y libyuv-devel libvpx-devel libvpx2* libdb4* libidn-devel unbound-devel libuuid-devel lua-devel libsndfile-devel
yum install -y gsm gsm-devel ilbc2 ilbc2-devel opus-devel
yum install -y nginx php php-fpm php-devel php-pgsql php-mbstring
yum install -y redis hiredis hiredis-devel libconfig libconfig-devel

systemctl enable nginx.service
systemctl start nginx.service
systemctl enable php-fpm.service
systemctl enable redis.service
systemctl start redis.service

// install phpredis
tar -zxvf phpredis-2.2.7.tar.gz
phpize
./configure
make
make install

// edit php.ini
extension = /usr/lib64/php/modules/redis.so

// install postgresql
yum install -y postgresql postgresql-server postgresql-devel
postgresql-setup initdb
systemctl enable postgresql.service
systemctl start postgresql.service

// freeswitch
cd freeswitch-1.6.7
emacs modules.conf
./configure --enable-optimization --disable-debug --disable-libyuv --disable-libvpx --with-cachedir=/dev/shm --enable-core-pgsql-support
make
make install
make cd-sounds-install
make cd-moh-install

// install esl php modules
cd libs/esl
make phpmod
cp php/ESL.so /usr/lib64/php/modules

// edit php.ini
extension = /usr/lib64/php/modules/ESL.so

cp freeswitch.service /etc/systemd/system
systemctl enable freeswitch.service

ln -s /usr/local/freeswitch/bin/freeswitch /usr/bin/freeswitch
ln -s /usr/local/freeswitch/bin/fs_cli /usr/bin/fs_cli

mkdir /var/service
mkdir /var/freeswitch

cd /usr/local/freeswitch/conf
mkdir queues agents tiers

systemctl start php-fpm.service

chown -R apache:apache /var/www
chown -R apache:apache /var/service
chown -R apache:apache /var/lib/nginx
chown -R apache:apache /var/freeswitch
chown -R apache:apache /usr/local/freeswitch

// postgresql configure
grant all on DATABASE postgres to postgres;
create database freeswitch owner postgres;
grant ALL on DATABASE freeswitch to postgres;

-- 公司
create table company (
       id serial primary key,
       name varchar(36) not null,
       concurrent int not null,
       billing varchar(32) not null,
       level int not null,
       sound_check int not null,
       create_time timestamp not null
);

-- 用户
create table users (
       uid varchar(32) not null primary key,
       name varchar(32) not null,
       password varchar(40) not null,
       type int not null,
       company int not null,
       create_time timestamp not null,
       last_login timestamp not null,
       last_ipaddr varchar(32) not null,
       constraint fk_company foreign key(company) references company(id)
);

-- 座席
create table agent (
       uid varchar(32) primary key,
       name varchar(36) not null,
       password varchar(40) not null,
       type int not null,
       callerid varchar(16) not null,
       company int not null,
       icon varchar(40) not null,
       status int not null,
       last_login timestamp not null,
       last_ipaddr varchar(32) not null,
       constraint fk_company foreign key(company) references company(id)
);

-- 中继网关
create table gateway (
       id serial primary key,
       username varchar(40) not null,
       password varchar(40) not null,
       ip_addr varchar(40) not null,
       company int not null,
       registered int not null
);

-- 语音
create table sounds (
       id serial primary key,
       name varchar(40) not null,
       file varchar(40) not null,
       duration int not null,
       company int not null,
       remark text not null,
       status int not null,
       create_time timestamp not null,
       operator varchar(32) not null,
       ip_addr varchar(32) not null
);

-- 商品
create table product (
       id serial primary key,
       name varchar(40) not null,
       price numeric(7, 2) not null,
       inventory int not null,
       create_time timestamp not null,
       remark text not null,
       company int not null,
       constraint fk_company foreign key(company) references company(id)
);


systemctl start freeswitch.service


// cdr postgresql table
create database db105 owner postgres;

grant ALL ON DATABASE db105 to postgres;

create table cdr (
    id                        serial primary key,
    local_ip_v4               inet not null,
    caller_id_name            varchar,
    caller_id_number          varchar,
    destination_number        varchar not null,
    context                   varchar not null,
    start_stamp               timestamp with time zone not null,
    answer_stamp              timestamp with time zone,
    end_stamp                 timestamp with time zone not null,
    duration                  int not null,
    billsec                   int not null,
    hangup_cause              varchar not null,
    uuid                      uuid not null,
    bleg_uuid                 uuid,
    accountcode               varchar,
    read_codec                varchar,
    write_codec               varchar,
    sip_hangup_disposition    varchar,
    ani                       varchar
);

-- 订单表
create table orders (
       id serial primary key,
       name varchar(32) not null,
       phone varchar(16) not null,
       telephone varchar(16) not null,
       product int not null,
       number int not null,
       address text not null,
       comment text not null,
       company int not null,
       creator varchar(16) not null,
       quality varchar(40) not null,
       reason text not null,
       status int not null,
       express_id varchar(40) not null,
       logistics_status text not null,
       create_time timestamp not null,
       quality_time timestamp not null,
       delivery_time timestamp not null
);

// freeswitch 1.2 安装编译
systemctl disable auditd.service
systemctl disable firewalld.service
systemctl disable microcode.service
systemctl disable NetworkManager.service
systemctl disable postfix.service
systemctl disable tuned.service

// edit file /etc/sysctl.conf
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv4.ip_forward = 1
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_tw_recycle = 1
net.ipv4.tcp_fin_timeout = 30
fs.file-max = 2097152

// edit file /etc/security/limits.conf
* soft nofile 102400
* hard nofile 102400
* soft nproc unlimited
* hard nproc unlimited

yum -y install epel-release
yum makecache
yum install -y gcc gcc-c++ autoconf automake libtool wget python ncurses-devel zlib-devel libjpeg-devel openssl-devel e2fsprogs-devel
yum install -y sqlite-devel libcurl-devel pcre-devel speex-devel ldns-devel libedit-devel libxml2-devel
yum install -y libyuv-devel libvpx-devel libdb4* libidn-devel unbound-devel libuuid-devel lua-devel libsndfile-devel
yum install -y gsm gsm-devel ilbc2 ilbc2-devel
yum install -y nginx php php-fpm php-devel php-pgsql php-mbstring
yum install -y redis hiredis hiredis-devel libconfig libconfig-devel

systemctl enable nginx.service
systemctl enable php-fpm.service
systemctl enable redis.service
systemctl start redis.service

// install phpredis
tar -zxvf phpredis-2.2.7.tar.gz
phpize
./configure
make
make install

// edit php.ini
extension = /usr/lib64/php/modules/redis.so

// install postgresql
yum install -y postgresql postgresql-server postgresql-devel
postgresql-setup initdb
systemctl enable postgresql.service
systemctl start postgresql.service

// edit modules.conf file
applications/mod_callcenter
applications/mod_commands
applications/mod_conference
applications/mod_curl
applications/mod_db
applications/mod_dptools
applications/mod_esf
applications/mod_expr
applications/mod_fifo
applications/mod_hash
applications/mod_sms
applications/mod_spandsp
applications/mod_valet_parking
applications/mod_voicemail
dialplans/mod_dialplan_xml
endpoints/mod_loopback
endpoints/mod_sofia
event_handlers/mod_cdr_pg_csv
event_handlers/mod_event_socket
formats/mod_local_stream
formats/mod_native_file
formats/mod_sndfile
formats/mod_tone_stream
languages/mod_lua
loggers/mod_console
loggers/mod_logfile
loggers/mod_syslog
say/mod_say_en

./configure --enable-optimization --enable-64 --enable-core-pgsql-support

// freeswitch
cd freeswitch-1.6.5
emacs modules.conf
./configure --enable-core-pgsql-support
make
make install
make cd-sounds-install
make cd-moh-install

// install esl php modules
cd libs/esl
make phpmod
cp php/ESL.so /usr/lib64/php/modules

// edit php.ini
extension = /usr/lib64/php/modules/ESL.so

cp freeswitch.service /etc/systemd/system
systemctl enable freeswitch.service

ln -s /usr/local/freeswitch/bin/freeswitch /usr/bin/freeswitch
ln -s /usr/local/freeswitch/bin/fs_cli /usr/bin/fs_cli

mkdir /var/service
mkdir /var/freeswitch

cd /usr/local/freeswitch/conf
mkdir queues agents tiers

chown -R apache:apache /var/www
chown -R apache:apache /var/service
chown -R apache:apache /var/lib/nginx
chown -R apache:apache /var/freeswitch
chown -R apache:apache /usr/local/freeswitch

systemctl start nginx.service
systemctl start php-fpm.service
