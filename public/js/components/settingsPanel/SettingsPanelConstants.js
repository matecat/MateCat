import {SCHEMA_KEYS} from '../../hooks/useProjectTemplates'

export const SETTINGS_PANEL_TABS = {
  translationMemoryGlossary: 'tm',
  machineTranslation: 'mt',
  other: 'other',
  analysis: 'analysis',
  qualityFramework: 'qf',
  fileImport: 'fileImport',
  editorSettings: 'editorSettings',
  editorOther: 'editorOther',
}

export const TEMPLATE_PROPS_BY_TAB = {
  [SETTINGS_PANEL_TABS.translationMemoryGlossary]: [
    SCHEMA_KEYS.tm,
    SCHEMA_KEYS.getPublicMatches,
    SCHEMA_KEYS.publicTmPenalty,
    SCHEMA_KEYS.pretranslate100,
    SCHEMA_KEYS.tmPrioritization,
  ],
  [SETTINGS_PANEL_TABS.machineTranslation]: [SCHEMA_KEYS.mt],
  [SETTINGS_PANEL_TABS.qualityFramework]: [SCHEMA_KEYS.qaModelTemplateId],
  [SETTINGS_PANEL_TABS.fileImport]: [
    SCHEMA_KEYS.segmentationRule,
    SCHEMA_KEYS.filtersTemplateId,
    SCHEMA_KEYS.XliffConfigTemplateId,
  ],
  [SETTINGS_PANEL_TABS.analysis]: [SCHEMA_KEYS.payableRateTemplateId],
  [SETTINGS_PANEL_TABS.other]: [
    SCHEMA_KEYS.speech2text,
    SCHEMA_KEYS.tagProjection,
    SCHEMA_KEYS.lexica,
    SCHEMA_KEYS.crossLanguageMatches,
    SCHEMA_KEYS.idTeam,
  ],
  [SETTINGS_PANEL_TABS.editorSettings]: [],
  [SETTINGS_PANEL_TABS.editorOther]: [],
}
