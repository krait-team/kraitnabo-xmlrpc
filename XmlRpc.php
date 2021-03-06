<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id$
 */

/**
 * XmlRpc接口
 *
 * @author Krait (https://github.com/kraity)
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Widget_XmlRpc extends Widget_Abstract_Contents implements Widget_Interface_Do
{

    /**
     * uid
     * @var int
     */
    private $uid;

    /**
     * 当前错误
     *
     * @access private
     * @var IXR_Error
     */
    private $error;

    /**
     * 已经使用过的组件列表
     *
     * @access private
     * @var array
     */
    private $_usedWidgetNameList = [];

    /**
     * 代理工厂方法,将类静态化放置到列表中
     *
     * @access public
     * @param string $alias 组件别名
     * @param mixed $params 传递的参数
     * @param mixed $request 前端参数
     * @param boolean $enableResponse 是否允许http回执
     * @return object
     * @throws Typecho_Exception
     */
    private function singletonWidget($alias, $params = NULL, $request = NULL, $enableResponse = false)
    {
        $this->_usedWidgetNameList[] = $alias;
        return Typecho_Widget::widget($alias, $params, $request, $enableResponse);
    }

    /**
     * 如果这里没有重载, 每次都会被默认执行
     *
     * @access public
     * @param boolean $run 是否执行
     * @return void
     */
    public function execute($run = false)
    {
        if ($run) {
            parent::execute();
        }
        // 临时保护模块
        $this->security->enable(false);
    }

    /**
     * XmlRpc清单
     *
     * @param $arg
     * @return array
     */
    public function NbGetManifest($arg)
    {
        return Widget_XmlRpc::NbGetManifestStatic();
    }

    /**
     * 静态清单
     *
     * @return array
     */
    public static function NbGetManifestStatic()
    {
        return [
            "engineName" => "typecho",
            "versionCode" => 17,
            "versionName" => "3.0"
        ];
    }


    /**
     * 检查权限
     *
     * @access public
     * @param string $union
     * @param string $name
     * @param string $password
     * @param string $level
     * @return boolean
     * @throws Typecho_Widget_Exception
     */
    public function access($union, $name, $password, $level = 'contributor')
    {
        /** 唯一标识 */
        Typecho_Cookie::set('__typecho_xmlrpc_union', $union);
        Typecho_Cookie::set('__typecho_xmlrpc_name', $name);
        /** 登陆状态 */
        if (!$this->user->hasLogin()) {
            if ($this->user->login($name, $password, true)) {
                $this->uid = $this->user->uid;
                $this->user->execute();
            } else {
                $this->error = new IXR_Error(102, _t('无法登陆, 密码错误'));
                return false;
            }
        }
        /** 验证权限 */
        if ($this->user->pass($level, true)) {
            return true;
        } else {
            $this->error = new IXR_Error(101, _t('权限不足'));
            return false;
        }
    }

    /**
     * 成功
     *
     * @param $data
     * @return array
     */
    public function prosper($data)
    {
        return array(true, $data);
    }

    /**
     * 失败
     *
     * @param $message
     * @param int $code
     * @return array
     */
    public function pervert($message, $code = -1)
    {
        return array(false, [$code, $message]);
    }

    /**
     * 当前通知
     *
     * @param bool $strict
     * @return false|mixed|string
     */
    public function currentNotice($strict = false)
    {
        $data = Typecho_Cookie::get('__typecho_notice', false);
        if ($data === false) {
            return $strict ? null : "";
        }
        $notice = json_decode($data, true);
        return (string)$notice[0];
    }

    /**
     * 用户
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     */
    public function NbGetUser($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        return $this->prosper([
            'site' => (string)$this->user->url,
            'uid' => (int)$this->user->uid,
            'name' => (string)$this->user->name,
            'mail' => (string)$this->user->mail,
            'nickname' => (string)$this->user->screenName,
            'logged' => (int)$this->user->logged,
            'created' => (int)$this->user->created,
            'activated' => (int)$this->user->activated,
            'group' => (string)$this->user->group,
            'token' => (string)$this->user->authCode
        ]);
    }

    /**
     * 分析内容
     *
     * @param string $text
     * @return string
     */
    public function commonParseMarkdown($text)
    {
        return strpos($text, '<!--markdown-->') === 0 ? substr($text, 15) : $text;
    }

    /**
     * 统一解析笔记
     *
     * @param array $note
     * @param $isSingle
     * @return array
     * @throws Typecho_Exception
     */
    public function commonNoteStruct($note, $isSingle = false)
    {
        $markdown = $isSingle ? $this->commonParseMarkdown($note['text']) : NULL;
        $filter = $this->singletonWidget('Widget_Abstract_Contents')->filter($note);

        return array(
            'nid' => (int)$note["cid"],
            'title' => (string)$note['title'],
            'content' => (string)$markdown,
            'authorId' => (int)$note['authorId'],

            'slug' => (string)$note['slug'],
            'order' => (int)$note['order'],
            'type' => (string)$note['type'],
            'status' => (string)$note['status'],
            'password' => (string)$note['password'],
            'parentId' => (int)$note['parent'],
            'template' => (string)$note['template'],

            'allowComment' => (int)$note['allowComment'],
            'allowPing' => (int)$note['allowPing'],
            'allowFeed' => (int)$note['allowFeed'],

            'created' => (int)$note['created'],
            'modified' => (int)$note['modified'],

            'permalink' => (string)$filter['permalink'],
            'fields' => (string)$this->commonFields($note["cid"]),
            'tags' => (string)$this->commonMetaNames($note['cid'], false),
            'categories' => (string)$this->commonMetaNames($note['cid'], true),
        );
    }

    /**
     * 获取分类和标签的字符串
     *
     * @param int $cid
     * @param boolean $isCategory
     * @return string
     */
    public function commonMetaNames($cid, $isCategory)
    {
        $relationships = $this->db->fetchAll($this->db->select()
            ->from('table.relationships')
            ->where('cid = ?', $cid));
        $meta = [];
        $type = $isCategory ? "category" : "tag";
        foreach ($relationships as $id) {
            $metas = $this->db->fetchAll($this->db->select()
                ->from('table.metas')
                ->where('mid = ?', $id['mid']));
            foreach ($metas as $row) {
                if ($row['type'] == $type) {
                    $meta[] = $row['name'];
                }
            }
        }
        return implode(",", $meta);
    }

    /**
     * 统计文字字数
     *
     * @param string $from
     * @param string $type
     * @return int
     */
    public function getCharacters($from, $type)
    {
        $chars = 0;
        $owner = "table.comments" == $from ? "ownerId" : "authorId";
        $select = $this->db->select('text')
            ->from($from)
            ->where($owner . " = ?", $this->uid)
            ->where('type = ?', $type);
        $rows = $this->db->fetchAll($select);
        foreach ($rows as $row) {
            $chars += mb_strlen($row['text'], 'UTF-8');
        }
        return $chars;
    }

    /**
     * 字段
     *
     * @param string $cid
     * @return string
     */
    public function commonFields($cid)
    {
        $fields = [];
        $rows = $this->db->fetchAll($this->db->select()
            ->from('table.fields')
            ->where('cid = ?', $cid));
        foreach ($rows as $row) {
            $fields[] = array(
                "name" => $row['name'],
                "type" => $row['type'],
                "value" => $row[$row['type'] . '_value']
            );
        }
        return json_encode($fields, JSON_UNESCAPED_UNICODE);
    }


    /**
     * 评论
     *
     * @param array $comment
     * @return array
     */
    public function commonCommentStruct($comment)
    {
        return array(
            'oid' => (int)$comment['coid'],
            'nid' => (int)$comment['cid'],
            'author' => (string)$comment['author'],
            'mail' => (string)$comment['mail'],
            'site' => (string)$comment['url'],
            'message' => (string)$comment['text'],

            'authorId' => (int)$comment['authorId'],
            'ownerId' => (int)$comment['ownerId'],

            'agent' => (string)$comment['agent'],
            'address' => (string)$comment['ip'],

            'type' => (string)$comment['type'],
            'status' => (string)$comment['status'],
            'parentId' => (string)$comment['parent'],
            'parentTitle' => (string)$comment['title'],
            'permalink' => "",
            'created' => (int)$comment['created'],
        );
    }

    /**
     * META
     *
     * @param $meta
     * @return array
     */
    public function commonMetaStruct($meta)
    {
        return array(
            'mid' => (int)$meta->mid,
            'name' => (string)$meta->name,
            'slug' => (string)$meta->slug,
            'type' => (string)$meta->type,
            'desc' => (string)$meta->description,
            'count' => (int)$meta->count,
            'order' => (int)$meta->order,
            'parentId' => (int)$meta->parent,
            'permalink' => (string)$meta->permalink,
        );
    }

    /**
     * 统一附件
     * @param $media
     * @return array
     */
    public function commonMediaStruct($media)
    {
        return array(
            'mid' => (int)$media->cid,
            'title' => (string)$media->title,
            'slug' => (string)$media->slug,
            'size' => (int)$media->attachment->size,
            'link' => (string)$media->attachment->url,
            'path' => (string)$media->attachment->path,
            'mime' => (string)$media->attachment->mime,
            'desc' => (string)$media->attachment->description,
            'created' => (int)$media->created,

            'parentId' => (int)$media->parentPost->cid,
            'parentType' => (string)$media->parentPost->type,
            'parentTitle' => (string)$media->parentPost->title,
        );
    }

    /**
     * 常用统计
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     * @access public
     */
    public function NbGetStat($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }
        $stat = array(
            "post" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('authorId = ?', $this->uid))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('authorId = ?', $this->uid))->num,
                "waiting" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ? OR type = ?', 'post', 'post_draft')
                    ->where('status = ?', 'waiting')
                    ->where('authorId = ?', $this->uid))->num,
                "draft" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post_draft')
                    ->where('authorId = ?', $this->uid))->num,
                "hidden" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'hidden')
                    ->where('authorId = ?', $this->uid))->num,
                "private" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'private')
                    ->where('authorId = ?', $this->uid))->num,
                "textSize" => $this->getCharacters("table.contents", "post")
            ),
            "page" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('authorId = ?', $this->uid))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'publish')
                    ->where('authorId = ?', $this->uid))->num,
                "hidden" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'hidden')
                    ->where('authorId = ?', $this->uid))->num,
                "draft" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page_draft')
                    ->where('authorId = ?', $this->uid))->num,
                "textSize" => $this->getCharacters("table.contents", "page")
            ),
            "comment" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid))->num,
                "me" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('authorId = ?', $this->uid))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('status = ?', 'approved'))->num,
                "waiting" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('status = ?', 'waiting'))->num,
                "spam" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('status = ?', 'spam'))->num,
                "textSize" => $this->getCharacters("table.comments", "comment")
            ),
            "category" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'category'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'category')
                    ->where('count != ?', '0'))->num
            ),
            "tag" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'tag'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'tag')
                    ->where('count != ?', '0'))->num
            ),
            "media" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('authorId = ?', $this->uid)
                    ->where('type = ?', 'attachment'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('authorId = ?', $this->uid)
                    ->where('type = ?', 'attachment')
                    ->where('parent != ?', '0'))->num
            )
        );

        return $this->prosper(json_encode(
            $stat, JSON_UNESCAPED_UNICODE
        ));
    }

    /**
     * 笔记
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $nid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPost($union, $userName, $password, $nid)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $select = $this->db->select()
            ->from('table.contents')
            ->where('authorId = ?', $this->uid)
            ->where('cid = ?', $nid);

        $row = $this->db->fetchRow($select);
        if (count($row) > 0) {
            return $this->prosper($this->commonNoteStruct($row, true));
        }
        return $this->pervert('不存在此文章', 403);
    }

    /**
     * 独立页面
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $nid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPage($union, $userName, $password, $nid)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        return $this->NbGetPost($union, $userName, $password, $nid);
    }

    /**
     * 笔记列表
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPosts($union, $userName, $password, $struct)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $status = isset($struct['status']) ? $struct['status'] : "all";
        $type = $struct['type'] == 'page' ? 'page' : 'post';

        $select = $this->db->select()->from('table.contents');
        if (is_array($struct['metas'])) {
            $select->join('table.relationships', 'table.contents.cid = table.relationships.cid');
            foreach ($struct['metas'] as $meta) {
                $select->orWhere('mid = ?', $meta);
            }
        }
        $select->where('authorId = ?', $this->uid);

        switch ($status) {
            case "draft":
                $select->where('type LIKE ?', '%_draft');
                break;
            case "all":
                $select->where('type = ?', $type);
                break;
            default:
                $select->where('type = ?', $type);
                $select->where('status = ?', $status);
        }

        if (isset($struct['keywords'])) {
            $searchQuery = '%' . str_replace(' ', '%', $struct['keywords']) . '%';
            $select->where('table.contents.title LIKE ? OR table.contents.text LIKE ?', $searchQuery, $searchQuery);
        }

        $pageSize = ($pageSize = intval($struct['number'])) > 0 ? $pageSize : 10;
        $currentPage = ($offset = intval($struct['offset'])) > 0 ? ceil($offset / $pageSize) : 1;

        if ("page" == $type) {
            $select->order('table.contents.order');
        } else {
            $select->order('table.contents.' . ("draft" == $status ? "modified" : "created"), Typecho_Db::SORT_DESC);
        }
        $select->page($currentPage, $pageSize);

        try {
            $listRough = $this->db->fetchAll($select);
            $list = [];
            foreach ($listRough as $post) {
                $list[] = $this->commonNoteStruct($post);
            }
            return $this->prosper($list);
        } catch (Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 独立页面列表
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPages($union, $userName, $password, $struct)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        $struct['type'] = "page";
        return $this->NbGetPosts($union, $userName, $password, $struct);
    }

    /**
     * 撰写笔记
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbNewPost($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }
        $isPage = $content['type'] == 'page';
        $request = [
            'do' => 'save',
            'cid' => ($cid = intval($content['nid'])) > 0 ? $cid : NULL,
            'type' => $isPage ? 'page' : 'post',
            'title' => $content['title'] == NULL ? _t('未命名文档') : $content['title'],
            'text' => $content['content'],

            'status' => isset($content["status"]) ? $content["status"] : "publish",
            'password' => $content["password"],
            'order' => $content["order"],
            'category' => [],
            'tags' => is_array($content['tags']) ? implode(',', $content['tags']) : NULL,
            'template' => $isPage ? $content['template'] : NULL,

            'allowComment' => isset($content['allowComment']) ? $content['allowComment'] : $this->options->defaultAllowComment,
            'allowPing' => isset($content['allowPing']) ? $content['allowPing'] : $this->options->defaultAllowPing,
            'allowFeed' => isset($content['allowFeed']) ? $content['allowFeed'] : $this->options->defaultAllowFeed
        ];

        if (isset($content['slug'])) {
            $request['slug'] = $content['slug'];
        }
        if (isset($content['dateCreated'])) {
            /** 解决客户端与服务器端时间偏移 */
            $request['created'] = $content['dateCreated']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
        }

        if (isset($content['fields'])) {
            $fields = json_decode($content['fields'], true);
            foreach ($fields as $field) {
                if (!is_array($field["value"])) {
                    $request['fields'][$field["name"]] = array(
                        $field["type"], $field["value"]
                    );
                }
            }
        }

        if (is_array($content['categories'])) {
            foreach ($content['categories'] as $category) {
                if (!$this->db->fetchRow($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category))) {
                    $this->NbNewCategory($union, $userName, $password, array('name' => $category));
                }

                $request['category'][] = $this->db->fetchObject($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)
                    ->limit(1))->mid;
            }
        }

        /** 调整状态 */
        $status = $request['status'];
        $request['visibility'] = isset($content["visibility"]) ? $content["visibility"] : $status;
        if (in_array($status, ['publish', 'waiting', 'private', 'hidden'])) {
            $request['do'] = 'publish';
            if ('private' == $status) {
                $request['private'] = 1;
            }
        }

        /** 对未归档附件进行归档 */
        $unattached = $this->db->fetchAll($this->select()->where('table.contents.type = ? AND
        (table.contents.parent = 0 OR table.contents.parent IS NULL)', 'attachment'), [$this, 'filter']);

        if (!empty($unattached)) {
            foreach ($unattached as $attach) {
                if (false !== strpos($request['text'], $attach['attachment']->url)) {
                    if (!isset($request['attachment'])) {
                        $request['attachment'] = array();
                    }
                    $request['attachment'][] = $attach['cid'];
                }
            }
        }

        /** 调用已有组件 */
        try {

            // 南博仅支持Markdown，所以必须开启xmlrpc md
            $request['markdown'] = true;
            Helper::options()->markdown = true;
            Helper::options()->xmlrpcMarkdown = true;

            /** 插入 */
            $this->singletonWidget(
                $isPage ? 'Widget_Contents_Page_Edit' : 'Widget_Contents_Post_Edit',
                NULL,
                $request
            )->action();
            $highlightId = $this->singletonWidget('Widget_Notice')->getHighlightId();

            return $this->prosper([
                'nid' => (int)$highlightId
            ]);
        } catch (Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 自定义字段
     *
     * @param $union
     * @param $userName
     * @param $password
     * @param $content
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbFieldPost($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $isPage = $content['type'] == 'page';
        $widget = $this->singletonWidget(
            $isPage ? 'Widget_Contents_Page_Edit' : 'Widget_Contents_Post_Edit'
        );

        ob_start();
        $configFile = $this->options->themeFile($this->options->theme, 'functions.php');
        $layout = new Typecho_Widget_Helper_Layout();

        $widget->pluginHandle()->getDefaultFieldItems($layout);

        if (file_exists($configFile)) {
            require_once $configFile;

            if (function_exists('themeFields')) {
                themeFields($layout);
            }

            if (function_exists($widget->themeCustomFieldsHook)) {
                call_user_func($widget->themeCustomFieldsHook, $layout);
            }
        }

        $layout->render();
        $html = ob_get_contents();
        ob_end_clean();

        return $this->prosper($html);
    }

    /**
     * 编辑笔记
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbEditPost($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }
        return $this->NbNewPost($union, $userName, $password, $content);
    }

    /**
     * 删除笔记
     *
     * @param string $union
     * @param mixed $userName
     * @param mixed $password
     * @param int $nid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbDeletePost($union, $userName, $password, $nid)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        try {
            $this->singletonWidget(
                'Widget_Contents_Post_Edit',
                NULL,
                ['cid' => $nid]
            )->deletePost();
            return $this->prosper($this->currentNotice());
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 删除独立页面
     *
     * @param string $union
     * @param mixed $userName
     * @param mixed $password
     * @param int $nid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbDeletePage($union, $userName, $password, $nid)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        try {
            $this->singletonWidget(
                'Widget_Contents_Page_Edit',
                NULL,
                ["cid" => $nid]
            )->deletePage();
            return $this->prosper($this->currentNotice());
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 评论列表
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetComments($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $select = $this->db->select('table.comments.coid',
            'table.comments.*',
            'table.contents.title'
        )->from('table.comments')
            ->join('table.contents',
                'table.comments.cid = table.contents.cid',
                Typecho_Db::LEFT_JOIN
            )->where('table.comments.ownerId = ?', $this->uid);

        if (isset($struct['nid'])) {
            $select->where('table.comments.cid = ?', $struct['nid']);
        }

        if (isset($struct['mail'])) {
            $select->where('table.comments.mail = ?', $struct['mail']);
        }

        if (isset($struct['status'])) {
            $select->where('table.comments.status = ?', $struct['status']);
        }

        $pageSize = ($pageSize = intval($struct['number'])) > 0 ? $pageSize : 10;
        $currentPage = ($offset = intval($struct['offset'])) > 0 ? ceil($offset / $pageSize) : 1;

        $select->order('created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize);

        try {
            $commentRough = $this->db->fetchAll($select);
            $list = [];

            foreach ($commentRough as $comment) {
                $list[] = $this->commonCommentStruct($comment);
            }

            return $this->prosper($list);
        } catch (Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 删除评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $oid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbDeleteComment($union, $userName, $password, $oid)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', intval($oid));

        if (!$commentWidget->commentIsWriteable($where)) {
            return $this->pervert('无法编辑此评论', 403);
        }

        $count = $this->singletonWidget('Widget_Abstract_Comments')->delete($where);
        $tip = $count > 0 ? "删除成功" : "删除失败";

        return $this->prosper($tip);
    }

    /**
     * 创建评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param mixed $path
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbNewComment($union, $userName, $password, $path, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        if (is_numeric($path)) {
            $post = $this->singletonWidget(
                'Widget_Archive',
                'type=single',
                ['cid' => $path]
            );
        } else {
            /** 检查目标地址是否正确*/
            $pathInfo = Typecho_Common::url(substr($path, strlen($this->options->index)), '/');
            $post = Typecho_Router::match($pathInfo);
        }

        /** 这样可以得到cid或者slug*/
        if (!isset($post) || !($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            return new IXR_Error(404, _t('这个目标地址不存在'));
        }

        if (!isset($struct['message'])) {
            return $this->pervert('评论内容为空', 404);
        }

        $request = [
            'author' => $struct['author'],
            'mail' => $struct['mail'],
            'url' => $struct['site'],
            'text' => $struct['message'],
            'parent' => $struct['parentId'],
            'type' => 'comment',
            'permalink' => $post->pathinfo,
        ];

        try {
            //临时评论关闭反垃圾保护
            Helper::options()->commentsAntiSpam = false;
            $commentWidget = $this->singletonWidget(
                'Widget_Feedback',
                'checkReferer=false',
                $request
            );
            $commentWidget->action();

            $callback = [
                "oid" => (int)$commentWidget->coid
            ];

            return $this->prosper($callback);
        } catch (Typecho_Exception $e) {
            return new IXR_Error(500, $e->getMessage());
        }
    }

    /**
     * 评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $oid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetComment($union, $userName, $password, $oid)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $comments = $this->singletonWidget(
            'Widget_Comments_Edit',
            NULL,
            ['do' => 'get', 'coid' => intval($oid)]
        );

        if (!$comments->have()) {
            return $this->pervert('评论不存在', 404);
        }

        if (!$comments->commentIsWriteable()) {
            return $this->pervert('没有获取评论的权限', 403);
        }

        $comment = $this->commonCommentStruct(
            (array)$comments
        );

        return $this->prosper($comment);
    }

    /**
     * 编辑评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $oid
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbEditComment($union, $userName, $password, $oid, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', intval($oid));

        if (!$commentWidget->commentIsWriteable($where)) {
            return $this->pervert('无法编辑此评论', 403);
        }

        $request = [
            'status' => isset($struct['status']) ? $struct['status'] : "approved",
        ];

        if (isset($struct['author'])) {
            $request['author'] = $struct['author'];
        }

        if (isset($struct['mail'])) {
            $request['mail'] = $struct['mail'];
        }

        if (isset($struct['message'])) {
            $request['text'] = $struct['message'];
        }

        if (isset($struct['created'])) {
            $request['created'] = $struct['created'];
        }

        if (isset($struct['site'])) {
            $request['url'] = $struct['site'];
        }

        $result = $commentWidget->update($request, $where);
        if ($result === false) {
            return $this->pervert('编辑评论失败', 201);
        }

        return $this->prosper($result);
    }

    /**
     * 创建媒体
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $data
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbNewMedia($union, $userName, $password, $data)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $result = Widget_Upload::uploadHandle($data);

        if (false === $result) {
            return new IXR_Error(500, _t('上传失败'));
        } else {

            $insertId = $this->insert(array(
                'title' => $result['name'],
                'slug' => $result['name'],
                'type' => 'attachment',
                'status' => 'publish',
                'text' => serialize($result),
                'allowComment' => 1,
                'allowPing' => 0,
                'allowFeed' => 1
            ));

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $insertId)
                ->where('table.contents.type = ?', 'attachment'), array($this, 'push'));

            /** 增加插件接口 */
            $this->pluginHandle()->upload($this);

            return $this->prosper([
                'name' => $this->attachment->name,
                'url' => $this->attachment->url
            ]);
        }
    }

    /**
     * 获取所有的分类
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetCategories($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $categories = $this->singletonWidget('Widget_Metas_Category_List');

        $list = [];
        while ($categories->next()) {
            $list[] = $this->commonMetaStruct($categories);
        }

        return $this->prosper($list);
    }

    /**
     * 创建分类
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $category
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbNewCategory($union, $userName, $password, $category)
    {
        if (!$this->access($union, $userName, $password, "editor")) {
            return $this->error;
        }

        /** 开始接受数据 */
        $request[] = [
            'do' => 'insert',
            'name' => $category['name'],
            'slug' => Typecho_Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']),
            'parent' => isset($category['parentId']) ? $category['parentId'] : 0,
            'description' => isset($category['desc']) ? $category['desc'] : $category['name']
        ];

        /** 调用已有组件 */
        try {
            /** 插入 */
            $categoryWidget = $this->singletonWidget(
                'Widget_Metas_Category_Edit',
                NULL,
                $request
            );
            $categoryWidget->action();
            $callback = [
                'mid' => (int)$categoryWidget->mid
            ];
            return $this->prosper($callback);
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), "无法添加分类");
        }
    }

    /**
     * 编辑分类
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $category
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbEditCategory($union, $userName, $password, $category)
    {
        if (!$this->access($union, $userName, $password, "editor")) {
            return $this->error;
        }

        if (empty($mid = $category['mid'])) {
            return new IXR_Error(403, "请求错误");
        }

        if (!$this->db->fetchRow($this->db->select('mid')
            ->from('table.metas')->where('type = ? AND mid = ?', 'category', $mid))) {
            return $this->pervert('没有查找到分类', 404);
        }

        /** 开始接受数据 */
        $request[] = [
            'mid' => $mid,
            'do' => 'update',
            'name' => $category['name'],
            'slug' => Typecho_Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']),
            'parent' => isset($category['parentId']) ? $category['parentId'] : 0,
            'description' => isset($category['desc']) ? $category['desc'] : $category['name']
        ];

        /** 调用已有组件 */
        try {
            /**更新 */
            $categoryWidget = $this->singletonWidget(
                'Widget_Metas_Category_Edit',
                NULL,
                $request
            );
            $categoryWidget->action();
            return $this->prosper("更新成功");
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), "无法编辑分类");
        }
    }

    /**
     * 删除分类
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $mid
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbDeleteCategory($union, $userName, $password, $mid)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'editor')) {
            return $this->error;
        }

        try {
            $this->singletonWidget(
                'Widget_Metas_Category_Edit',
                NULL,
                ['do' => 'delete', 'mid' => intval($mid)]
            );
            return $this->prosper("删除成功");
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "删除分类失败");
        }
    }

    /**
     * 所有标签
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetTags($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password)) {
            return ($this->error);
        }

        try {
            $tags = $this->singletonWidget('Widget_Metas_Tag_Cloud');
            $list = [];
            while ($tags->next()) {
                $list[] = $this->commonMetaStruct($tags);
            }
            return $this->prosper($list);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 撰写独立页面
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbNewPage($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        $content['type'] = 'page';
        return $this->NbNewPost($union, $userName, $password, $content);
    }

    /**
     * 编辑独立页面
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbEditPage($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        $content['type'] = 'page';
        return $this->NbNewPost($union, $userName, $password, $content);
    }


    /**
     * 获取系统选项
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetOptions($union, $userName, $password, $options = [])
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = [];
        $this->options->siteUrl = rtrim($this->options->siteUrl, '/');
        foreach ($options as $option) {
            if (isset($this->options->{$option})) {
                $o = array(
                    'name' => $option,
                    'user' => $this->uid,
                    'value' => $this->options->{$option}
                );
            } else {
                $select = $this->db->select()->from('table.options')
                    ->where('name = ?', $option)
                    ->where('user = ? ', 0)
                    ->order('user', Typecho_Db::SORT_DESC);
                $o = $this->db->fetchRow($select);
            }
            $struct[] = array(
                $o['name'],
                $o['user'],
                $o['value']
            );
        }

        return $this->prosper($struct);
    }

    /**
     * 设置系统选项
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbSetOptions($union, $userName, $password, $options = [])
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = [];
        foreach ($options as $object) {
            if ($this->db->query($this->db->update('table.options')
                    ->rows(array('value' => $object[2]))
                    ->where('name = ?', $object[0])) > 0) {
                $struct[] = array(
                    $object[0],
                    $object[2]
                );
            }
        }

        return $this->prosper($struct);
    }

    /**
     * 媒体文件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetMedias($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        $pageSize = ($pageSize = intval($struct['number'])) > 0 ? $pageSize : 10;
        $currentPage = ($offset = intval($struct['offset'])) > 0 ? ceil($offset / $pageSize) : 1;

        $request = [
            'parent' => $struct['parentId'],
            'mime' => $struct['mime'],
            'page' => $currentPage
        ];

        try {
            $attachments = $this->singletonWidget(
                'Widget_Contents_Attachment_Admin',
                ['pageSize' => $pageSize],
                $request
            );
            $list = [];
            while ($attachments->next()) {
                $list[] = $this->commonMediaStruct($attachments);
            }
            return $this->prosper($list);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 清理未归档的附件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbClearMedias($union, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'administrator')) {
            return $this->error;
        }

        try {
            $mediaWidget = $this->singletonWidget(
                'Widget_Contents_Attachment_Edit',
                NULL,
                ['do' => 'clear']
            );
            $mediaWidget->action();
            return $this->prosper("成功清理未归档的文件");
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 删除附件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbDeleteMedia($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        if (!is_array($struct["list"])) {
            return new IXR_Error(403, "缺少必要参数");
        }

        try {
            $mediaWidget = $this->singletonWidget(
                'Widget_Contents_Attachment_Edit',
                NULL,
                ['do' => 'delete', 'cid' => $struct["list"]]
            );
            $mediaWidget->action();
            return $this->prosper("删除文件成功");
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 编辑附件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbEditMedia($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password)) {
            return $this->error;
        }

        if (empty($struct["mid"]) || empty($struct["name"])) {
            return $this->pervert("确实必要参数", 404);
        }

        $request = [
            'do' => 'update',
            'cid' => $struct["mid"],
            'slug' => $struct["slug"],
            'name' => $struct["name"],
            'description' => $struct["desc"]
        ];

        try {
            $mediaWidget = $this->singletonWidget(
                'Widget_Contents_Attachment_Edit',
                NULL,
                $request
            );
            $mediaWidget->action();
            return $this->prosper("编辑文件成功");
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

    }

    /**
     * 内容替换 - 插件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbPluginReplace($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        if (empty($struct['former']) || empty($struct['last']) || empty($struct['object'])) {
            return $this->pervert("确实必要参数", 404);
        } else {
            $former = $struct['former'];
            $last = $struct['last'];
            $object = $struct['object'];
            $array = [
                'post|text',
                'post|title',
                'page|text',
                'page|title',
                'field|thumb',
                'field|mp4',
                'field|fm',
                'comment|text',
                'comment|url'
            ];
            if (in_array($object, $array)) {
                $prefix = $this->db->getPrefix();
                $obj = explode("|", $object);
                $type = $obj[0];
                $aim = $obj[1];
                try {
                    switch ($type) {
                        case "post":
                        case "page":
                            $data_name = $prefix . 'contents';
                            $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}') WHERE type='{$type}'");
                            break;
                        case "field":
                            $data_name = $prefix . 'fields';
                            $this->db->query("UPDATE `{$data_name}` SET `str_value`=REPLACE(`str_value`,'{$former}','{$last}')  WHERE name='{$aim}'");
                            break;
                        case "comment":
                            $data_name = $prefix . 'comments';
                            $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}')");
                    }
                    return $this->prosper("替换成功");
                } catch (Exception $e) {
                    return new IXR_Error($e->getCode(), $e->getMessage());
                }

            } else {
                return $this->pervert("不含此参数,无法替换", 202);
            }
        }
    }

    /**
     * 我的动态 - 插件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbPluginDynamics($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($this->options->plugins['activated']['Dynamics'])) {
            return $this->pervert('没有启用我的动态插件', 404);
        }

        switch ($struct['method']) {
            case "insert":
                if (!is_array($struct['dynamic'])) {
                    return new IXR_Error(403, "非法请求");
                }

                $map = $struct['dynamic'];
                $isAdd = empty($did = $map['did']);
                if (empty($map['text'])) {
                    return $this->pervert('无动态内容', 404);
                }
                $date = (new Typecho_Date($this->options->gmtTime))->time();
                $dynamic = [
                    'authorId' => $this->uid,
                    'text' => $map['text'],
                    'status' => $map['status'],
                    'modified' => $date
                ];

                if ($isAdd) {
                    $dynamic['created'] = $date;
                } else {
                    $dynamic['did'] = $did;
                }

                $result = $this->pluginHandle()
                    ->trigger($dynamicPluggable)
                    ->{$isAdd ? 'dynamicsAdd' : 'dynamicsAlter'}($this->uid, $dynamic);
                if ($dynamicPluggable) {
                    return $this->prosper($result);
                }

                if ($isAdd) {
                    $dynamic['did'] = $this->db->query($this->db
                        ->insert('table.dynamics')
                        ->rows($dynamic));
                } else {
                    $this->db->query($this->db
                        ->update('table.dynamics')
                        ->rows($dynamic)
                        ->where('did = ?', $did));
                }
                return $this->prosper($dynamic);
            case "delete":
                $list = $struct['list'];
                if (!is_array($list)) {
                    return new IXR_Error(403, "非法请求");
                }

                $deleteCount = $this->pluginHandle()
                    ->trigger($dynamicPluggable)
                    ->dynamicsRemove($this->uid, $list);

                if ($dynamicPluggable) {
                    return $this->prosper($deleteCount);
                }

                $deleteCount = 0;
                foreach ($list as $did) {
                    if ($this->db->query($this->db->delete('table.dynamics')->where('did = ?', $did))) {
                        $deleteCount++;
                    }
                }
                return $this->prosper($deleteCount);
            case "get":
                $status = $struct['status'];
                $pageSize = ($pageSize = intval($struct['number'])) > 0 ? $pageSize : 10;
                $currentPage = ($offset = intval($struct['offset'])) > 0 ? ceil($offset / $pageSize) : 1;

                $list = $this->pluginHandle()
                    ->trigger($dynamicPluggable)
                    ->dynamicsGain($this->uid, $status, $pageSize, $currentPage);

                if ($dynamicPluggable) {
                    return $this->prosper($list);
                }

                $select = $this->db->select()->from('table.dynamics')
                    ->where('authorId = ?', $this->uid);

                if (isset($struct['status']) && $struct['status'] != "all") {
                    $select->where('status = ?', $struct['status']);
                }

                $select->order('created', Typecho_Db::SORT_DESC)
                    ->page($currentPage, $pageSize);

                $dynamicRough = $this->db->fetchAll($select);
                $list = [];
                foreach ($dynamicRough as $dynamic) {
                    $dynamic["title"] = date("m月d日, Y年", $dynamic["created"]);
                    $dynamic["permalink"] = Dynamics_Plugin::applyUrl($dynamic["did"], true);
                    $list[] = $dynamic;
                }
                return $this->prosper($list);
            default:
                return $this->pervert("缺少必要参数", 403);
        }
    }

    /**
     * 友情链接 - 插件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbPluginLinks($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($this->options->plugins['activated']['Links'])) {
            return $this->pervert('没有启用友情链接插件', 404);
        }

        switch ($struct['method']) {
            case "insert":
                if (!is_array($struct['link'])) {
                    return $this->pervert("非法请求", 403);
                }
                $map = $struct['link'];
                $isAdd = empty($lid = $map['lid']);
                if (!isset($map['name'])) {
                    return new IXR_Error(403, "没有设定名字");
                }
                if (!isset($map['url'])) {
                    return new IXR_Error(403, "没有设定链接地址");
                }

                $link = [
                    'name' => $map['name'],
                    'url' => $map['url'],
                    'image' => $map['image'],
                    'description' => $map['description'],
                    'user' => $map['url'],
                    'order' => $map['order'],
                    'sort' => $map['sort']
                ];

                if ($isAdd) {
                    $link['order'] = $this->db->fetchObject($this->db
                            ->select(array('MAX(order)' => 'maxOrder'))
                            ->from('table.links'))->maxOrder + 1;
                    $link['lid'] = $this->db->query($this->db->insert('table.links')->rows($link));
                } else {
                    $this->db->query($this->db->update('table.links')
                        ->rows($link)
                        ->where('lid = ?', $lid));
                }
                return $this->prosper($link);
            case "delete":
                $lids = $struct['lids'];
                if (!is_array($lids)) {
                    return new IXR_Error(403, "lids 不是一个数组对象");
                }
                $deleteCount = 0;
                foreach ($lids as $lid) {
                    if ($this->db->query($this->db->delete('table.links')->where('lid = ?', $lid))) {
                        $deleteCount++;
                    }
                }
                return $this->prosper($deleteCount);
            case "get":
                $pageSize = ($pageSize = intval($struct['number'])) > 0 ? $pageSize : 10;
                $currentPage = ($offset = intval($struct['offset'])) > 0 ? ceil($offset / $pageSize) : 1;

                $select = $this->db->select()
                    ->from('table.links')
                    ->order('order')
                    ->page($currentPage, $pageSize);

                $links = $this->db->fetchAll($select);
                return $this->prosper($links);
            default:
                return $this->pervert("缺少必要参数", 403);
        }
    }

    /**
     * 插件配置管理
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbConfigPlugin($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setPluginAble == 0) {
                return $this->pervert("你已关闭插件设置能力\n可以在 Aidnabo 插件里开启设置能力", 202);
            }
        }

        if (!isset($struct['pluginName'])) {
            return $this->pervert("缺少必要参数", 403);
        }

        if (!isset($this->options->plugins['activated']{$struct['pluginName']})) {
            return $this->pervert("没有启用插件", 403);
        }

        $className = $struct['pluginName'] . "_Plugin";
        switch ($struct['method']) {
            case "set":
                if (empty($struct['settings'])) {
                    return new IXR_Error(403, "settings 不规范");
                }

                $settings = json_decode($struct['settings'], true);

                ob_start();
                $form = new Typecho_Widget_Helper_Form();
                call_user_func(array($className, 'config'), $form);

                foreach ($settings as $key => $val) {
                    if (!empty($form->getInput($key))) {
                        $_GET{$key} = $settings{$key};
                        $form->getInput($key)->value($val);
                    }
                }

                /** 验证表单 */
                if ($form->validate()) {
                    return new IXR_Error(403, "表中有数据不符合配置要求");
                }

                $settings = $form->getAllRequest();
                ob_end_clean();

                try {
                    $edit = $this->singletonWidget(
                        'Widget_Plugins_Edit'
                    );
                    if (!$edit->configHandle($struct['pluginName'], $settings, false)) {
                        Widget_Plugins_Edit::configPlugin($struct['pluginName'], $settings);
                    }

                    return $this->prosper("设置成功");
                } catch (Typecho_Exception $e) {
                    return new IXR_Error($e->getCode(), $e->getMessage());
                }
            case "get":
                ob_start();
                $config = $this->singletonWidget(
                    'Widget_Plugins_Config',
                    NULL,
                    ["config" => $struct['pluginName']]
                );
                $form = $config->config();
                $form->setAction(NULL);
                $form->setAttribute("id", "form");
                $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
                $form->render();
                $string = ob_get_contents();
                $html = $string;
                ob_end_clean();

                return $this->prosper($html);
            default:
                return $this->pervert("缺少必要参数", 403);
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbConfigProfile($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($struct['option'])) {
            return $this->pervert("缺少必要参数", 403);
        }
        switch ($struct['method']) {
            case "set":
                if (isset($this->options->plugins['activated']['Aidnabo'])) {
                    if ($this->options->plugin("Aidnabo")->setOptionAble == 0) {
                        return $this->pervert("你已关闭基本设置能力\n可以在 Aidnabo 插件里开启设置能力", 202);
                    }
                }

                if (empty($struct['settings'])) {
                    return new IXR_Error(403, "settings 不规范");
                }
                $settings = json_decode($struct['settings'], true);

                ob_start();
                $config = $this->singletonWidget(
                    'Widget_Users_Profile',
                    NULL,
                    $settings
                );
                if ($struct['option'] == "profile") {
                    $config->updateProfile();
                } else if ($struct['option'] == "options") {
                    $config->updateOptions();
                } else if ($struct['option'] == "password") {
                    $config->updatePassword();
//            } else if ($struct['option'] == "personal") {
//                $config->updatePersonal();
                }
                ob_end_clean();
                return $this->prosper("设置已经保存");
            case "get":
                ob_start();
                $config = $this->singletonWidget(
                    'Widget_Users_Profile'
                );

                if ($struct['option'] == "profile") {
                    $form = $config->profileForm();
                } else if ($struct['option'] == "options") {
                    $form = $config->optionsForm();
                } else if ($struct['option'] == "password") {
                    $form = $config->passwordForm();
//            } else if ($struct['option'] == "personal") {
//                $form = $config->personalFormList();
                } else {
                    return new IXR_Error(403, "option 不规范");
                }

                $form->setAction(NULL);
                $form->setAttribute("id", "form");
                $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
                $form->render();
                $string = ob_get_contents();
                $html = $string;
                ob_end_clean();

                return $this->prosper($html);
            default:
                return $this->pervert("缺少必要参数", 403);
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbConfigOption($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($struct['option'])) {
            return $this->pervert("缺少必要参数", 403);
        }

        if ($struct['option'] == "general") {
            $alias = "Widget_Options_General";
        } else if ($struct['option'] == "discussion") {
            $alias = "Widget_Options_Discussion";
        } else if ($struct['option'] == "reading") {
            $alias = "Widget_Options_Reading";
        } else if ($struct['option'] == "permalink") {
            $alias = "Widget_Options_Permalink";
        } else {
            return new IXR_Error(403, "option 不规范");
        }

        switch ($struct['method']) {
            case  "set":
                if (isset($this->options->plugins['activated']['Aidnabo'])) {
                    if ($this->options->plugin("Aidnabo")->setOptionAble == 0) {
                        return $this->pervert("你已关闭基本设置能力\n可以在 Aidnabo 插件里开启设置能力", 202);
                    }
                }

                if (empty($struct['settings'])) {
                    return new IXR_Error(403, "settings 不规范");
                }
                $settings = json_decode($struct['settings'], true);

                ob_start();
                $config = $this->singletonWidget(
                    $alias,
                    null,
                    $settings
                );
                if ($struct['option'] == "general") {
                    $config->updateGeneralSettings();
                } else if ($struct['option'] == "discussion") {
                    $config->updateDiscussionSettings();
                } else if ($struct['option'] == "reading") {
                    $config->updateReadingSettings();
                } else if ($struct['option'] == "permalink") {
                    $config->updatePermalinkSettings();
                }
                ob_end_clean();
                return $this->prosper("设置已经保存");
            case "get":
                ob_start();
                $config = $this->singletonWidget(
                    $alias
                );
                $form = $config->form();
                $form->setAction(NULL);
                $form->setAttribute("id", "form");
                $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
                $form->render();
                $string = ob_get_contents();
                $html = $string;
                ob_end_clean();

                return $this->prosper($html);
            default:
                return $this->pervert("缺少必要参数", 403);
        }
    }

    /**
     * 主题配置管理
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbConfigTheme($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setThemeAble == 0) {
                return $this->pervert("你已关闭主题设置能力\n可以在 Aidnabo 插件里开启设置能力", 202);
            }
        }

        if (!Widget_Themes_Config::isExists()) {
            return $this->pervert('没有主题可配置', 404);
        }

        switch ($struct['method']) {
            case "set":
                if (empty($struct['settings'])) {
                    return new IXR_Error(403, "settings 不规范");
                }

                try {
                    $settings = json_decode($struct['settings'], true);
                    $theme = $this->options->theme;

                    ob_start();
                    $form = new Typecho_Widget_Helper_Form();
                    themeConfig($form);
                    $inputs = $form->getInputs();

                    if (!empty($inputs)) {
                        foreach ($inputs as $key => $val) {
                            $_GET{$key} = $settings{$key};
                            $form->getInput($key)->value($settings{$key});
                        }
                    }

                    /** 验证表单 */
                    if ($form->validate()) {
                        return new IXR_Error(403, "表中有数据不符合配置要求");
                    }

                    $settings = $form->getAllRequest();
                    ob_end_clean();

                    $db = Typecho_Db::get();
                    $themeEdit = $this->singletonWidget(
                        'Widget_Themes_Edit'
                    );

                    if (!$themeEdit->configHandle($settings, false)) {
                        if ($this->options->__get('theme:' . $theme)) {
                            $update = $db->update('table.options')
                                ->rows(array('value' => serialize($settings)))
                                ->where('name = ?', 'theme:' . $theme);
                            $db->query($update);
                        } else {
                            $insert = $db->insert('table.options')
                                ->rows(array(
                                    'name' => 'theme:' . $theme,
                                    'value' => serialize($settings),
                                    'user' => 0
                                ));
                            $db->query($insert);
                        }
                    }

                    return $this->prosper("外观设置已经保存");
                } catch (Typecho_Exception $e) {
                    return new IXR_Error($e->getCode(), $e->getMessage());
                }
            case "get":
                ob_start();
                $config = $this->singletonWidget(
                    'Widget_Themes_Config'
                );
                $form = $config->config();
                $form->setAction(NULL);
                $form->setAttribute("id", "form");
                $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
                $form->render();
                $string = ob_get_contents();
                $html = $string;
                ob_end_clean();

                return $this->prosper($html);
            default:
                return $this->pervert("缺少必要参数", 403);
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetPlugins($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        $target = isset($struct['option']) ? $struct['option'] : "typecho";
        $list = [];
        $activatedPlugins = $this->singletonWidget('Widget_Plugins_List@activated', 'activated=1');

        if ($activatedPlugins->have() || !empty($activatedPlugins->activatedPlugins)) {
            while ($activatedPlugins->next()) {
                $list[$activatedPlugins->name] = array(
                    "activated" => true,
                    "name" => $activatedPlugins->name,
                    "title" => $activatedPlugins->title,
                    "dependence" => $activatedPlugins->dependence,
                    "description" => strip_tags($activatedPlugins->description),
                    "version" => $activatedPlugins->version,
                    "homepage" => $activatedPlugins->homepage,
                    "author" => $activatedPlugins->author,
                    "config" => $activatedPlugins->config
                );
            }
        }

        $deactivatedPlugins = $this->singletonWidget('Widget_Plugins_List@unactivated', 'activated=0');

        if ($deactivatedPlugins->have() || !$activatedPlugins->have()) {
            while ($deactivatedPlugins->next()) {
                $list[$deactivatedPlugins->name] = array(
                    "activated" => false,
                    "name" => $deactivatedPlugins->name,
                    "title" => $deactivatedPlugins->title,
                    "dependence" => true,
                    "description" => strip_tags($deactivatedPlugins->description),
                    "version" => $deactivatedPlugins->version,
                    "homepage" => $deactivatedPlugins->homepage,
                    "author" => $deactivatedPlugins->author,
                    "config" => false
                );
            }
        }

        if ($target == "testore") {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $testore = $this->singletonWidget(
                    "TeStore_Action"
                );
                $storeList = array();
                $plugins = $testore->getPluginData();

                foreach ($plugins as $plugin) {
                    $thisPlugin = $list[$plugin['pluginName']];
                    $installed = array_key_exists($plugin['pluginName'], $list);
                    $activated = $installed ? $thisPlugin["activated"] : false;
                    $storeList[] = array(
                        "activated" => $activated,
                        "name" => $plugin['pluginName'],
                        "title" => $plugin['pluginName'],
                        "dependence" => $activated ? $thisPlugin["dependence"] : null,
                        "description" => strip_tags($plugin['desc']),
                        "version" => $plugin['version'],
                        "homepage" => $plugin['pluginUrl'],
                        "author" => strip_tags($plugin['authorHtml']),
                        "config" => $activated ? $thisPlugin["config"] : false,

                        "installed" => $installed,
                        "mark" => $plugin['mark'],
                        "zipFile" => $plugin['zipFile'],
                    );
                }

                return $this->prosper($storeList);
            } else {
                return $this->pervert("你没有安装 TeStore 插件", 301);
            }
        } else {
            $callList = array();
            foreach ($list as $key => $info) {
                $callList[] = $info;
            }
            return $this->prosper($callList);
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbSetPlugins($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setPluginAble == 0) {
                return $this->pervert("你已关闭插件设置能力\n可以在 Aidnabo 插件里开启设置能力", 202);
            }
        }

        $target = isset($struct['option']) ? $struct['option'] : "typecho";
        if ($target == "testore") {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $authors = preg_split('/([,&])/', $struct['authorName']);
                foreach ($authors as $key => $val) {
                    $authors[$key] = trim($val);
                }
                $testore = $this->singletonWidget(
                    "TeStore_Action",
                    null,
                    array(
                        "plugin" => $struct['pluginName'],
                        "author" => implode('_', $authors),
                        "zip" => $struct['zipFile'],
                    )
                );

                $isActivated = $activatedList[$struct['pluginName']];

                if ($struct['method'] == "activate") {
                    if ($isActivated) {
                        return $this->pervert("该插件已被安装过", 401);
                    } else {
                        $testore->install();
                    }
                } else if ($struct['method'] == "deactivate") {
                    $testore->uninstall();
                }

                $notice = Json::decode(
                    Typecho_Cookie::get("__typecho_notice"), true
                )[0];

                return $this->prosper($notice);
            } else {
                return $this->pervert("你没有安装 TeStore 插件", 301);
            }
        } else {
            try {
                $plugins = $this->singletonWidget(
                    'Widget_Plugins_Edit'
                );

                if ($struct['method'] == "activate") {
                    $plugins->activate($struct['pluginName']);

                } else if ($struct['method'] == "deactivate") {
                    $plugins->deactivate($struct['pluginName']);

                }

                $notice = Json::decode(
                    Typecho_Cookie::get("__typecho_notice"), true
                )[0];

                return $this->prosper($notice);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error(403, $e);
            }
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetThemes($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        $list = [];
        $themes = $this->singletonWidget('Widget_Themes_List');

        while ($themes->next()) {
            $list[] = array(
                "activated" => $themes->activated,
                "name" => $themes->name,
                "title" => $themes->title,
                "description" => strip_tags($themes->description),
                "version" => $themes->version,
                "homepage" => $themes->homepage,
                "author" => $themes->author,
                "config" => false
            );
        }
        return $this->prosper($list);
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbSetThemes($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setThemeAble == 0) {
                return $this->pervert("你已关闭主题设置能力\n可以在 Aidnabo 插件里开启设置能力", 202);
            }
        }

        try {
            $themes = $this->singletonWidget(
                'Widget_Themes_Edit'
            );

            if ($struct['method'] == "changeTheme") {
                $themes->changeTheme($struct['themeName']);
                return $this->prosper("外观已经改变");
            } else {
                return new IXR_Error(403, "method 未知");
            }

        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error(403, $e);
        }
    }

    /**
     * pingbackPing
     *
     * @param string $source
     * @param string $target
     * @return IXR_Error|void
     * @throws Typecho_Exception
     * @access public
     */
    public function pingbackPing($source, $target)
    {
        /** 检查目标地址是否正确*/
        $pathInfo = Typecho_Common::url(substr($target, strlen($this->options->index)), '/');
        $post = Typecho_Router::match($pathInfo);

        /** 检查源地址是否合法 */
        $params = parse_url($source);
        if (false === $params || !in_array($params['scheme'], array('http', 'https'))) {
            return new IXR_Error(16, _t('源地址服务器错误'));
        }

        if (!Typecho_Common::checkSafeHost($params['host'])) {
            return new IXR_Error(16, _t('源地址服务器错误'));
        }

        /** 这样可以得到cid或者slug*/
        if (!($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            return new IXR_Error(33, _t('这个目标地址不存在'));
        }

        if ($post) {
            /** 检查是否可以ping*/
            if ($post->allowPing) {

                /** 现在可以ping了，但是还得检查下这个pingback是否已经存在了*/
                $pingNum = $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')->where(
                        'table.comments.cid = ? AND table.comments.url = ? AND table.comments.type <> ?',
                        $post->cid,
                        $source,
                        'comment'
                    ))->num;

                if ($pingNum <= 0) {
                    /** 检查源地址是否存在*/
                    if (!($http = Typecho_Http_Client::get())) {
                        return new IXR_Error(16, _t('源地址服务器错误'));
                    }

                    try {

                        $http->setTimeout(5)->send($source);
                        $response = $http->getResponseBody();

                        if (200 == $http->getResponseStatus()) {

                            if (!$http->getResponseHeader('x-pingback')) {
                                preg_match_all("/<link[^>]*rel=[\"']([^\"']*)[\"'][^>]*href=[\"']([^\"']*)[\"'][^>]*>/i", $response, $out);
                                if (!isset($out[1]['pingback'])) {
                                    return new IXR_Error(50, _t('源地址不支持PingBack'));
                                }
                            }
                        } else {
                            return new IXR_Error(16, _t('源地址服务器错误'));
                        }
                    } catch (Exception $e) {
                        return new IXR_Error(16, _t('源地址服务器错误'));
                    }

                    /** 现在开始插入以及邮件提示了 $response就是第一行请求时返回的数组*/
                    preg_match("/\<title\>([^<]*?)\<\/title\\>/is", $response, $matchTitle);
                    $finalTitle = Typecho_Common::removeXSS(trim(strip_tags($matchTitle[1])));

                    /** 干掉html tag，只留下<a>*/
                    $text = Typecho_Common::stripTags($response, '<a href="">');

                    /** 此处将$target quote,留着后面用*/
                    $pregLink = preg_quote($target);

                    /** 找出含有target链接的最长的一行作为$finalText*/
                    $finalText = '';
                    $lines = explode("\n", $text);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (NULL != $line) {
                            if (preg_match("|<a[^>]*href=[\"']{$pregLink}[\"'][^>]*>(.*?)</a>|", $line)) {
                                if (strlen($line) > strlen($finalText)) {
                                    /** <a>也要干掉，*/
                                    $finalText = Typecho_Common::stripTags($line);
                                }
                            }
                        }
                    }

                    /** 截取一段字*/
                    if (NULL == trim($finalText)) {
                        return new IXR_Error('17', _t('源地址中不包括目标地址'));
                    }

                    $finalText = '[...]' . Typecho_Common::subStr($finalText, 0, 200, '') . '[...]';

                    $pingback = array(
                        'cid' => $post->cid,
                        'created' => $this->options->time,
                        'agent' => $this->request->getAgent(),
                        'ip' => $this->request->getIp(),
                        'author' => Typecho_Common::subStr($finalTitle, 0, 150, '...'),
                        'url' => Typecho_Common::safeUrl($source),
                        'text' => $finalText,
                        'ownerId' => $post->author->uid,
                        'type' => 'pingback',
                        'status' => $this->options->commentsRequireModeration ? 'waiting' : 'approved'
                    );

                    /** 加入plugin */
                    $pingback = $this->pluginHandle()->pingback($pingback, $post);

                    /** 执行插入*/
                    $insertId = $this->singletonWidget('Widget_Abstract_Comments')->insert($pingback);

                    /** 评论完成接口 */
                    $this->pluginHandle()->finishPingback($this);

                    return $insertId;

                    /** todo:发送邮件提示*/
                } else {
                    return new IXR_Error(48, _t('PingBack已经存在'));
                }
            } else {
                return new IXR_Error(49, _t('目标地址禁止Ping'));
            }
        } else {
            return new IXR_Error(33, _t('这个目标地址不存在'));
        }
    }

    /**
     * 回收变量
     *
     * @access public
     * @param string $methodName 方法
     * @return void
     */
    public function hookAfterCall($methodName)
    {
        if (!empty($this->_usedWidgetNameList)) {
            foreach ($this->_usedWidgetNameList as $key => $widgetName) {
                $this->destory($widgetName);
                unset($this->_usedWidgetNameList[$key]);
            }
        }
    }

    /**
     * 入口执行方法
     *
     * @access public
     * @return void
     * @throws Typecho_Widget_Exception
     */
    public function action()
    {
        if (0 == $this->options->allowXmlRpc) {
            throw new Typecho_Widget_Exception(_t('请求的地址不存在'), 404);
        }

        if (isset($this->request->rsd)) {
            echo
            <<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName>Typecho</engineName>
        <engineLink>http://www.typecho.org/</engineLink>
        <homePageLink>{$this->options->siteUrl}</homePageLink>
        <apis>
            <api name="Typecho" blogID="1" preferred="true" apiLink="{$this->options->xmlRpcUrl}" />
        </apis>
    </service>
</rsd>
EOF;
        } else if (isset($this->request->wlw)) {
            echo
            <<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<manifest xmlns="http://schemas.microsoft.com/wlw/manifest/weblog">
    <options>
        <supportsKeywords>Yes</supportsKeywords>
        <supportsFileUpload>Yes</supportsFileUpload>
        <supportsExtendedEntries>Yes</supportsExtendedEntries>
        <supportsCustomDate>Yes</supportsCustomDate>
        <supportsCategories>Yes</supportsCategories>

        <supportsCategoriesInline>Yes</supportsCategoriesInline>
        <supportsMultipleCategories>Yes</supportsMultipleCategories>
        <supportsHierarchicalCategories>Yes</supportsHierarchicalCategories>
        <supportsNewCategories>Yes</supportsNewCategories>
        <supportsNewCategoriesInline>Yes</supportsNewCategoriesInline>
        <supportsCommentPolicy>Yes</supportsCommentPolicy>

        <supportsPingPolicy>Yes</supportsPingPolicy>
        <supportsAuthor>Yes</supportsAuthor>
        <supportsSlug>Yes</supportsSlug>
        <supportsPassword>Yes</supportsPassword>
        <supportsExcerpt>Yes</supportsExcerpt>
        <supportsTrackbacks>Yes</supportsTrackbacks>

        <supportsPostAsDraft>Yes</supportsPostAsDraft>

        <supportsPages>Yes</supportsPages>
        <supportsPageParent>No</supportsPageParent>
        <supportsPageOrder>Yes</supportsPageOrder>
        <requiresXHTML>True</requiresXHTML>
        <supportsAutoUpdate>No</supportsAutoUpdate>

    </options>
</manifest>
EOF;
        } else {

            $api = array(
                /** Kraitnabo API */
                'kraitnabo.manifest.get' => array($this, 'NbGetManifest'),
                'kraitnabo.user.get' => array($this, 'NbGetUser'),
                'kraitnabo.stat.get' => array($this, 'NbGetStat'),
                'kraitnabo.post.new' => array($this, 'NbNewPost'),
                'kraitnabo.post.edit' => array($this, 'NbEditPost'),
                'kraitnabo.post.get' => array($this, 'NbGetPost'),
                'kraitnabo.post.delete' => array($this, 'NbDeletePost'),
                'kraitnabo.post.field' => array($this, 'NbFieldPost'),
                'kraitnabo.posts.get' => array($this, 'NbGetPosts'),
                'kraitnabo.page.new' => array($this, 'NbNewPage'),
                'kraitnabo.page.edit' => array($this, 'NbEditPage'),
                'kraitnabo.page.get' => array($this, 'NbGetPage'),
                'kraitnabo.page.delete' => array($this, 'NbDeletePage'),
                'kraitnabo.pages.get' => array($this, 'NbGetPages'),
                'kraitnabo.comment.new' => array($this, 'NbNewComment'),
                'kraitnabo.comment.edit' => array($this, 'NbEditComment'),
                'kraitnabo.comment.delete' => array($this, 'NbDeleteComment'),
                'kraitnabo.comments.get' => array($this, 'NbGetComments'),
                'kraitnabo.category.new' => array($this, 'NbNewCategory'),
                'kraitnabo.category.edit' => array($this, 'NbEditCategory'),
                'kraitnabo.category.delete' => array($this, 'NbDeleteCategory'),
                'kraitnabo.categories.get' => array($this, 'NbGetCategories'),
                'kraitnabo.tags.get' => array($this, 'NbGetTags'),
                'kraitnabo.media.new' => array($this, 'NbNewMedia'),
                'kraitnabo.media.edit' => array($this, "NbEditMedia"),
                'kraitnabo.media.delete' => array($this, "NbDeleteMedia"),
                'kraitnabo.medias.get' => array($this, 'NbGetMedias'),
                'kraitnabo.medias.clear' => array($this, "NbClearMedias"),
                'kraitnabo.plugin.replace' => array($this, 'NbPluginReplace'),
                'kraitnabo.plugin.links' => array($this, 'NbPluginLinks'),
                'kraitnabo.plugin.dynamics' => array($this, 'NbPluginDynamics'),
                'kraitnabo.config.plugin' => array($this, 'NbConfigPlugin'),
                'kraitnabo.config.theme' => array($this, 'NbConfigTheme'),
                'kraitnabo.config.option' => array($this, 'NbConfigOption'),
                'kraitnabo.config.profile' => array($this, 'NbConfigProfile'),
                'kraitnabo.plugins.get' => array($this, 'NbGetPlugins'),
                'kraitnabo.plugins.set' => array($this, 'NbSetPlugins'),
                'kraitnabo.themes.get' => array($this, 'NbGetThemes'),
                'kraitnabo.themes.set' => array($this, 'NbSetThemes'),
                'kraitnabo.options.get' => array($this, 'NbGetOptions'),
                'kraitnabo.options.set' => array($this, 'NbSetOptions'),

                /** PingBack */
                'pingback.ping' => array($this, 'pingbackPing'),

                /** hook after */
                'hook.afterCall' => array($this, 'hookAfterCall'),
            );

            if (1 == $this->options->allowXmlRpc) {
                unset($api['pingback.ping']);
            }

            /** 直接把初始化放到这里 */
            new IXR_Server($api);
        }
    }
}