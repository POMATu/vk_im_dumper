<?php
##################################################
#     Originally by bafoed. Forked by POMATu     #
##################################################

set_time_limit(0);
@ini_set('max_execution_time', '0');
error_reporting(-1);
ini_set('display_errors', 'On');


$myid        = ''; // свой айди
$token       = ''; // Получить тут: http://oauth.vkontakte.ru/authorize?client_id=2626107&scope=16383&redirect_uri=http://oauth.vk.com/blank.html&response_type=token

$myname = null;
$mysurname = null;
$myphoto = null;
$workingdir = null;

/* ############### */
$messages = array();
function API($method, $sett)
{
	usleep(200000);
    global $token;
	
    $ch = curl_init('https://api.vkontakte.ru/method/' . $method . '.json?' . http_build_query($sett) . '&access_token=' . $token);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);

}



function destroy_dir($dir) { 
    if (!is_dir($dir) || is_link($dir)) return unlink($dir); 
        foreach (scandir($dir) as $file) { 
            if ($file == '.' || $file == '..') continue; 
            if (!destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) { 
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777); 
                if (!destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) return false; 
            }; 
        } 
        return rmdir($dir); 
    } 

function copyr($source, $dest)
{
    // recursive function to copy
    // all subdirectories and contents:
    if(is_dir($source)) {
        $dir_handle=opendir($source);
        $sourcefolder = basename($source);
        mkdir($dest."/".$sourcefolder);
        while($file=readdir($dir_handle)){
            if($file!="." && $file!=".."){
                if(is_dir($source."/".$file)){
                    self::copyr($source."/".$file, $dest."/".$sourcefolder);
                } else {
                    copy($source."/".$file, $dest."/".$file);
                }
            }
        }
        closedir($dir_handle);
    } else {
        // can also handle simple copy commands
        copy($source, $dest);
    }
}

function prepare() {

	global $myid, $zip;
	global $myname,$mysurname,$myphoto,$workingdir;
	
	$info    = API('getProfiles', array(
        'uid' => $myid,
        'fields' => 'photo'
    ));
    $myname    = $info['response'][0]['first_name']; // 
    $mysurname = $info['response'][0]['last_name']; // -- Граббинг инфы о себе
    $myphoto   = $info['response'][0]['photo']; // //
	
	
	$workingdir = iconv("UTF-8", "WINDOWS-1251",$myname."_".$mysurname."_".$myid);
	if (is_dir($workingdir)) {
	destroy_dir($workingdir); 
	}
	mkdir($workingdir);
	mkdir($workingdir."\\dialog_files");
	copyr("dialog_files", $workingdir."\\dialog_files");
	
	
}

function dump($id)
{
    global $myid, $zip;
	global $myname,$mysurname,$myphoto,$workingdir;
	
	
	
    $info      = API('getProfiles', array(
        'uid' => $id,
        'fields' => 'photo'
    ));
	if(empty($info['response'])) { 
		die('<pre>Error</pre>');
	}
	
    $s_name    = $info['response'][0]['first_name']; // 
    $s_surname = $info['response'][0]['last_name']; // -- Граббинг инфы о собеседнике
    $s_photo   = $info['response'][0]['photo']; // //
    $s_tabname = $s_name . " " . $s_surname;
    
	$info = null;

	
	$name = $myname;
	$surname = $mysurname;
	$photo = $myphoto;
    
    # Let`s get is started!
    $page  = API('messages.getHistory', array(
        'uid' => $id,
        'count' => '1'
    ));
    $count = (int) $page['response'][0]; // Количество сообщений с данным человеком
    
    $first      = $count % 100; // API позволяет получать не больше 100 сообщений за раз, сначала получим те, которые не получить при count = 100
    $iterations = ($count - $first) / 100; // Сколько раз получать по 100 сообщений
    
    $page = API('messages.getHistory', array(
        'uid' => $id,
        'count' => $first,
        'offset' => (string) ($iterations * 100)
    ));
    unset($page['response'][0]); // Количество сообшений мы уже знаем
    $messages = array_reverse(array_values($page['response'])); // ВК отдает сообщения сверху вниз
    
    
    for ($i = $iterations; $i >= 0; $i--) {
	echo "+";
        $page = API('messages.getHistory', array(
            'uid' => $id,
            'count' => 100,
            'offset' => (string) ($i * 100)
        ));
        unset($page['response'][0]);
        $messages = array_merge($messages, array_reverse(array_values($page['response'])));
    }
    echo "\n";
	
    $page  = str_replace('%username%', $s_tabname, file_get_contents('head.tpl')); // Замена названия на вкладке
    $lines = array(); // Линии файла упрощенного стиля
    
    foreach ($messages as $msg) { // Обрабатываем каждое сообщение
        if ($msg['from_id'] == $myid) {
            $tname  = "$name $surname";
            $tphoto = $photo;
            $tid    = $myid;
        } else {
            $tname  = "$s_name $s_surname";
            $tphoto = $s_photo;
            $tid    = $id;
        }
        
        
        $body = $msg['body'];
        $date = (string) ((int) $msg['date'] + 3600);
        $time = date("d.m.Y H:i", $date);
        
        $lines[] = "$tname ($time): $body";
        $page .= <<<EOF
 <tr class="im_in">
      <td class="im_log_act">
        <div class="im_log_check_wrap"><div class="im_log_check"></div></div>
      </td>
      <td class="im_log_author"><div class="im_log_author_chat_thumb"><a href="http://vkontakte.ru/id$tid"><img src="$tphoto" class="im_log_author_chat_thumb"></a></div></td>
      <td class="im_log_body"><div class="wrapped"><div class="im_log_author_chat_name"><a href="http://vkontakte.ru/id$tid" class="mem_link">$tname</a></div>$body</div></td>
      <td class="im_log_date"><a class="im_date_link">$time</a><input type="hidden" value="$date"></td>
      <td class="im_log_rspacer"></td>
    </tr>
EOF;
    }
    $page .= file_get_contents('foot.tpl');
    
    
    file_put_contents("$workingdir\\".iconv("UTF-8", "WINDOWS-1251","$s_name $s_surname".".id".$id) . '.htm', iconv('utf-8', 'windows-1251//IGNORE', $page));
    file_put_contents("$workingdir\\".iconv("UTF-8", "WINDOWS-1251","$s_name $s_surname".".id".$id) . '.txt', iconv('utf-8', 'windows-1251//IGNORE', implode("\r\n", $lines)));
 
}

function dump_chat($chatid)
{

    global $myid, $zip;
	global $myname,$mysurname,$myphoto,$workingdir;
	
	$name = $myname;
	$surname = $mysurname;
	$photo = $myphoto;
    
	 $titleresponse = API('messages.getChat', array(
            'chat_id' => $chatid,
            'count' => 1
        ));
		
	 $title = $titleresponse['response']['title'];
	
	
    # Let`s get is started!
    $page  = API('messages.getHistory', array(
        'chat_id' => $chatid,
        'count' => '1'
    ));
    $count = (int) $page['response'][0]; // Количество сообщений с данным человеком
    
    $first      = $count % 100; // API позволяет получать не больше 100 сообщений за раз, сначала получим те, которые не получить при count = 100
    $iterations = ($count - $first) / 100; // Сколько раз получать по 100 сообщений
	
    $page = API('messages.getHistory', array(
        'chat_id' => $chatid,
        'count' => $first,
        'offset' => (string) ($iterations * 100)
    ));
    unset($page['response'][0]); // Количество сообшений мы уже знаем
    $messages = array_reverse(array_values($page['response'])); // ВК отдает сообщения сверху вниз
    
    
    for ($i = $iterations; $i >= 0; $i--) {
	echo "+";
        $page = API('messages.getHistory', array(
            'chat_id' => $chatid,
            'count' => 100,
            'offset' => (string) ($i * 100)
        ));
        unset($page['response'][0]);
        $messages = array_merge($messages, array_reverse(array_values($page['response'])));
    }
     echo "\n";
	 
    $page  = str_replace('%username%', $title, file_get_contents('head.tpl')); // Замена названия на вкладке
    $lines = array(); // Линии файла упрощенного стиля
    
    foreach ($messages as $msg) { // Обрабатываем каждое сообщение
	 if ($msg['from_id'] == $myid) {
            $tname  = "$name $surname";
            $tphoto = $photo;
            $tid    = $myid;
        } else {
	
    $info      = API('getProfiles', array(
        'uid' => $msg['from_id'],
        'fields' => 'photo'
    ));
	if(empty($info['response'])) { 
		die('<pre>Error</pre>');
	}
	
	
	echo "*";
    $s_name    = $info['response'][0]['first_name']; // 
    $s_surname = $info['response'][0]['last_name']; // -- Граббинг инфы о собеседнике
    $s_photo   = $info['response'][0]['photo']; // //
    $s_tabname = $s_name . " " . $s_surname;
    
	
       
			
            $tname  = "$s_name $s_surname";
            $tphoto = $s_photo;
            $tid    = $msg['from_id'];
        }    
        
        $body = $msg['body'];
        $date = (string) ((int) $msg['date'] + 3600);
        $time = date("d.m.Y H:i", $date);
        
        $lines[] = "$tname ($time): $body";
        $page .= <<<EOF
 <tr class="im_in">
      <td class="im_log_act">
        <div class="im_log_check_wrap"><div class="im_log_check"></div></div>
      </td>
      <td class="im_log_author"><div class="im_log_author_chat_thumb"><a href="http://vkontakte.ru/id$tid"><img src="$tphoto" class="im_log_author_chat_thumb"></a></div></td>
      <td class="im_log_body"><div class="wrapped"><div class="im_log_author_chat_name"><a href="http://vkontakte.ru/id$tid" class="mem_link">$tname</a></div>$body</div></td>
      <td class="im_log_date"><a class="im_date_link">$time</a><input type="hidden" value="$date"></td>
      <td class="im_log_rspacer"></td>
    </tr>
EOF;
    }
	echo "\n";
    $page .= file_get_contents('foot.tpl');
    
    // 
    file_put_contents("$workingdir\\".iconv("UTF-8", "WINDOWS-1251", "беседа".$chatid.".".$title) . '.htm', iconv('utf-8', 'windows-1251//IGNORE', $page));
    file_put_contents("$workingdir\\".iconv("UTF-8", "WINDOWS-1251", "беседа".$chatid.".".$title). '.txt', iconv('utf-8', 'windows-1251//IGNORE', implode("\r\n", $lines)));

}

function iterate_dialogs($response) {
	foreach ($response['response'] as $resp) {
     
	   if (isset($resp['chat_id'])) {
				echo "chat".$resp['chat_id']."\n";
				dump_chat($resp['chat_id']);
		}	   
		else {
			if (isset($resp['uid'])) {
				echo "id".$resp['uid']."\n";
				dump($resp['uid']);
			
				}
		}
		
	  //
    }
}


	prepare();

	$response = API('messages.getDialogs', array( "count" => "1"));
	
	$count = (int) $response['response'][0]; 
	
	$first      = $count % 100; 
    $iterations = ($count - $first) / 100; 
	
	$response = API('messages.getDialogs', array( "count" => $first, "offset" => 0));
	unset($response['response'][0]);
	iterate_dialogs($response);
	unset($response);
	
	for ($i =$first; $i <= $iterations*100; $i=$i+100) {

	$response = API('messages.getDialogs', array( "count" => 100, "offset" => $i));	
	unset($response['response'][0]);
	iterate_dialogs($response);
	
	unset($response);
	}
	


echo '<pre>Completed.</pre>';