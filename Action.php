<?php
header('Access-Control-Allow-Origin: *');
class TypechoWeapp_Action extends Typecho_Widget implements Widget_Interface_Do
{

    private $db;
    private $res;
    private $userInfo;
    private $publicAction = [
        'authorizations',
        'get_contents',
        'get_top_contents',
        'get_cid_content',
        'get_comments',
        'get_ranking',
        'search_contents',
        'get_about'
    ];
    private $authAction = [
        'get_user',
        'authcurrent',
        'add_comment',
        'get_user_like',
        'update_user_like',
    ];

    private $picUrls = [
        'https://i0.hdslb.com/bfs/album/f1a7f951358990b1fa6016325de780d5bf5873d0.png',
        'https://ossweb-img.qq.com/images/lol/web201310/skin/big84001.jpg',
        'https://ossweb-img.qq.com/images/lol/web201310/skin/big39000.jpg',
        'https://ossweb-img.qq.com/images/lol/web201310/skin/big10001.jpg',
        'https://ossweb-img.qq.com/images/lol/web201310/skin/big25011.jpg',
        'https://ossweb-img.qq.com/images/lol/web201310/skin/big21016.jpg',
        'https://ossweb-img.qq.com/images/lol/web201310/skin/big99008.jpg',
    ];
    const LACK_PARAMETER = 'Not found';

    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);

        $this->setConfig();

        $this->checkAction($this->request->type);

        $this->checkApiSecret();

        $this->checkAuthAction($this->request->type);

        if (method_exists($this, $this->request->type)) {
            call_user_func(array(
                $this,
                $this->request->type,
            ));
        } else {
            $this->export(['message' => '系统异常'], 500);
        }
    }

    private function get_about() {
      try {
        $cid = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->aboutCid ?? 1;

        // 文章
        $content = $this->db->fetchRow($this->db->select('cid', 'title', 'slug', 'created', 'authorId', 'type', 'text', 'status', 'commentsNum', 'views', 'likes')->from('table.contents')->where('status = ?', 'publish')->where('created < ?', time())->where('cid = ?', $cid));

        $content['authorPic'] = $content['authorId'] == 1 ? $this->authorPic : $this->picUrls[array_rand($this->picUrls)];

        $content['authorName'] = $content['authorId'] == 1 ? $this->authorName : 'Admin';

        // 处理Markdown
        if (isset($content['text']) && (0 === strpos($content['text'], '<!--markdown-->'))) {
            $content['text'] = substr($content['text'], 15);
        }

        // 获取自定义字段
        $fields = $this->db->fetchAll($this->db->select('cid', 'name', 'str_value')->from('table.fields')->where('cid = ?', $cid));

        foreach ($fields as $item) {
            if ($item['name'] == 'description') {
                $content['description'] = $item['str_value'];
            }

            if ($item['name'] == 'views') {
                $content['views'] = $content['views'] + $item['str_value'];
            }

            if ($item['name'] == 'picUrl') {
                $content['picUrl'] = $item['str_value'];
            }

        }

        // 获取comments
        $comments = $this->get_comments($cid);

        // 更新浏览次数
        $this->db->query('update ' . $this->db->getPrefix() . 'contents set `views`= `views` + 1 WHERE  (`cid` = ' . $cid . ' )', Typecho_Db::WRITE);

        $this->export(['data' => ['content' => $content, 'comments' => $comments]]);
    } catch (Exception $e) {
        $this->export(['message' => $e->getMessage()], 500);
    }
    }

    private function search_contents() {
      try {

        if (!isset($this->request->title)) {
          $this->export(['message' => 'error title'], 500);
        } else {
          $title = $this->request->title;
        }

        $rankLimit = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->rankLimit ?? 30;
        $prefix = $this->db->getPrefix();

        $data = $this->db->fetchAll($this->db->query("select cid, title, created from " . $prefix . "contents where type = 'post' and status = 'publish' and title like '%" . $title . "%' order by created desc limit " . $rankLimit, Typecho_Db::READ));

        for ($i=0; $i < count($data); $i++) { 
          $data[$i]['authorPic'] = $this->authorPic;
        }

        $this->export(['data' => $data], 200);

    } catch (Exception $e) {
        $this->export(['message' => $e->getMessage()], 500);
    }
    }

    private function get_ranking() {
      try {

        $orders = [
          'view' => '(a.views + b.str_value)',
          'comment' => 'commentsNum',
          'like' => 'likes'
        ];

        $order = $this->request->order ?? 'views';
        $rankLimit = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->rankLimit ?? 30;
        $prefix = $this->db->getPrefix();

        if (array_key_exists($order, $orders)) {
          $orderby = $orders[$order];
        } else {
          $orderby = $orders['view'];
        }

        $data = $this->db->fetchAll($this->db->query("select a.cid, a.title, a.created, a.commentsNum, (a.views + b.str_value) as views, a.likes from " . $prefix . "contents a left join (select * from " . $prefix . "fields where name = 'views') b on a.cid = b.cid where a.type = 'post' and a.status = 'publish' order by " . $orderby . " desc, created desc limit " . $rankLimit, Typecho_Db::READ));

        $this->export(['data' => $data], 200);

    } catch (Exception $e) {
        $this->export(['message' => $e->getMessage()], 500);
    }
    }

    private function update_user_like()
    {
        try {

            if (!isset($this->request->cid)) {
                $this->export(['message' => 'error cid'], 500);
            }

            $liked = $this->request->liked ?? false;

            if ($liked === 'true') {
              $this->db->query($this->db->insert('table.welike')->rows(['openid' => $this->userInfo['openid'], 'cid' => $this->request->cid, 'create_at' => time()]));

              // 更新点赞次数
              $this->db->query('update ' . $this->db->getPrefix() . 'contents set `likes`= `likes` + 1 WHERE  (`cid` = ' . $this->request->cid . ' )', Typecho_Db::WRITE);
            } else {
              $this->db->query($this->db->delete('table.welike')->rows(['openid' => $this->userInfo['openid'], 'cid' => $this->request->cid])->where('openid = ?', $this->userInfo['openid'])->where('cid = ?', $this->request->cid));

              // 更新点赞次数
              $this->db->query('update ' . $this->db->getPrefix() . 'contents set `likes`= `likes` - 1 WHERE  (`cid` = ' . $this->request->cid . ' )', Typecho_Db::WRITE);
            }

            $this->export(['data' => ['is_like' => $liked === 'true' ? 1 : 0]], 200);

        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    private function get_user_like()
    {
        try {

            if (!isset($this->request->cid)) {
                $this->export(['message' => 'error cid'], 500);
            }

            $data = $this->db->fetchRow($this->db->select('id')->from('table.welike')->where('openid = ?', $this->userInfo['openid'])->where('cid = ?', $this->request->cid));

            $this->export(['data' => ['is_like' => count($data)]], 200);

        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    private function add_comment()
    {
        if ($this->request->isPost()) {
            try {

                $data = [
                    'cid' => $this->getParams('cid', 1),
                    'created' => time(),
                    'author' => $this->getParams('author', 'guest'),
                    'authorId' => 0,
                    'ownerId' => 1,
                    'mail' => 'wx@wx.com',
                    'url' => 'NULL',
                    'ip' => '8.8.8.8',
                    'agent' => 'wx-miniprogram',
                    'text' => $this->getParams('text', 'text'),
                    'type' => 'comment',
                    'status' => 'waiting',
                    'parent' => $this->getParams('coid', 0),
                    'authorImg' => $this->getParams('authorImg', $this->picUrls[array_rand($this->picUrls)]),
                ];

                $this->db->query($this->db->insert('table.comments')->rows($data));

                // 更新评论量，若允许用户回复即审核通过的话，请解除以下注释
                // $this->db->query('update '. $this->db->getPrefix() .'contents set `commentsNum`= `commentsNum` + 1 WHERE  (`cid` = '. $data['cid'] .' )',Typecho_Db::WRITE);

                $this->export(['message' => 'add comment ok'], 201);

            } catch (Exception $e) {
                $this->export(['message' => $e->getMessage()], 500);
            }
        } else {
            $this->export(null, 403);
        }
    }

    /**
     * 获取 评论
     *
     * @param integer $cid
     * @return array
     */
    private function get_comments($cid = 1)
    {
        try {
            $comments = $this->db->fetchAll($this->db->select('cid', 'coid', 'created', 'author', 'text', 'parent', 'authorImg')->from('table.comments')->where('cid = ?', $cid)->where('status = ?', 'approved')->order('table.comments.created', Typecho_Db::SORT_ASC));

            $parentData = [];
            $data = [];
            foreach ($comments as $item) {
                if ($item['parent'] == 0) {
                    $parentData[$item['coid']] = $item;
                }

                $data[$item['coid']] = $item;
            }

            foreach ($comments as $item) {
                if ($item['parent'] != 0) {
                    $parent = $item['parent'];
                    if (array_key_exists($parent, $data)) {
                        $parentItem = $data[$parent];
                        $temp = $data[$parent];
                    } else {
                        break;
                    }

                    while ($temp['parent'] != 0) {
                        $parent = $temp['parent'];
                        if (array_key_exists($parent, $data)) {
                            $temp = $data[$parent];
                        } else {
                            break;
                        }
                    }

                    $item['parentItem'] = $parentItem ?? [];
                    if (array_key_exists($temp['coid'], $parentData)) {
                        $parentData[$temp['coid']]['replays'][] = $item;
                    } else {
                        break;
                    }

                }
            }

            $parentData = array_reverse($parentData);

            return $parentData;
        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    private function get_cid_content()
    {
        try {
            $cid = $this->request->cid ?? 1;

            // 文章
            $content = $this->db->fetchRow($this->db->select('cid', 'title', 'slug', 'created', 'authorId', 'type', 'text', 'status', 'commentsNum', 'views', 'likes')->from('table.contents')->where('status = ?', 'publish')->where('created < ?', time())->where('cid = ?', $cid));

            $content['authorPic'] = $content['authorId'] == 1 ? $this->authorPic : $this->picUrls[array_rand($this->picUrls)];

            $content['authorName'] = $content['authorId'] == 1 ? $this->authorName : 'Admin';

            // 处理Markdown
            if (isset($content['text']) && (0 === strpos($content['text'], '<!--markdown-->'))) {
                $content['text'] = substr($content['text'], 15);
            }

            // 分类
            $category = [];
            $tags = [];
            $prefix = $this->db->getPrefix();
            $metas = $this->db->fetchAll($this->db->query('select mid, name, type, description from ' . $prefix . 'metas where mid in (select mid from ' . $prefix . 'relationships where cid = ' . $cid . ')', Typecho_Db::READ));

            foreach ($metas as $item) {
                if ($item['type'] === 'category') {
                    $category[] = $item;
                }

                if ($item['type'] === 'tag') {
                    $tags[] = $item;
                }

            }

            // 获取自定义字段
            $fields = $this->db->fetchAll($this->db->select('cid', 'name', 'str_value')->from('table.fields')->where('cid = ?', $cid));

            foreach ($fields as $item) {
                if ($item['name'] == 'description') {
                    $content['description'] = $item['str_value'];
                }

                if ($item['name'] == 'views') {
                    $content['views'] = $content['views'] + $item['str_value'];
                }

                if ($item['name'] == 'picUrl') {
                    $content['picUrl'] = $item['str_value'];
                }

            }

            // 获取comments
            $comments = $this->get_comments($cid);

            // 更新浏览次数
            $this->db->query('update ' . $this->db->getPrefix() . 'contents set `views`= `views` + 1 WHERE  (`cid` = ' . $cid . ' )', Typecho_Db::WRITE);

            $this->export(['data' => ['content' => $content, 'category' => $category, 'tags' => $tags, 'comments' => $comments]]);
        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    private function get_top_contents()
    {
        try {
            $topIds = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->topContents;

            $data = $this->db->fetchAll($this->db->select('cid', 'title')->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish')->where('created < ?', time())->where('cid in (' . $topIds . ')'));

            $fieldsPicUrl = $this->db->fetchAll($this->db->select('cid', 'name', 'str_value')->from('table.fields')->where('cid in (' . $topIds . ')')->where('name = ?', 'picUrl'));

            $picUrl = array_column($fieldsPicUrl, 'str_value', 'cid');

            for ($i = 0; $i < count($data); $i++) {

                $data[$i]['picUrl'] = (isset($picUrl[$data[$i]['cid']]) && (0 === strpos($picUrl[$data[$i]['cid']], 'http'))) ? $picUrl[$data[$i]['cid']] : $this->picUrls[array_rand($this->picUrls)];

            }
            $this->export(['data' => $data]);
        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    private function get_contents()
    {

        $pagination = [
            'total' => null,
            'count' => null,
            'per_page' => (int) ($this->request->limit ?? '10'),
            'current_page' => (int) ($this->request->page ?? 1),
            'total_pages' => null,
            'links' => null,
        ];

        $offset = $pagination['per_page'] * ($pagination['current_page'] - 1);

        try {
            // 计算总条数
            $contentNums = $this->db->fetchRow($this->db->select('count(*) as nums')->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish')->where('created < ?', time()));

            $pagination['total'] = (int) $contentNums['nums'];
            $pagination['total_pages'] = ceil($contentNums['nums'] / $pagination['per_page']);

            // 获取文章数据
            $data = $this->db->fetchAll($this->db->select('cid', 'title', 'slug', 'created', 'authorId', 'type', 'status', 'commentsNum', 'views', 'likes')->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish')->where('created < ?', time())->order('table.contents.created', Typecho_Db::SORT_DESC)->offset($offset)->limit($pagination['per_page']));

            // 获取自定义字段 PicUrl
            $Cids = array_column($data, 'cid');
            $fieldsPicUrl = $this->db->fetchAll($this->db->select('cid', 'name', 'str_value')->from('table.fields')->where("cid in (" . implode(',', $Cids) . ")")->where('name = ?', 'picUrl'));

            $picUrl = array_column($fieldsPicUrl, 'str_value', 'cid');

            // 模板点击量修复 （可注释）
            $fieldsViews = $this->db->fetchAll($this->db->select('cid', 'name', 'str_value')->from('table.fields')->where("cid in (" . implode(',', $Cids) . ")")->where('name = ?', 'views'));

            $themeViews = array_column($fieldsViews, 'str_value', 'cid');

            // 更新点击量
            // $this->db->query($this->db->update('table.contents')->rows(['views' => 'views + 1'])->where('cid = ?', $cid));

            $pagination['count'] = count($data);

            for ($i = 0; $i < $pagination['count']; $i++) {
                // 处理Markdown
                // if (isset($data[$i]['text']) && (0 === strpos($data[$i]['text'], '<!--markdown-->'))) {
                //   $data[$i]['text'] = substr($data[$i]['text'], 15);
                // }

                if (isset($themeViews[$data[$i]['cid']])) {
                    $data[$i]['views'] = $data[$i]['views'] + $themeViews[$data[$i]['cid']];
                }

                $data[$i]['picUrl'] = isset($picUrl[$data[$i]['cid']]) ? $picUrl[$data[$i]['cid']] : $this->picUrls[array_rand($this->picUrls)];

                $data[$i]['authorPic'] = $data[$i]['authorId'] == 1 ? $this->authorPic : $this->picUrls[array_rand($this->picUrls)];

                $data[$i]['authorName'] = $data[$i]['authorId'] == 1 ? $this->authorName : 'Admin';
            }

            $this->export(['data' => $data, 'meta' => ['pagination' => $pagination]]);

        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * 登录用户信息
     *
     * @return void
     */
    private function get_user()
    {
        $this->export(['data' => $this->userInfo]);
    }

    /**
     * 登录
     *
     * @return void
     */
    public function authorizations()
    {
        if ($this->request->isPost()) {
            $code = $this->getParams('code', 'null');

            if ($code === 'null') {
                $this->export(['message' => 'error code'], 422);
            }

            $nickName = $this->getParams('nickName', 'null');
            $avatarUrl = $this->getParams('avatarUrl', 'null');
            $city = $this->getParams('city', 'null');
            $country = $this->getParams('country', 'null');
            $gender = $this->getParams('gender', 'null');
            $province = $this->getParams('province', 'null');

            $url = sprintf('https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code', $this->appId, $this->appSecret, $code);

            $info = get_object_vars(json_decode(file_get_contents($url)));

            $openId = $info['openid'] ?? '';

            if ($openId == null && $openId == '') {
                $this->export(['message' => 'error openId'], 422);
            }

            $user = $this->db->fetchRow($this->db->select('openid', 'lastlogin_at')->from('table.weapp')->where('openid = ?', $openId));

            $data = [
                'openid' => $openId,
                'lastlogin_at' => time(),
                'nickname' => $nickName,
                'avatarUrl' => $avatarUrl,
                'city' => $city,
                'country' => $country,
                'gender' => $gender,
                'province' => $province,
                'token' => $this->getToken($nickName, $openId),
                'token_expired_at' => time() + 604800,
                'token_refresh_at' => time() + 2592000,
            ];

            if (count($user) > 0) {
                $this->db->query($this->db->update('table.weapp')->rows($data)->where('openid = ?', $openId));
            } else {
                $data['create_at'] = $data['lastlogin_at'];
                $this->db->query($this->db->insert('table.weapp')->rows($data));
            }
            $this->export(['access_token' => $data['token'], 'expires_in' => 604800], 201);
        } else {
            $this->export(null, 403);
        }
    }

    private function authcurrent()
    {
        try {
            $userInfo = $this->userInfo;
            $authData = $this->db->fetchRow($this->db->select('id', 'nickname', 'openid', 'token', 'token_expired_at', 'token_refresh_at')->from('table.weapp')->where('id = ?', $userInfo['id']));

            if ($authData['token_refresh_at'] < time()) {
                $this->export(['message' => 'error token'], 405);
            }

            $data = [
                'token' => $this->getToken($authData['nickname'], $authData['openid']),
                'token_expired_at' => time() + 604800,
                'token_refresh_at' => time() + 2592000,
            ];

            $this->db->query($this->db->update('table.weapp')->rows($data)->where('id = ?', $userInfo['id']));

            $this->export(['access_token' => $data['token'], 'expires_in' => 604800], 201);
        } catch (Exception $e) {
            $this->export(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取 Token
     *
     * @param string $name  用户昵称
     * @param string $openId 用户openId
     * @return string
     */
    private function getToken($name, $openId)
    {
        $data = [
            'iss' => 'auth',
            'iat' => time(),
            'exp' => 604800,
            'sub' => 'TypechoWeapp',
            'openid' => $openId,
            'user_name' => $name,
        ];
        return urlencode(base64_encode(json_encode(['typ' => 'JWT'])) . '.' . base64_encode(json_encode($data)));
    }
    private function getParams($key, $value = '')
    {
        $postData = $postData = json_decode(file_get_contents("php://input"), true);
        return isset($postData[$key]) ? $postData[$key] : $value;
    }

    // 登录状态鉴权
    private function checkAuthAction($action)
    {
        if (in_array($action, $this->authAction)) {
            if ($this->request->getServer('HTTP_TOKEN')) {
                $auth = $this->db->fetchRow($this->db->select('id', 'openid', 'nickname', 'avatarUrl', 'city', 'country', 'gender', 'province')->from('table.weapp')->where('token = ?', $this->request->getServer('HTTP_TOKEN')));
                if (count($auth) == 0) {
                    $this->export(['message' => '未登录', 'auth' => $auth], 402);
                } else {
                    // 更新访问时间
                    $this->db->query($this->db->update('table.weapp')->rows(['lastlogin_at' => time()])->where('id = ?', $auth['id']));
                    $this->userInfo = $auth;
                }
            } else {
                $this->export(['message' => '未登录'], 401);
            }
        }
    }

    /**
     * 鉴权
     *
     * @return void
     */
    private function checkApiSecret()
    {
        if (strcmp($this->request->getServer('HTTP_APISECRET'), $this->apiSecret) != 0) {
            $this->export(['message' => '鉴权失败'], 403);
        }
    }

    /**
     * 设置 Config 参数
     *
     * @return void
     */
    private function setConfig()
    {
        $this->db = Typecho_Db::get();
        $this->req = new Typecho_Request();
        $this->res = new Typecho_Response();
        $this->apiSecret = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->apiSecret;
        $this->appId = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->appid;
        $this->appSecret = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->appsecret;
        $this->topContents = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->topContents;
        $this->monitorOid = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->monitorOid;
        $this->authorPic = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->authorPic;
        $this->authorName = Typecho_Widget::widget('Widget_Options')->plugin('TypechoWeapp')->authorName;
    }

    /**
     * Json Response
     *
     * @param void $data response.data
     * @param integer $status response.status
     * @return void
     */
    public function export($data = [], $status = 200)
    {
        $this->res->setStatus($status);
        $this->res->throwJson($data);
        exit;
    }

    /**
     * 检查是否为允许访问的 Action
     *
     * @param string $action 请求方法名
     * @return void
     */
    private function checkAction($action)
    {
        if (!in_array($action, $this->publicAction) && !in_array($action, $this->authAction)) {
            $this->export(['message' => '访问受限'], 403);
        }
    }

    public function action()
    {
        $this->on($this->request);
    }
}
