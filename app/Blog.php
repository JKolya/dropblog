<?php

class Blog
{

    /*
     * Dropbox object
     *
     * @var object
     */
    public $dropbox;

    /*
     * Database collection object
     *
     * @var object
     */
    public $collection;

    /*
     * gets meta data from the top of the file
     *
     * @param array
     * @return array
     */
    function getMetaData($data)
    {
        $data_array = explode("\n", $data);
        $post_meta_data = array();

        foreach ($data_array as $data) {
            $meta = preg_split("/:/", $data);
            if (strtolower($meta[0]) == 'title') {
                //set article title
                $post_meta_data['title'] = trim($meta[1]);
            } elseif (strtolower($meta[0]) == 'date') {
                //set date format Mon, 18 Feb 2013 00:13:37 +0000
                $post_meta_data['date'] = date('Y-m-d H:i:s', strtotime(trim($meta[1])));
            } elseif (strtolower($meta[0]) == 'author') {
                //set article author
                $post_meta_data['author'] = trim($meta[1]);
            } elseif (strtolower($meta[0]) == 'tags') {
                $post_meta_data['tags']  = explode(",", $meta[1]);
                //array_walk($post_meta_data['tags'], 'Blog::aTrim');
            }
        }
        return $post_meta_data;
    }

    /*
     * trim string
     *
     * @param string $item
     * @return string
     */
    public static function aTrim(&$item)
    {
        $item = trim((string) $item);
    }

    /*
     * create a url slug from article title
     *
     * @param string
     * @return string
     */
    public function createSlug($string)
    {
        $slug = strtolower((string) $string);
        $slug = trim($slug);
        $slug = preg_replace("/[^a-zA-Z0-9\s]/", "", $slug); //remove non alpha-num characters
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $slug); //replace spaces with dashes
        return $slug;
    }

    public function fileGet($contents)
    {
        $arr = $this->dropbox->filesGet($contents, null, true);
        return $arr;
    }

    /*
     * grab the contents of the markdown file
     *
     * @param array $contents
     *
     * @return array
     */
    public function getFileContents($contents)
    {
        $file = $this->fileGet($contents['path']);
        $file['data'] = base64_decode($file['data']);
        $content = explode("\n\n", $file["data"]);
        $raw_meta = array_shift($content);
        $data['meta'] = $this->getMetaData($raw_meta);
        $data['content'] = implode("\n\n", $content);
        return $data;
    }

    /*
     * Save the post to the db
     *
     * @param array $contents
     *
     */
    function updatePost($contents)
    {
        $markdownParser = new dflydev\markdown\MarkdownParser();
        $criteria = array(
            'path' => $contents['path'],
          );
        $post = $this->collection->findOne($criteria);

        //get the contents of the file from Dropbox
        $post_content = $this->getFileContents($contents);

        if ($post_content['meta']['author'] = "") {
            $post_content['meta']['author'] = "me";
        }

        $post['title'] = $post_content['meta']['title'];
        $post['author'] = $post_content['meta']['author'];
        $post[ 'timestamp'] = $post_content['meta']['date'];
        $post['slug' ]= $this->createSlug($post_content['meta']['title']);
        $post['content'] = $markdownParser->transformMarkdown($post_content['content']);
        $post['path'] = $contents['path'];
        $post['tags'] = $post_content['meta']['tags'];
        $post['modified'] = $contents['modified'];

        $this->collection->save($post);
    }

    /*
     * Sync blog posts with Dropbox
     * Get all .md, .txt and .markdown files from Dropbox and save or update the local storage
     * Check local storage for removed files from Dropbox
     *
     * @param object $dropbox Dropbox API connection
     * @param array $meta_data The meta data from the Dropbox folder listing the files
     *
     * @return int $number_of_posts Counts how many posts have been synced
     */
    function syncAllBlogPosts($dropbox, $meta_data)
    {

        $this->dropbox = $dropbox;
        //count the number of posts synced
        $number_of_posts = (int) 0;

        $db = DB::connectDB();
        $this->collection = $db->posts;
        $posts = $this->collection->find();

        //Find all posts from DropBox and add to Mongo
        foreach ($meta_data['contents'] as $contents) {
            //file extentions
            $ext = substr(strrchr($contents['path'], '.'), 1);
            //only get files with extentions md, txt and markdown
            if (($ext == "md") || ($ext == "txt") || ($ext == "markdown")) {
                if(isset($contents['is_deleted'])) {
                    $this->deletedRemovedFiles($contents);
                } else {
                    $this->updatePost($contents);
                }
                $number_of_posts++;
            }
        }
        return $number_of_posts;
    }

    /*
     * Delete records from db of files removed from Dropbox
     *
     * @parm array $contents
     */
    function deletedRemovedFiles($contents)
    {
        $criteria = array('path' => $contents['path']);
        $r = $this->collection->remove($criteria);
    }

    /*
    * Get number of pages in order to paginate
    *
    * @param int $page
    * @param int $total
    * @param int $per_page
    *
    * @return array
    */
   function getPages($page, $total, $per_page)
   {

       $calc = $per_page * $page;
       $total_page = ceil($total / $per_page);
       $pages['start'] = $calc - $per_page;

       if ($page > 1) {
           $pages['next'] = $page - 1;
       }

       if ($page < $total_page) {
           $pages['previous'] = $page + 1;
       }
       return $pages;
   }

}