<?php

namespace Email;

class CommentEmail extends BaseCommentEmail {

    protected $title = "New comment";

    protected function _getTemplateVariables() {
        $vars = parent::_getTemplateVariables();
        $var['title'] = $this->title;
        $vars['action'] = "commented on";
        return $vars;
    }

}