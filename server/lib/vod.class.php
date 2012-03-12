<?php
/**
 * Main VOD class.
 *
 * @package stalker_portal
 * @author zhurbitsky@gmail.com
 */

class Vod extends AjaxResponse
{
    private static $instance = NULL;

    public static function getInstance()
    {
        if (self::$instance == NULL) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function __construct()
    {
        parent::__construct();
    }

    public function createLink()
    {

        preg_match("/\/media\/(\d+).mpg(.*)/", $_REQUEST['cmd'], $tmp_arr);

        $media_id = $tmp_arr[1];
        $params = $tmp_arr[2];

        $forced_storage = $_REQUEST['forced_storage'];

        /*$master = new VideoMaster();

       try {
           $res = $master->play($media_id, intval($_REQUEST['series']), true, $forced_storage);
       }catch (Exception $e){
           trigger_error($e->getMessage());
       }

       $res['cmd'] = $res['cmd'].$params;

       var_dump($res);

       return $res;*/

        $link = $this->getLinkByVideoId($media_id, intval($_REQUEST['series']), $forced_storage);

        $link['cmd'] = $link['cmd'] . $params;

        var_dump($link);

        return $link;
    }

    public function getLinkByVideoId($video_id, $series = 0, $forced_storage = "")
    {

        $video_id = intval($video_id);

        $master = new VideoMaster();

        try {
            $res = $master->play($video_id, intval($series), true, $forced_storage);
        } catch (Exception $e) {
            trigger_error($e->getMessage());
        }

        return $res;
    }

    public function getUrlByVideoId($video_id, $series = 0, $forced_storage = "")
    {

        $video = Video::getById($video_id);

        if (empty($video)) {
            throw new Exception("Video not found");
        }

        if (!empty($video['rtsp_url'])) {
            return $video['rtsp_url'];
        }

        $link = $this->getLinkByVideoId($video_id, $series, $forced_storage);

        if (empty($link['cmd'])) {
            throw new Exception("Obtaining url failed");
        }

        return $link['cmd'];
    }

    public function delLink()
    {

        $item = $_REQUEST['item'];

        if (preg_match("/\/(\w+)$/", $item, $tmp_arr)) {

            $key = $tmp_arr[1];

            var_dump($tmp_arr, strlen($key));

            if (strlen($key) != 32) {
                return false;
            }

            return Cache::getInstance()->del($key);
        }

        return false;
    }

    public function getMediaCats()
    {

        return $this->db->get('media_category')->all();

    }

    public function setVote()
    {

        if ($_REQUEST['vote'] == 'good') {
            $good = 1;
            $bad = 0;
        } else {
            $good = 0;
            $bad = 1;
        }

        $type = $_REQUEST['type'];

        $this->db->insert('vclub_vote',
            array(
                'media_id' => intval($_REQUEST['media_id']),
                'uid' => $this->stb->id,
                'vote_type' => $type,
                'good' => $good,
                'bad' => $bad,
                'added' => 'NOW()'
            ));

        //$video = $this->db->getFirstData('video', array('id' => intval($_REQUEST['media_id'])));
        $video = $this->db->from('video')->where(array('id' => intval($_REQUEST['media_id'])))->get()->first();

        $this->db->update('video',
            array(
                'vote_' . $type . '_good' => $video['vote_' . $type . '_good'] + $good,
                'vote_' . $type . '_bad' => $video['vote_' . $type . '_bad'] + $bad,
            ),
            array('id' => intval($_REQUEST['media_id'])));

        return true;
    }

    public function setPlayed()
    {

        $video_id = intval($_REQUEST['video_id']);
        $storage_id = intval($_REQUEST['storage_id']);

        if (date("j") <= 15) {
            $field_name = 'count_first_0_5';
        } else {
            $field_name = 'count_second_0_5';
        }

        //$video = $this->db->getFirstData('video', array('id' => $video_id));
        $video = $this->db->from('video')->where(array('id' => $video_id))->get()->first();

        $this->db->update('video',
            array(
                $field_name => $video[$field_name] + 1,
                'count' => $video['count'] + 1,
                'last_played' => 'NOW()'
            ),
            array('id' => $video_id));

        $this->db->insert('played_video',
            array(
                'video_id' => $video_id,
                'uid' => $this->stb->id,
                'storage' => $storage_id,
                'playtime' => 'NOW()'
            ));

        $this->db->update('users',
            array('time_last_play_video' => 'NOW()'),
            array('id' => $this->stb->id));

        //$today_record = $this->db->getFirstData('daily_played_video', array('date' => 'CURDATE()'));
        $today_record = $this->db->from('daily_played_video')->where(array('date' => date('Y-m-d')))->get()->first();

        if (empty($today_record)) {

            $this->db->insert('daily_played_video',
                array(
                    'count' => 1,
                    'date' => date('Y-m-d')
                ));

        } else {

            $this->db->update('daily_played_video',
                array(
                    'count' => $today_record['count'] + 1,
                    'date' => date('Y-m-d')
                ),
                array(
                    'id' => $today_record['id']
                ));

        }

        /*$played_video = $this->db->getData('stb_played_video',
        array(
            'uid' => $this->stb->id,
            'video_id' => $video_id
        ));*/
        $played_video = $this->db->from('stb_played_video')
            ->where(array(
            'uid' => $this->stb->id,
            'video_id' => $video_id
        ))
            ->get()
            ->all();

        if (empty($played_video)) {

            $this->db->insert('stb_played_video',
                array(
                    'uid' => $this->stb->id,
                    'video_id' => $video_id,
                    'playtime' => 'NOW()'
                ));

        } else {

            $this->db->update('stb_played_video',
                array('playtime' => 'NOW()'),
                array(
                    'uid' => $this->stb->id,
                    'video_id' => $video_id
                ));

        }

        return true;
    }

    public function setFav()
    {

        $new_id = intval($_REQUEST['video_id']);

        $favorites = $this->getFav();

        if ($favorites === null) {
            $favorites = array($new_id);
        } else {
            $favorites[] = $new_id;
        }

        return $this->saveFav($favorites, $this->stb->id);

        /*if ($fav_video === null){
            $this->db->insert('fav_vclub',
                               array(
                                    'uid'       => $this->stb->id,
                                    'fav_video' => serialize(array($new_id)),
                                    'addtime'   => 'NOW()'
                               ));
             return true;                      
        }
        
        if (!in_array($new_id, $fav_video)){
            
            $fav_video[] = $new_id;
            $fav_video_s = serialize($fav_video);
            
            $this->db->update('fav_vclub',
                               array(
                                    'fav_video' => $fav_video_s,
                                    'edittime'  => 'NOW()'),
                               array('uid' => $this->stb->id));
            
        }
        
        return true;*/
    }

    public function saveFav(array $fav_array, $uid)
    {

        if (empty($uid)) {
            return false;
        }

        $fav_videos_str = serialize($fav_array);

        $fav_video = $this->getFav();

        if ($fav_video === null) {
            return $this->db->insert('fav_vclub',
                array(
                    'uid' => $uid,
                    'fav_video' => $fav_videos_str,
                    'addtime' => 'NOW()'
                ))->insert_id();
        } else {
            return $this->db->update('fav_vclub',
                array(
                    'fav_video' => $fav_videos_str,
                    'edittime' => 'NOW()'),
                array('uid' => $uid))->result();
        }
    }

    public function getFav()
    {

        return $this->getFavByUid($this->stb->id);

        /*$fav_video_arr = $this->db->from('fav_vclub')->where(array('uid' => $this->stb->id))->get()->first();

       if ($fav_video_arr === null){
           return null;
       }

       if (empty($fav_video_arr)){
           return array();
       }

       $fav_video = unserialize($fav_video_arr['fav_video']);

       if (!is_array($fav_video)){
           $fav_video = array();
       }

       return $fav_video;*/
    }

    public function getFavByUid($uid)
    {

        $uid = (int)$uid;

        $fav_video_arr = $this->db->from('fav_vclub')->where(array('uid' => $uid))->get()->first();

        if ($fav_video_arr === null) {
            return null;
        }

        if (empty($fav_video_arr)) {
            return array();
        }

        $fav_video = unserialize($fav_video_arr['fav_video']);

        if (!is_array($fav_video)) {
            $fav_video = array();
        }

        return $fav_video;
    }

    public function delFav()
    {

        $del_id = intval($_REQUEST['video_id']);

        $fav_video = $this->getFav();

        if (is_array($fav_video)) {

            if (in_array($del_id, $fav_video)) {

                unset($fav_video[array_search($del_id, $fav_video)]);

                $fav_video_s = serialize($fav_video);

                $this->db->update('fav_vclub',
                    array(
                        'fav_video' => $fav_video_s,
                        'edittime' => 'NOW()'
                    ),
                    array('uid' => $this->stb->id));

            }
        }

        return true;
    }

    public function setEnded()
    {
        $video_id = intval($_REQUEST['video_id']);

        $not_ended = $this->db->from('vclub_not_ended')
            ->where(array(
            'uid' => $this->stb->id,
            'video_id' => $video_id
        ))
            ->get()
            ->first();

        if (!empty($not_ended)){
            return Mysql::getInstance()->delete('vclub_not_ended', array('uid' => $this->stb->id, 'video_id' => $video_id))->result();
        }

        return true;
    }

    public function setNotEnded()
    {

        $video_id = intval($_REQUEST['video_id']);
        $series = intval($_REQUEST['series']);
        $end_time = intval($_REQUEST['end_time']);

        /*$not_ended = $this->db->getFirstData('vclub_not_ended',
        array(
             'uid' => $this->stb->id,
             'video_id' => $video_id
        ));*/
        $not_ended = $this->db->from('vclub_not_ended')
            ->where(array(
            'uid' => $this->stb->id,
            'video_id' => $video_id
        ))
            ->get()
            ->first();


        if (empty($not_ended)) {

            $this->db->insert('vclub_not_ended',
                array(
                    'uid' => $this->stb->id,
                    'video_id' => $video_id,
                    'series' => $series,
                    'end_time' => $end_time,
                    'added' => 'NOW()'
                ));

        } else {

            $this->db->update('vclub_not_ended',
                array(
                    'series' => $series,
                    'end_time' => $end_time,
                    'added' => 'NOW()'
                ),
                array(
                    'uid' => $this->stb->id,
                    'video_id' => $video_id
                ));

        }

        return true;
    }

    private function getData()
    {

        $offset = $this->page * self::max_page_items;

        //$where = array('status' => 1);
        $where = array();

        if (@$_REQUEST['hd']) {
            $where['hd'] = 1;
        } else {
            $where['hd<='] = 1;
        }

        /*if (!$this->stb->hd && Config::get('vclub_mag100_filter')){
            $where['for_sd_stb'] = 1;
        }*/

        if (!$this->stb->isModerator()) {
            $where['accessed'] = 1;

            $where['status'] = 1;

            if ($this->stb->hd) {
                $where['disable_for_hd_devices'] = 0;
            }
        } else {
            $where['status>='] = 1;
        }

        if (@$_REQUEST['years'] && @$_REQUEST['years'] !== '*') {
            $where['year'] = $_REQUEST['years'];
        }

        if (@$_REQUEST['category'] && @$_REQUEST['category'] !== '*') {
            $where['category_id'] = intval($_REQUEST['category']);
        }

        $like = array();

        if (@$_REQUEST['abc'] && @$_REQUEST['abc'] !== '*') {

            $letter = $_REQUEST['abc'];

            $like = array('video.name' => $letter . '%');
        }

        $where_genre = array();

        if (@$_REQUEST['genre'] && @$_REQUEST['genre'] !== '*' && $_REQUEST['category'] !== '*') {

            $genre = intval($_REQUEST['genre']);

            $where_genre['cat_genre_id_1'] = $genre;
            $where_genre['cat_genre_id_2'] = $genre;
            $where_genre['cat_genre_id_3'] = $genre;
            $where_genre['cat_genre_id_4'] = $genre;
        }

        if (@$_REQUEST['category'] == '*' && @$_REQUEST['genre'] !== '*') {

            $genre_title = $this->db->from('cat_genre')->where(array('id' => intval($_REQUEST['genre'])))->get()->first('title');

            $genres_ids = $this->db->from('cat_genre')->where(array('title' => $genre_title))->get()->all('id');
        }

        $search = array();

        if (!empty($_REQUEST['search'])) {

            $letters = $_REQUEST['search'];

            $search['video.name'] = '%' . $letters . '%';
            $search['o_name'] = '%' . $letters . '%';
            $search['actors'] = '%' . $letters . '%';
            $search['director'] = '%' . $letters . '%';
            $search['year'] = '%' . $letters . '%';
        }

        $data = $this->db
        //->select('video.*, screenshots.id as screenshot_id')
            ->select('video.*, (select group_concat(screenshots.id) from screenshots where media_id=video.id) as screenshots')
            ->from('video')
        //->join('screenshots', 'video.id', 'screenshots.media_id', 'LEFT')
            ->where($where)
            ->where($where_genre, 'OR ');

        if (!empty($genres_ids) && is_array($genres_ids)) {

            $data = $data->group_in(array(
                'cat_genre_id_1' => $genres_ids,
                'cat_genre_id_2' => $genres_ids,
                'cat_genre_id_3' => $genres_ids,
                'cat_genre_id_4' => $genres_ids,
            ), 'OR');
        }

        $data = $data->like($like)
            ->like($search, 'OR ')
        //->groupby('video.path')
            ->limit(self::max_page_items, $offset);

        return $data;
    }

    public function getOrderedList()
    {
        $fav = $this->getFav();

        $result = $this->getData();

        if (@$_REQUEST['sortby']) {
            $sortby = $_REQUEST['sortby'];

            if ($sortby == 'name') {
                $result = $result->orderby('video.name');
            } elseif ($sortby == 'added') {
                $result = $result->orderby('video.added', 'DESC');
            } elseif ($sortby == 'top') {
                $result->select('(count_first_0_5+count_second_0_5) as top')->orderby('top', 'DESC');
            } elseif ($sortby == 'last_ended') {
                $result = $result->orderby('vclub_not_ended.added', 'DESC');
            }

        } else {
            $result = $result->orderby('video.name');
        }

        if (@$_REQUEST['fav']) {
            $result = $result->in('video.id', $fav);
        }

        if (@$_REQUEST['hd']) {
            $result = $result->where(array('hd' => 1));
        }

        if (@$_REQUEST['not_ended']) {
            $result = $result->from('vclub_not_ended')
                ->select('vclub_not_ended.series as cur_series, vclub_not_ended.end_time as position')
                ->where('video.id=vclub_not_ended.video_id', 'AND ', null, -1)
                ->where(array('vclub_not_ended.uid' => $this->stb->id));
        }

        $this->setResponseData($result);

        return $this->getResponse('prepareData');
    }

    public function prepareData()
    {

        $fav = $this->getFav();

        for ($i = 0; $i < count($this->response['data']); $i++) {

            if ($this->response['data'][$i]['hd']) {
                $this->response['data'][$i]['sd'] = 0;
            } else {
                $this->response['data'][$i]['sd'] = 1;
            }

            /// TRANSLATORS: "%2$s" - original video name, "%1$s" - video name.
            $this->response['data'][$i]['name'] = sprintf(_('video_name_format'), $this->response['data'][$i]['name'], $this->response['data'][$i]['o_name']);

            $this->response['data'][$i]['hd'] = intval($this->response['data'][$i]['hd']);

            if ($this->response['data'][$i]['censored']) {
                $this->response['data'][$i]['lock'] = 1;
            } else {
                $this->response['data'][$i]['lock'] = 0;
            }

            if ($fav !== null && in_array($this->response['data'][$i]['id'], $fav)) {
                $this->response['data'][$i]['fav'] = 1;
            } else {
                $this->response['data'][$i]['fav'] = 0;
            }

            $this->response['data'][$i]['series'] = unserialize($this->response['data'][$i]['series']);

            if (!empty($this->response['data'][$i]['series'])) {
                $this->response['data'][$i]['position'] = 0;
            }

            //$this->response['data'][$i]['screenshot_uri'] = $this->getImgUri($this->response['data'][$i]['screenshot_id']);

            //var_dump('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!', $this->response['data'][$i]['screenshots']);

            if ($this->response['data'][$i]['screenshots'] === null) {
                $this->response['data'][$i]['screenshots'] = '0';
            }

            $screenshots = explode(",", $this->response['data'][$i]['screenshots']);

            $this->response['data'][$i]['screenshot_uri'] = $this->getImgUri($screenshots[0]);

            $this->response['data'][$i]['genres_str'] = $this->getGenresStrByItem($this->response['data'][$i]);

            if (!empty($this->response['data'][$i]['rtsp_url'])) {
                $this->response['data'][$i]['cmd'] = $this->response['data'][$i]['rtsp_url'];
            } else {
                $this->response['data'][$i]['cmd'] = '/media/' . $this->response['data'][$i]['id'] . '.mpg';
            }

            if (@$_REQUEST['sortby'] && @$_REQUEST['sortby'] == 'added') {
                $this->response['data'][$i] = array_merge($this->response['data'][$i], $this->getAddedArr($this->response['data'][$i]['added']));
            }
        }

        return $this->response;
    }

    private function getAddedArr($datetime)
    {

        $added_time = strtotime($datetime);

        $added_arr = array(
            //'str'       => '',
            //'bg_level'  => ''
        );

        $this_mm = date("m");
        $this_dd = date("d");
        $this_yy = date("Y");

        if ($added_time > mktime(0, 0, 0, $this_mm, $this_dd, $this_yy)) {
            //$added_arr['today'] = System::word('vod_today');
            $added_arr['today'] = _('today');
        } elseif ($added_time > mktime(0, 0, 0, $this_mm, $this_dd - 1, $this_yy)) {
            //$added_arr['yesterday'] = System::word('vod_yesterday');
            $added_arr['yesterday'] = _('yesterday');
        } elseif ($added_time > mktime(0, 0, 0, $this_mm, $this_dd - 7, $this_yy)) {
            //$added_arr['week_and_more'] = System::word('vod_last_week');
            $added_arr['week_and_more'] = _('last week');
        } else {
            $added_arr['week_and_more'] = $this->months[date("n", $added_time) - 1] . ' ' . date("Y", $added_time);
        }

        return $added_arr;
    }

    public function getCategories()
    {

        $categories = $this->db
            ->select('id, category_name as title, category_alias as alias')
            ->from("media_category")
            ->get()
            ->all();

        array_unshift($categories, array('id' => '*', 'title' => $this->all_title, 'alias' => '*'));

        $categories = array_map(function($item)
        {
            $item['title'] = _($item['title']);
            return $item;
        }, $categories);

        return $categories;
    }

    public function getGenresByCategoryAlias($cat_alias = '')
    {

        if (!$cat_alias) {
            $cat_alias = @$_REQUEST['cat_alias'];
        }

        $where = array();

        if ($cat_alias != '*') {
            $where['category_alias'] = $cat_alias;
        }

        $genres = $this->db
            ->select('id, title')
            ->from("cat_genre")
            ->where($where)
            ->groupby('title')
            ->orderby('title')
            ->get()
            ->all();

        array_unshift($genres, array('id' => '*', 'title' => '*'));

        $genres = array_map(function($item)
        {
            $item['title'] = _($item['title']);
            return $item;
        }, $genres);

        return $genres;
    }

    public function getYears()
    {

        $where = array('year>' => '1900');

        if (@$_REQUEST['category'] && @$_REQUEST['category'] !== '*') {
            $where['category_id'] = $_REQUEST['category'];
        }

        $years = $this->db
            ->select('year as id, year as title')
            ->from('video')
            ->where($where)
            ->groupby('year')
            ->orderby('year')
            ->get()
            ->all();

        array_unshift($years, array('id' => '*', 'title' => '*'));

        return $years;
    }

    public function getAbc()
    {

        $abc = array();

        foreach ($this->abc as $item) {
            $abc[] = array(
                'id' => $item,
                'title' => $item
            );
        }

        return $abc;
    }

    public function getGenresStrByItem($item)
    {

        return implode(', ', array_map(function($item)
        {
            return _($item);
        }, $this->db->from('cat_genre')->in('id', array($item['cat_genre_id_1'], $item['cat_genre_id_2'], $item['cat_genre_id_3'], $item['cat_genre_id_4']))->get()->all('title')));
    }

    public function setClaim()
    {

        return $this->setClaimGlobal('vclub');
    }
}

class VodLinkException extends Exception
{
}

?>