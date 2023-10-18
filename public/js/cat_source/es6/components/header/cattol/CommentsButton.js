import React, {useEffect, useState} from 'react'
import CommentsActions from '../../../actions/CommentsActions'
import {getComments} from '../../../api/getComments'

export const CommentsButton = ({teams}) => {
  const [teamUsers, setTeamUsers] = useState([])
  const [comments, setComments] = useState([])
  const loadCommentData = () => {
    getComments({}).then((resp) => {
      setComments(resp.data.entries.comments)
      CommentsActions.storeComments(resp.data.entries.comments, resp.data.user)
    })
  }
  useEffect(() => {
    loadCommentData()
  }, [])
  useEffect(() => {
    const team = teams.find((t) => t.id === config.id_team)
    if (team) {
      const members = team.members.map((m) => m.user)
      const teamTemp = {
        uid: 'team',
        first_name: 'Team',
        last_name: '',
      }
      setTeamUsers([...members, teamTemp])
      CommentsActions.updateTeamUsers([...team.members, teamTemp])
    } else {
      setTeamUsers()
    }
  }, [teams])
  return (
    <>
      {config.comments_enabled && (
        <div
          id="mbc-history"
          title="View comments"
          className="mbc-history-balloon-icon-has-no-comments"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="3 3 36 36">
            <path
              fill="#fff"
              fillRule="evenodd"
              stroke="none"
              strokeWidth="1"
              d="M33.125 13.977c-1.25-1.537-2.948-2.75-5.093-3.641C25.886 9.446 23.542 9 21 9c-2.541 0-4.885.445-7.031 1.336-2.146.89-3.844 2.104-5.094 3.64C7.625 15.514 7 17.188 7 19c0 1.562.471 3.026 1.414 4.39.943 1.366 2.232 2.512 3.867 3.439-.114.416-.25.812-.406 1.187-.156.375-.297.683-.422.922-.125.24-.294.505-.508.797a8.15 8.15 0 01-.484.617 249.06 249.06 0 00-1.023 1.133 1.1 1.1 0 00-.126.141l-.109.132-.094.141c-.052.078-.075.127-.07.148a.415.415 0 01-.031.156c-.026.084-.024.146.007.188v.016c.042.177.125.32.25.43a.626.626 0 00.422.163h.079a11.782 11.782 0 001.78-.344c2.73-.697 5.126-1.958 7.189-3.781.78.083 1.536.125 2.265.125 2.542 0 4.886-.445 7.032-1.336 2.145-.891 3.843-2.104 5.093-3.64C34.375 22.486 35 20.811 35 19c0-1.812-.624-3.487-1.875-5.023z"
            ></path>
          </svg>
          <div className="mbc-history-balloon-outer hide">
            <div className="mbc-triangle mbc-triangle-top">
              <div className="mbc-history-balloon mbc-history-balloon-has-no-comments">
                <div className="mbc-thread-wrap">
                  <div className="mbc-show-comment">
                    <span className="mbc-comment-label">No comments</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
