<?php
$root = realpath(dirname(__FILE__) . '/../../');
//echo $root;exit;
include_once ($root."/inc/config.inc.php"); // only fortesting purpose
include_once (INIT::$UTILS_ROOT."/utils.class.php"); //only for testing purpose
/*
  This code is copyrighted and property of Translated s.r.l.
  Should not be distrubuted.
  This is made available for Matecat partners for executing the field test.
  Thank you for keeping is confidential.
 */

class MyMemoryAnalyzer {

    private $url = "http://mymemory.translated.net";
    private $root_path = "api";

    public function __construct() {
        
    }

    public function fastAnalysys($segs_array) {
        if (!is_array($segs_array)){
            
            return null;
        }
        $json_segs=json_encode($segs_array);

        $d['fast'] = "1";
        $d['df'] = "matecat_array";
        $d['segs'] = $json_segs;
        $countwordReport = Utils::curl_post("$this->url/$this->root_path/analyze", $d);
        
        //echo $countwordReport;
        $reportDecoded=json_decode($countwordReport,true);
       //log::doLog('FAST ANALYSIS REPORT'.print_r($reportDecoded,true));
        return $reportDecoded;
    }

}
/*
$a = new MyMemoryAnalyzer();

$text = "WordPress Theming
MenuSkip to content
All Posts
Tutorials
Options Framework Plugin
Options Framework Theme
Portfolio Press
Portfolio+
Portfolio Post Type Plugin
Contact

Adding and Removing Tags on GitHub
Posted April 2, 2011 by Devin
For my projects on GitHub I like to have the most recent stable version tagged so that people have an easy download link to it. So when I go from versions 0.4 > 0.5 on project (for instance), I do this:

1) Delete the v0.4 tag locally: git tag -d v0.4
2) Delete the v0.4 tag on GitHub (which removes its download link): git push origin :v0.4
3) Add a new tag for the newest stable release: git tag -a v0.5 -m \"Version 0.5 Stable\"
4) Push the latest tag to GitHub (two dashes): git push --tags


About Devin

I'm a WordPress developer based in Austin, Texas. Follow my projects on GitHub, or more general WordPress ramblings as @devinsays on twitter.
 8 Comments |  Posted in Tutorials |  Tagged github
8 thoughts on “Adding and Removing Tags on GitHub”

Ryan wrote:
October 19, 2011 at 11:56 am
Why do you delete the older tags? Do you not want a record of them?

Reply
Devin wrote:
October 19, 2011 at 6:58 pm
Yeah, I don’t think folks necessarily need download links for all 9 versions of certain plugins. I just keep the most recent stable version tagged.

Reply
Ryan wrote:
October 21, 2011 at 1:12 pm
What if someone has an issue with version v1.0 and the latest stable is v1.5? Wouldn’t it be nice to just do a git checkout v1.0 to identify the bug, fix it and merge it into the latest?

SeanJA wrote:
January 16, 2013 at 7:38 pm
Today I needed to delete a tag because I forgot to push something before creating the tag…. so I guess that is a case?

Reply
Trent wrote:
November 2, 2011 at 4:48 am
Thanks for this, helped out a lot.

Reply
Ben wrote:
December 9, 2011 at 4:50 pm
Ha! Just found a use for this, thanks! (I accidently tagged a repo before committing some changes, so the tagged version wasn’t correct. Just deleted and then re-tagged it – quite handy!)

Reply
Grayson wrote:
May 31, 2012 at 6:19 am
Thanks this was useful. Might be you’ll want to fix #4 to


git push --tags

Reply
Chris wrote:
August 26, 2012 at 4:23 am
Step 2 should be replaced by

git push origin :refs/tags/v0.4

See http://nathanhoad.net/how-to-delete-a-remote-git-tag

Reply
Leave a Reply

Your email address will not be published. Required fields are marked *

Name * 

Email * 

Website

Comment 
You may use these HTML tags and attributes:
<a href=\"\" title=\"\"> <abbr title=\"\"> <acronym title=\"\"> <b> <blockquote cite=\"\"> <cite> <code> <del datetime=\"\"> <em> <i> <q cite=\"\"> <strike> <strong>


 Notify me of followup comments via e-mail

Search
Links
@devinsays
GitHub
Managed WordPress Hosting
Cheap WordPress Hosting
Powered by WordPress | Managed WordPress Hosting";


$id_customer = "";
$res = $a->fastAnalysys($text);

print_r($res);
 * 
 */
?>