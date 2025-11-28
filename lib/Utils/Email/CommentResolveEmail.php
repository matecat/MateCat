<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/07/2018
 * Time: 14:53
 */

namespace Utils\Email;

class CommentResolveEmail extends BaseCommentEmail
{

    protected ?string $title = "Thread resolved";

    protected function _getTemplateVariables(): array
    {
        $vars                 = parent::_getTemplateVariables();
        $vars[ 'title' ]      = $this->title;
        $vars[ 'action' ]     = "resolved a thread that you are following on";
        $vars[ 'id_segment' ] = $this->comment->id_segment;

        return $vars;
    }

}
