<?php

namespace Email;

class CommentEmail extends BaseCommentEmail {

    protected $title = "New comment";

<<<<<<< Updated upstream
    protected function _getTemplateVariables() {
        $vars = parent::_getTemplateVariables();
        $var['title'] = $this->title;
        $vars['action'] = "commented on";
        $vars['id_segment'] = $this->comment->id_segment;
=======
    protected function _getTemplateVariables(): array {
        $vars                 = parent::_getTemplateVariables();
        $vars[ 'title' ]       = $this->title;
        $vars[ 'action' ]     = "commented on";
        $vars[ 'id_segment' ] = $this->comment->id_segment;

>>>>>>> Stashed changes
        return $vars;
    }

}