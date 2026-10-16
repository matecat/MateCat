import React from 'react'
import PropTypes from 'prop-types'
import {UNIT_COUNT} from '../../../constants/Constants'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../common/Button/Button'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {formatCount} from '../utils/buckets'
import {ProjectHeaderCard} from '../ProjectHeaderCard'
import {AnalysisStatusCard} from '../shared/AnalysisStatusCard'
import {ANALYSIS_PAGE_TITLE, AnalysisPage} from '../shared/AnalysisPage'
import {DisabledHint} from '../shared/DisabledHint'
import {
  ACTIONS_DISABLED_TITLE,
  CopyLinkButton,
  getLanguagePair,
  openTranslate,
} from '../LanguageTree'
import {JobActions} from '../JobActions'
import {getSubtitle} from '../DetailsPanel'
import {JobSummaryCard} from '../JobSummaryCard'
import {BreakdownCard} from '../BreakdownCard'
import {FilesCard} from '../FilesCard'
import {OutsourceOfferCard} from '../OutsourceOfferCard'
import {BuyTranslationDrawer, useBuyTranslation} from '../BuyTranslationDrawer'
import styles from './SingleJobAnalysis.module.scss'

// "Job ID 421 · 585 words", or "counting words…" until the count is ready
export const getJobSubtitle = (job, rawKnown) => {
  const unit = job.countUnit === UNIT_COUNT.CHARACTERS ? 'characters' : 'words'
  return `Job ID ${job.id} · ${
    rawKnown ? `${formatCount(job.totals.raw)} ${unit}` : `counting ${unit}…`
  }`
}

/**
 * The analysis of a project with one language combination in one chunk: the
 * job itself, with no tree to choose from.
 */
export const SingleJobAnalysis = ({project, view, workflowType}) => {
  const {jobs, phase, known, actionsEnabled, progress, totals} = view
  const [job] = jobs
  const [chunk] = job.chunks
  const {order, openBuy, closeBuy} = useBuyTranslation({project})
  const done = phase === ANALYSIS_PHASE.DONE
  const projectJob = (project.jobs ?? []).find(
    (current) =>
      String(current.id) === String(job.id) &&
      current.password === chunk.password,
  )

  return (
    <AnalysisPage title={ANALYSIS_PAGE_TITLE.PROJECT}>
      <ProjectHeaderCard
        projectId={project.id}
        projectName={project.name}
        projectPassword={project.password}
        languageCount={1}
        phase={phase}
        totals={totals}
      />
      {!done && <AnalysisStatusCard phase={phase} progress={progress} />}
      <JobSummaryCard
        title={getLanguagePair(job)}
        subtitle={getJobSubtitle(job, known.raw)}
        totals={chunk.totals}
        known={known}
        workflowType={workflowType}
        countUnit={job.countUnit}
        actions={
          <>
            <DisabledHint
              hint={ACTIONS_DISABLED_TITLE}
              active={!actionsEnabled}
            >
              <CopyLinkButton
                label={`Copy the ${job.target} link`}
                url={chunk.urls?.t}
                disabled={!actionsEnabled}
              />
            </DisabledHint>
            <JobActions
              project={project}
              job={job}
              chunk={null}
              actionsEnabled={actionsEnabled}
            />
            <DisabledHint
              hint={ACTIONS_DISABLED_TITLE}
              active={!actionsEnabled}
            >
              <Button
                type={BUTTON_TYPE.PRIMARY}
                size={BUTTON_SIZE.SMALL}
                tabIndex="0"
                disabled={!actionsEnabled}
                onClick={() => openTranslate(chunk)}
              >
                Translate
              </Button>
            </DisabledHint>
          </>
        }
      />
      <div className={styles.columns}>
        <div className={styles.main}>
          <BreakdownCard
            matches={chunk.summary}
            payableRates={job.payableRates}
            workflowType={workflowType}
            countUnit={job.countUnit}
            totals={chunk.totals}
            done={done}
          />
        </div>
        <div className={styles.side}>
          <OutsourceOfferCard
            projectId={project.id}
            projectPassword={project.password}
            job={job}
            chunk={chunk}
            jobOutsource={projectJob?.outsource ?? null}
            actionsEnabled={actionsEnabled}
            onBuy={openBuy}
            headingLevel={2}
          />
          <FilesCard
            files={chunk.files}
            payableRates={job.payableRates}
            workflowType={workflowType}
            countUnit={job.countUnit}
            done={done}
          />
        </div>
      </div>
      {order && (
        <BuyTranslationDrawer
          open={true}
          onClose={closeBuy}
          project={project}
          projectJob={order.projectJob}
          title={getLanguagePair(order.job)}
          subtitle={getSubtitle(order.job, null)}
          weightedWords={order.chunk.totals.equivalent}
        />
      )}
    </AnalysisPage>
  )
}

SingleJobAnalysis.propTypes = {
  // Plain project, as returned by the project API
  project: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
    name: PropTypes.string,
    password: PropTypes.string.isRequired,
    jobs: PropTypes.array,
  }).isRequired,
  // deriveAnalysisView output with one job in one chunk
  view: PropTypes.shape({
    phase: PropTypes.string.isRequired,
    known: PropTypes.object.isRequired,
    actionsEnabled: PropTypes.bool,
    progress: PropTypes.object,
    jobs: PropTypes.array.isRequired,
    totals: PropTypes.object.isRequired,
  }).isRequired,
  workflowType: PropTypes.string,
}
