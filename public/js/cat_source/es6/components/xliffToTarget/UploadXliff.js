import React, {useContext, useLayoutEffect} from 'react'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'
import ModalsActions from '../../actions/ModalsActions'

const init = () => {
  function b64toBlob(b64Data, contentType, sliceSize) {
    contentType = contentType || ''
    sliceSize = sliceSize || 512
    var byteCharacters = atob(b64Data)
    var byteArrays = []
    for (var offset = 0; offset < byteCharacters.length; offset += sliceSize) {
      var slice = byteCharacters.slice(offset, offset + sliceSize)
      var byteNumbers = new Array(slice.length)
      for (var i = 0; i < slice.length; i++) {
        byteNumbers[i] = slice.charCodeAt(i)
      }
      var byteArray = new Uint8Array(byteNumbers)
      byteArrays.push(byteArray)
    }
    var blob = new Blob(byteArrays, {
      type: contentType,
    })
    return blob
  }

  $('#fileupload').fileupload({
    autoUpload: true,
    acceptFileTypes: config.allowedFileTypes,
    dataType: config.dataType,
    maxFileSize: config.maxFileSize,

    add: function (e, data) {
      conversionFailed = {}
      var that =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        options = that.options,
        files = data.files
      $(this)
        .fileupload('process', data)
        .done(function () {
          that._adjustMaxNumberOfFiles(-files.length)
          data.isAdjusted = true
          data.files.valid = data.isValidated = that._validate(files)
          data.context = that._renderUpload(files).data('data', data)
          options.filesContainer[options.prependFiles ? 'prepend' : 'append'](
            data.context,
          )
          that._renderPreviews(files, data.context)
          that._forceReflow(data.context)

          that._transition(data.context).done(function () {
            if (
              that._trigger('added', e, data) !== false &&
              (options.autoUpload || data.autoUpload) &&
              data.autoUpload !== false &&
              data.isValidated
            ) {
              data.submit()
            }
          })
        })
    },

    // Callback for the start of each file upload request:
    send: function (e, data) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      if (!data.isValidated) {
        if (!data.isAdjusted) {
          that._adjustMaxNumberOfFiles(-data.files.length)
        }
        if (!that._validate(data.files)) {
          return false
        }
      }
      if (
        data.context &&
        data.dataType &&
        data.dataType.substr(0, 6) === 'iframe'
      ) {
        // Iframe Transport does not support progress events.
        // In lack of an indeterminate progress bar, we set
        // the progress to 100%, showing the full animated bar:
        data.context
          .find('.progress')
          .addClass(!$.support.transition && 'progress-animated')
          .attr('aria-valuenow', 100)
          .find('.bar')
          .css('width', '100%')
      }
      return that._trigger('sent', e, data)
    },
    // Callback for successful uploads:
    done: function (e, data) {
      var that =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        template
      if (data.context) {
        data.context.each(function (index) {
          if (
            data instanceof Object &&
            typeof data.result.fileContent == 'string'
          ) {
            var file = data.result
          } else {
            var file = {error: 'emptyResult'}
          }

          if (file.error) {
            that._adjustMaxNumberOfFiles(1)
          }
          that._transition($(this)).done(function () {
            var node = $(this)
            template = that._renderDownload([file]).replaceAll(node)
            that._forceReflow(template)
            that._transition(template).done(function () {
              data.context = $(this)
              that._trigger('completed', e, data)
            })
            $('.size span').css('visibility', 'visible')
            $(data.context)
              .find('.size + td')
              .addClass('file_upload_ok')
              .html('<span>' + file.message + '</span>')
          })
        })
      } else {
        template = that
          ._renderDownload(data.result)
          .appendTo(that.options.filesContainer)
        that._forceReflow(template)
        that._transition(template).done(function () {
          data.context = $(this)
          that._trigger('completed', e, data)
        })
      }
    },
    // Callback for failed (abort or error) uploads:
    fail: function (e, data) {
      conversionFailed = true
      conversionInfo = data.originalFiles[0]
      var that =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        template
      that._adjustMaxNumberOfFiles(data.files.length)
      if (data.context) {
        data.context.each(function (index) {
          var file = {
            fileName: data.originalFiles[0].name,
            size: data.originalFiles[0].size,
            type: data.originalFiles[0].type,
          }
          file.error =
            'An error occurred. Please, be sure that the xliff file has been downloaded from Matecat'
          that._transition($(this)).done(function () {
            var node = $(this)
            template = that._renderDownload([file]).replaceAll(node)
            that._forceReflow(template)
            that._transition(template).done(function () {
              data.context = $(this)
              that._trigger('failed', e, data)
            })
            $('.size span').css('visibility', 'visible')
          })
        })
      } else if (data.errorThrown !== 'abort') {
        that._adjustMaxNumberOfFiles(-data.files.length)
        data.context = that
          ._renderUpload(data.files)
          .appendTo(that.options.filesContainer)
          .data('data', data)
        that._forceReflow(data.context)
        that._transition(data.context).done(function () {
          data.context = $(this)
          that._trigger('failed', e, data)
        })
      } else {
        that._trigger('failed', e, data)
      }
    },
    // Callback for upload progress events:
    progress: function (e, data) {
      if (data.context) {
        var progress = parseInt((data.loaded / data.total) * 100, 10)
        data.context
          .find('.progress')
          .attr('aria-valuenow', progress)
          .find('.bar')
          .css('width', progress + '%')
      }
    },
    // Callback for global upload progress events:
    progressall: function (e, data) {
      var $this = $(this),
        upload =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        progress = parseInt((data.loaded / data.total) * 100, 10),
        globalProgressNode = $this.find('.fileupload-progress'),
        extendedProgressNode = globalProgressNode.find('.progress-extended')
      if (extendedProgressNode.length) {
        extendedProgressNode.html(upload._renderExtendedProgress(data))
      }
      globalProgressNode
        .find('.progress')
        .attr('aria-valuenow', progress)
        .find('.bar')
        .css('width', progress + '%')
    },
    // Callback for uploads start, equivalent to the global ajaxStart event:
    start: function (e) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      that._transition($(this).find('.fileupload-progress')).done(function () {
        that._trigger('started', e)
      })
    },
    // Callback for uploads stop, equivalent to the global ajaxStop event:
    stop: function (e) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      that._transition($(this).find('.fileupload-progress')).done(function () {
        $(this)
          .find('.progress')
          .attr('aria-valuenow', '0')
          .find('.bar')
          .css('width', '0%')
        $(this).find('.progress-extended').html('&nbsp;')
        that._trigger('stopped', e)
      })
    },
    // Callback for file deletion:
    destroy: function (e, data) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      if (data.url) {
        $.ajax(data)
        that._adjustMaxNumberOfFiles(1)
      }

      var _deleteRow = function (rowToBeDeleted) {
        that._transition($(rowToBeDeleted)).done(function () {
          $(this).remove()
          that._trigger('destroyed', e, data)
        })
      }

      _deleteRow(data.context)

      /* END Editing */
    },
    complete: function (e, data) {
      if (
        conversionFailed == false ||
        JSON.stringify(conversionFailed) === JSON.stringify({})
      ) {
        console.log('completed!!!')
        var result = e.responseJSON //JSON.parse( data.result );
        saveAs(b64toBlob(result.fileContent), result.fileName)
      }
    },
  })
  $._data($('#fileupload')[0], 'events')['fileuploadcompleted'] = []

  UI.errorsBeforeUpload = function (file) {
    console.log(file)

    var msg = ''

    if (file.type.match(/^image/)) {
      msg = 'Images not allowed in Matecat'
    } else {
      msg = 'Filetype not allowed in this page. Please upload an xliff file'
    }

    UI.checkFailedConversionsNumber()

    console.log('msg: ', msg)

    return msg
  }
}

export const UploadXliff = () => {
  const {isUserLogged} = useContext(ApplicationWrapperContext)

  useLayoutEffect(() => {
    console.log('############', isUserLogged)
    isUserLogged && init()
  }, [isUserLogged])

  return (
    <div id="dropzone_wrapper">
      <div className="upload-files drag">
        <form
          id="fileupload"
          action="/index.php?action=xliffToTarget"
          method="POST"
          encType="multipart/form-data"
        >
          <div id="overlay"></div>
          {isUserLogged ? (
            <div id="droptest">
              <div className="upload-icon">
                <svg
                  width="44px"
                  height="42px"
                  viewBox="0 0 44 42"
                  version="1.1"
                >
                  <g
                    id="0.-Matecat"
                    stroke="none"
                    strokeWidth="1"
                    fill="none"
                    fillRule="evenodd"
                  >
                    <g
                      id="1.0-Homepage"
                      transform="translate(-698.000000, -412.000000)"
                      fill="#BBBBBB"
                      fillRule="nonzero"
                      stroke="#BBBBBB"
                    >
                      <g transform="translate(699.000000, 413.000000)">
                        <g id="upload">
                          <path
                            d="M40.7275424,19.2974945 C40.0601695,19.2974945 39.5262712,19.8413412 39.5262712,20.5211496 L39.5262712,31.6518791 C39.5262712,34.6792926 37.1059322,37.1356669 34.1427966,37.1356669 L7.78601695,37.1356669 C4.81398305,37.1356669 2.40254237,34.6702284 2.40254237,31.6518791 L2.40254237,20.3398674 C2.40254237,19.660059 1.86864407,19.1162122 1.20127119,19.1162122 C0.533898305,19.1162122 0,19.660059 0,20.3398674 L0,31.6518791 C0,36.0298452 3.4970339,39.5829772 7.78601695,39.5829772 L34.1427966,39.5829772 C38.440678,39.5829772 41.9288136,36.0207811 41.9288136,31.6518791 L41.9288136,20.5211496 C41.9288136,19.8504053 41.3949153,19.2974945 40.7275424,19.2974945 Z"
                            id="Shape"
                          >
                            {' '}
                          </path>
                          <path
                            d="M20.1190678,29.8662491 C20.3504237,30.101916 20.6618644,30.2288136 20.9644068,30.2288136 C21.2669492,30.2288136 21.5783898,30.1109801 21.8097458,29.8662491 L29.4444915,22.089241 C29.9161017,21.608843 29.9161017,20.8383935 29.4444915,20.3579956 C28.9728814,19.8775976 28.2165254,19.8775976 27.7449153,20.3579956 L22.165678,26.0502579 L22.165678,1.22365512 C22.165678,0.543846721 21.6317797,0 20.9644068,0 C20.2970339,0 19.7631356,0.543846721 19.7631356,1.22365512 L19.7631356,26.0502579 L14.175,20.3579956 C13.7033898,19.8775976 12.9470339,19.8775976 12.4754237,20.3579956 C12.0038136,20.8383935 12.0038136,21.608843 12.4754237,22.089241 L20.1190678,29.8662491 Z"
                            id="Shape"
                            transform="translate(20.959958, 15.114407) rotate(-180.000000) translate(-20.959958, -15.114407) "
                          >
                            {' '}
                          </path>
                        </g>
                      </g>
                    </g>
                  </g>
                </svg>
              </div>

              <p className="file-drag">
                <strong>Drag and drop</strong> your XLIFF here
                <br />
                <span className="min">to obtain the translated documents</span>
              </p>

              <p className="file-drop">
                <strong>Drop it</strong> here.
              </p>

              <div className="container">
                <div className="row fileupload-buttonbar">
                  <div className="span7">
                    <span className="btn btn-success fileinput-button">
                      <span>Add files...</span>
                      <input
                        type="file"
                        name="xliff"
                        multiple="multiple"
                        className="multiple-button"
                      />
                    </span>
                  </div>

                  <div className="span5 fileupload-progress fade">
                    <div
                      className="progress progress-success progress-striped active"
                      role="progressbar"
                      aria-valuemin="0"
                      aria-valuemax="100"
                    >
                      <div className="bar" style={{width: '0%'}}></div>
                    </div>
                    <div className="progress-extended">&nbsp;</div>
                  </div>
                </div>
                <div className="fileupload-loading"></div>
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
                <div className="cl"></div>
              </div>
            </div>
          ) : (
            <div className="upload-xliff-box-not-logged">
              <h2>
                <a onClick={ModalsActions.openLoginModal}>Sign in</a> to use
                XLIFF-to-target converter.
              </h2>
            </div>
          )}

          <div className="cl"></div>
        </form>
      </div>
    </div>
  )
}
