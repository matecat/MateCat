<?php

namespace Email;

class CommentEmail extends BaseCommentEmail {

    protected $title = "New comment";

    protected function _getTemplateVariables() {
        $vars = parent::_getTemplateVariables();
        $var['title'] = $this->title;
        $vars['action'] = "commented on";
        $vars['id_segment'] = $this->comment->id_segment;
        return $vars;
    }

}