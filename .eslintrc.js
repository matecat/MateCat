const nodeEcmaVersion = 2018
const babelEcmaVersion = 2018

module.exports = {
  ignorePatterns: ['**/public/js/lib/**/*.js'],
  extends: ['eslint:recommended'],
  rules: {
    'no-extra-semi': 'off',
  },
  overrides: [
    // nodejs 9.11 related files
    {
      files: ['*.js', '**/support_scripts/**/*.js'],
      parserOptions: {
        ecmaVersion: nodeEcmaVersion,
      },
      env: {node: true},
    },

    // jest related files
    {
      files: ['**/*.jest.js', '**/*.test.js'],
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: nodeEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
      env: {jest: true, node: true, browser: true},
    },

    // browserify compiled files
    {
      files: ['**/cat_source/es6/**/*.js'],
      parser: '@babel/eslint-parser',
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: babelEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
    },

    // concat related files
    {
      files: ['**/public/js/**/*.js'],
      env: {browser: true},

      /**
       * THE SHAME LIST
       */
      globals: {
        $: true,
        jQuery: true,
        _: true,
        React: true,
        ReactDOM: true,
        moment: true,
        sprintf: true,
        diff_match_patch: true,
        Base64: true,
        config: true,
        APP: true,
        UI: true,
        PEE: true,
        MC: true,
        API: true,
        classnames: true,
        Review: true,
        ReviewExtended: true,
        ReviewExtendedFooter: true,
        SegmentFilter: true,
        NotificationBox: true,
        ManageConstants: true,
        ManageActions: true,
        AnalyzeActions: true,
        TeamsActions: true,
        ModalsActions: true,
        OutsourceActions: true,
        CatToolActions: true,
        SegmentActions: true,
        CommentsActions: true,
        ProjectsStore: true,
        TeamsStore: true,
        SegmentStore: true,
        CatToolStore: true,
        Header: true,
        JobMetadata: true,
        AnalyzeMain: true,
        LanguageSelector: true,
        SegmentsContainer: true,
        ModalWindow: true,
        SuccessModal: true,
        ConfirmRegister: true,
        PreferencesModal: true,
        ResetPasswordModal: true,
        LoginModal: true,
        ForgotPasswordModal: true,
        RegisterModal: true,
        ConfirmMessageModal: true,
        OutsourceModal: true,
        SplitJobModal: true,
        DQFModal: true,
        ShortCutsModal: true,
        CreateTeamModal: true,
        ModifyTeamModal: true,
        JobMetadataModal: true,
        SegmentBody: true,
        SegmentTarget: true,
        SegmentFooter: true,
        SegmentTabMatches: true,
        SegmentTabMessages: true,
        SegmentButtons: true,
        TranslationIssuesSideButton: true,
        QaCheckGlossary: true,
        SearchUtils: true,
        TagUtils: true,
        TextUtils: true,
        CommonUtils: true,
        CursorUtils: true,
        OfflineUtils: true,
        Shortcuts: true,
        Customizations: true,
        SegmentUtils: true,
        DraftMatecatUtils: true,
        LXQ: true,
        MBC: true,
        Speech2Text: true,
      },
    },
  ],
}
