import CatToolStore from '../../stores/CatToolStore'
import CattoolConstants from '../../constants/CatToolConstants'
import _ from 'lodash'

class CattoolFooter extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      stats: undefined,
    }

    this.sourceLang = this.props.languagesArray.find(
      (item) => item.code === this.props.source,
    ).name
    this.targetLang = this.props.languagesArray.find(
      (item) => item.code === this.props.target,
    ).name
  }

  updateProgress = (stats) => {
    let s = stats

    let a_perc_2nd_formatted, a_perc_2nd, reviewWordsSecondPass
    let t_perc = s.TRANSLATED_PERC
    let a_perc = s.APPROVED_PERC
    let d_perc = s.DRAFT_PERC
    let r_perc = s.REJECTED_PERC

    let t_perc_formatted = s.TRANSLATED_PERC_FORMATTED
    let a_perc_formatted = s.APPROVED_PERC_FORMATTED
    let d_perc_formatted = s.DRAFT_PERC_FORMATTED
    let r_perc_formatted = s.REJECTED_PERC_FORMATTED

    let revise_todo_formatted = Math.round(s.TRANSLATED + s.DRAFT)

    // If second pass enabled
    if (config.secondRevisionsCount && s.revises) {
      let reviewedWords = s.revises.find(function (value) {
        return value.revision_number === 1
      })
      if (reviewedWords) {
        let approvePerc =
          (parseFloat(reviewedWords.advancement_wc) * 100) / s.TOTAL
        approvePerc =
          approvePerc > s.APPROVED_PERC ? s.APPROVED_PERC : approvePerc
        a_perc_formatted = approvePerc < 0 ? 0 : _.round(approvePerc, 1)
        a_perc = approvePerc
      }

      reviewWordsSecondPass = s.revises.find(function (value) {
        return value.revision_number === 2
      })

      if (reviewWordsSecondPass) {
        let approvePerc2ndPass =
          (parseFloat(reviewWordsSecondPass.advancement_wc) * 100) / s.TOTAL
        approvePerc2ndPass =
          approvePerc2ndPass > s.APPROVED_PERC
            ? s.APPROVED_PERC
            : approvePerc2ndPass
        a_perc_2nd_formatted =
          approvePerc2ndPass < 0 ? 0 : _.round(approvePerc2ndPass, 1)
        a_perc_2nd = approvePerc2ndPass
        revise_todo_formatted =
          config.revisionNumber === 2
            ? revise_todo_formatted +
              _.round(parseFloat(reviewedWords.advancement_wc))
            : revise_todo_formatted
      }
    }

    stats.a_perc_formatted = a_perc_formatted
    stats.a_perc = a_perc
    stats.t_perc_formatted = t_perc_formatted
    stats.t_perc = t_perc
    stats.d_perc_formatted = d_perc_formatted
    stats.d_perc = d_perc
    stats.r_perc_formatted = r_perc_formatted
    stats.r_perc = r_perc
    stats.a_perc_2nd_formatted = a_perc_2nd_formatted
    stats.a_perc_2nd = a_perc_2nd
    stats.revise_todo_formatted =
      revise_todo_formatted >= 0 ? revise_todo_formatted : 0

    this.setState({
      stats: stats,
    })
  }

  componentDidMount() {
    CatToolStore.addListener(CattoolConstants.SET_PROGRESS, this.updateProgress)
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      CattoolConstants.SET_PROGRESS,
      this.updateProgress,
    )
  }

  render() {
    return (
      <div className="footer-body">
        {/*// <!-- job id -->*/}
        <div className="item">
          <p id="job_id">
            Job ID: <span>{this.props.idJob}</span>
          </p>
        </div>

        {/*// <!-- to from -->*/}
        <div className="item language">
          <p>
            <span>{this.sourceLang}</span>
            <span className="to-arrow"> &#8594; </span>
            <span id="footer-target-lang">{this.targetLang}</span>
          </p>
        </div>

        {/*// <!-- progress -->*/}

        <div className="progress-bar">
          <div className="meter" style={{width: '100%', position: 'relative'}}>
            {_.isUndefined(this.state.stats) ? (
              <div className="bg-loader" />
            ) : this.state.stats && this.state.stats.ANALYSIS_COMPLETE ? (
              <React.Fragment>
                <a
                  href="#"
                  className="approved-bar"
                  style={{width: this.state.stats.a_perc + '%'}}
                  title={'Approved ' + this.state.stats.a_perc_formatted}
                />
                <a
                  href="#"
                  className="approved-bar-2nd-pass"
                  style={{width: this.state.stats.a_perc_2nd + '%'}}
                  title={
                    '2nd Approved ' + this.state.stats.a_perc_2nd_formatted
                  }
                />
                <a
                  href="#"
                  className="translated-bar"
                  style={{width: this.state.stats.t_perc + '%'}}
                  title={'Translated ' + this.state.stats.t_perc_formatted}
                />
                <a
                  href="#"
                  className="rejected-bar"
                  style={{width: this.state.stats.r_perc + '%'}}
                  title={'Rejected ' + this.state.stats.r_perc_formatted}
                />
                <a
                  href="#"
                  className="draft-bar"
                  style={{width: this.state.stats.d_perc + '%'}}
                  title={'Draft ' + this.state.stats.d_perc_formatted}
                />
              </React.Fragment>
            ) : null}
          </div>
          <div className="percent">
            <span id="stat-progress">
              {this.state.stats
                ? this.state.stats.PROGRESS_PERC_FORMATTED
                : '-'}
            </span>
            %
          </div>
        </div>

        {/*// <!-- weighted words -->*/}
        <div className="item">
          <div className="statistics-core">
            <div id="stat-eqwords">
              {config.allow_link_to_analysis ? (
                <a
                  target="_blank"
                  href={
                    '/jobanalysis/' +
                    this.props.idProject +
                    '-' +
                    this.props.idJob +
                    '-' +
                    this.props.password
                  }
                >
                  {!this.props.isCJK ? (
                    <span>Weighted words</span>
                  ) : (
                    <span>Characters</span>
                  )}
                </a>
              ) : (
                <a target="_blank">
                  {!this.props.isCJK ? (
                    <span>Weighted words</span>
                  ) : (
                    <span>Characters</span>
                  )}
                </a>
              )}
              :
              <strong id="total-payable">
                {' '}
                {this.state.stats ? this.state.stats.TOTAL_FORMATTED : '-'}
              </strong>
            </div>
          </div>
        </div>

        {/*// <!-- to do segments -->*/}
        <div className="item">
          {this.props.isReview ? (
            <div id="stat-todo">
              <span>To-do</span> :{' '}
              <strong>
                {this.state.stats
                  ? this.state.stats.revise_todo_formatted
                  : '-'}
              </strong>
            </div>
          ) : (
            <div id="stat-todo">
              <span>To-do</span> :{' '}
              <strong>
                {this.state.stats ? this.state.stats.TODO_FORMATTED : '-'}
              </strong>
            </div>
          )}
        </div>

        {/*// <!-- speed stats -->*/}
        {this.state.stats ? (
          <div className="statistics-details">
            {this.state.stats.WORDS_PER_HOUR ? (
              <div id="stat-wph" title="Based on last 10 segments performance">
                Speed:
                <strong>{this.state.stats.WORDS_PER_HOUR}</strong> Words/h
              </div>
            ) : null}
            {this.state.stats.ESTIMATED_COMPLETION ? (
              <div id="stat-completion">
                Completed in:
                <strong>{this.state.stats.ESTIMATED_COMPLETION}</strong>
              </div>
            ) : null}
          </div>
        ) : null}

        {_.isUndefined(this.state.stats) ||
        !this.state.stats.ANALYSIS_COMPLETE ? (
          <div id="analyzing">
            <p className="progress">Calculating word count...</p>
          </div>
        ) : null}
      </div>
    )
  }
}

export default CattoolFooter
