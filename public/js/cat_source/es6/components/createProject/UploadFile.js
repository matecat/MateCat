import React from 'react'

export const UploadFile = ({}) => {
  return (
    <>
      <div id="upload-files-list" className="upload-files drag">
        <form
          id="fileupload"
          action="/lib/Utils/fileupload/"
          method="POST"
          encType="multipart/form-data"
        >
          <div id="overlay"></div>
          <div id="droptest">
            <div className="upload-icon">
              <svg
                version="1.1"
                width="45px"
                height="45px"
                id="Livello_1"
                xmlns="http://www.w3.org/2000/svg"
                x="0px"
                y="0px"
                viewBox="0 0 100.14 100.14"
                style={{enableBackground: 'new 0 0 100.14 100.14'}}
              >
                <g>
                  <path
                    className="st1"
                    fill="#CDD4DD"
                    d="M30.41,39.53l13.73-12.82v40.8c0,3.28,2.66,5.94,5.94,5.94s5.94-2.66,5.94-5.94v-40.8l13.73,12.82
                        c1.14,1.07,2.6,1.6,4.05,1.6c1.59,0,3.17-0.63,4.34-1.89c2.24-2.4,2.11-6.16-0.29-8.39L54.13,8.7c-0.04-0.04-0.09-0.08-0.14-0.12
                        c-0.07-0.06-0.15-0.13-0.22-0.19c-0.08-0.06-0.15-0.12-0.23-0.17c-0.08-0.05-0.15-0.11-0.23-0.16c-0.08-0.05-0.17-0.1-0.25-0.15
                        c-0.08-0.05-0.16-0.09-0.24-0.13c-0.09-0.04-0.17-0.09-0.26-0.13c-0.08-0.04-0.17-0.08-0.26-0.11c-0.09-0.04-0.18-0.07-0.26-0.1
                        c-0.09-0.03-0.18-0.06-0.27-0.09c-0.09-0.03-0.18-0.05-0.27-0.07c-0.09-0.02-0.18-0.05-0.28-0.07C51.1,7.19,51,7.18,50.9,7.17
                        c-0.08-0.01-0.17-0.03-0.25-0.03c-0.17-0.02-0.34-0.02-0.51-0.03c-0.02,0-0.04,0-0.06,0c-0.02,0-0.04,0-0.06,0
                        c-0.17,0-0.34,0.01-0.51,0.03c-0.09,0.01-0.17,0.02-0.25,0.03c-0.1,0.01-0.21,0.03-0.31,0.05c-0.09,0.02-0.19,0.04-0.28,0.07
                        c-0.09,0.02-0.18,0.04-0.27,0.07c-0.09,0.03-0.18,0.06-0.27,0.09c-0.09,0.03-0.18,0.06-0.26,0.1c-0.09,0.04-0.17,0.07-0.26,0.11
                        c-0.09,0.04-0.18,0.08-0.26,0.13c-0.08,0.04-0.16,0.09-0.24,0.13c-0.09,0.05-0.17,0.1-0.25,0.15c-0.08,0.05-0.15,0.1-0.23,0.16
                        c-0.08,0.06-0.16,0.11-0.23,0.17c-0.08,0.06-0.15,0.12-0.22,0.19c-0.05,0.04-0.09,0.08-0.14,0.12L22.3,30.85
                        c-2.4,2.24-2.53,6-0.29,8.39C24.25,41.64,28.01,41.77,30.41,39.53z"
                  />
                  <path
                    className="st1"
                    fill="#CDD4DD"
                    d="M86.1,68.49c-3.28,0-5.94,2.66-5.94,5.94v6.73H19.99v-6.73c0-3.28-2.66-5.94-5.94-5.94s-5.94,2.66-5.94,5.94
                            V87.1c0,3.28,2.66,5.94,5.94,5.94H86.1c3.28,0,5.94-2.66,5.94-5.94V74.43C92.04,71.15,89.38,68.49,86.1,68.49z"
                  />
                </g>
              </svg>
            </div>

            <p className="file-drag">
              <strong>Drop your files to translate them with Matecat</strong>
              <br />
            </p>

            <p className="file-drop">
              <strong>Drop it</strong> here.
            </p>

            <div className="container">
              {/* <!-- The file upload form used as target for the file upload widget -->*/}

              {/*<!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->*/}
              <div className="row fileupload-buttonbar">
                <div className="span7">
                  {/*<!-- The fileinput-button span is used to style the file input field as button -->*/}
                  <span className="fileinput-link fileinput-button">
                    <label htmlFor="upload-file">or click here to browse</label>
                    <input
                      id="upload-file"
                      type="file"
                      name="files[]"
                      multiple="multiple"
                      className="multiple-button"
                    />
                  </span>
                </div>
                {/*<!-- The global progress information -->*/}
                <div className="span5 fileupload-progress fade">
                  {/* <!-- The global progress bar -->*/}
                  <div
                    className="progress progress-success progress-striped active"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                  >
                    <div className="bar" style={{width: '0%'}}></div>
                  </div>
                  {/*<!-- The extended global progress information -->*/}
                  <div className="progress-extended">&nbsp;</div>
                </div>
              </div>
              {/*<!-- The loading indicator is shown during file processing -->*/}
              <div className="fileupload-loading"></div>
              {/*<!-- The table listing the files available for upload/download -->*/}
              <table
                cellPadding="0"
                cellSpacing="0"
                className="upload-table table-striped"
                role="presentation"
              >
                <tbody
                  className="files"
                  data-toggle="modal-gallery"
                  data-target="#modal-gallery"
                ></tbody>
              </table>
            </div>
          </div>

          <div className="btncontinue">
            <p>
              <strong>Drag and drop</strong> your file here or{' '}
            </p>

            <span id="add-files" className="btn fileinput-button">
              <span>Add files...</span>
              <input
                type="file"
                name="files[]"
                multiple="multiple"
                className="multiple-button"
              />
            </span>
            <span
              id="clear-all-files"
              className="btn fileinput-button cancel-btn"
            >
              <span>Clear all</span>
            </span>
            <span
              id="delete-failed-conversions"
              className="btn fileinput-button cancel-btn"
            >
              <span>Clear all failed</span>
            </span>
          </div>
        </form>
      </div>

      {/*  TODO: GOOGLE DRIVE*/}
      <div id="gdrive-files-list">
        <div className="gdrive-upload-files drag uploaded">
          <table
            cellPadding="0"
            cellSpacing="0"
            className="gdrive-upload-table table-striped"
            role="presentation"
          >
            <tbody
              className="files-gdrive"
              data-toggle="modal-gallery"
              data-target="#modal-gallery"
            ></tbody>
          </table>
          <div className="btncontinue continue-gdrive">
            <span className="btn load-gdrive load-gdrive-disabled">
              Add from Google Drive
            </span>
            <span
              id="clear-all-gdrive"
              className="btn fileinput-button cancel-btn clear-gdrive"
            >
              <span>Clear all</span>
            </span>
          </div>
        </div>
      </div>
    </>
  )
}

export default UploadFile
