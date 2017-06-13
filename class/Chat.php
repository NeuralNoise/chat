<?php

class Chat {

    private $chaters = array();
    private $vkdata = array();
    private $config;
    private $db;

    function __construct($config){
        $this->config = $config;
        $this->db = new DB($config);
        $this->db->connect();
    }

    public function start($uid) {

        $message_type = 'system_start';
        $message_from = 0;
        $message_text = array('Попытка входа в чат');
        $message_targets = array($uid);
        $message_data = array();

        return array('type' => $message_type, 'from' => $message_from, 'targets' => $message_targets, 'text' => $message_text, 'data' => $message_data);
    }

    public function process($uid, $data) {

        if(!isset($data['type'])){
            return array();
        }

        $type = $data['type'];
        $text = '';
        $from = 0;
        $dialogs = array();
        $messages = array();

        if ($data['type'] == 'system_identify') {

            $this->checkForExistingSession($uid, $data['cookie']['mychatcookid']);

            $user_data = $this->db->selectRow('mychat_users', 'client_id="' . $data['cookie']['mychatcookid'] . '"');
            if (!$user_data) { // user does not exist
                $user_data = array(
                    'id' => 0,
                    'client_id' => $data['cookie']['mychatcookid'],
                    'mycookid' => $data['cookie']['mycookid'],
                    'name' => $this->config['monkeys'][array_rand($this->config['monkeys'])],
                    'image' => $this->config['uploadUrl'] . '/no-avatar.jpg',
                    'role' => 'user',
                    'detected' => 0,
                    'created' => 0
                );
            }

            $this->chaters[$uid] = array(
                'current_dialog_id' => 0,
                'partner_uid' => 0,
                'user' => $user_data,
                'partner' => array(),
                'vkdata' => array(),
            );

            if($this->chaters[$uid]['user']['id'] > 0){
                $dialogs = $this->getDialogs($this->chaters[$uid]['user']['id']);
                if (isset($dialogs[0])) {
                    $this->chaters[$uid]['current_dialog_id'] = $dialogs[0]['id'];
                    $this->chaters[$uid]['partner'] = $this->getPartnerFromDialog($this->chaters[$uid]['user']['id'], $dialogs[0]);
                    $this->chaters[$uid]['partner_uid'] = $this->getUidById($this->chaters[$uid]['partner']['id']);
                    $messages = $this->getDialogMessages($this->chaters[$uid]['current_dialog_id']);
                }
            }
            else{
                $this->chaters[$uid]['partner'] = $this->getPartnerAdmin($uid);
                $text = 'Задайте вопрос или поделитесь отзывом. Обычно мы отвечаем в течение пары минут (за исключением выходных и ночных часов).';
            }
        }

        ////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////

        if ($data['type'] == 'apply_vkdata') {
            //return array('targets' => array($uid), 'type' => 'system', 'from' => 0, 'text' => array(print_r($data, true)), 'data' => array());
            if ($this->chaters[$uid]['user']['role'] != 'admin') {
                $vkdata = $data['vkdata'];
                if ($this->chaters[$uid]['user']['id'] > 0) {
                    $this->db->update('mychat_users',
                        array(
                            'name' => $vkdata['fname'] . (!empty($vkdata['lname']) ? ' ' . $vkdata['lname'] : ''),
                            'image' => $vkdata['image'],
                            'detected' => 1
                        ),
                        'id = ' . $this->chaters[$uid]['user']['id']);
                }
                $this->chaters[$uid]['user']['name'] = $vkdata['fname'] . (!empty($vkdata['lname']) ? ' ' . $vkdata['lname'] : '');
                $this->chaters[$uid]['user']['image'] = $vkdata['image'];
                $this->chaters[$uid]['user']['detected'] = 1;
                $this->chaters[$uid]['vkdata'] = $vkdata;
                $this->vkdata[$this->chaters[$uid]['user']['mycookid']] = $vkdata;
            }

            $dialogs = $this->getDialogs($this->chaters[$uid]['user']['id']);
        }

        ////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////

        if ($data['type'] == 'load_dialog') {

            $this->chaters[$uid]['current_dialog_id'] = $data['dialog_id'];

            $dialog = $this->db->selectRow("mychat_dialogs", "id = " . $data['dialog_id']);
            $partner = $this->getPartnerFromDialog($this->chaters[$uid]['user']['id'], $dialog);
            $partner_uid = $this->getUidById($partner['id']);

            $this->chaters[$uid]['partner'] = $partner;
            $this->chaters[$uid]['partner_uid'] = $partner_uid;

//            if(isset($this->chaters[$partner_uid])) { // user online
//                $this->chaters[$partner_uid]['partner_uid'] = $uid;
//                $this->chaters[$partner_uid]['partner'] = $this->chaters[$uid]['user'];
//            }

            $dialogs = $this->getDialogs($this->chaters[$uid]['user']['id']);
            $messages = $this->getDialogMessages($this->chaters[$uid]['current_dialog_id']);
        }

        ////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////

        if ($data['type'] == 'text') {

            if (empty($this->chaters[$uid]['partner']) && $this->chaters[$uid]['current_dialog_id'] == 0) {
                return $this->chatEmptyMessage($uid);
            }

            if ($this->chaters[$uid]['user']['id'] == 0) {
                $this->chaters[$uid]['user']['id'] = $this->saveChater($this->chaters[$uid]['user']);
            }

            if ($this->chaters[$uid]['current_dialog_id'] == 0) {
                $this->chaters[$uid]['current_dialog_id'] = $this->newDialog($this->chaters[$uid]['user']['id'], $this->chaters[$uid]['partner']['id']);
            }

            $this->saveMessage($uid, $data['text']);

            $this->chaters[$uid]['partner_uid'] = $this->getUidById($this->chaters[$uid]['partner']['id']);

            $from = $this->chaters[$uid]['user']['id'];
            $text = $data['text'];
            $dialogs = $this->getDialogs($this->chaters[$uid]['user']['id']);
        }

        return $this->sendMessage($uid, $type, $from, $text, $dialogs, $messages);
    }

    public function sendMessage($uid, $type, $from, $text = '', $dialogs = array(), $messages = array()) {

        $message_type = $type;
        $message_from = $from;
        $message_targets = array();
        $message_text = array();
        $message_data = array();

        ////////////////////////////////////////////////////////////////////////////////////////////////////

        $message_targets[0] = $uid;
        $message_text[0] = $text;
        $message_data[0]['partner_uid'] = $this->chaters[$uid]['partner_uid'];
        $message_data[0]['current_dialog_id'] = $this->chaters[$uid]['current_dialog_id'];
        $message_data[0]['user'] = $this->chaters[$uid]['user'];
        $message_data[0]['partner'] = $this->chaters[$uid]['partner'];
        $message_data[0]['partner_data'] = $this->getVkData($uid);
        $message_data[0]['partner_file'] = $this->getVkFile($uid);
        $message_data[0]['dialogs'] = $dialogs;
        $message_data[0]['messages'] = $messages;

        ////////////////////////////////////////////////////////////////////////////////////////////////////

        if($this->chaters[$uid]['user']['id'] > 0 && !empty($this->chaters[$uid]['partner'])){
            $partner_uid = $this->getUidById($this->chaters[$uid]['partner']['id']);
            if($partner_uid){

                if($type == 'text') {
                    $partner_available = $this->checkPartnerIsAvailable($uid, $partner_uid);
                    if ($partner_available) {
                        //$this->chaters[$partner_uid]['current_dialog_id'] = $this->chaters[$uid]['current_dialog_id'];
                        $message_text[1] = $text;
                    }
                }

                $message_targets[1] = $partner_uid;
                $message_data[1]['partner_uid'] = $uid;
                $message_data[1]['current_dialog_id'] = $this->chaters[$partner_uid]['current_dialog_id'];
                $message_data[1]['user'] = $this->chaters[$partner_uid]['user'];
                $message_data[1]['partner'] = $this->chaters[$partner_uid]['partner'];
                $message_data[1]['partner_data'] = $this->getVkData($partner_uid);
                $message_data[1]['partner_file'] = $this->getVkFile($partner_uid);
                $message_data[1]['dialogs'] = $this->getDialogs($this->chaters[$partner_uid]['user']['id']);
            }
        }

        ////////////////////////////////////////////////////////////////////////////////////////////////////

        return array('type' => $message_type, 'from' => $message_from, 'targets' => $message_targets, 'text' => $message_text, 'data' => $message_data);

    }

    public function removeFromChat($uid){
        if(isset($this->chaters[$uid])) {

            $message_type = 'chater_removed';
            $message_from = 0;
            $message_targets = array();
            $message_text = array();
            $message_data = array();

            $user_id = $this->chaters[$uid]['user']['id'];
            $dialogs = $this->getDialogs($user_id);
            unset($this->chaters[$uid]);

            foreach($dialogs as $dialog) {

                if ($user_id == $dialog['user_id']) {
                    $partner_uid = $this->getUidById($dialog['partner_id']);
                }
                if ($user_id == $dialog['partner_id']) {
                    $partner_uid = $this->getUidById($dialog['user_id']);
                }

                if (isset($this->chaters[$partner_uid])) { // user online

                    $this->chaters[$partner_uid]['partner_uid'] = 0;

                    $message_targets[] = $partner_uid;
                    $message_data[] = array(
                        'partner_uid'  => $this->chaters[$partner_uid]['partner_uid'],
                        'user'         => $this->chaters[$partner_uid]['user'],
                        'partner'      => $this->chaters[$partner_uid]['partner'],
                        'partner_data' => $this->getVkData($partner_uid),
                        'partner_file' => $this->getVkFile($partner_uid),
                        'dialogs'      => $this->getDialogs($this->chaters[$partner_uid]['user']['id'])
                    );
                }
            }

            //Console::write('removeFromChat() DUMP: <pre>'.print_r($message_data, true).'</pre>');

            return array('type' => $message_type, 'from' => $message_from, 'targets' => $message_targets, 'text' => $message_text, 'data' => $message_data);
        }
    }

    public function getVkData($uid) {
        $vkdata = array();
        if($this->chaters[$uid]['user']['role'] == 'admin') {
            if(!empty($this->chaters[$uid]['partner'])){
                $partner_uid = $this->chaters[$uid]['partner_uid'];
                if($partner_uid) {
                    if (isset($this->chaters[$partner_uid]['vkdata']) && !empty($this->chaters[$partner_uid]['vkdata'])) {
                        $vkdata = $this->chaters[$partner_uid]['vkdata'];
                    }
                }
                else{
                    if($this->chaters[$uid]['partner']['detected'] == 1){
                        if(isset($this->vkdata[$this->chaters[$uid]['partner']['mycookid']])){
                            $vkdata = $this->vkdata[$this->chaters[$uid]['partner']['mycookid']];
                        }
                        else{
                            $vkdata = $this->getVkDataFromDb($this->chaters[$uid]['partner']['mycookid']);
                            $this->vkdata[$this->chaters[$uid]['partner']['mycookid']] = $vkdata;
                        }
                    }
                }
            }
        }
        return $vkdata;
    }

    public function getVkDataFromDb($mycookid) {
        $vkdata = array();
        $json = file_get_contents('http://new.wantresult.ru/newvktrackerru.php?mycookid='.$mycookid);
        $json_decoded = json_decode($json, true);
        if(json_last_error() == JSON_ERROR_NONE) {
            $vkdata = $json_decoded;
        }
        return $vkdata;
    }

    public function getVkFile($uid) {

        $vkdata = $this->getVkData($uid);
        $user_file = array();
        if(!empty($vkdata)) {

            $user_file[0] = array(
                'label' => 'Первый визит',
                'value' => $vkdata['visits'][0]['data'],
            );
            $user_file[1] = array(
                'label' => 'Последний визит',
                'value' => $vkdata['visits'][1]['data'],
            );

            $user_file[2] = array(
                'label' => 'Общее время',
                'value' => gmdate("H:i:s", $vkdata['statistics']['seconds']),
            );

            $user_file[3] = array(
                'label' => 'Страница идентификации',
                'value' => $vkdata['visits'][0]['link'],
            );
        }
        return $user_file;
    }

    public function chatEmptyMessage($uid) {
        $message_text = array(
            0 => 'В чате никого нет'
        );
        $message_type = 'system';
        $message_from = array(
            0 => 0
        );
        $message_targets = array(
            0 => $uid
        );
        $message_data = array();
        return array('targets' => $message_targets,'type' => $message_type,  'from' => $message_from, 'text' => $message_text, 'data' => $message_data);
    }

    public function checkForExistingSession($uid, $client_id) {

        foreach($this->chaters as $chater_uid => $chater){
            if(isset($chater['user']) && $chater['user']['client_id'] == $client_id) {
                if ($chater_uid != $uid) {
                    $partner_uid = $this->chaters[$chater_uid]['partner_uid'];
                    if($partner_uid) {
                        $partner_available = $this->checkPartnerIsAvailable($chater_uid, $partner_uid);
                        if ($partner_available) {
                            $this->chaters[$partner_uid]['partner_uid'] = $uid;
                        }
                    }
                    unset($this->chaters[$chater_uid]);
                    break;
                }
            }
        }
    }

    public function checkPartnerIsAvailable($uid, $partner_uid) {

        if($this->chaters[$partner_uid]['current_dialog_id'] == $this->chaters[$uid]['current_dialog_id']) {
            return 1;
        }
        elseif((empty($this->chaters[$partner_uid]['partner']) || $this->chaters[$partner_uid]['partner_uid'] == 0) && $this->chaters[$partner_uid]['current_dialog_id'] == 0) {
            $this->chaters[$partner_uid]['partner_uid'] = $uid;
            $this->chaters[$partner_uid]['partner'] = $this->chaters[$uid]['user'];
            $this->chaters[$partner_uid]['current_dialog_id'] = $this->chaters[$uid]['current_dialog_id'];
            return 1;
        }
        elseif($this->chaters[$partner_uid]['partner_uid'] == $uid){
            return 1;
        }
        return 0;
    }

    public function isAdminOnline() {
        foreach ($this->chaters as $chater_uid => $chater) {
            if(isset($chater['user'])) {
                if ($chater['user']['role'] == 'admin') {
                    return true;
                }
            }
        }
        return false;
    }

    public function getPartnerAdmin($uid) {

        $partner_data = array();

        foreach ($this->chaters as $chater_uid => $chater) {
            if(isset($chater['user'])) {
                if ($chater['user']['role'] == 'admin') {
                    $partner_data = $chater['user'];
                    break;
                }
            }
        }

        if(empty($partner_data)) {
            $partner_data = $this->db->selectRow("mychat_users", "role = 'admin'");
        }

        return $partner_data;
    }

    public function getPartnerFromDialog($user_id, $dialog) {

        $partner_data = array();

        if ($user_id == $dialog['user_id']) {
            $partner_data = $this->db->selectRow("mychat_users", "id = " . $dialog['partner_id']);
        }
        if ($user_id == $dialog['partner_id']) {
            $partner_data = $this->db->selectRow("mychat_users", "id = " . $dialog['user_id']);
        }

        return $partner_data;
    }

    public function isOnline($uid){
        if(!isset($this->chaters[$uid])) {
            return false;
        }
        return true;
    }

    public function getUidById($user_id){
        foreach ($this->chaters as $chater_uid => $chater) {
            if(isset($chater['user'])) {
                if ($chater['user']['id'] == $user_id) {
                    return $chater_uid;
                }
            }
        }
        return 0;
    }

    public function saveChater($data){
        unset($data['id']);
        $data['created'] = time();
        $user_id = $this->db->insert('mychat_users', $data);
        return $user_id;
    }

    public function saveMessage($uid, $body){

        $data = array(
            'dialog_id' => $this->chaters[$uid]['current_dialog_id'],
            'user_id' => $this->chaters[$uid]['user']['id'],
            'type' => $this->chaters[$uid]['user']['role'],
            'body' => $body,
            'created' => microtime(true),
        );
        $message_id = $this->db->insert('mychat_messages', $data);
        return $message_id;
    }

    public function newDialog($user_id, $partner_id){
        $data = array(
            'user_id' => $user_id,
            'partner_id' => $partner_id,
            'created' => time(),
        );
        $res = $this->db->insert('mychat_dialogs', $data);
        return $res;
    }

    public function getDialogs($user_id){

        $uid = $this->getUidById($user_id);

        $dialogs = $this->db->select("SELECT DISTINCT d.*, 
                                        m2.body last_message_body, 
                                        m1.created first_message_created,
                                        m2.created last_message_created,
                                        ( SELECT COUNT(*) FROM mychat_messages WHERE dialog_id = d.id )  messages_count
                                      FROM mychat_dialogs d
                                      LEFT JOIN mychat_messages m1 ON m1.dialog_id = d.id
                                      LEFT JOIN mychat_messages m2 ON m2.dialog_id = d.id
                                      WHERE (d.user_id = " . $user_id . " || d.partner_id = " . $user_id . ") 
                                      AND m1.created = ( SELECT MIN(created) FROM mychat_messages WHERE dialog_id = d.id ) 
                                      AND m2.created = ( SELECT MAX(created) FROM mychat_messages WHERE dialog_id = d.id ) 
                                      ORDER BY m2.created DESC");

        if(!empty($dialogs)) {
            foreach ($dialogs as $index => $dialog) {

                $dialogs[$index]['active'] = 0;
                if($uid > 0) {
                    if($this->chaters[$uid]['current_dialog_id'] == 0) {
                        if ($index == 0) {
                            $dialogs[$index]['active'] = 1;
                        }
                    }
                    else {
                        if ($dialog['id'] == $this->chaters[$uid]['current_dialog_id']) {
                            $dialogs[$index]['active'] = 1;
                        }
                    }
                }
                else{
                    if ($index == 0) {
                        $dialogs[$index]['active'] = 1;
                    }
                }

                if ($user_id == $dialog['user_id']) {
                    $partner_data = $this->db->selectRow("mychat_users", "id = " . $dialog['partner_id']);
                }
                if ($user_id == $dialog['partner_id']) {
                    $partner_data = $this->db->selectRow("mychat_users", "id = " . $dialog['user_id']);
                }
                if ($partner_data) {
                    $partner_uid = $this->getUidById($partner_data['id']);
                    $dialogs[$index]['partner_state'] = $partner_uid;
                    $dialogs[$index]['partner_user_id'] = $partner_data['id'];
                    $dialogs[$index]['partner_client_id'] = $partner_data['client_id'];
                    $dialogs[$index]['partner_name'] = $partner_data['name'];
                    $dialogs[$index]['partner_avatar'] = $partner_data['image'];
                    $dialogs[$index]['partner_role'] = $partner_data['role'];

                    if($partner_data['role'] != 'admin'){
                        if(!$partner_uid) {
                            if($partner_data['detected'] == 1){
                                if(!isset($this->vkdata[$partner_data['mycookid']])){
                                    $this->vkdata[$partner_data['mycookid']] = $this->getVkDataFromDb($partner_data['mycookid']);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $dialogs;
    }

    public function getDialogMessages($dialog_id){
        $messages = $this->db->select("SELECT *  FROM mychat_messages WHERE dialog_id = ".$dialog_id." ORDER BY created ASC");
        return $messages;
    }

	function __destruct() {

    }
}
