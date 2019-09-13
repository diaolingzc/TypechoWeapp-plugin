CREATE TABLE `typecho_weapp` (
  `id`                int(10) unsigned NOT NULL auto_increment,
  `openid`            varchar(255)     default ''  ,
  `create_at`        int(10)          default 0   ,
  `lastlogin_at`         int(10)          default 0   ,
  `nickname`          varchar(255)     default ''  ,
  `avatarUrl`         varchar(255)      default ''  ,
  `city`              varchar(255)      default ''  ,
  `country`           varchar(255)      default ''  ,
  `gender`            varchar(255)      default ''  ,
  `province`          varchar(255)     default ''  ,
  `token`          varchar(255)     default ''  ,
  `token_expired_at`         int(10)          default 0   ,
  `token_refresh_at`         int(10)          default 0   ,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;