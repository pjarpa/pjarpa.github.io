<?php
namespace TotalCMS\Component;

use \FeedWriter\RSS2;

//---------------------------------------------------------------------------------
// Blog class
//---------------------------------------------------------------------------------
class Blog extends Component
{
	protected $json_file;
	protected $rss_file;
	protected $gallery;

	protected $rss_title;
	protected $rss_description;
	protected $baseurl;
	public    $posturl;
	protected $baseurl_file;
	protected $posturl_file;
	protected $sitemap_file;

	public    $categories;
	public    $tags;
	public    $draft;
	public    $author;
	public    $genre;
	public    $extra;
	public    $permalink;
	public    $title;
	public    $summary;
	public    $content;
	public    $timestamp;
	public    $posts;

	public function __construct($slug,$options=array())
	{
		$options = array_merge(array(
			'type'            => 'blog',
			"categories"      => "",
			"tags"            => "",
			"featured"        => "false",
			"draft"           => "false",
			"author"          => "",
			"genre"           => "default",
			"title"           => "",
			"permalink"       => "",
			"dateformat"      => "m/d/Y",
			"timestamp"       => false,
			"summary"         => "",
			"content"         => "",
			"extra"           => "",
			'rss_title'       => 'News Feed',
			'rss_description' => 'News Feed powered by Total CMS for RapidWeaver',
			'baseurl'     => '',
			'posturl'     	  => '',
			'image_options'   => array()
		), $options);

		$options['set'] = true;

		parent::__construct($slug,$options);

		$this->categories = array_filter(array_map(function($category){return trim($category);}, explode(",",$options['categories'])));
		$this->tags       = array_filter(array_map(function($tag){return trim($tag);}, explode(",",$options['tags'])));
		$this->draft      = ($options['draft'] === 'true');
		$this->featured   = ($options['featured'] === 'true');
		$this->author     = $options['author'];
		$this->permalink  = $this->urlify_string($options['permalink']);
		$this->title      = $options['title'];
		$this->summary    = $options['summary'];
		$this->content    = $options['content'];
		$this->extra      = $options['extra'];
		$this->genre      = $options['genre'];

		$this->rss_title       = $options['rss_title'];
		$this->rss_description = $options['rss_description'];
		$this->baseurl     	   = $options['baseurl'];
		$this->posturl         = $options['posturl'];

		$this->baseurl_file    = "$this->target_dir/$this->slug.baseurl";
		$this->posturl_file    = "$this->target_dir/$this->slug.posturl";

		if (empty($this->baseurl)) {
			if (file_exists($this->baseurl_file)) $this->baseurl = file_get_contents($this->baseurl_file);
		}
		else {
			$this->make_dir(dirname($this->baseurl_file)); // make the dir just in case
			file_put_contents($this->baseurl_file,$this->baseurl);
		}

		if (empty($this->posturl)) {
			if (file_exists($this->posturl_file)) $this->posturl = file_get_contents($this->posturl_file);
		}
		else {
			if (strpos($this->posturl,'http') === 0){
				if (!preg_match("#/$#",$this->posturl)) $this->posturl .= '/';
			}
			else {
				$posturl = new \URL\Normalizer($this->baseurl.$this->posturl);
				$this->posturl = $posturl->normalize().'?permalink=';
			}
			$this->make_dir(dirname($this->posturl_file)); // make the dir just in case
			file_put_contents($this->posturl_file,$this->posturl);
		}

		$this->timestamp = empty($options['timestamp']) ? time() : \DateTime::createFromFormat($options["dateformat"],$options['timestamp'])->getTimestamp();

		$this->set_filename($this->permalink);

		$options['image_options'] = array_merge($options,$options['image_options']);
		$options['image_options']['type'] = 'gallery';
		$options['image_options']['target_dir'] = "/gallery/$this->type/$slug/$this->permalink";

	    $this->gallery = new Gallery($this->permalink,$options['image_options']);

		$this->posts = array();

		$this->json_file = "$this->target_dir/_blog.json";
		$this->rss_file  = "$this->target_dir/$this->slug.rss";
		$this->sitemap_file  = "$this->target_dir/$this->slug-sitemap.xml";
	}

	public function featured_image()
	{
		$image = false;
		if (file_exists($this->gallery->json_file)) {
			$gallery = json_decode(file_get_contents($this->gallery->json_file));
			$image = $gallery[0];
		}
		return $image;
	}

	public function get_contents($format=false)
	{
		return file_exists($this->target_path()) ? json_decode(file_get_contents($this->target_path())) : false;
	}

	public function post_summary($post=false)
	{
		$obj = $post === false ? $this : $post;
		return array(
			"categories" => $obj->categories,
			"tags"       => $obj->tags,
			"gallery"    => $this->gallery_images($obj->permalink),
			"genre"      => property_exists($obj,'genre') ? $obj->genre : 'default',
			"draft"      => $obj->draft,
			"featured"   => $obj->featured,
			"author"     => $obj->author,
			"title"      => $obj->title,
			"permalink"  => $obj->permalink,
			"timestamp"  => $obj->timestamp,
			"summary"    => $obj->summary
		);
	}

	public function meta_description($post)
	{
		$summary = strip_tags($post->summary);
		return "<meta name=\"description\" content=\"$summary\">";
	}

	public function meta_twitter($post,$twitter)
	{
		$summary = strip_tags($post->summary);
		$tags = "<meta name=\"twitter:card\" content=\"summary\">
				<meta name=\"twitter:site\" content=\"$twitter\">
				<meta name=\"twitter:url\" content=\"$this->posturl$post->permalink\">
				<meta name=\"twitter:title\" content=\"$post->title\">
				<meta name=\"twitter:description\" content=\"$summary\">";

		if (isset($post->gallery)) {
			$image = $post->gallery[0];
			$tags .= "<meta name=\"twitter:image\" content=\"$this->baseurl$image->img\">";
		}
		return $tags;
	}

	public function meta_facebook($post)
	{
		$summary = strip_tags($post->summary);
		$tags = "<meta property=\"og:url\" content=\"$this->posturl$post->permalink\">
				<meta property=\"og:type\" content=\"article\">
				<meta property=\"og:title\" content=\"$post->title\">
				<meta property=\"og:description\" content=\"$summary\">";

		if (isset($post->gallery)) {
			$image = $post->gallery[0];
			$tags .= "<meta property=\"og:image\" content=\"$this->baseurl$image->img\">";
		}
		return $tags;
	}

	public function meta_google($post)
	{
		$summary = strip_tags($post->summary);
		$tags = "<meta itemprop=\"name\" content=\"$post->title\">
				<meta itemprop=\"description\" content=\"$summary\">";

		if (isset($post->gallery)) {
			$image = $post->gallery[0];
			$tags .= "<meta itemprop=\"image\" content=\"$this->baseurl$image->img\">";
		}
		return $tags;
	}

	public function post_to_json()
	{
		$post = $this->post_summary();
		$post["content"] = $this->content;
		$post["extra"] = $this->extra;
		return json_encode($post);
	}

	public function post_summary_to_json()
	{
		return json_encode(post_summary());
	}

	public function save_content_to_cms($contents,$options=array())
	{
		if (empty($this->permalink)) {
			$this->log_error("Must define a permalink in order to save a blog post");
			return false;
		}

    	// Save images
		if ($options['image'] !== false) {
			$this->gallery->save_content($options['image'],$options);
		}

    	// Save post JSON
    	$rc = file_put_contents($this->target_path(),$this->post_to_json());

		$this->refresh_json();
		$this->generate_rss();
		$this->generate_sitemap();
		return $rc;
	}

	public function delete()
	{
		parent::delete();
		$this->gallery->deleteAll();
		$this->refresh_json();
		$this->generate_rss();
		$this->generate_sitemap();
	}

	public function deleteImage()
	{
		$this->gallery->delete();
		$this->update_gallery();
	}

	public function get_gallery()
	{
		return $this->gallery;
	}

	private function update_gallery()
	{
		// Not so nice hack to redo the gallery images in post file
		$post = json_decode(file_get_contents($this->target_path()));
		$post->gallery = $this->gallery_images();
		file_put_contents($this->target_path(), json_encode($post));
		$this->refresh_json();
	}

	public function reorder_images($old,$new)
	{
		$rc = $this->gallery->reorder_images($old,$new);
		$this->update_gallery();
		return $rc;
	}

	public function blog_featured_image($featured)
	{
		$rc = $this->gallery->update_featured($featured);
		$this->update_gallery();
		return $rc;
	}

	public function update_alt($alt)
	{
		$rc = $this->gallery->update_alt($alt);
		$this->update_gallery();
		return $rc;
	}

	private function refresh_json()
	{
		$this->delete_json();
		$this->process_data();
	}

	private function delete_json()
	{
		if (file_exists($this->json_file)) unlink($this->json_file);
	}

	public function toggle_featured()
	{
		$post = $this->get_contents();
		if ($post) {
			$post->featured = !$post->featured;
			if (file_put_contents($this->target_path(),json_encode($post))) {
				$this->delete_json();
				return $post;
			}
		}
		return false;
	}

	public function toggle_draft()
	{
		$post = $this->get_contents();
		if ($post) {
			$post->draft = !$post->draft;
			if (file_put_contents($this->target_path(),json_encode($post))) {
				$this->delete_json();
				return $post;
			}
		}
		return false;
	}

	public function generate_sitemap()
	{
		if (!isset($this->posts)) $this->process_data();

		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

		foreach ($this->filter_posts() as $post) {
  			$xml .= "<url><loc>$this->posturl$post->permalink</loc></url>\n";
		}

		$xml .= "\n</urlset>";
		file_put_contents($this->sitemap_file,$xml);
	}

	public function generate_rss()
	{
		if (!isset($this->posts)) $this->process_data();

		$feed_path = ltrim(str_replace($this->site_root,"",$this->rss_file),"/");
		$feed_url  = $this->baseurl.$feed_path;

		$feed = new RSS2;
		$feed->setTitle($this->rss_title);
		$feed->setLink($feed_url);
		$feed->setSelfLink($feed_url);
		$feed->setDescription($this->rss_description);

		foreach (array_slice($this->filter_posts(),0,self::MAXFEED) as $post) {
			$content = \Michelf\Markdown::defaultTransform($post->summary);

			// Need to add featured image to RSS feed
			if (isset($post->gallery) && is_array($post->gallery) && count($post->gallery) > 0) {
				$image = $post->gallery[0];
				$content .= "<img src=\"$this->baseurl$image->img\" alt=\"$image->alt\"/>";
			}

			$item = $feed->createNewItem();
			$item->setTitle($post->title);
			$item->setLink("$this->posturl$post->permalink");
			// $item->setAuthor($post->author);
			$item->setDescription($content);
			$item->setId($post->permalink);
			$item->setDate(date(DATE_RSS,$post->timestamp));
			$feed->addItem($item);
		}
		file_put_contents($this->rss_file,$feed->generateFeed());
	}

	private function build_db($posts)
	{
		$db = array(
			"author"   => array(),
			"category" => array(),
			"history"  => array(),
			"post"     => array(),
			"tag"      => array()
		);

		foreach ($posts as $post) {
			$summary = $this->post_summary($post);

			$db["post"][$post->permalink] = $summary;

			$year = date("Y",$post->timestamp);
			$month = date("m",$post->timestamp);
			$db["history"][$year][$month][] = $post->permalink;

			if (!$post->draft) { // Don't process author, tags and categories for drafts

				$authorId = $this->urlify_string($post->author);
				if (!empty($authorId)) $db["author"][$authorId][] = $post->permalink;

				foreach ($post->categories as $category) {
					$key = $this->urlify_string($category);
					if (!empty($key)) $db["category"][$key][] = $post->permalink;
				}
				foreach ($post->tags as $tag) {
					$key = $this->urlify_string($tag);
					if (!empty($key)) $db["tag"][$key][] = $post->permalink;
				}
			}
		}
		return $db;
	}

	public function process_data($id=false)
	{
		if (!file_exists($this->target_dir)) {
			$this->posts = array();
			return false;
		}

		foreach (new \DirectoryIterator($this->target_dir) as $fileInfo) {
			if ($fileInfo->isDot()) continue;

			$filename = $fileInfo->getFilename();
			if (strpos($filename,'.'.self::EXT) === false) continue;
			if ($filename === '.'.self::EXT) continue;

			$post = json_decode(file_get_contents("$this->target_dir/$filename"));
			if (gettype($post) !== 'object' || empty($post->permalink)) {
				$this->log_message("Warning: Ignoring malformed data in $this->target_dir/$filename");
				continue;
			}

			$this->posts[] = $post;
		}

		file_put_contents($this->json_file, json_encode($this->build_db($this->posts)));

		return $this->posts;
	}

	private function filter_attributes($permalinks,$db,$filter)
	{
		$keys = array("author","category","tag");

		foreach ($keys as $key) {
			if (empty($filter[$key])) continue;
			// split search terms by , or |
			$search_terms = preg_split('/(\||,)/',$filter[$key]);;
			$results = array();

			foreach ($search_terms as $term) {
				if (empty($term)) continue;
				$term = $this->urlify_string($term);
				if (isset($db->{$key}->{$term})) {
					$results = array_merge($results,$db->{$key}->{$term});
				}
			}

			$permalinks = array_filter($permalinks,function($var) use ($results){
				return in_array($var,$results);
			});
		}

		return $permalinks;
	}

	public function search_posts($query,$filter)
	{
		$query = $this->urlify_string($query);
		$db = json_decode(file_get_contents($this->json_file));

		$byPermalink = array();
		$byTitle = array();
		$byAuthor = array();
		$byCategory = array();
		$byTag = array();
		$bySummary = array();

		foreach ($db->post as $post) {
			if (is_array($filter)) {
				if (!empty($filter['draft'])) {
					// Draft Filter
					if ($filter["draft"] === "hide" && $post->draft) continue;
					if ($filter["draft"] === "only" && !$post->draft) continue;
				}
				if (!empty($filter['featured'])) {
					// Featured Filter
					if ($filter["featured"] === "hide" && $post->featured) continue;
					if ($filter["featured"] === "only" && !$post->featured) continue;
				}
				// Hide Filter
				if (isset($filter["date"])) {
					if ($filter["date"] === "past") {
						// Always include all posts from today
						if ($post->timestamp > strtotime('tomorrow midnight')) continue;
					}
					elseif ($filter["date"] === "future") {
						// Always include all posts from today
						if ($post->timestamp < strtotime('today midnight')) continue;
					}
				}
				if (!empty($filter['category'])) {
					if (strpos($this->urlify_string(implode(',',$post->categories)), $this->urlify_string($filter['category'])) === false) continue;
				}
				if (!empty($filter['tag'])) {
					if (strpos($this->urlify_string(implode(',',$post->tags)), $this->urlify_string($filter['tag'])) === false) continue;
				}
				if (!empty($filter['author'])) {
					if (strpos($this->urlify_string($post->author), $this->urlify_string($filter['author'])) === false) continue;
				}
			}
		    if     (strpos($this->urlify_string($post->permalink),$query) !== false){ $byPermalink[] = $post; }
		    elseif (strpos($this->urlify_string($post->title),$query) !== false){ $byTitle[] = $post; }
		    elseif (strpos($this->urlify_string($post->author),$query) !== false){ $byAuthor[] = $post; }
		    elseif (strpos($this->urlify_string(implode(',',$post->categories)),$query) !== false){ $byCategory[] = $post; }
		    elseif (strpos($this->urlify_string(implode(',',$post->tags)),$query) !== false){ $byTag[] = $post; }
		    elseif (strpos($this->urlify_string($post->summary),$query) !== false){ $bySummary[] = $post; }
		}
		return array_merge($byPermalink,$byTitle,$byAuthor,$byCategory,$byTag,$bySummary);
	}

	public function get_all_posts()
	{
		$db = $this->get_post_db();
		return array_values((array) $db->post);
	}

	public function get_post_db()
	{
		if (file_exists($this->json_file)) {
			return json_decode(file_get_contents($this->json_file));
		}
		return array();
	}

	public function filter_posts($filter=array())
	{
		if (gettype($filter) === 'string') $filter = json_decode($filter,true);
		// $this->log_message(json_encode($filter));
		$filter = array_merge(array(
			'all'      => false,
			'featured' => 'with',
			'draft'    => 'hide',
			'sort'     => 'new'
		), $filter);

		# Need to implement post return limits and pages

		if (isset($filter['permalink'])) {
			# return just that post details
		}

		# Process the data and create json if it does not exist
		if (!file_exists($this->json_file)) $this->process_data();

		if (!file_exists($this->json_file)) {
			$this->log_message('No blog posts found at '.$this->target_dir);
			return array();
		}

		$db = json_decode(file_get_contents($this->json_file));
		$posts = array();

		if ($filter["all"]) {
			$posts = array_values((array) $db->post);
		}
		else {
			// Limit posts by date first
			if (isset($filter["year"])) {
				$history = [];
				if (property_exists($db->history,$filter["year"])) {
					if (isset($filter["month"])) {
						$month = sprintf("%02d",$filter["month"]);
						if (property_exists($db->history->{$filter["year"]},$month)) {
							$history = $db->history->{$filter["year"]}->{$month};
						}
					}
					else {
						$history = $db->history->{$filter["year"]};
					}
				}
			}
			else {
				$history = $db->history;
			}
			// Flatten the array
			$it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($history));
			$history = iterator_to_array($it,false);

			// Filter the results
			$permalinks = $this->filter_attributes($history,$db,$filter);

			foreach ($permalinks as $permalink) {
				// Exclude a post
				if (!empty($filter['exclude']) && $filter['exclude'] == $permalink) continue;
				// Draft Filter
				if ($filter["draft"] === "hide" && $db->post->$permalink->draft) continue;
				if ($filter["draft"] === "only" && !$db->post->$permalink->draft) continue;
				// Featured Filter
				if ($filter["featured"] === "hide" && $db->post->$permalink->featured) continue;
				if ($filter["featured"] === "only" && !$db->post->$permalink->featured) continue;

				// Hide Filter
				if (isset($filter["date"])) {
					if ($filter["date"] === "past") {
						// Always include all posts from today
						if ($db->post->$permalink->timestamp > strtotime('tomorrow midnight')) continue;
					}
					elseif ($filter["date"] === "future") {
						// Always include all posts from today
						if ($db->post->$permalink->timestamp < strtotime('today midnight')) continue;
					}
				}

				# Not Filtered... Add Post
				$posts[] = $db->post->$permalink;
			}
		}

		// Sort posts
		if(!empty($posts)) {
			// Shuffle Posts
			if ($filter["sort"] === "shuffle") shuffle($posts);
			// Custom Sort Function
			usort($posts, function($a,$b) use (&$filter) {
				// Draft is highest priority
				if ($filter["draft"] === "top" && ($b->draft || $a->draft)) {
					return $b->draft - $a->draft;
				}
				// Featured is next
				if ($filter["featured"] === "top" && ($b->featured || $a->featured)) {
					return $b->featured - $a->featured;
				}
				// Sort by defined field
				switch ($filter["sort"]) {
				    case "abc":
				    	return strcmp($a->title,$b->title);
				    case "zyx":
				    	return strcmp($b->title,$a->title);
				    case "old":
						return $a->timestamp - $b->timestamp;
				    case "shuffle":
						return 0;
				}
				return $b->timestamp - $a->timestamp;
			});
		}

		return $posts;
	}

	public function to_date($timestamp)
	{
		return date('c',$timestamp);
	}

	public function to_data($filter=false)
	{
		return $this->filter_posts($filter);
	}

	public function gallery_images($permalink=false)
	{
		if ($permalink) {
			$options = array("target_dir" => "/gallery/blog/$this->slug/$permalink");
		    $gallery = new Gallery($this->permalink,$options);
		    return $gallery->process_data();
		}
	    return $this->gallery->process_data();
	}
}
