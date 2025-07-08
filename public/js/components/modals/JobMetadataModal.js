import React from 'react'
import CommonUtils from '../../utils/commonUtils'
import {Accordion} from '../common/Accordion/Accordion'

class JobMetadataModal extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      currentFile: this.props.currentFile,
    }
  }
  createFileList() {
    const {currentFile} = this.state
    return this.props.files.map((file) => {
      let isCurrentFile = currentFile && currentFile === file.id

      const title = (
        <div className="title">
          <span
            title={file.file_name}
            className={
              'fileFormat ' +
              CommonUtils.getIconClass(
                file.file_name.split('.')[file.file_name.split('.').length - 1],
              )
            }
          >
            {file.file_name}
          </span>
        </div>
      )

      return (
        file.metadata &&
        file.metadata.instructions && (
          <Accordion
            key={file.id}
            id={file.id}
            className="instructions-accordion"
            title={title}
            expanded={isCurrentFile}
            onShow={(id) => this.setState({currentFile: id})}
          >
            <div className="content">
              <div
                className="transition"
                dangerouslySetInnerHTML={{
                  __html: this.getHtml(file.metadata.instructions),
                }}
              />
            </div>
          </Accordion>
        )
      )
    })
  }

  createSingleFile() {
    const file = this.props.files.find(
      (file) => parseInt(file.id) === parseInt(this.props.currentFile),
    )
    return (
      <div className="matecat-modal-text">
        <div className={'description'}>
          <h3>Please read the following notes and references carefully:</h3>
        </div>
        <div className="instructions-container">
          <p
            dangerouslySetInnerHTML={{
              __html: this.getHtml(file.metadata.instructions),
            }}
          />
        </div>
      </div>
    )
  }

  getHtml(text) {
    return text
  }

  componentDidMount() {
    setTimeout(() => {
      const element = document.querySelector('.title.current.active')
      element && element.scrollIntoView({behavior: 'smooth'})
    }, 200)
  }

  render() {
    return (
      <div className="instructions-modal">
        <div className="matecat-modal-middle">
          {this.props.showCurrent ? (
            this.createSingleFile()
          ) : (
            <div className="matecat-modal-text">
              {this.props.projectInfo && (
                <div>
                  <h2>Project instructions</h2>
                  <div className="instructions-container">
                    <p
                      dangerouslySetInnerHTML={{
                        __html: this.getHtml(this.props.projectInfo),
                      }}
                    />
                  </div>
                </div>
              )}
              {this.props.files &&
                this.props.files.find((file) => file.metadata.instructions) && (
                  <div>
                    <h2>File instructions</h2>
                    <div className="ui styled fluid accordion">
                      {this.createFileList()}
                    </div>
                  </div>
                )}
            </div>
          )}
        </div>
      </div>
    )
  }
}

export default JobMetadataModal
