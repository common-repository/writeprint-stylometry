<?php  
	/* 
	Plugin Name: Writeprint Stylometry
	Plugin URI: http://www.writeprint.com.ar/
	Description: Analyze comments in order to identify authorship
	Author: Rodrigo S. Alhadeff
	Version: 0.1 	
	*/
	include('includes/GphpChart.class.php'); 
	add_action('admin_menu', 'bjl_wprintstylo_add_menu');
	add_action('manage_comments_nav', 'bjl_wprintstylo_comments_nav');
	add_action('admin_head', 'bjl_wprintstylo_style_admin');
	session_start(); 
					
	function bjl_wprintstylo_admin()
	{
		global $wpdb, $table_prefix;		
		
		$bjl_wprintstylo_counter = 0;
		$bjl_wprintstylo_start = 0;
		$bjl_wprintstylo_limit = 10;			
		$bjl_cexport_limit=10;
	
		if ($_GET["bjl_cexport_post"] != 0)
		{
			$post = get_post($_GET["bjl_cexport_post"]);
			
			$bjl_cexport_filter = " comment_post_ID = ".$_GET["bjl_cexport_post"]." AND ";
		
		}
		
		
		$lc1 = array();
		$lc2 = array();						
		$_SESSION['arrayCounter'] = 1; 
										
		echo '<div class="wrap">';
		echo '<div id="icon-users" class="icon32"></div><h2>'.__("Analyze Comments Authorship").'</h2>';
					

		if ($_GET['pagenum'] > 1) { $bjl_wprintstylo_start = ($_GET['pagenum'] - 1) * $bjl_wprintstylo_limit; }
		
		
		// Get how many comments authors
		$bjl_cwriteprint_commenters = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS COUNT(comment_ID) AS count, comment_ID, comment_author, comment_author_email, comment_author_url, comment_author_ip, comment_date FROM $table_prefix"."comments WHERE".$bjl_cexport_filter." comment_approved = 1 AND comment_type = '' GROUP BY comment_author, comment_author_email ORDER BY comment_author ASC, comment_date DESC LIMIT ".$bjl_wprintstylo_start.", ".$bjl_wprintstylo_limit.";");
		
		// count
		$bjl_cwriteprint_commenters_count = $wpdb->get_var("SELECT FOUND_ROWS();");
		
		if ($bjl_cwriteprint_commenters_count > 0)
		{
			echo '<h3>'.$bjl_cwriteprint_commenters_count.' '.__("Authors Found").'</h3>';

			echo '<form id="bjl_cexport-filter" action="'.admin_url('edit-comments.php').'" method="get">';
			echo '<input type="hidden" name="page" value="writeprint-stylometry.php" />';
			
			echo '<div class="tablenav">';
			echo '<div class="alignleft actions">';

			echo '<select name="bjl_cexport_post">';
			echo '<option value="0">'.__("All Posts & Pages").'</option>';
			echo '<optgroup label="'.__("Recent Posts with Comments").'">';
			
			// LAST 20 POSTS WITH COMMENTS
			$bjl_cexport_posts = $wpdb->get_results("SELECT ID, post_title, comment_count FROM $table_prefix"."posts WHERE post_type = 'post' AND post_status = 'publish' AND comment_count > 0 ORDER BY post_date DESC LIMIT 0, 30;");
			foreach ($bjl_cexport_posts as $bjl_cexport_post)
			{
				echo '<option value="'.$bjl_cexport_post->ID.'"'.($_GET["bjl_cexport_post"] == $bjl_cexport_post->ID ? " selected" : "").'>'.bjl_cexport_trim_string($bjl_cexport_post->post_title).'</option>';
			}
			echo '</optgroup>';
			
			// PAGES WITH COMMENTS
			$bjl_cexport_pages = $wpdb->get_results("SELECT ID, post_title, comment_count FROM $table_prefix"."posts WHERE post_type = 'page' AND post_status = 'publish' AND comment_count > 0 ORDER BY post_title ASC");
			if (count($bjl_cexport_pages) > 0)
			{
				echo '<optgroup label="'.__("Pages with Comments").'">';
				foreach ($bjl_cexport_pages as $bjl_cexport_page)
				{
					echo '<option value="'.$bjl_cexport_page->ID.'"'.($_GET["bjl_cexport_post"] == $bjl_cexport_page->ID ? " selected" : "").'>'.bjl_cexport_trim_string($bjl_cexport_page->post_title).'</option>';
				}
				echo '</optgroup>';
			}
				
			echo '</select> ';
			
			echo '<input type="submit" id="post-query-submit" value="'.__("Filter").'" class="button-secondary" /> ';
						
			echo '</div>';
			
			// PAGINATION CODE - MODIFIED FROM wp-admin/edit-pages.php
			$pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 0;
			if (empty($pagenum)) $pagenum = 1;
			$per_page = $bjl_cexport_limit;
			$num_pages = ceil($bjl_cwriteprint_commenters_count / $per_page);
			
			$page_links = paginate_links(array(
				'base' => add_query_arg('pagenum', '%#%'),
				'format' => '',
				'prev_text' => __('&laquo;'),
				'next_text' => __('&raquo;'),
				'total' => $num_pages,
				'current' => $pagenum
			));
			
			if ($page_links)
			{
			?>

				<div class="tablenav-pages">
				<?php $page_links_text = sprintf('<span class="displaying-num">'.__('Displaying %s&#8211;%s of %s').'</span>%s',
					number_format_i18n(($pagenum - 1) * $per_page + 1),
					number_format_i18n(min($pagenum * $per_page, $bjl_cwriteprint_commenters_count)),
					number_format_i18n($bjl_cwriteprint_commenters_count),
					$page_links);
					
					echo $page_links_text;
				?>
				</div>
				<div class="clear"></div>
			<?
			}
			// PAGINATION CODE - END
			
			echo '</div>';
			echo '<div class="clear"></div>';
			echo '</form>';
			
			echo '<table class="widefat post fixed" cellspacing="0">';
			echo '<thead>';
			echo '<tr>';
			echo '<th scope="col" id="author">'.__("Author").'</th>';
			echo '<th scope="col" id="email">'.__("Email Address").'</th>';
			echo '<th scope="col" id="ip">'.__("IP").'</th>';
			echo '<th scope="col" id="date" class="manage-column column-date">'.__("Date").'</th>';
			echo '<th scope="col" id="writeprint">'.__("AVL").'</th>';
			echo '<th scope="col" id="writeprint">'.__("W/P").'</th>';
			echo '<th scope="col" id="writeprint">'.__("W/S").'</th>';
			echo '<th scope="col" id="writeprint">'.__("U/L").'</th>';
			echo '<th scope="col" id="comments" class="manage-column column-comments num"><div class="vers"><img alt="'.__("Comments").'" src="images/comment-grey-bubble.png" /></div></th>';
			echo '</tr>';
			echo '</thead>';
			
			echo '<tfoot>';
			echo '<tr>';
			echo '<th scope="col" id="author">'.__("Author").'</th>';
			echo '<th scope="col" id="email">'.__("Email Address").'</th>';
			echo '<th scope="col" id="ip">'.__("IP").'</th>';
			echo '<th scope="col" id="date" class="manage-column column-date">'.__("Date").'</th>';
			echo '<th scope="col" id="writeprint">'.__("AVL").'</th>';
			echo '<th scope="col" id="writeprint">'.__("W/P").'</th>';
			echo '<th scope="col" id="writeprint">'.__("W/S").'</th>';
			echo '<th scope="col" id="writeprint">'.__("U/L").'</th>';			
			echo '<th scope="col" id="comments" class="manage-column column-comments num"><div class="vers"><img alt="'.__("Comments").'" src="images/comment-grey-bubble.png" /></div></th>';
			echo '</tr>';
			echo '</tfoot>';
			
			echo '<tbody>';
			foreach ($bjl_cwriteprint_commenters as $bjl_cexport_commenter)
			
			{
				echo '<tr'.($bjl_cexport_counter % 2 == 1 ? "" : " class='alternate'").'>';
				echo '<td><strong><font color=#'.call_user_func('setColor', $bjl_cexport_counter+1).'>'.$bjl_cexport_commenter->comment_author.'</font></strong></td>';
				echo '<td><a href="mailto:'.$bjl_cexport_commenter->comment_author_email.'">'.$bjl_cexport_commenter->comment_author_email.'</a></td>';
				echo '<td>'.$bjl_cexport_commenter->comment_author_ip.'</td>';
				echo '<td class="date column-date">'.mysql2date(__('Y/m/d'), $bjl_cexport_commenter->comment_date).'</td>';
				echo '<td>'.bjl_wprintstylo_analyze($bjl_cexport_commenter->comment_author).'</td>';		
				echo '<td style="width:75px; text-align:center;"><div class="post-com-count-wrapper"><a class="post-com-count"><span class="comment-count">'.$bjl_cexport_commenter->count.'</span></a></div></td>';
				echo '</tr>';
				
				$bjl_cexport_counter++;
			}
			echo '</tbody>';
			echo '</table>';
			
			echo '<br><br>';
									
			$GphpChart = new GphpChart('lc');
			$GphpChart->title = 'Writeprint chart';
			if(file_exists($GphpChart->filename)) echo $GphpChart->get_Image_String();
			else
			  {
			  $GphpChart->add_grid('8.3333,10,1,5');			  
			  for ($i = 1; $i < $_SESSION['arrayCounter'] ; $i++) {
 			   $GphpChart->add_data($_SESSION['array'.$i],call_user_func('setColor', $i));  			    			   
 			   $GphpChart->add_style('3,6,3');		   			  
			  }
			  
			  $labels=array("AVL","W/P","W/S","U/L");
			  $GphpChart->add_labels("x",$labels); 
			  echo $GphpChart->get_Image_String();
			  $GphpChart->save_Image();
			  }
	  
  
		}
		else
		{
			echo '<p>'.__("No Commenters Found.").'</p>';
		}
						
				
		// links to writeprint
		echo '<div id="bjl_shameless_plugs">';
		echo '<h3><a href="http://www.writeprint.com.ar/">More about Writeprint</a></h3>';
		echo 'Reference: ';
		echo 'AVL Average Word Length - ';
		echo 'W/P Word per paragraph - ';
		echo 'W/S Word per sentence - ';
		echo 'U/L Upper per letters - ';
				
		echo '<ul>';
		echo '<li class="buffer">';
		echo '<a href="http://www.writeprint.com.ar/"><img src="'.get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/images/writeprint.jpg'.'" alt="WP Word Count" title="WP Writeprint" /></a>';
		echo '<div><a href="http://www.writeprint.com.ar/">Writeprint Web Site</a></div>';
		echo 'More information about Writeprint and Stylometry.';
		echo '</li>';
		
		echo '<ul>';
		echo '<li>';
		echo '<a href="http://www.writeprint.com.ar/writeprintblog/?page_id=37"><img src="'.get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/images/writeprintfull.jpg'.'" alt="Writeprint Full Version" title="WP Plugin Full" /></a>';
		echo '<div><a href="http://www.writeprint.com.ar/writeprintblog/?page_id=37">Full Version</a></div>';
		echo 'Get the full version of this plugin.';
		echo '</li>';
		
		
		echo '</ul>';
		
		echo '</div>';
				
		echo '</div>';	// END writeprint
	}
	
	function bjl_wprintstylo_analyze($comment_author)
	{
		global $wpdb, $table_prefix,$contentCompilation;
				               
		// strip HTML
		$comment_author=strip_tags($comment_author);
		        				
		// compile all comments from same author
		$bjl_wprintstylo_commenters = $wpdb->get_results("SELECT comment_ID, comment_author, comment_author_email, comment_content, comment_date, comment_author_IP FROM $table_prefix"."comments WHERE comment_author='$comment_author';");
				
		
		foreach ($bjl_wprintstylo_commenters as $bjl_wprintstylo_commenter)
		{			
			$contentCompilation==$contentCompilation.$bjl_wprintstylo_commenter->comment_content;
			
			//echo '<hr><p>'.$bjl_wprintstylo_commenter->comment_content.'</p>';
			//echo '<p>Words: '.str_word_count($bjl_wprintstylo_commenter->comment_content).'</p>';
			//echo '<p>Length: '.strlen($bjl_wprintstylo_commenter->comment_content).'</p>';						
			//echo '<p>Average Word Length: '.call_user_func('averageWordLength', "$bjl_wprintstylo_commenter->comment_content").'</p>';						
			//echo '<p>Paragraphs: '.call_user_func('countParagraphs', "$bjl_wprintstylo_commenter->comment_content").'</p>';						
			//echo '<p>Words/Paragraphs: '.str_word_count($bjl_wprintstylo_commenter->comment_content)/call_user_func('countParagraphs', "$bjl_wprintstylo_commenter->comment_content").'</p>';						
			//echo '<p>Sentences: '.call_user_func('countSentences', "$bjl_wprintstylo_commenter->comment_content").'</p>';						
			//echo '<p>Words/Sentences: '.(int)(str_word_count($bjl_wprintstylo_commenter->comment_content)/call_user_func('countSentences', "$bjl_wprintstylo_commenter->comment_content")).'</p>';						
			//echo '<p>Upper Words: '.call_user_func('countUpper', "$bjl_wprintstylo_commenter->comment_content").'</p>';						
			//echo '<p>Upper/Letters: '.round(call_user_func('countUpper', "$bjl_wprintstylo_commenter->comment_content")/strlen($bjl_wprintstylo_commenter->comment_content),2).'</p>';						
						
		}				
		
		$arrayCounter=$_SESSION['arrayCounter'];
		$ul100=round(call_user_func('countUpper', "$bjl_wprintstylo_commenter->comment_content")/strlen($bjl_wprintstylo_commenter->comment_content),2)*50;
		$_SESSION['array'.$arrayCounter]=array(call_user_func('averageWordLength', "$bjl_wprintstylo_commenter->comment_content"),str_word_count($bjl_wprintstylo_commenter->comment_content)/call_user_func('countParagraphs', "$bjl_wprintstylo_commenter->comment_content"),(int)(str_word_count($bjl_wprintstylo_commenter->comment_content)/call_user_func('countSentences', "$bjl_wprintstylo_commenter->comment_content")),$ul100);				
		
		$arrayCounter++;
		
		$_SESSION['arrayCounter']=$arrayCounter;						
		
		
		return call_user_func('averageWordLength', "$bjl_wprintstylo_commenter->comment_content").'</td><td>'.round(str_word_count($bjl_wprintstylo_commenter->comment_content)/call_user_func('countParagraphs', "$bjl_wprintstylo_commenter->comment_content"),2).'</td><td>'.(int)(str_word_count($bjl_wprintstylo_commenter->comment_content)/call_user_func('countSentences', "$bjl_wprintstylo_commenter->comment_content")).'</td><td>'.round(call_user_func('countUpper', "$bjl_wprintstylo_commenter->comment_content")/strlen($bjl_wprintstylo_commenter->comment_content),2);						
	        								
	}
		
	function averageWordLength($text)
	{
		$word_count = $word_length = 0;	
		
		// Array white space
		$words = preg_split('/\s+/',$text,-1,PREG_SPLIT_NO_EMPTY);
		foreach ($words as $word) {
		$word_count++;
		$word_length += strlen($word);
		}
		return (int)($word_length/$word_count);
	}
	
	
	function countParagraphs($text)
	{
		$par_count  = 0;	
				
		$pars = preg_split('/\n+/',$text,-1,PREG_SPLIT_NO_EMPTY);
		foreach ($pars as $par) {
		$par_count++;
		
		}
		return $par_count;
	}
		

	function countUpper($str)
	{        
	
	    preg_match_all('/[A-Z]/', $str, $your_match) ;
	
	    $total_upper_case_count = count($your_match [0]);
	
	    return $total_upper_case_count;
	}
	
	function countSentences($str)
	{
	
	return preg_match_all('/[^\s](\.|\!|\?)(?!\w)/',$str,$match);
	
	}

function setColor($counter)	
{	
	if ($counter==1) {$color="99FF00";}
	if ($counter==2) {$color="CC0000";}
	if ($counter==3) {$color="CC6600";}
	if ($counter==4) {$color="FF0000";}
	if ($counter==5) {$color="FF6660";}
	if ($counter==6) {$color="FF9900";}
	if ($counter==7) {$color="FFFF00";}
	if ($counter==8) {$color="99FFCC";}
	if ($counter==9) {$color="3399CC";}
	if ($counter==10) {$color="663366";}
	
	
	return $color;
}
	function bjl_wprintstylo_add_menu()
	{
		add_comments_page(__("Writeprint analysis"), __("Writeprint"), 1, basename(__FILE__), 'bjl_wprintstylo_admin');
	}
	
	function bjl_wprintstylo_comments_nav()
	{
		if ($_GET["p"])
		{
			echo '</div>';
			echo '<div class="alignleft">';
			echo '<a class="button-secondary checkforspam" href="?page='.basename(__FILE__).'&bjl_wprintstylo=true&bjl_wprintstylo_post='.$_GET["p"].'" title="'.__("Export Authors").'">'.__("Export Authors").'</a>';
		}
	}
	
	function bjl_wprintstylo_style_admin()
	{
		$siteurl = get_option('siteurl');
		$url = get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/style_admin.css';
		
		echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
	}
?>