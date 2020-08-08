%[if preview]%
<?php if (!class_exists('totalpreview')) {

date_default_timezone_set('Europe/London');
error_reporting(E_ALL);

// This cannot be done via central included library in RapidWeaver. It will not work in preview
class totalpreview
{
	protected $ext;
	protected $type;
	protected $baseurl;
	public $path;

	const NOTFOUND = 'Unable to locate the cms file with the id';
	const EXT = 'cms';

	function __construct($type='text',$ext=self::EXT)
	{
		$this->ext  = $ext;
		$this->type = $type;
		// %baseURL% is replaced by the Stacks API. Cannot use str_replace because of http://
		$this->baseurl = preg_replace('/\/\/$/','/','%baseURL%/');

		if (php_sapi_name() === 'cli') {
			// RapidWeaver 6
			$prodpath = __DIR__.'/Library/Application Support/RapidWeaver/Stacks/TotalCMS.stack/Contents/TotalAdminStyles.stack/Contents/Resources/total-cms';
			$devpath = __DIR__.'/Library/Application Support/RapidWeaver/Stacks/TotalCMS.devstack/Contents/TotalAdminStyles.devstack/Contents/Resources/total-cms';
			if     (file_exists($prodpath)){ $this->path = $prodpath; }
			elseif (file_exists($devpath)) { $this->path = $devpath;  }
			// RapidWeaver 7 external preview?
			else   { $this->path = dirname($this->locate_preview_asset('totaldebug')); }
		}
		else {
			// RapidWeaver 7
			$this->path = dirname($this->locate_preview_asset('totaldebug'));
		}

		if ($this->path && file_exists($this->path)) {
			set_include_path(get_include_path().PATH_SEPARATOR.$this->path);
			include_once('autoload.php');
			include_once('vendor/autoload.php');
		}
		else {
			echo "<p>Unable to load CMS preview libraries. Try adding a CMS Text stack to the bottom of your page.</p>";
		}
	}

	private function query($url) {
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
		$results = curl_exec($ch);
		$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $httpCode == 404 ? false : $results;
	}

	public function urlify_string($string)
	{
		return strtolower(str_replace(" ","-",trim(urldecode($string))));
	}

	public function locate_preview_asset($file,$last=false) {
		$rw7dir = $_SERVER['DOCUMENT_ROOT'].dirname($_SERVER['SCRIPT_NAME']).'/';

		if (php_sapi_name() === 'cli') {
			// RapidWeaver 6
			$rw6dir = $_SERVER['TMPDIR'].'RapidWeaver/';
			$directory = file_exists($rw6dir) ? $rw6dir : $rw7dir;
		}
		else {
			// RapidWeaver 7
			$directory = $rw7dir;
			$last = false;
		}

		$path = false;
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
		while($it->valid()) {
			if (strpos($it->getSubPathname(),$file) !== false) {
				$newpath = $directory.$it->getSubPathname();
				// return the first occurance of the file by default
				// We may want the last instance of a file since previous ones may not have settings changes
				if ($last === false) return $newpath;

				if ($path === false) {
					// Set intial path
					$path = $newpath;
				}
				else {
					// Check the mtime on the files to ensure that we have the most recent
					if (filemtime($newpath) > filemtime($path)) $path = $newpath;
				}
			}
		    $it->next();
		}
		return $path;
	}

	public function to_date($timestamp)
	{
		return date('c',$timestamp);
	}

	public function format_text($text)
	{
		// Convert Breaks in the middle of a paragraph
		$text = str_replace("\r\n","\n",$text);
		$text = preg_replace('/([^\n])\n([a-zA-Z0-9])/um',"$1<br/>$2", $text);
		$text = preg_replace('/<br\/>(\d+\.)/',"\n$1", $text);  //Fix numbered lists
	    return \Michelf\Markdown::defaultTransform("\n".$text);
	}

	public function get_contents($slug,$format=false)
	{
		$not_found = "<p>".self::NOTFOUND." <b>$slug</b>.</p>";

		$file = $this->baseurl."cms-data/$this->type/$slug.$this->ext";
		$contents = $this->query($file);
		// $contents = $results === false ? $not_found : $results;

	    return $format !== false ? $this->format_text($contents) : $contents;
	}

	public function process_template($template,$data)
	{
		$m = new \Mustache_Engine;
		return $m->render($template,$data);
	}

	//---------------------------------------------------------------------------------
	// Replace Methods
	//---------------------------------------------------------------------------------
	public function replace($buffer)
	{
		if ($this->type === 'text') return $this->totaltext_replace($buffer);
		return $buffer;
	}

	public function cmsText($macro,$slug,$buffer)
	{
		$text   = str_replace("\n",'<br/>',$this->get_contents($slug));
		return str_replace($macro,$text,$buffer);
	}

	private function cmsTextFormat($macro,$slug,$buffer)
	{
		return str_replace($macro, $this->get_contents($slug,true), $buffer);
	}

	private function cmsImageAlt($macro,$slug,$buffer)
	{
		return str_replace($macro, $this->get_alt($slug), $buffer);
	}

	private function cmsImageAltFormat($macro,$slug,$buffer)
	{
		$alt = $this->get_alt($slug);
		return str_replace($macro, $this->format_text($alt), $buffer);
	}

	private function cmsGalleryImageFirstAlt($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$alt = $images[0]['alt'];
		return str_replace($macro, $alt, $buffer);
	}

	private function cmsGalleryImageFirstAltFormat($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$alt = $images[0]['alt'];
		return str_replace($macro, $this->format_text($alt), $buffer);
	}

	private function cmsGalleryImageLastAlt($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$image = array_pop($images);
		$alt = $image['alt'];
		return str_replace($macro, $alt, $buffer);
	}

	private function cmsGalleryImageLastAltFormat($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$image = array_pop($images);
		$alt = $image['alt'];
		return str_replace($macro, $this->format_text($alt), $buffer);
	}

	private function cmsImage($macro,$slug,$buffer)
	{
		$image = $this->baseurl."cms-data/image/$slug.jpg";
		return str_replace($macro, $image, $buffer);
	}

	private function cmsImageThumb($macro,$slug,$buffer)
	{
		$image = $this->baseurl."cms-data/image/$slug-th.jpg";
		return str_replace($macro, $image, $buffer);
	}

	private function cmsImageSquare($macro,$slug,$buffer)
	{
		$image = $this->baseurl."cms-data/image/$slug-sq.jpg";
		return str_replace($macro, $image, $buffer);
	}

	private function cmsImagePng($macro,$slug,$buffer)
	{
		$image = $this->baseurl."cms-data/image/$slug.png";
		return str_replace($macro, $image, $buffer);
	}

	private function cmsImagePngThumb($macro,$slug,$buffer)
	{
		$image = $this->baseurl."cms-data/image/$slug-th.png";
		return str_replace($macro, $image, $buffer);
	}

	private function cmsImagePngSquare($macro,$slug,$buffer)
	{
		$image = $this->baseurl."cms-data/image/$slug-sq.png";
		return str_replace($macro, $image, $buffer);
	}

	private function cmsGalleryImageFirst($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$path = $this->baseurl.$images[0]['img'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageFirstThumb($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$path = $this->baseurl.$images[0]['thumb']['th'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageFirstSquare($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$path = $this->baseurl.$images[0]['thumb']['sq'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageLast($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$image = array_pop($images);
		$path = $this->baseurl.$image['img'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageLastThumb($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$image = array_pop($images);
		$path = $this->baseurl.$image['thumb']['th'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageLastSquare($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$image = array_pop($images);
		$path = $this->baseurl.$image['thumb']['sq'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageRandom($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$index = array_rand($images);
		$path = $this->baseurl.$images[$index]['img'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageRandomThumb($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$index = array_rand($images);
		$path = $this->baseurl.$images[$index]['thumb']['th'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsGalleryImageRandomSquare($macro,$slug,$buffer)
	{
		$images = $this->get_gallery($slug);
		$index = array_rand($images);
		$path = $this->baseurl.$images[$index]['thumb']['sq'];
		return str_replace($macro, $path, $buffer);
	}

	private function cmsVideo($macro,$slug,$buffer)
	{
		$this->type = 'video';
		$code = $this->totalvideo_embed($slug);
		return str_replace($macro, $code, $buffer);
	}

	private function cmsDataStore($macro,$slug,$buffer)
	{
		$path = $this->baseurl."cms-data/datastore/$slug.csv";
		return str_replace($macro, $path, $buffer);
	}

	private function cmsDataStoreDownload($macro,$filename,$buffer)
	{
		$info = pathinfo($filename);
		$download = $this->baseurl."rw_common/plugins/stacks/total-cms/totaldownload.php?type=datastore&slug=$slug";
		return str_replace($macro, $download, $buffer);
	}

	private function cmsFile($macro,$filename,$buffer)
	{
		$path = $this->baseurl."cms-data/file/$filename";
		return str_replace($macro, $path, $buffer);
	}

	private function cmsFileDownload($macro,$filename,$buffer)
	{
		$info = pathinfo($filename);
		$download = $this->baseurl."rw_common/plugins/stacks/total-cms/totaldownload.php?type=file&slug=".$info['filename']."&ext=".$info['extension'];
		return str_replace($macro, $download, $buffer);
	}

	private function cmsToggle($macro,$slug,$buffer)
	{
		$status = $this->get_toggle_status($slug) ? 'true' : 'false';
		return str_replace($macro, $status, $buffer);
	}

	private function blogTitle($macro,$post,$buffer)
	{
		return str_replace($macro, $post->title, $buffer);
	}

	private function blogAuthor($macro,$post,$buffer)
	{
		return str_replace($macro, $post->author, $buffer);
	}

	private function blogContent($macro,$post,$buffer)
	{
		return str_replace($macro, $post->content, $buffer);
	}

	private function blogExtraContent($macro,$post,$buffer)
	{
		return str_replace($macro, $post->extra, $buffer);
	}

	private function blogContentFormat($macro,$post,$buffer)
	{
		return str_replace($macro, $this->format_text($post->content), $buffer);
	}

	private function blogExtraContentFormat($macro,$post,$buffer)
	{
		return str_replace($macro, $this->format_text($post->extra), $buffer);
	}

	private function blogTags($macro,$post,$buffer)
	{
		return str_replace($macro, implode(', ',$post->tags), $buffer);
	}

	private function blogCategories($macro,$post,$buffer)
	{
		return str_replace($macro, implode(', ',$post->categories), $buffer);
	}

	private function blogPermalink($macro,$post,$buffer)
	{
		return str_replace($macro, $post->permalink, $buffer);
	}

	private function blogSummary($macro,$post,$buffer)
	{
		return str_replace($macro, $post->summary, $buffer);
	}

	private function blogSummaryFormat($macro,$post,$buffer)
	{
		return str_replace($macro, $this->format_text($post->summary), $buffer);
	}

	private function blogImageFirstAlt($macro,$post,$buffer)
	{
		return str_replace($macro, $post->gallery[0]->alt, $buffer);
	}

	private function blogImageFirstAltFormat($macro,$post,$buffer)
	{
		return str_replace($macro, $this->format_text($post->gallery[0]->alt), $buffer);
	}

	private function blogImageLastAlt($macro,$post,$buffer)
	{
		$image = array_pop($post->gallery);
		return str_replace($macro, $image->alt, $buffer);
	}

	private function blogImageLastAltFormat($macro,$post,$buffer)
	{
		$image = array_pop($post->gallery);
		return str_replace($macro, $this->format_text($image->alt), $buffer);
	}

	private function blogImageFirst($macro,$post,$buffer)
	{
		$path = $this->baseurl.$post->gallery[0]->img;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageFirstThumb($macro,$post,$buffer)
	{
		$path = $this->baseurl.$post->gallery[0]->thumb->th;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageFirstSquare($macro,$post,$buffer)
	{
		$path = $this->baseurl.$post->gallery[0]->thumb->sq;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageLast($macro,$post,$buffer)
	{
		$image = array_pop($post->gallery);
		$path = $this->baseurl.$image->img;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageLastThumb($macro,$post,$buffer)
	{
		$image = array_pop($post->gallery);
		$path = $this->baseurl.$image->thumb->th;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageLastSquare($macro,$post,$buffer)
	{
		$image = array_pop($post->gallery);
		$path = $this->baseurl.$image->thumb->sq;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageRandom($macro,$post,$buffer)
	{
		$index = array_rand($post->gallery);
		$path = $this->baseurl.$post->gallery[$index]->img;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageRandomThumb($macro,$post,$buffer)
	{
		$index = array_rand($post->gallery);
		$path = $this->baseurl.$post->gallery[$index]->thumb->th;
		return str_replace($macro, $path, $buffer);
	}

	private function blogImageRandomSquare($macro,$post,$buffer)
	{
		$index = array_rand($post->gallery);
		$path = $this->baseurl.$post->gallery[$index]->thumb->sq;
		return str_replace($macro, $path, $buffer);
	}

	private function legacy_macros($buffer)
	{
		// Find all of the macros defined on the page
		if (preg_match_all('/\W%(\w+)(\s-(format|alt))*%\W/', $buffer, $matches)) {
			$slugs = array_unique($matches[1]);

			foreach ($slugs as $slug) {
				$macro = "%$slug%";
				if (strpos($buffer,$macro) !== false) {
					$text   = str_replace("\n",'<br/>',$this->get_contents($slug));
					$buffer = str_replace($macro,$text,$buffer);
				}

				$macro = "%$slug -format%";
				if (strpos($buffer,$macro) !== false) {
					$buffer = str_replace($macro, $this->get_contents($slug,true), $buffer);
				}

				$macro = "%$slug -alt%";
				if (strpos($buffer,$macro) !== false) {
					$buffer = str_replace($macro, $this->get_alt($slug), $buffer);
				}
			}
		}
		return $buffer;
	}

	private function totaltext_replace($buffer)
	{
		// Find all of the macros defined on the page
		if (preg_match_all('/%(cms\w+)\(([\w\-\.]+)\)%/', $buffer, $matches)) {
			$macros = $matches[0];
			$functions = $matches[1];
			$slugs = $matches[2];

			foreach ($functions as $index => $function) {
				if (method_exists($this,$function)) $buffer = $this->$function($macros[$index],$slugs[$index],$buffer);
			}
		}

		// Eventually this needs to go away
		$buffer = $this->legacy_macros($buffer);
		return $buffer;
	}

	public function blog_replace($post,$buffer)
	{
		// Find all of the macros defined on the page
		if (preg_match_all('/%(blog\w+)\(.*?\)%/', $buffer, $matches)) {
			$macros = $matches[0];
			$functions = $matches[1];

			foreach ($functions as $index => $function) {
				if (method_exists($this,$function)) $buffer = $this->$function($macros[$index],$post,$buffer);
			}
		}
		return $buffer;
	}

	//---------------------------------------------------------------------------------
	// Alt Methods
	//---------------------------------------------------------------------------------
	public function get_alt($slug,$format=false)
	{
		$not_found = '';

		$file = $this->baseurl."cms-data/image/$slug.$this->ext";
		$results = $this->query($file);
		$contents = $results === false ? $not_found : $results;

	    return $format ? \Michelf\Markdown::defaultTransform($contents) : $contents;
	}

	//---------------------------------------------------------------------------------
	// Toggle Methods
	//---------------------------------------------------------------------------------
	public function get_toggle_status($slug)
	{
		$file = $this->baseurl."cms-data/toggle/$slug.$this->ext";
		$results = $this->query($file);
		return $results === false ? false : true;
	}

	//---------------------------------------------------------------------------------
	// Blog Methods
	//---------------------------------------------------------------------------------
	public function get_blog_db($slug)
	{
	    $blog_json = $this->baseurl."cms-data/blog/$slug/$slug.json";
	    $results = $this->query($blog_json);

	    if ($results === false) {
	    	return false;
	    }
	    return json_decode($results);
	}
	public function get_blog($slug,$filter=false)
	{
		$feed_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=blog&slug=$slug&filter=".json_encode($filter);
		if ($filter) $feed_url = $feed_url."&filter=".json_encode($filter);
		$results = $this->query($feed_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results);
		return $data->data;
	}
	public function get_blog_post($slug,$permalink)
	{
		$feed_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=blog&slug=$slug&permalink=$permalink";
		$results = $this->query($feed_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results);
		return $data->data;
	}
	public function blog_gallery_images($slug,$permalink)
	{
	    $gallery_json = $this->baseurl."cms-data/gallery/blog/$slug/$permalink/$permalink.json";
	    $results = $this->query($gallery_json);

	    if ($results === false) {
	    	return false;
	    }
	    return json_decode($results);
	}


	//---------------------------------------------------------------------------------
	// News Feed Methods
	//---------------------------------------------------------------------------------
	public function get_feed($slug)
	{
		$feed_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=feed&slug=$slug";
		$results = $this->query($feed_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results,true);
		return $data['data']['posts'];
	}

	//---------------------------------------------------------------------------------
	// Gallery Methods
	//---------------------------------------------------------------------------------
	public function get_gallery($slug)
	{
		$gallery_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=gallery&slug=$slug";
		$results = $this->query($gallery_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results,true);
		$images = array();
		foreach ($data['data']['images'] as $image) {
			if ($image['alt'] != '') {
				$image['alt'] = $image['alt'];
			}
			$images[] = $image;
		}
		return $images;
	}

	//---------------------------------------------------------------------------------
	// Video Methods
	//---------------------------------------------------------------------------------
	public function totalvideo_embed($slug,$options=array())
	{
    	$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'ycolor'   => 'red',
			'ytheme'   => 'dark',
			'vcolor'   => '33aaff',
    	), $options);

		$contents = trim($this->get_contents($slug));

		if (strpos($contents,self::NOTFOUND) !== false) return $contents;

		if (strpos($contents,'youtu')  !== false) { //youtube.com or youtu.be
			$this->service = 'youtube';
			$embed = $this->youtube_embed($contents,$options);
		}
		elseif (strpos($contents,'vimeo') !== false) {
			$this->service = 'vimeo';
			$embed = $this->vimeo_embed($contents,$options);
		}
		elseif (strpos($contents,'wistia') !== false) {
			$this->service = 'wistia';
			$embed = $this->wistia_embed($contents,$options);
		}

		return isset($embed) ? $embed : "<p>Unable to locate video ID from video url: '$contents'</p>";
	}

	private static function wistia_embed($url,$options=array())
	{
		if (preg_match('/(\w+)$/', $url, $matches)) {
			$video_id = $matches[0];
			return "<script src=\"//fast.wistia.com/embed/medias/$video_id.jsonp\" async></script><script src=\"//fast.wistia.com/assets/external/E-v1.js\" async></script><div class=\"wistia_responsive_padding\" style=\"padding:56.25% 0 0 0;position:relative;\"><div class=\"wistia_responsive_wrapper\" style=\"height:100%;left:0;position:absolute;top:0;width:100%;\"><div class=\"wistia_embed wistia_async_$video_id videoFoam=true\" style=\"height:100%;width:100%\">&nbsp;</div></div></div>";
		}
		return false;
	}

	private static function vimeo_embed($url,$options=array())
	{
		$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'vcolor'   => '33aaff',
    	), $options);

		if (preg_match('/(\w+)$/', $url, $matches)) {
			$video_id = $matches[0];
			$query = http_build_query(array(
				'autoplay' => $options['autoplay'],
				'color'    => $options['vcolor'],
				'loop'     => $options['loop'],
				'api'      => 1,
				'badge'    => 0,
				'byline'   => 0,
				'portrait' => 0,
				'title'    => 0
			),'','&amp;');
			return "<iframe width='1280' height='720' src='//player.vimeo.com/video/$video_id?$query' frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";
		}
		return false;
	}

	private static function youtube_embed($url,$options=array())
	{
    	$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'ycolor'   => 'red',
			'ytheme'   => 'dark',
			'private'  => true
    	), $options);

		if (preg_match('/([\w-]+)$/', $url, $matches)) {
			$video_id = $matches[0];

			$query = array(
				'autoplay'       => $options['autoplay'],
				'loop'    		 => $options['loop'],
				'color'    		 => $options['ycolor'],
				'theme'    		 => $options['ytheme'],
				'origin'    	 => 'localhost', // or $_SERVER["SERVER_NAME"]
				'enablejsapi'    => 1,
				'rel'            => 0,
				'showinfo'       => 0
			);
			if ((strpos($url,'list')  !== false)) {
				// playlist
				$query['listType'] = 'playlist';
				$query['list'] = $video_id;
				$video_id = '';
			}
			$http_query = http_build_query($query,'','&amp;');
			$domain = $options['private'] === true ? 'www.youtube-nocookie.com' : 'www.youtube.com';
			return "<iframe width='1280' height='720' src='//$domain/embed/$video_id?$http_query' frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";
		}
		return false;
	}

	//---------------------------------------------------------------------------------
	// Depot Methods
	//---------------------------------------------------------------------------------
	public function get_depot($slug,$sort)
	{
		$depot_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=depot&slug=$slug";
		$results = $this->query($depot_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results,true);
		return $data['data']['files'];
	}

}

} ?>
%[endif]%
