<?php

defined('is_running') or die('Not an entry point...');

gpPlugin::incl('SimpleBlogCommon.php','require_once');


class SimpleBlogComments extends SimpleBlogCommon{

	var $dir;
	var $cache = array();
	var $cache_mod = 0;

	function SimpleBlogComments(){
		global $page, $addonFolderName;

		$this->Init();
		$this->dir = $this->addonPathData.'/comments';
		$page->css_user[] = '/data/_addoncode/'.$addonFolderName.'/admin.css';
		//gpPlugin::css('admin.css'); //gpeasy 4.0+

		$this->GetCache();

		$cmd = common::GetCommand();
		switch( $cmd ){
			case 'delete_comment':
				$this->DeleteComment();
			break;
		}

		$this->ShowRecent();
	}

	function ShowRecent(){
		global $langmessage;

		$page->css_admin[] = '/include/css/addons.css'; //for hmargin css pre gpEasy 3.6

		echo '<h2 class="hmargin">';
		$label = gpOutput::SelectText('Blog');
		echo common::Link('Special_Blog',$label);
		echo ' &#187; ';
		echo common::Link('Admin_Blog',$langmessage['configuration']);
		echo ' <span>|</span> ';
		echo common::Link('Admin_BlogCategories','Categories');
		echo ' <span>|</span> ';
		echo gpOutput::SelectText('Comments');
		echo '</h2>';


		echo '<table style="width:100%" class="bordered">';
		echo '<tr><th>';
		echo 'Comment';
		echo '</th><th>';
		echo 'Time / Website';
		echo '</th><th>';
		echo 'Options';
		echo '</th></tr>';

		uasort($this->cache, array('SimpleBlogComments','Sort') );

		foreach($this->cache as $comment){
			$this->OutputComment($comment);
		}
		echo '</table>';
	}

	function OutputComment($comment){
		global $langmessage;
		echo '<tr><td class="user_submitted">';
		echo '<b>'.$comment['name'].'</b>';

		echo '<p>';
		echo $comment['comment'];
		echo '</p>';


		echo '</td><td>';
		//echo strftime($this->blogData['strftime_format'],$comment['time']);
		echo strftime( "%Y-%m-%d %H:%M:%S", $comment['time'] );
		echo '<br/>';
		if( ($this->blogData['commenter_website'] == 'nofollow') && !empty($comment['website']) ){
			echo '<a href="'.$comment['website'].'" rel="nofollow">'.$comment['website'].'</a>';
		}elseif( ($this->blogData['commenter_website'] == 'link') && !empty($comment['website']) ){
			echo '<a href="'.$comment['website'].'">'.$comment['website'].'</a>';
		}

		echo '</td><td>';
		echo common::Link('Special_Blog','View&nbsp;Post','id='.$comment['post_id']);
		echo ' &nbsp; ';
		echo common::Link('Admin_BlogComments',$langmessage['delete'],'cmd=delete_comment&id='.$comment['post_id'].'&comment_time='.$comment['time'],array('name'=>'postlink','class'=>'gpconfirm','title'=>$langmessage['delete_confirm']));
		echo '</td></tr>';
	}

	function DeleteComment(){
		$post_id = $_REQUEST['id'];
		$comment_time = $_REQUEST['comment_time'];
		$data = $this->GetCommentData($post_id);
		foreach($data as $key => $comment){
			if( $comment['time'] == $comment_time ){
				unset($data[$key]);
			}
		}
		$this->SaveCommentData($post_id,$data);
		$this->GetCache();
	}


	/**
	 * The cache file will store the 100 most recent comments
	 *
	 */
	function GetCache(){
		$this->cache = array();
		$this->cache_mod = 0;
		$this->cache_file = $this->addonPathData.'/comments/cache.txt';

		if( file_exists($this->cache_file) ){
			$this->cache_mod = filemtime($this->cache_file)-100;
			$this->cache = SimpleBlogCommon::FileData($this->cache_file);
		}

		$this->GetRecent();
	}


	function GetRecent(){
		if( !file_exists($this->dir) ){
			return false;
		}

		$new_entries = false;

		$files = scandir($this->dir);
		foreach($files as $file){

			if( $file == '.' || $file == '..' ){
				continue;
			}

			list($post_id,$ext) = explode('.',$file,2);

			if( !is_numeric($post_id) ){
				continue;
			}

			//should already be part of the cache
			$full_path = $this->dir.'/'.$file;
			$mod_time = filemtime($full_path);
			if( $mod_time < $this->cache_mod ){
				continue;
			}

			$data = SimpleBlogCommon::FileData($full_path);

			foreach($data as $comment){
				if( $comment['time'] < $this->cache_mod ){
					continue;
				}
				$unique = $post_id.'.'.$comment['time'];
				$comment['post_id'] = $post_id;
				$this->cache[$unique] = $comment;
				$new_entries = true;
			}
		}

		if( $new_entries ){
			uasort($this->cache, array('SimpleBlogComments','Sort') );
			$dataTxt = serialize($this->cache);
			gpFiles::Save($this->cache_file,$dataTxt);
		}
	}

	function Sort($a,$b){
		if( $a['time'] == $b['time'] ){
			return 0;
		}
		return ($a['time'] < $b['time']) ? 1 : -1;
	}


}
