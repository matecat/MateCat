import React, {useEffect, useState} from 'react'
import CommonUtils from '../../utils/commonUtils'
import {Accordion} from '../common/Accordion/Accordion'
import {filterXSS} from 'xss'

// Links the deployment does not allow are flattened to inert markdown text. Core allows none
// (isAllowedLinkRedirect is `() => false`); plugins override it.
const removeNotAllowedLinksFromHtml = (html) => {
  const div = document.createElement('div')
  div.innerHTML = html
  const links = div.getElementsByTagName('a')
  const linksArray = Array.from(links)
  for (var i = 0; i < linksArray.length; i++) {
    const linkElement = linksArray[i]
    const link = linkElement.getAttribute('href')
    if (!CommonUtils.isAllowedLinkRedirect(link)) {
      const text = '[' + linkElement.textContent + '](' + link + ')'
      // Replace the node we already hold: re-querying by href breaks on an anchor with no
      // href and on any href containing a quote.
      linkElement.parentNode &&
        linkElement.parentNode.replaceChild(
          document.createTextNode(text),
          linkElement,
        )
    }
  }
  return div.innerHTML
}

const JobMetadataModal = (props) => {
  const [currentFile, setCurrentFile] = useState(props.currentFile)

  const isMtcReferenceValued = ({metadata}) =>
    typeof metadata?.['mtc:references'] === 'string'

  // Instructions keep their anchors and are injected with dangerouslySetInnerHTML, so the
  // escaping and the link decision happen here. Plugins render markdown over the same value
  // first; filterXSS and the link check are both idempotent.
  const getHtml = (text) =>
    text ? removeNotAllowedLinksFromHtml(filterXSS(text)) : text

  const getMTCReferences = ({metadata}) => {
    return (
      isMtcReferenceValued({metadata}) && (
        <p
          dangerouslySetInnerHTML={{
            __html: `<b>Reference:</b> ${removeNotAllowedLinksFromHtml(filterXSS(metadata['mtc:references']))}`,
          }}
        ></p>
      )
    )
  }

  const createFileList = () => {
    return props.files.map((file) => {
      let isCurrentFile = currentFile && currentFile === file.id

      const title = (
        <div className="title">
          {CommonUtils.getFileIcon(
            file.file_name.split('.')[file.file_name.split('.').length - 1],
          )}

          <div>{file.file_name}</div>
        </div>
      )

      return (
        file.metadata &&
        (file.metadata.instructions || isMtcReferenceValued(file)) && (
          <Accordion
            key={file.id}
            id={file.id}
            className="instructions-accordion"
            title={title}
            expanded={isCurrentFile}
            onShow={(id) => setCurrentFile(currentFile !== id ? id : undefined)}
          >
            <div className="content">
              <div className="transition">
                {file.metadata.instructions && (
                  <div
                    dangerouslySetInnerHTML={{
                      __html: getHtml(file.metadata.instructions),
                    }}
                  ></div>
                )}
                {getMTCReferences(file)}
              </div>
            </div>
          </Accordion>
        )
      )
    })
  }

  const createSingleFile = () => {
    const file = props.files.find(
      (file) => parseInt(file.id) === parseInt(props.currentFile),
    )
    return (
      <div className="matecat-modal-text">
        <div className={'description'}>
          <h3>Please read the following notes and references carefully:</h3>
        </div>
        <div className="instructions-container">
          <p
            dangerouslySetInnerHTML={{
              __html: getHtml(file.metadata.instructions),
            }}
          />
          {getMTCReferences(file)}
        </div>
      </div>
    )
  }

  useEffect(() => {
    setTimeout(() => {
      const element = document.querySelector('.title.current.active')
      element && element.scrollIntoView({behavior: 'smooth'})
    }, 200)
  }, [])

  return (
    <div className="instructions-modal">
      <div className="matecat-modal-middle">
        {props.showCurrent ? (
          createSingleFile()
        ) : (
          <div className="matecat-modal-text">
            {props.projectInfo && (
              <div>
                <h4>Project instructions</h4>
                <div className="instructions-container">
                  <p
                    dangerouslySetInnerHTML={{
                      __html: getHtml(props.projectInfo),
                    }}
                  />
                </div>
              </div>
            )}
            {props.files &&
              (props.files.find((file) => file.metadata.instructions) ||
                props.files.find((file) => isMtcReferenceValued(file))) && (
                <div>
                  <h4>File instructions</h4>
                  <div className="file-instructions-accordion">
                    {createFileList()}
                  </div>
                </div>
              )}
          </div>
        )}
      </div>
    </div>
  )
}

export default JobMetadataModal
