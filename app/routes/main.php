<?php

class DB
{
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
            //$db = $m->test;
            $db = $m->selectDB($db);
            return $db;
        } catch (MongoConnectionException $e) {
            die('Error connecting to MongoDB server');
        } catch (MongoException $e) {
            die('Error: ' . $e->getMessage());
        }
    }

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
    $db = DB::connectDB();
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

    $dropbox = new \TijsVerkoyen\Dropbox\Dropbox($app->config('dropbox.key'), $app->config('dropbox.secret'));
    $blog = new Blog();
    $db = DB::connectDB();
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

    $response = $dropbox->metadata("/", 10000, $hash, true, true, null, null, true);

    $num = $blog->syncAllBlogPosts($dropbox, $response);

    $user['hash'] = $response['hash'];
    $collection->save($user);

    $app->redirect($app->config('base.url') . '/admin?s=synced&n=' . $num);

});

$app->post('/getdbposts', function() use ($app) {
    $dropbox = new \TijsVerkoyen\Dropbox\Dropbox($app->config('dropbox.key'), $app->config('dropbox.secret'));
    $db = DB::connectDB();
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
    $db = DB::connectDB();
    $collection = $db->posts;
    $articles = $collection->find();
    $total = $articles->count();
    $pages = Blog::getPages(1, $total, $app->config('article.limit'));
    $articles->sort(array('timestamp' => -1));
    $articles->limit($app->config('article.limit'));

    $app->render('main.html', array('articles' => $articles, 'pages' => $pages));
})->name('/');

$app->get('/page/:page', function ($page) use ($app) {
    $db = DB::connectDB();
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
    $db = DB::connectDB();

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
    $db = DB::connectDB();

    $collection = $db->posts;
    $post = $collection->find(array("tags" => array('$in' => array("{$page}"))));

    if (!$post) {
        $app->notFound();
    }
    return $app->render('main.html', array('articles' => $post));
});
