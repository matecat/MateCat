// Vite wrapper entry — mirrors webpack's "cattool" entry point
// globalFunctions provides segment plugin hooks (getContextBefore/After, registerFooterTabs)
// — only needed by cattool pages, moved here from mountPage.js to avoid pulling
// SegmentActions/SegmentStore into every page's dependency graph.
import '../js/globalFunctions'
import '../js/pages/CatTool.js'
import '../css/sass/components/pages/CattoolPage.scss'
