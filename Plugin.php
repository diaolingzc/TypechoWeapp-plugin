<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * TypechoWeapp 接口插件
 *
 * @package TypechoWeapp
 * @author  云璃
 * @version 0.1
 * @link https://www.masterzc.cn
 */
class TypechoWeapp_Plugin implements Typecho_Plugin_Interface
{

    private static $db;
    private static $prefix;
    private static $weappPath = 'usr/plugins/TypechoWeapp/data/weapp.sql';
    private static $welikePath = 'usr/plugins/TypechoWeapp/data/welike.sql';

    /**
     * 前置方法
     * 添加访问接口
     * 获取数据库实例化对象
     * 获取数据表前缀
     */
    public static function prepositionAction()
    {
        Helper::addRoute('weapis', '/weapi/[type]', 'TypechoWeapp_Action');
        Helper::addAction('weapp', 'TypechoWeapp_Action');
        Helper::removePanel(1, 'TypechoWeapp/WeUsers.php');
        Helper::addPanel(1, 'TypechoWeapp/WeUsers.php', 'WeUsers', '我的用户', 'administrator');
        self::$db = Typecho_Db::get();
        self::$prefix = self::$db->getPrefix();
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('TypechoWeapp_Plugin', 'view_count');
    }

    public static function activate()
    {
        self::prepositionAction();

        // 创建用户数据库
        self::createTable(self::$db, self::$weappPath, self::$prefix, 'weapp');

        // 创建赞数据库
        self::createTable(self::$db, self::$welikePath, self::$prefix, 'welike');

        // 添加 contents 表字段 views
        self::createTableColumn(self::$db, self::$prefix, 'contents', 'views', 'INT', 0);
        // 添加 contents 表字段 likes
        self::createTableColumn(self::$db, self::$prefix, 'contents', 'likes', 'INT', 0);
        // 添加 comments 表字段 authorImg
        self::createTableColumn(self::$db, self::$prefix, 'comments', 'authorImg', 'varchar(500)', NULL);        

    }

    public static function deactivate()
    {
        Helper::removeRoute('weapis');
        Helper::removeAction('weapp');
        Helper::removePanel(1, 'TypechoWeapp/WeUsers.php');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $topContents = new Typecho_Widget_Helper_Form_Element_Text('topContents', null, '1,2', _t('首页轮播'), _t('要在首页轮播里面显示的文章的cid值，用英文逗号隔开。'));
        $form->addInput($topContents);
        $apiSecret = new Typecho_Widget_Helper_Form_Element_Text('apiSecret', null, 'xxx', _t('API密钥'), _t('要与小程序端config.js中API_SECRET字段保持一致，否则无法从服务器读取数据'));
        $form->addInput($apiSecret);
        $appID = new Typecho_Widget_Helper_Form_Element_Text('appid', null, 'xxx', _t('微信小程序的APPID'), _t('小程序的APP ID'));
        $form->addInput($appID);
        $appSecret = new Typecho_Widget_Helper_Form_Element_Text('appsecret', null, 'xxx', _t('微信小程序的APP secret ID'), _t('小程序的APP secret ID'));
        $form->addInput($appSecret);
        $aboutCid = new Typecho_Widget_Helper_Form_Element_Text('aboutCid', null, '1', _t('关于页面CID'), _t('小程序关于页面显示内容'));
        $form->addInput($aboutCid);
        $monitorOid = new Typecho_Widget_Helper_Form_Element_Text('monitorOid', null, '1', _t('资源监控所允许的微信openid'), _t('资源监控所允许的微信openid，可在TypechoWeapp控制台查看自己Openid来添加'));
        $form->addInput($monitorOid);
        $authorName = new Typecho_Widget_Helper_Form_Element_Text('authorName', null, 'Admin', _t('博客作者昵称'), _t('博客作者昵称'));
        $form->addInput($authorName);
        $authorPic = new Typecho_Widget_Helper_Form_Element_Text('authorPic', null, '1', _t('显示头像'), _t('博客作者显示头像'));
        $form->addInput($authorPic);
        $hiddenmid = new Typecho_Widget_Helper_Form_Element_Text('hiddenmid', null, null, _t('要在小程序端显示的分类的mid(其余隐藏)，为了过微信审核你懂的^-^，可在过审核后取消隐藏（不填写则不隐藏任何分类）。'), _t('可在Typecho后台分类管理中查看分类的mid，以英文逗号隔开。不填写则不隐藏任何分类'));
        $form->addInput($hiddenmid);
        $hiddenShare = new Typecho_Widget_Helper_Form_Element_Radio('hiddenShare', array('0' => '禁用', '1' => '启用'), '1', _t('是否开启小程序端分享，转发功能，1为开启，0为关闭。为了过微信审核你懂的^-^，可在过审核后打开该功能'), _t('审核时建议关闭，防止微信判定小程序有诱导用户分享的嫌疑，审核通过后再开启。'));
        $form->addInput($hiddenShare);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {}

    public static function render()
    {}
    public static function view_count($archive)
    {
        if ($archive->is('single')) {
            $cid = $archive->cid;
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
            $db->query($db->update('table.contents')->rows(array('views' => (int) $row['views'] + 1))->where('cid = ?', $cid));
        }
    }

    /**
     * 创建数据库
     *
     * @param Typecho_Db $db 数据库实例化对象
     * @param string $path sql文件路径
     * @param string $prefix 数据表前缀
     * @param string $name 数据表名称
     * @return void
     */
    public static function createTable($db, $path, $prefix, $name)
    {
        $scripts = file_get_contents($path);
        $scripts = str_replace('typecho_', $prefix, $scripts);
        $scripts = explode(';', $scripts);
        try {
            if (!$db->fetchRow($db->query("SHOW TABLES LIKE '".$prefix . $name . "';", Typecho_Db::READ))) {
                foreach ($scripts as $script) {
                    $script = trim($script);
                    if ($script) {
                        $db->query($script, Typecho_Db::WRITE);
                    }
                }
            }
        } catch (Typecho_Db_Exception $e) {
            throw new Typecho_Plugin_Exception(_t('数据表建立失败，插件启用失败，错误信息：%s。', $e->getMessage()));
        } catch (Exception $e) {
            throw new Typecho_Plugin_Exception($e->getMessage());
        }
    }

    /**
     * 添加数据表字段
     *
     * @param Typecho_Db $db 数据库实例化对象
     * @param string $prefix 数据表前缀
     * @param string $tableName 数据表名
     * @param string $column 字段名
     * @param string $columnType 字段类型
     * @param void $columnValue 字段默认值
     * @return void
     */
    public static function createTableColumn($db, $prefix, $tableName, $column, $columnType, $columnValue)
    {
        try {
            //增加点赞和阅读量
            if (!array_key_exists($column, $db->fetchRow($db->select()->from('table.' . $tableName)))) {
                $db->query(
                    'ALTER TABLE `' . $prefix
                    . $tableName. '` ADD `'. $column .'` ' . $columnType . ' DEFAULT '. $columnValue .';'
                );
            }
        } catch (Exception $e) {
            echo ($e->getMessage());
        }
    }
}
