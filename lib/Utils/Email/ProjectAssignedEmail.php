<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 16:13
 */

namespace Email;


class ProjectAssignedEmail extends AbstractEmail {

    protected $user ;
    protected $project ;
    protected $assignee ;
    protected $title ;

    public  function __construct(\Users_UserStruct $user, \Projects_ProjectStruct $project, \Users_UserStruct $assignee) {
        $this->user = $user ;
        $this->project = $project ;
        $this->assignee = $assignee ;
        $this->title = "You've been assigned a project" ;

        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Project/project_assigned_content.html');
    }

    protected function _getTemplateVariables()
    {
        return array(
            'user'      => $this->assignee->toArray(),
            'sender'    => $this->user->toArray(),
            'project'   => $this->project->toArray(),
            'project_url' => \Routes::analyze([
                'project_name' => $this->project->name,
                'id_project'   => $this->project->id,
                'password'     => $this->project->password
            ])
        );
    }

    protected function _getLayoutVariables()
    {
        $vars = parent::_getLayoutVariables();
        $vars['title'] = $this->title ;

        return $vars ;
    }

    public function send()
    {
        $recipient  = array( $this->assignee->email, $this->assignee->fullName() );

        $this->doSend( $recipient, $this->title ,
            $this->_buildHTMLMessage(),
            $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

}