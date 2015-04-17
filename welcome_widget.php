<?php

//include server_info functions
require_once("server_info.php");

//convert memory usage
function convert($size){
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2);
}

// number of posts
function wpqore_posts() {
    global $wpqore_count_options;
    $count_posts = wp_count_posts();
    return $wpqore_count_options['count_posts_before'] . $count_posts->publish . $wpqore_count_options['count_posts_after'];
}

// number of pages
function wpqore_pages() {
    global $wpqore_count_options;
    $count_pages = wp_count_posts('page');
    return $wpqore_count_options['count_pages_before'] . $count_pages->publish . $wpqore_count_options['count_pages_after'];
}

// number of drafts
function wpqore_drafts() {
    global $wpqore_count_options;
    $count_drafts = wp_count_posts();
    return $wpqore_count_options['count_drafts_before'] . $count_drafts->draft . $wpqore_count_options['count_drafts_after'];
}

// number of comments (total)
function wpqore_comments() {
    global $wpqore_count_options;
    $count_comments = wp_count_comments();
    return $wpqore_count_options['count_comments_before'] . $count_comments->total_comments . $wpqore_count_options['count_comments_after'];
}

// number of comments (moderated)
function wpqore_moderated() {
    global $wpqore_count_options;
    $count_moderated = wp_count_comments();
    return $wpqore_count_options['count_moderated_before'] . $count_moderated->moderated . $wpqore_count_options['count_moderated_after'];
}

// number of comments (approved)
function wpqore_approved() {
    global $wpqore_count_options;
    $count_approved = wp_count_comments();
    return $wpqore_count_options['count_approved_before'] . $count_approved->approved . $wpqore_count_options['count_approved_after'];
}

// number of users
function wpqore_users() {
    global $wpqore_count_options;
    $count_users = count_users();
    return $wpqore_count_options['count_users_before'] . $count_users['total_users'] . $wpqore_count_options['count_users_after'];
}

// number of categories
function wpqore_cats() {
    global $wpqore_count_options;
    $cats = wp_list_categories('title_li=&style=none&echo=0');
    $cats_parts = explode('<br />', $cats);
    $cats_count = count($cats_parts) - 1;
    return $wpqore_count_options['count_cats_before'] . $cats_count . $wpqore_count_options['count_cats_after'];
}

// number of tags
function wpqore_tags() {
    global $wpqore_count_options;
    return $wpqore_count_options['count_tags_before'] . wp_count_terms('post_tag') . $wpqore_count_options['count_tags_after'];
}

// last updated posts
function wpqore_updated($d = '') {
    global $wpqore_count_options;
    $count_posts = wp_count_posts();
    $published_posts = $count_posts->publish; 
    $recent = new WP_Query("showposts=1&orderby=date&post_status=publish");
    if ($recent->have_posts()) {
        while ($recent->have_posts()) {
            $recent->the_post();
            $last_update = get_the_modified_date($d);
        }
        return $wpqore_count_options['site_updated_before'] . $last_update . $wpqore_count_options['site_updated_after'];
    } else {
        return $wpqore_count_options['site_updated_before'] . 'awhile ago' . $wpqore_count_options['site_updated_after'];
    }
}

// calculate space
function wp_calc_disk_usage() {
    $upload_dir     = wp_upload_dir();
    $upload_space   = wpqore_foldersize( $upload_dir['basedir'], NULL );
    $content_space  = wpqore_foldersize( WP_CONTENT_DIR, $upload_dir['basedir'] ) + $upload_space;
    $wp_space       = wpqore_foldersize( ABSPATH, WP_CONTENT_DIR ) + $content_space;

	return (array(
		'upload' => wpqore_format_size( $upload_space ),
		'content' => wpqore_format_size( $content_space ),
		'wp' => wpqore_format_size( $wp_space )
	));
}

function wp_qore_logdata($sMsg)
{
	global $fUpload;

	if ($fUpload)
	{
		$sFile = dirname(__FILE__) . '/~log.txt';
		$fh = fopen($sFile, 'a+');

		if (!empty($sPlugin))
			$sMsg = $sPlugin . ': ' . $sMsg;

		if ($fh !== FALSE)
		{
			if ($sMsg === NULL)
				fwrite($fh, date("\r\nY-m-d H:i:s:\r\n"));
			else
				fwrite($fh, date("Y-m-d H:i:s: ") . $sMsg . "\r\n");
			fclose($fh);
		}
	}
}

function wpqore_foldersize( $path, $exclude = NULL ) {
    $total_size = 0;
    $path = untrailingslashit($path);

    $transient = 'wp_qore_foldersize_' . str_replace(ABSPATH, '', $path);

	// return transient value if we've done this recently
    $size = get_transient($transient);
    if ($size !== false)
    	return ($size);

	// set up the timer
	list($usec, $sec) = explode(' ', microtime());
	$starttime = $sec;

	$size = wp_qore_calcfoldersize($path, $exclude, $starttime);

	// save transient value for 4 hours
	set_transient($transient, $size, 60 * 60 * 4);
	return $size;
}

function wp_qore_calcfoldersize($path, $exclude, $starttime)
{
	// check timer
	list($usec, $sec) = explode(' ', microtime());
	if ($sec - $starttime > 9) {
		return 0;			// spending more than 15 seconds, give up
	}

	$total_size = 0;
    $cleanPath = $path . '/';
    $files = scandir( $path );

    foreach( $files as $t ) {
        if ( '.' != $t && '..' != $t ) {
            $currentFile = $cleanPath . $t;

            if ( is_dir( $currentFile ) ) {
				$size = 0;
				if ( $exclude == NULL || ($exclude != NULL && substr($currentFile, 0, strlen($exclude)) != $exclude))
	                $size = wp_qore_calcfoldersize( $currentFile, $exclude, $starttime );
                $total_size += $size;
            } else {
                $size = filesize( $currentFile );
                $total_size += $size;
            }
        }
    }

	return $total_size;
}

function wpqore_format_size($size) {
    $units = explode( ' ', 'B KB MB GB TB PB' );
    $mod = 1024;

    for ( $i = 0; $size > $mod; $i++ )
        $size /= $mod;

    $endIndex = strpos( $size, "." ) + 3;
    return substr( $size, 0, $endIndex ) . ' ' . $units[$i];
}

$wpqoreUrl = plugins_url('', dirname(__FILE__));

$wp_disk_spaces = @wp_calc_disk_usage();

?>
<script type="text/javascript">
google.load("visualization", "1", {packages:["corechart"]});
google.setOnLoadCallback(drawChart);

function drawChart() {
    var data = google.visualization.arrayToDataTable([
    ['Task', 'PHP Memory'],
    ['Free', <?php echo convert(ini_get('memory_limit')); ?>],
    ['Used', <?php echo convert(memory_get_usage(true)); ?>],
    ]);

    var options = {
    title: 'PHP Memory',
    pieHole: 0.2,
    };

    var chart = new google.visualization.PieChart(document.getElementById('donutchart'));
    chart.draw(data, options);
    }
</script>

<article role="main">

<div id="main_content">
<section>

<?php if ( get_option( 'wordpress_api_key' ) == FALSE ) { ?>
<div style="padding: 5px; border: 1px solid #e5e5e5;-webkit-box-shadow: 0 1px 1px rgba(0,0,0,.04);box-shadow: 0 1px 1px rgba(0,0,0,.04);background:#faf4b1;margin-bottom: 20px">If you are recieving spam comments on your PSMU blog, please <a href="options-general.php?page=akismet-key-config"><b><u>activate Akismet</u></b></a> now. All you need is the <b>free plan</b> since there is no exchange of money on our blogs.</div>
<?php } ?>

<!--<div style="padding: 5px; border: 1px solid #e5e5e5;-webkit-box-shadow: 0 1px 1px rgba(0,0,0,.04);box-shadow: 0 1px 1px rgba(0,0,0,.04);background:#45aab8;color:#fff;margin-bottom: 20px">Starting April 21st, <a style="color:#faf4b1" target="_blank" href="http://googlewebmastercentral.blogspot.com/2015/02/finding-more-mobile-friendly-search.html"><b><u>Google announced</u></b></a> they'll start ranking your site based on if it's mobile friendly or not. Please <a style="color:#faf4b1" href="admin.php?page=wptouch-admin-touchboard"><b><u>activate WPtouch Pro</u></b></a> now to make your site mobile friendly.</div>-->

<!--<div style="padding: 5px; border: 1px solid #e5e5e5;-webkit-box-shadow: 0 1px 1px rgba(0,0,0,.04);box-shadow: 0 1px 1px rgba(0,0,0,.04);background:#ff4486;color:#fff;margin-bottom: 20px">Maintenance: On ____ at 12am until 1am Pacific we will be under scheduled maintenance. No downtime is expected.</div>-->

<div class="row">

<div class="one_five">
    <div class="background_grey">
    <table>
      <tr>
        <td class="td_width">Posts:</td>
        <td><a href="edit.php"><?php echo wpqore_posts(); ?></a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Draft:</td>
        <td><a href="edit.php?post_status=draft&post_type=post"><?php echo wpqore_drafts(); ?></a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Pages:</td>
        <td><a href="edit.php?post_type=page"><?php echo wpqore_pages(); ?></a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Comments:</td>
        <td><a href="edit-comments.php"><?php echo wpqore_comments(); ?></a></td>
      </tr>
    </table>	
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Category:</td>
        <td><a href="edit-tags.php?taxonomy=category"><?php echo wpqore_cats(); ?></a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Tags:</td>
        <td><a href="edit-tags.php?taxonomy=post_tag"><?php echo wpqore_tags(); ?></a></td>
      </tr>
    </table>
    </div>
</div>
			
<div class="one_five">
    <div class="background_grey">
    <table>
      <tr>
        <td class="td_width">Users:</td>
        <td><a href="users.php"><?php $result = count_users(); echo $result['total_users']; ?></a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Media:</td>
        <td><a href="upload.php"><?php echo $wp_disk_spaces['upload']; ?></a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">WP Total:</td>
        <td><?php echo $wp_disk_spaces['wp']; ?></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Free Space:</td>
        <td><?php echo $perc;?>%</td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">PageSpeed:</td>
        <td>On <a class="button thickbox" id="td_thick" style="float:right" href="http://psmutheme.com/docs/pagespeed?TB_iframe=true">info</a></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">SuperCache:</td>
        <td>On <a class="button thickbox" id="td_thick" style="float:right" href="http://psmutheme.com/docs/supercache?TB_iframe=true">info</a></td>
      </tr>
    </table>
    </div>
</div>

<div class="one_five">
    <div class="background_grey">
    <table>
      <tr>
        <td class="td_width">Server OS:</td>
        <td>RedHat <?php echo PHP_OS ?></td>
      </tr>
    </table>
    <div class="line_break"></div>	 
    <table>
      <tr>
        <td class="td_width">PHP Ngine:</td>
        <td><?php echo phpversion() ?></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">MySQL DB:</td>
        <td><?php echo $wpdb->db_version() ?></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">WordPress:</td>
        <td><?php echo $wp_version ?></td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Processors:</td>
        <td>24 Intel Xeon's</td>
      </tr>
    </table>
    <div class="line_break"></div>
    <table>
      <tr>
        <td class="td_width">Total RAM:</td>
        <td>128 GB</td>
      </tr>
    </table>
    </div>
</div>

<div class="two_five">
<div id="donutchart" class="donutchart"></div>	
</div>
		
</div>
</section>
</div>
</article>
