# Dropblog


Dropblog is a simple blog engine for AppFog created with Slim Framework using Markdown files in Dropbox. I wanted something similar to scriptogr.am but that I could host on AppFog. This is what I've got so far.

## Dependencies

* [tijsverkoyen/dropbox](https://github.com/tijsverkoyen/Dropbox)
* [Slim Framework](http://github.com/codeguy/Slim)
* [Slim Extras](http://github.com/codeguy/Slim-Extras)
* [Markdown](http://github.com/dflydev/dflydev-markdown)


## Installation

Clone repo

    git clone https://github.com/JKolya/dropblog.git

Install dependencies with composer

    composer install

Change settings in `app\config.php`

Upload to AppFog

##Posting

Posts should can have an `md` or `txt` file extension.

Files should start with the post info two spaces and then the content

    Date: YYYY-MM-DD
    Title: Post Title
    Author:
    Tags: comma separated

    #post content