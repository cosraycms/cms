// Language modules register a grammar or the editing behavior when they
// load and export nothing, so modules/ ships no declarations for them
// (scripts/modules.mjs). Which ones exist is checked by modules:check.
declare module 'prism-code-editor/languages/*.js' {}
declare module 'prism-code-editor/prism/languages/*.js' {}
