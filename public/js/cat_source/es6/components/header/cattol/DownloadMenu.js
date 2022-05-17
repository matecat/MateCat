import React from 'react'

export const DownloadMenu = ({password, jid, stats}) => {
  return (
    <div
      className="action-submenu ui simple pointing top center floating dropdown"
      id="action-download"
      title="Download"
    >
      <div className="dropdown-menu-overlay"></div>
      <ul
        className="menu"
        id="previewDropdown"
        data-download={
          stats && stats['TODO_FORMATTED'] == 0 && stats['ANALYSIS_COMPLETE']
            ? 'true'
            : 'false'
        }
      >
        <li className="item previewLink" data-value="draft">
          <a title="Draft" alt="Draft" href="#">
            Draft
          </a>
        </li>

        <li className="item downloadTranslation" data-value="translation">
          <a title="Translation" alt="Translation" href="#">
            Download Translation
          </a>
        </li>
        {config.isGDriveProject && (
          <>
            <li className="item" data-value="original">
              <a
                className="originalDownload"
                title="Original"
                alt="Original"
                data-href={`/?action=downloadOriginal&id_job=${jid}&password=${password}&download_type=all`}
                target="_blank"
              >
                Original
              </a>
            </li>

            <li className="item">
              <a
                className="originalsGDrive"
                title="Original in Google Drive"
                alt="Original in Google Drive"
                href="javascript:void(0)"
              >
                Original in Google Drive
              </a>
            </li>
          </>
        )}
        <li className="item" data-value="xlif">
          <a
            className="sdlxliff"
            title="Export XLIFF"
            alt="Export XLIFF"
            data-href={`/SDLXLIFF/${jid}/${password}/${jid}.zip`}
            target="_blank"
          >
            Export XLIFF
          </a>
        </li>

        <li className="item" data-value="tmx">
          <a
            rel="noreferrer"
            className="tmx"
            title="Export job TMX for QA"
            alt="Export job TMX for QA"
            href={`/TMX/${jid}/${password}`}
            target="_blank"
          >
            Export Job TMX
          </a>
        </li>
      </ul>
    </div>
  )
}
