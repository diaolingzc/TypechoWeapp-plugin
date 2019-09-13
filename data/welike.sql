CREATE TABLE `typecho_welike` (
  `id`                int(10) unsigned NOT NULL auto_increment,
  `openid`            varchar(255)     default ''  ,
  `cid`               int(10)          default 0   ,
  `create_at`         int(10)          default 0   ,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;