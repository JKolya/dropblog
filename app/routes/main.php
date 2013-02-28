<?php

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
            array_walk($post_meta_data['tags'], 'aTrim');
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

function aTrim(&$item)
{
    $item = trim((string) $item);
}

/*
 * create a url slug from article title
 *
 * @param string
 * @return string
 */

function createSlug($string)
{
    $slug = strtolower((string) $string);
    $slug = trim($slug);
    $slug = preg_replace("/[^a-zA-Z0-9\s]/", "", $slug); //remove non alpha-num characters
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $slug); //replace spaces with dashes
    return $slug;
}

/*
 * grab the contents of the markdown file
 *
 * @param array $contents
 * @param object $dropbox
 * @return array
 */

function getFileContents($contents, $dropbox)
{

    $file = $response = $dropbox->filesGet($contents['path'], null, true);
    $file['data'] = base64_decode($file['data']);
    $content = explode("\n\n", $file["data"]);

    $raw_meta = array_shift($content);
    $data['meta'] = getMetaData($raw_meta);
    $data['content'] = implode("\n\n", $content);

    return $data;
}

/*
 * save the post to the db
 *
 * @param object $dropbox
 * @param array $contents
 * @param object $collection
 * @param object $markdownParser
 *
 */

function updatePost($dropbox, $contents, $collection, $markdownParser)
{

    $criteria = array(
        'path' => $contents['path'],
      );
    $post = $collection->findOne($criteria);

    //get the contents of the file from Dropbox
    $post_content = getFileContents($contents, $dropbox);

    if ($post_content['meta']['author'] = "") {
        $post_content['meta']['author'] = "me";
    }

    $post['title'] = $post_content['meta']['title'];
    $post['author'] = $post_content['meta']['author'];
    $post[ 'timestamp'] = $post_content['meta']['date'];
    $post['slug' ]= createSlug($post_content['meta']['title']);
    $post['content'] = $markdownParser->transformMarkdown($post_content['content']);
    $post['path'] = $contents['path'];
    $post['tags'] = $post_content['meta']['tags'];
    $post['modified'] = $contents['modified'];

    $collection->save($post);
}

/*
 * find tag in an array
 *
 * @param array $json
 * @param array $tag
 *
 * @return mixed
 */

function searchJSON($json, $tag)
{
    $json =  objectToArray($json);
    foreach ($json as $arr) {
        if (in_array($tag, $arr)) {
            return $arr;
        }
    }
    return false;
}

/*
 * convert an object to an array
 */

function objectToArray($object)
{
    if (!is_object($object) && !is_array($object)) {
        return $object;
    }
    if (is_object($object)) {
        $object = get_object_vars($object);
    }
    return array_map('objectToArray', $object);
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
    $markdownParser = new dflydev\markdown\MarkdownParser();

    //count the number of posts synced
    $number_of_posts = (int) 0;

    $db = connectDB();
    $collection = $db->posts;
    $posts = $collection->find();

    //Find all posts from DropBox and add to Mongo
    foreach ($meta_data['contents'] as $contents) {
        //file extentions
        $ext = substr(strrchr($contents['path'], '.'), 1);
        //only get files with extentions md, txt and markdown
        if (($ext == "md") || ($ext == "txt") || ($ext == "markdown")) {
            try {
                    updatePost($dropbox, $contents, $collection, $markdownParser);
            } catch (Exception $e) {
                echo 'Exception caught: ' . $e->getMessage() . "\n";
            }
            $number_of_posts++;
        }
    }

    //check posts in Mongo against Dropbox for deleted files
    foreach ($posts as $post) {
        $arr = searchJSON($meta_data['contents'], $post['path']);
        if (!$arr || $arr['is_dir'] == 1) {
            $r = $collection->remove(array( 'path' => $post['path'],));
        }
    }
    return $number_of_posts;
}

/*
 * Connect to Mongo db
 *
 * @return object
 */
function connectDB()
{
    //For AppFog's Mongo service
    $services_json = json_decode(getenv("VCAP_SERVICES"), true);
    $mongo_config = $services_json["mongodb-1.8"][0]["credentials"];
    $username = $mongo_config["username"];
    $password = $mongo_config["password"];
    $hostname = $mongo_config["hostname"];
    $port = $mongo_config["port"];
    $db = $mongo_config["db"];
    $name = $mongo_config["name"];

    //$connect = "mongodb://localhost/test";
    $connect = "mongodb://$username:$password@$hostname:$port/$db";

    try {
        $m = new Mongo($connect);
        // $db = $m->test;
        $db = $m->selectDB($db);
        return $db;
    } catch (MongoConnectionException $e) {
        die('Error connecting to MongoDB server');
    } catch (MongoException $e) {
        die('Error: ' . $e->getMessage());
    }

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

/*
 *
 * Check that the email and password match
 *
 * @param object $app
 * @param string $email
 * @param string $pass
 *
 * @return bool
 */
function authenticate($app, $email, $pass)
{
    $db = connectDB();
    $collection = $db->users;
    $user = $collection->findOne(array(
                'email' => $email,
                'password' => $pass
              ));
    if ($user) {
        return true;
    } else {
        return false;
    }
}

//admin section
//needs to be password protected
$app->get('/admin', function() use ($app) {
    if (!isset($_SESSION['user'])) {
        $app->flash('error', 'Login required');
        $app->redirect($app->config('base.url') . '/login');
    }

    if (isset($_GET['s'])) {
        $data['synced'] = true;
        $data['num'] = $_GET['n'];
    } else {
        $data['synced'] = false;
    }
    $app->render('admin.html', array('data' => $data));
})->name('admin');


$app->get('/getdbposts', function() use ($app) {
     echo "<pre>";
    $dropbox = new \TijsVerkoyen\Dropbox\Dropbox($app->config('dropbox.key'), $app->config('dropbox.secret'));
    $db = connectDB();
    $collection = $db->users;
    $user = $collection->findOne(array(
                'email' => $_SESSION['user']
              ));

    if (isset($_GET['authorize'])) {
        $dropbox->setOAuthTokenSecret($_SESSION['oauth_token_secret']);
        $dropbox->setOAuthToken($_SESSION['oauth_token']);
        $response = $dropbox->oAuthAccessToken($_SESSION['oauth_token']);
        $user['token'] = $response['oauth_token'];
        $user['secret_token'] = $response['oauth_token_secret'];

        $collection->save($user);
    }

    $dropbox->setOAuthTokenSecret($user['secret_token']);
    $dropbox->setOAuthToken($user['token']);
    if (isset($user['hash'])) {
        $hash = $user['hash'];
    } else {
        $hash = false;
    }

    $response = $dropbox->metadata("/", 10000, $hash, true, false, null, null, true);
    $num = syncAllBlogPosts($dropbox, $response);
    $user['hash'] = $response['hash'];
    $collection->save($user);

    $app->redirect($app->config('base.url') . '/admin?s=synced&n=' . $num);

});
$app->post('/getdbposts', function() use ($app) {
    $dropbox = new \TijsVerkoyen\Dropbox\Dropbox($app->config('dropbox.key'), $app->config('dropbox.secret'));
    $db = connectDB();
    $collection = $db->users;
    $user = $collection->findOne(array(
                'email' => $_SESSION['user']
              ));

    if (!isset($user['token'])) {
          $response = $dropbox->oAuthRequestToken();
          $_SESSION['oauth_token_secret'] = $response['oauth_token_secret'];
          $_SESSION['oauth_token'] = $response['oauth_token'];
          $dropbox->oAuthAuthorize($response['oauth_token'], 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .'?authorize=true');
    }
    $app->redirect($app->config('base.url') . '/getdbposts');
});

$app->get('/logout', function() use ($app) {
    unset($_SESSION['user']);
    $app->view()->setData('user', null);
    $app->redirect($app->config('base.url') . '/');
});

$app->get('/login', function () use ($app) {
    $flash = $app->view()->getData('flash');
    $app->render('login.html');
});

$app->post('/login', function() use ($app) {
    $email = $app->request()->post('email');
    $pass = $app->request()->post('password');

    if (authenticate($app, $email, $pass)) {
        $_SESSION['user'] = $email;
        $app->redirect($app->config('base.url') . '/admin');
    }
    $app->redirect($app->config('base.url') . '/login');
});

//main blog page
$app->get('/', function () use ($app) {
    $db = connectDB();
    $collection = $db->posts;
    $articles = $collection->find();
    $total = $articles->count();
    $pages = getPages(1, $total, $app->config('article.limit'));
    $articles->sort(array('timestamp' => -1));
    $articles->limit($app->config('article.limit'));

    $app->render('main.html', array('articles' => $articles, 'pages' => $pages));
})->name('/');

$app->get('/page/:page', function ($page) use ($app) {
    $db = connectDB();
    $collection = $db->posts;
    $articles = $collection->find();
    $total = $articles->count();
    $pages = getPages($page, $total, $app->config('article.limit'));

    $articles->skip($pages['start']);

    $articles->sort(array('timestamp' => -1));
    $articles->limit($app->config('article.limit'));

    $app->render('main.html', array('articles' => $articles, 'pages' => $pages));
})->name('/');

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

$app->get('/tag/:page', function ($page) use ($app) {
    $db = connectDB();

    $collection = $db->posts;
    $post = $collection->find(array("tags" => array('$in' => array("{$page}"))));

    if (!$post) {
        $app->notFound();
    }
    return $app->render('main.html', array('articles' => $post));
});
