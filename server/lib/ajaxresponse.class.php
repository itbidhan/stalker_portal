<?php
/**
 * Prepare raw data to AJAX response.
 * 
 * @package stalker_portal
 * @author zhurbitsky@gmail.com
 */

class AjaxResponse
{
    
    protected $db;
    protected $stb;
    protected $page = 0;
    protected $load_last_page = false;
    protected $cur_page = 0;
    protected $selected_item = 0;
    protected $response = array(
                    'total_items'    => 0,
                    'max_page_items' => MAX_PAGE_ITEMS,
                    'selected_item'  => 0,
                    'cur_page'       => 0,
                    'data'           => array(),
                );
    
    protected $abc = array();
    protected $months = array();
    protected $all_title = '';
    protected $no_ch_info = '';
    
    protected function __construct(){
        
        $this->db  = Mysql::getInstance();
        $this->stb = Stb::getInstance();
        
        $this->abc = System::word('all_abc');
        $this->months =  System::word('all_months');
        $this->all_title =  System::word('all_title');
        $this->no_ch_info =  System::word('no_ch_info');
        
        $this->page = @intval($_REQUEST['p']);
        
        if ($this->page == 0){
            $this->load_last_page = true;
        }
        
        if ($this->page > 0){
            $this->page--;
        }
    }
    
    /**
     * Append data to response.
     *
     * @param string $key
     * @param mixed $value
     */
    protected function setResponse($key, $value){
        $this->response[$key] = $value;
    }
    
    /**
     * Add main response fields.
     *
     * @param Mysql $query
     */
    protected function setResponseData(Mysql $query){
        
        $query_rows = clone $query;
        
        $this->setResponse('total_items', $query_rows->nolimit()->nogroupby()->noorderby()->count()->get()->counter());
        $this->setResponse('cur_page', $this->cur_page);
        $this->setResponse('selected_item', $this->selected_item);
        $this->setResponse('data', $query->get()->all());
    }
    
    /**
     * Apply callback on responce.
     *
     * @param string $callback
     * @return array
     */
    protected function getResponse($callback = ''){
        
        if ($callback && is_callable(array($this, $callback))){
            return call_user_func(array($this, $callback));
        }
        
        return $this->response;
    }
    
    /**
     * Return image dir using image id.
     *
     * @param int $id
     * @return string
     */
    protected function getImgUri($id){
    
        $dir_name = ceil($id/FILES_IN_DIR);
        $dir_path = IMG_URI.$dir_name;
        $dir_path .= '/'.$id.'.jpg';
        return $dir_path;
    }
    
    /**
     * Main claim method.
     *
     * @param string $media_type
     */
    protected function setClaimGlobal($media_type){
        
        $id   = intval($_REQUEST['id']);
        $type = $_REQUEST['real_type'];
        
        $this->db->insert('media_claims_log',
                          array(
                              'media_type' => $media_type,
                              'media_id'   => $id,
                              'type'       => $type,
                              'uid'        => $this->stb->id,
                              'added'      => 'NOW()'
                          ));
                          
        $total_media_claims = $this->db->from('media_claims')->where(array('media_type' => $media_type, 'media_id' => $id))->get()->first();
        
        $sound_counter = 0;
        $video_counter = 0;
        
        if ($type == 'video'){
            $video_counter++;
        }else{
            $sound_counter++;
        }
        
        if (!empty($total_media_claims)){
            $this->db->update('media_claims',
                              array(
                                  'sound_counter' => $total_media_claims['sound_counter'] + $sound_counter,
                                  'video_counter' => $total_media_claims['video_counter'] + $video_counter,
                              ),
                              array(
                                  'media_type' => $media_type,
                                  'media_id'   => $id
                              ));
        }else{
            $this->db->insert('media_claims',
                              array(
                                  'sound_counter' => $sound_counter,
                                  'video_counter' => $video_counter,
                                  'media_type'    => $media_type,
                                  'media_id'      => $id
                              ));
        }
        
        $total_daily_claims = $this->db->from('daily_media_claims')->where(array('date' => 'CURDATE()'))->get()->first();
        
        if (!empty($total_daily_claims)){
            $this->db->update('daily_media_claims',
                              array(
                                  $media_type.'_sound' => $total_daily_claims[$media_type.'_sound'] + $sound_counter,
                                  $media_type.'_video' => $total_daily_claims[$media_type.'_video'] + $video_counter
                              ),
                              array('date' => 'CURDATE()'));
        }else{
            $this->db->insert('daily_media_claims',
                              array(
                                  $media_type.'_sound' => $sound_counter,
                                  $media_type.'_video' => $video_counter,
                                  'date'               => 'CURDATE()'
                              ));
        }
    }
}

?>