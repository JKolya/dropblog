# Dropblog


Dropblog is a simple blog engine for AppFog created with Slim Framework using Markdown files in Dropbox. I wanted something similar to scriptogr.am but that I could host on AppFog. This is what I've got so far.

## Dependencies

* [Dropbox](https://github.com/BenTheDesigner/Dropbox)
* [Slim Framework](http://github.com/codeguy/Slim)
* [Slim Extras](http://github.com/codeguy/Slim-Extras)
* [Markdown](http://github.com/dflydev/dflydev-markdown)


## Installation

As of right now you probably shouldn't...

##Posting

Posts should can have an `md` or `txt` file extension. 

Files should start with the post info two spaces and then the content

    Date: YYYY-MM-DD
    Title: Post Title
    Author: 
    Tags: comma separated
    
    #post content

