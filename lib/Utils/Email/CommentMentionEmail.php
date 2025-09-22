<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/07/2018
 * Time: 14:52
 */

namespace Utils\Email;

class CommentMentionEmail extends BaseCommentEmail {

    protected ?string $title = "New mention on a comment";

    protected function _getTemplateVariables(): array {
        $vars                 = parent::_getTemplateVariables();
        $vars[ 'title' ]       = $this->title;
        $vars[ 'action' ]     = "mentioned you in a comment on";
        $vars[ 'id_segment' ] = $this->comment->id_segment;

        return $vars;
    }

}
