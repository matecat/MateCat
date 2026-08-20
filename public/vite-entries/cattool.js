// Vite wrapper entry — mirrors webpack's "cattool" entry point
// The extension manifest declares the segment-editor extension points and their
// core defaults; importing it is what makes those points exist. Only needed by
// cattool pages, kept here rather than in mountPage.js to avoid pulling
// SegmentActions/SegmentStore into every page's dependency graph.
import '../js/extensions/extensionManifest'
import '../js/pages/CatTool.js'
import '../css/sass/components/pages/CattoolPage.scss'
