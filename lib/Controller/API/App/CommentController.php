<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Comments_CommentDao;
use Comments_CommentStruct;
use Database;
use Jobs_JobDao;
use Users_UserDao;

class CommentController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function getRange()
    {
        $data = [];
        $request = $this->validateTheRequest();

        $struct                = new Comments_CommentStruct();
        $struct->id_job        = $request[ 'id_job' ];
        $struct->first_segment = $request[ 'first_seg' ];
        $struct->last_segment  = $request[ 'last_seg' ];

        $commentDao = new Comments_CommentDao( Database::obtain() );

        $data[ 'entries' ] = [
            'comments' => $commentDao->getCommentsForChunk( $request['job'] )
        ];

        $data[ 'user' ] = [
            'full_name' => $this->user->fullName()
        ];

        return $this->response->json([
            "data" => $data
        ]);
    }

    public function resolve()
    {
        $this->prepareCommentData();

        $commentDao = new Comments_CommentDao( Database::obtain() );
        $new_record = $commentDao->saveComment( $this->struct );

        foreach ( $this->users_mentioned as $user_mentioned ) {
            $mentioned_comment = $this->prepareMentionCommentData( $user_mentioned );
            $commentDao->saveComment( $mentioned_comment );
        }

        $commentDao->destroySegmentIdCache($this->__postInput[ 'id_segment' ]);

        $this->enqueueComment();
        $this->users = $this->resolveUsers();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ] = [ $this->payload ];
        $this->appendUser();
    }

    public function create()
    {}

    public function delete()
    {}

    /**
     * @return array|\Klein\Response
     * @throws \ReflectionException
     */
    private function validateTheRequest()
    {
        $id_client = filter_var( $this->request->param( 'id_client' ), FILTER_SANITIZE_STRING );
        $username = filter_var( $this->request->param( 'username' ), FILTER_SANITIZE_STRING );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $source_page = filter_var( $this->request->param( 'source_page' ), FILTER_SANITIZE_NUMBER_INT );
        $revision_number = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );
        $first_seg = filter_var( $this->request->param( 'first_seg' ), FILTER_SANITIZE_NUMBER_INT );
        $last_seg = filter_var( $this->request->param( 'last_seg' ), FILTER_SANITIZE_NUMBER_INT );
        $id_comment = filter_var( $this->request->param( 'id_comment' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $message = filter_var( $this->request->param( 'message' ), FILTER_UNSAFE_RAW );
        $message = htmlspecialchars( $message );

        $job = Jobs_JobDao::getByIdAndPassword( $id_job, $password, 60 * 60 * 24 );

        if ( empty( $job ) ) {
            $this->response->code(400);

            return $this->response->json([
                "code" => -10,
                "message" => "wrong password"
            ]);
        }

        return [
            'id_client' => $id_client,
            'username' => $username,
            'id_job' => $id_job,
            'id_segment' => $id_segment,
            'source_page' => $source_page,
            'revision_number' => $revision_number,
            'first_seg' => $first_seg,
            'last_seg' => $last_seg,
            'id_comment' => $id_comment,
            'password' => $password,
            'message' => $message,
            'job' => $job,
        ];
    }

    private function prepareCommentData($request)
    {
        $struct = new Comments_CommentStruct();

        $struct->id_segment      = $request[ 'id_segment' ];
        $struct->id_job          = $request[ 'id_job' ];
        $struct->full_name       = $request[ 'username' ];
        $struct->source_page     = $request[ 'source_page' ];
        $struct->message         = $request[ 'message' ];
        $struct->revision_number = $request[ 'revision_number' ];
        $struct->email           = $this->user->getEmail();
        $struct->uid             = $this->user->getUid();

        $user_mentions           = $this->resolveUserMentions();
        $user_team_mentions      = $this->resolveTeamMentions();
        $userDao                 = new Users_UserDao( Database::obtain() );
        $users_mentioned_id      = array_unique( array_merge( $user_mentions, $user_team_mentions ) );
        $users_mentioned         = $this->filterUsers( $userDao->getByUids( $this->users_mentioned_id ) );
    }
}