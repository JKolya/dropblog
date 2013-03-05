<?php
require '../app/Blog.php';

class BlogTest extends PHPUnit_Framework_TestCase
{

    protected function setUp() {
        $this->blog = new Blog();
    }

    function testgetMetaData() {
        $string = "Title: Hello World!\nDate: 2013-02-18\nAuthor: Anonymous\nTags: tag1";

        $actual = $this->blog->getMetaData($string);
        $this->assertArrayHasKey('title', $this->blog->getMetaData($string));
        $expected = array(
                        'title' => 'Hello World!',
                        'date' => '2013-02-18 00:00:00',
                        'author' => 'Anonymous',
                        'tags' => array( 'tag1')
                        );
        $this->assertEquals($expected, $this->blog->getMetaData($string));
    }

    public function testcreateSlug() {
        $expected = "hello-world";

        $actual= $this->blog->createSlug("hello world ");
        $this->assertEquals($expected, $actual);
        $actual= $this->blog->createSlug("   hello    world  ");
        $this->assertEquals($expected, $actual);
        $actual= $this->blog->createSlug("hello  world!.,/!@#$%^&*()");
        $this->assertEquals($expected, $actual);
    }

    public function testgetFileContents(){
            $apiResponse = array( 'data' => 'VGl0bGU6IEhlbGxvIFdvcmxkIQpEYXRlOiAyMDEzLTAyLTE4CkF1dGhvcjogQW5vbnltb3VzClRhZ3M6IHRhZzEKCkxvcmVtIGlwc3VtIGRvbG9yIHNpdCBhbWV0LCBjb25zZWN0ZXR1ciBhZGlwaXNjaW5nIGVsaXQuCg==');
            $api = $this->getMock('Blog', array('fileGet'));
            $api->expects($this->any())
                    ->method('fileGet')
                    ->will($this->returnValue($apiResponse));

            $path = array('path' => 'hello_world.markdown');

            $actual = $api->getFileContents($path);
            $expected = array(
                'meta' => array(
                        'title' => 'Hello World!',
                        'date' => '2013-02-18 00:00:00',
                        'author' => 'Anonymous',
                        'tags' => array('tag1')
                        ),
                'content' =>   "Lorem ipsum dolor sit amet, consectetur adipiscing elit.");
            $this->assertArrayHasKey('meta', $actual);
            $this->assertArrayHasKey('content', $actual);
            $this->assertEquals($expected, $actual);
    }

    function testgetPages() {

    }

}