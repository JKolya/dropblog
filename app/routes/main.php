<?php

/*
 * gets meta data from the top of the file
 * 
 */

function getMetaData($data) {
    $dataArray = explode("\n", $data);

    foreach ($dataArray as $data) {
        $meta = preg_split("/:/", $data);
        if (strtolower($meta[0]) == 'title') {
            //set article title
            $metaData['title'] = trim($meta[1]);
        } elseif (strtolower($meta[0]) == 'date') {
            //set date format Mon, 18 Feb 2013 00:13:37 +0000
            $metaData['date'] = date('Y-m-d H:i:s', strtotime(trim($meta[1])));
        } elseif (strtolower($meta[0]) == 'author') {
            //set article author
            $metaData['author'] = trim($meta[1]);
        }
    }
    return $metaData;
}

/*
 * create a url slug from article title
 * 
 */

function create_slug($string) {
    $slug = strtolower($string);
    $slug = trim($slug);
    $slug = preg_replace("/[^a-zA-Z0-9\s]/", "", $slug); //remove non alpha-num characters
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $slug); //replace spaces with dashes
    return $slug;
}

/*
 * grab the contents of the markdown file
 * 
 */

function getFileContents($contents, $dropbox) {
    $outFile = false;
    $file = $dropbox->getFile($contents->path, $outFile);

    $content = explode("\n\n", $file["data"]);

    $rawMeta = array_shift($content);
    $data['meta'] = getMetaData($rawMeta);
    $data['content'] = implode("\n\n", $content);

    return $data;
}

/*
 * create a connection to dropbox
 * 
 */

function connectDropbox($app) {
    $encrypt_key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXkksf';

    $callback = $app->config('base.url') . '/admin';
    $encrypter = new \Dropbox\OAuth\Storage\Encrypter($encrypt_key);
    $storage = new \Dropbox\OAuth\Storage\Session($encrypter);

    $key = $app->config('dropbox.key');
    $secret = $app->config('dropbox.secret');
    $OAuth = new \Dropbox\OAuth\Consumer\Curl($key, $secret, $storage, $callback);
    $dropbox = new \Dropbox\API($OAuth);

    return $dropbox;
}

/*
 * save the post to the db
 * 
 */

function updatePost($dropbox, $contents, $collection, $markdownParser) {
    
    $criteria = array(
        'path' => $contents->path,
      );
    $post = $collection->findOne($criteria);

    //get the contents of the file from Dropbox
    $postContent = getFileContents($contents, $dropbox);
    if ($postContent['meta']['author'] = "") {
        $postContent['meta']['author'] = "me";
    }
    
    $post['title'] = $postContent['meta']['title'];
    $post['author'] = $postContent['meta']['author'];
    $post[ 'timestamp'] = $postContent['meta']['date'];
    $post['slug' ]= create_slug($postContent['meta']['title']);
    $post['content'] = $markdownParser->transformMarkdown($postContent['content']);
    $post['path'] = $contents->path;
    $post['modified'] = $contents->modified;

    $collection->save($post);
}

/*
 * find tag in an array
 */

function searchJSON($json, $tag) {
    //make sure an array is passed
    $json =  objectToArray($json);
    foreach ($json as $arr) {
        if (in_array($tag, $arr)) {
            return $arr;
        }
    }
    return false;
}

function objectToArray( $object ) {
    if( !is_object( $object ) && !is_array( $object ) ) {
        return $object;
    }
    if( is_object( $object ) ) {
        $object = get_object_vars( $object );
    }
    return array_map( 'objectToArray', $object );
}

/*
 * get all blog posts from Dropbox files
 * 
 */

function syncAllBlogPosts($dropbox) {
    $markdownParser = new dflydev\markdown\MarkdownParser();

    //count the number of posts synced
    $numberOfPosts = 0;

    $metaData = getDropboxMeta($dropbox);

    $db = connectDB();
    $collection = $db->posts;
    $posts = $collection->find();
    
    //Find all posts from DropBox and add to Mongo
    foreach ($metaData["body"]->contents as $contents) {
        //file extentions
        $ext = substr(strrchr($contents->path, '.'), 1);
        //only get files with extentions md, txt and markdown
        if (($ext == "md") || ($ext == "txt") || ($ext == "markdown")) {
             try {
                    updatePost($dropbox, $contents, $collection, $markdownParser);
            } catch (Exception $e) {
                echo 'Exception caught: ' . $e->getMessage() . "\n";
            }
            $numberOfPosts++;
        }
    }
    
    //check posts in Mongo against Dropbox for deleted files
    foreach ($posts as $post) {
        $arr = searchJSON($metaData['body']->contents,  $post['path']) ;
        if (!$arr || $arr['is_dir'] == 1) {
            $r = $collection->remove(array( 'path' => $post['path'],));
        }
    }
    return $numberOfPosts;
}

/*
 * get meta information on the Dropbox folder
 * 
 */
function getDropboxMeta($dropbox) {
    $path = '';
    $hash = 'a3f5131bf030aa8fc826b047bb731aac';
    $limit = 10000;
    try {
        $metaData = $dropbox->metaData($path, null, $limit, $hash);
    } catch (Exception $e) {
        echo 'Exception caught: ' . $e->getMessage() . "\n";
    }
    return $metaData;
}


function connectDB() {
    //For AppFog's Mongo service
    $services_json = json_decode(getenv("VCAP_SERVICES"),true);
    $mongo_config = $services_json["mongodb-1.8"][0]["credentials"];
    $username = $mongo_config["username"];
    $password = $mongo_config["password"];
    $hostname = $mongo_config["hostname"];
    $port = $mongo_config["port"];
    $db = $mongo_config["db"];
    $name = $mongo_config["name"];
    
    //$connect = "mongodb://localhost/test";
    $connect = "mongodb://$username:$password@$hostname:$port/$db";
    
    try{
        $m = new Mongo( $connect );
        //$db = $m->test;
        $db = $m->selectDB($db);
        return $db;
    } 
    catch (MongoConnectionException $e) 
    {
        die('Error connecting to MongoDB server');
    } 
    catch (MongoException $e) 
    {
        die('Error: ' . $e->getMessage());
    }
    
}

//main blog page
$app->get('/', function () use ($app) {
    $db = connectDB();
    $collection = $db->posts;
    $articles = $collection->find();
    $articles->sort(array('timestamp' => -1));
    $articles->limit($app->config('article.limit'));

    $app->render('main.html', array('articles' => $articles));
})->name('/');

//admin section
//needs to be password protected
$app->get('/admin', function() use ($app) {
    
    if (isset($_GET['s'])) {
        $data['synced'] = true;
        $data['num'] = $_GET['n'];
    } else {
        $data['synced'] = false;
    }
    $app->render('admin.html', array('data' => $data));
})->name('admin');


//get posts from Dropbox
$app->post('/getdbmeta', function () use ($app) {
    $dropbox = connectDropbox($app); //dropbox connection

    $num = syncAllBlogPosts($dropbox);

    $app->redirect($app->config('base.url') . '/admin?s=synced&n=' . $num);
});

//blog post by url slug
$app->get('/:page', function ($page) use ($app) {
    $db = connectDB();

    $collection = $db->posts;
    $post = $collection->findOne(array(
                'slug' => $page,
              ));
     
    if (!$post) {
        $app->notFound();
    }
    return $app->render('blog_view.html', array('article' => $post));
});