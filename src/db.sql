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

-- 系统日志
create table logs (
       id serial primary key,
       type int not null,
       operator varchar(32) not null,
       context text not null,
       ip_addr varchar(40) not null,
       create_time timestamp not null
);

-- CRM 客户资料
create table customer (
       id serial primary key, -- id
       phone varchar(16) not null, -- 手机
       name varchar(32) not null, -- 姓名
       sex int not null, -- 性别
       birthday date, -- 出生日期
       ethnic_group varchar(40), -- 名族
       identification varchar(32), -- 身份证
       telephone varchar(16), -- 固定电话
       fax varchar(32), -- 传真
       home_address text, -- 家庭住址
       company int not null,
       company_name varchar(40),
       company_address varchar(40), -- 公司地址
       type int not null,  -- 客户类型
       qq int, -- QQ
       weixin varchar(40), -- 微信
       email varchar(40), -- 电子邮件
       remark text, -- 备注
       agent varchar(32) not null, -- 座席
       create_time timestamp, -- 创建时间
       update_time timestamp -- 更新时间
);


-- 订单
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

