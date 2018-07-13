<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/07/2018
 * Time: 14:52
 */

namespace Email;

class CommentMentionEmail extends BaseCommentEmail {

    protected $title = "New mention on a comment";

    protected function _getTemplateVariables() {
        $vars = parent::_getTemplateVariables();
        $var['title'] = $this->title;
        $vars['action'] = "mentioned you in a comment on";
        return $vars;
    }

}
